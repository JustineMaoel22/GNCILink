/* Private Content Editor checkpoints. Publishing stays with the existing handlers. */
(function () {
    'use strict';
    var blocked = ['__proto__', 'constructor', 'prototype'];
    var resources = new Map();
    function trackUrl(url, file) { if (typeof WeakRef !== 'undefined') resources.set(url, new WeakRef(file)); }
    function plain(value, binary) {
        if (value == null || typeof value !== 'object') return typeof value === 'function' ? undefined : value;
        if (value.nodeType || value === window || value instanceof WeakMap || value instanceof Map) return undefined;
        if (value instanceof Blob) return binary(value);
        if (Array.isArray(value)) return value.map(function (item) { return plain(item, binary); });
        var out = {};
        Object.keys(value).forEach(function (key) { if (blocked.indexOf(key) < 0) { var item = plain(value[key], binary); if (item !== undefined) out[key] = item; } });
        return out;
    }
    async function encode(snapshot) {
        var files = [], identities = new Map(), urls = {};
        function binary(blob) {
            if (!identities.has(blob)) { identities.set(blob, files.length); files.push(blob); }
            return { $draftFile: identities.get(blob) };
        }
        var packed = plain(snapshot, binary);
        var matches = JSON.stringify(packed).match(/blob:https?:\/\/[^"'()\s\\]+/g) || [];
        for (var url of new Set(matches)) {
            var blob = resources.has(url) && resources.get(url).deref();
            if (!blob) blob = await (await fetch(url)).blob();
            urls[url] = binary(blob).$draftFile;
        }
        packed.files = await Promise.all(files.map(function (blob) {
            return new Promise(function (resolve, reject) {
                var reader = new FileReader();
                reader.onerror = function () { reject(Error('Could not read draft media. Choose the file again.')); };
                reader.onload = function () { resolve({ name: blob.name || 'draft-media', type: blob.type, modified: blob.lastModified || 0, data: reader.result.split(',')[1] }); };
                reader.readAsDataURL(blob);
            });
        }));
        packed.urls = urls;
        return JSON.stringify(packed);
    }
    function decode(snapshot) {
        var files = (snapshot.files || []).map(function (file) {
            var bytes = atob(file.data), array = new Uint8Array(bytes.length);
            for (var i = 0; i < bytes.length; i++) array[i] = bytes.charCodeAt(i);
            return new File([array], file.name, {type:file.type, lastModified:file.modified});
        });
        var urls = {};
        Object.keys(snapshot.urls || {}).forEach(function (old) { if (files[snapshot.urls[old]]) { urls[old] = URL.createObjectURL(files[snapshot.urls[old]]); trackUrl(urls[old], files[snapshot.urls[old]]); } });
        function visit(value) {
            if (typeof value === 'string') return value.replace(/blob:https?:\/\/[^"'()\s\\]+/g, function (url) { return urls[url] || ''; });
            if (!value || typeof value !== 'object') return value;
            if (Object.keys(value).length === 1 && Number.isInteger(value.$draftFile)) return files[value.$draftFile] || null;
            if (Array.isArray(value)) return value.map(visit);
            var result = {};
            Object.keys(value).forEach(function (key) { if (blocked.indexOf(key) < 0) result[key] = visit(value[key]); });
            return result;
        }
        return {state:visit(snapshot.state), preview:visit(snapshot.preview)};
    }
    function selector(el) {
        var parts = [], doc = el.ownerDocument;
        while (el && el !== doc.body) {
            if (el.id && doc.querySelectorAll('#' + CSS.escape(el.id)).length === 1) { parts.unshift('#' + CSS.escape(el.id)); break; }
            var identity = Array.from(el.attributes).find(function (attr) {
                return /^data-ve-(section|key|slide|achievement-id|org-card|sport|team|facility|program-card|department-card|admission-category|person|office|id|item)$/.test(attr.name) && attr.value;
            });
            var part = el.tagName.toLowerCase();
            if (identity) part += '[' + identity.name + '="' + CSS.escape(identity.value) + '"]';
            else part += ':nth-of-type(' + (Array.from(el.parentNode.children).filter(function (node) { return node.tagName === el.tagName; }).indexOf(el) + 1) + ')';
            // Administration name/position spans share a person ID and parent.
            // Keep both fields distinct when the preview is restored.
            if (/^person-(name|role)$/.test(el.getAttribute('data-ve-field') || '')) part += '[data-ve-field="' + el.getAttribute('data-ve-field') + '"]';
            parts.unshift(part);
            if (doc.querySelectorAll(parts.join(' > ')).length === 1) break;
            el = el.parentElement;
        }
        return parts.join(' > ');
    }
    function preview(doc, clean) {
        var result = {fields:[], media:[], icons:[], orders:[], links:[]};
        doc.querySelectorAll('[data-ve-field], [data-ve-section], [data-ve-pick="prog-name"], [data-office-name], [data-office-address], [data-ve-org-assign-category]').forEach(function (el) {
            result.fields.push({selector:selector(el), rich:el.hasAttribute('data-ve-rich') || el.hasAttribute('data-ve-overview') || (el.hasAttribute('data-ve-section') && !el.hasAttribute('data-ve-plain-section') && !el.hasAttribute('data-ve-admission-label')), text:el.textContent, html:clean(el.innerHTML)});
        });
        doc.querySelectorAll('img, [data-ve-bg], [data-ve-img], [data-ve-sports-background-area], [style*="--home-section-background"], [style*="background-image"]').forEach(function (el) {
            if (el.closest('header, footer, #mainNavbar')) return;
            result.media.push({selector:selector(el), src:el.tagName === 'IMG' ? el.getAttribute('src') : null, background:el.style.backgroundImage, home:el.style.getPropertyValue('--home-section-background'), opacity:el.style.opacity, hidden:el.hidden});
        });
        doc.querySelectorAll('[data-ve-icon], [data-ve-org-icon]').forEach(function (el) {
            var child = el.firstElementChild;
            if (child && /^(I|SPAN|IMG)$/.test(child.tagName)) result.icons.push({selector:selector(el), tag:child.tagName.toLowerCase(), className:child.className, text:child.textContent, src:child.tagName==='IMG'?child.getAttribute('src'):null});
        });
        doc.querySelectorAll('[data-ve-slide], [data-ve-department-card], [data-ve-program-card], [data-ve-grid], [data-ve-person], [data-ve-office], [data-ve-sport], [data-ve-team], [data-ve-facility], [data-ve-org-card], [data-ve-section], [data-admission-category-tabs] > *, [data-admission-category-panels] > *').forEach(function (el) {
            var parent = el.parentElement;
            // The section intro and grid have a fixed layout; only their cards reorder.
            if (parent.matches('.admin-section-inner')) return;
            var path = selector(parent);
            if (!result.orders.some(function (order) { return order.selector === path; })) result.orders.push({selector:path, children:Array.from(parent.children).map(selector)});
        });
        doc.querySelectorAll('[data-ve-payment-link], [data-ve-linkkey], [data-ve-btn]').forEach(function(el){result.links.push({selector:selector(el),href:el.getAttribute('href'),value:el.getAttribute('data-ve-link')});});
        return result;
    }
    function safeMedia(value) { return typeof value === 'string' && /^(https?:\/\/|blob:https?:\/\/|\/(?!\/)|(?:assets|uploads)\/)/i.test(value) && !/[\x00-\x20]/.test(value) ? value : ''; }
    function restorePreview(doc, data, clean) {
        function find(path) { try { return doc.querySelector(path); } catch (_) { return null; } }
        (data.orders || []).forEach(function (order) {
            var parent=find(order.selector); if (!parent) return;
            // Older shared Administration drafts can reference a different tab's grid,
            // leaving only the intro matched and incorrectly appending it below the cards.
            if (parent.matches('.admin-section-inner')) return;
            order.children.map(find).filter(function (node) { return node && node.parentNode === parent; }).forEach(function (node) { parent.appendChild(node); });
        });
        (data.fields || []).forEach(function (field) {
            var el=find(field.selector); if (!el || !el.matches('[data-ve-field],[data-ve-section],[data-ve-pick="prog-name"],[data-office-name],[data-office-address],[data-ve-org-assign-category]') || el.getAttribute('contenteditable')==='false') return;
            if (field.rich) el.innerHTML=clean(field.html); else el.textContent=field.text;
        });
        function background(value) {
            if (!value) return '';
            var match=/^url\(["']?(.*?)["']?\)$/.exec(value);
            return match && safeMedia(match[1]) ? 'url(' + JSON.stringify(match[1]) + ')' : '';
        }
        (data.media || []).forEach(function (media) {
            var el=find(media.selector); if (!el) return;
            if (el.tagName==='IMG' && safeMedia(media.src)) el.src=safeMedia(media.src);
            el.style.backgroundImage=background(media.background);
            if (media.home) el.style.setProperty('--home-section-background',background(media.home));
            el.style.opacity=media.opacity==='0.25'||media.opacity==='.25'?'0.25':'';
            el.hidden=!!media.hidden;
        });
        (data.icons || []).forEach(function (entry) {
            var el=find(entry.selector); if (!el || !el.matches('[data-ve-icon],[data-ve-org-icon]') || !/^(i|span|img)$/.test(entry.tag)) return;
            var icon=doc.createElement(entry.tag);icon.className=String(entry.className || '');icon.textContent=entry.text || '';
            if(entry.tag==='img' && safeMedia(entry.src))icon.src=safeMedia(entry.src);
            el.replaceChildren(icon);
        });
        (data.links || []).forEach(function(entry){var el=find(entry.selector);if(!el||!el.matches('[data-ve-payment-link],[data-ve-linkkey],[data-ve-btn]'))return;['href','value'].forEach(function(key){var value=entry[key];if(typeof value==='string'&&/^(https?:\/\/|mailto:|tel:|\/(?!\/)|#)/i.test(value)&&!/[\x00-\x20]/.test(value))el.setAttribute(key==='href'?'href':'data-ve-link',value);});});
    }
    function create(options) {
        var revision=0, checkpoint='', route='', available=true, loading=false, fileIds=new WeakMap(), serial=0, submitting=null, held=false, offer=null;
        var reopen=document.createElement('button');reopen.type='button';reopen.className='btn btn-outline-secondary ve-btn';reopen.textContent='Review saved draft';reopen.hidden=true;
        var saveButton=document.getElementById('veSaveDraft');if(saveButton)saveButton.parentNode.insertBefore(reopen,saveButton);
        function identity(blob){if(!fileIds.has(blob))fileIds.set(blob,++serial);return {$file:fileIds.get(blob)};}
        function state(){return options.adapter().draftState ? options.adapter().draftState() : {};}
        function fingerprint(){return JSON.stringify(plain({state:state(), positions:options.positions(),textPlacements:options.textPlacements?options.textPlacements():{},backgrounds:options.backgrounds?options.backgrounds():{}},identity));}
        function saved(){return !!checkpoint && checkpoint===fingerprint();}
        function fields(action){return {action:action,page:options.page,route:route,revision:revision};}
        async function request(action, extra){var result=await options.send(Object.assign(fields(action),extra));if(!result.ok)throw Error(result.error);return result;}
        function restore(draft){
            var snapshot=decode(draft.snapshot);
            options.adapter().draftState(snapshot.state);options.setPositions(snapshot.state.imagePositions||{});
            restorePreview(options.document(),snapshot.preview,options.clean);
            if(options.adapter().draftRebind)options.adapter().draftRebind();
            if(options.setTextPlacements)options.setTextPlacements(snapshot.state.textPlacements||{});
            if(options.setBackgrounds)options.setBackgrounds(snapshot.state.responsiveBackgrounds||{});
            held=false;reopen.hidden=true;checkpoint=fingerprint();options.refresh();
            options.notify(draft.outdated?'Older draft resumed for review. Its original version is retained; publishing is blocked until the changes are reconciled with current content.':'Draft resumed. These changes are not live.');
        }
        function offerDraft(draft){
            reopen.hidden=false;reopen.onclick=function(){offerDraft(draft);};
            if(offer)offer.remove();
            var box=document.createElement('dialog'),title=document.createElement('h3'),note=document.createElement('p'),diff=document.createElement('pre');
            box.style.cssText='max-width:760px;width:90%;max-height:85vh;overflow:auto;padding:24px;border:1px solid #ccc;border-radius:12px';
            title.textContent=draft.outdated?'Saved draft uses an older page version':'Resume your saved draft?';
            note.textContent='The preview shows the latest published page. Your private draft is preserved. Review differences before resuming an older draft; it cannot overwrite newer published content.';
            diff.hidden=true;diff.style.cssText='white-space:pre-wrap;max-height:45vh;overflow:auto';box.append(title,note,diff);
            function action(label,fn){var button=document.createElement('button');button.type='button';button.className='btn btn-sm btn-outline-secondary m-1';button.textContent=label;button.onclick=async function(){try{await fn();}catch(error){options.notify(error.message,'error');}};box.appendChild(button);}
            action('Review differences',function(){
                var lines=[];
                (draft.snapshot.preview.fields||[]).forEach(function(field){var el;try{el=options.document().querySelector(field.selector);}catch(_){}if(!el||el.textContent!==field.text||field.rich&&options.clean(el.innerHTML)!==field.html)lines.push(field.selector+'\nCurrent: '+(el?el.textContent:'[missing from current page]')+'\nDraft: '+field.text);});
                lines.push('Current settings:\n'+JSON.stringify(plain(state(),function(file){return {name:file.name,type:file.type,size:file.size};}),null,2));
                lines.push('Saved settings and media references (binary files omitted):\n'+JSON.stringify(draft.snapshot.state,null,2));
                lines.push('Current media and layout:\n'+JSON.stringify(preview(options.document(),options.clean),null,2));
                lines.push('Saved media and layout:\n'+JSON.stringify(draft.snapshot.preview,null,2));
                diff.textContent=lines.join('\n\n');diff.hidden=false;
            });
            action('Keep draft and view current',function(){box.close();});
            action('Resume saved draft',async function(){
                if(options.pending()>0&&!await window.AdminDialogs.confirm('Replace your current local preview changes with the saved draft?'))return;
                var presence=options.presence&&options.presence();
                if(presence){await presence.acquire(false);await presence.resumeDraft({page:options.page,route:route,revision:revision});}
                restore(draft);box.close();
            });
            if(draft.outdated)action('Use reviewed draft with current version',async function(){
                if(diff.hidden){options.notify('Review differences first, including saved settings and media.','error');return;}
                if(!await window.AdminDialogs.confirm('Confirm you have compared the saved changes with the current published page. Resuming will use all saved draft changes; it does not merge them automatically. The draft remains private until publication or approval.'))return;
                var presence=options.presence&&options.presence();if(!presence)throw Error('Conflict protection is unavailable.');
                await presence.acquire(false);
                revision=await presence.reconcileDraft({page:options.page,route:route,revision:revision,confirmed:'1',current_revision:draft.current_version.revision,current_external:draft.current_version.external});
                draft.outdated=false;restore(draft);box.close();
            });
            action('Discard saved draft',async function(){if(!await window.AdminDialogs.confirm('Permanently discard your saved draft?'))return;await request('delete');revision=0;checkpoint='';held=false;reopen.hidden=true;box.close();options.refresh();});
            document.body.appendChild(box);offer=box;box.showModal();
        }
        return {
            supported:function(){return !!options.adapter().draftState;},
            available:function(){return available && !loading;},
            saved:saved,
            unsaved:function(){return checkpoint ? !saved() : options.pending()>0;},
            hasDraft:function(){return revision>0;},
            load:async function(url){
                if(!options.adapter().draftState)return;
                var target=new URL(url || '/',location.origin);['ce_preview','_','_ve','add_org'].forEach(function(key){target.searchParams.delete(key);});target.hash='';target.searchParams.sort();
                if(options.page==='alumni')target.searchParams.delete('event_page');
                var previousRoute=target.pathname+target.search;
                // Administration tabs edit one collection; keep additions together when switching sections.
                if(options.page==='administration') ['section','navigation'].forEach(function(key){target.searchParams.delete(key);});
                // The directory is one ordered collection; page/filter changes share its draft.
                if(options.page==='student-organizations'&&!(options.reviewing&&options.reviewing())&&/\/student-org(?:\.php)?$/.test(target.pathname))['p','category','archived'].forEach(function(key){target.searchParams.delete(key);});
                route=target.pathname+target.search;revision=0;checkpoint='';held=false;reopen.hidden=true;loading=true;if(offer){offer.remove();offer=null;}
                var documentAtStart=options.document();
                try{
                    var result=await request('load');
                    // Offer legacy checkpoints in place; viewing never copies or deletes a draft.
                    if(!result.draft&&['student-organizations','administration'].indexOf(options.page)>=0&&previousRoute!==route){
                        var previous=await request('load',{route:previousRoute});
                        if(previous.draft){result=previous;route=previousRoute;}
                    }
                    if(options.document()!==documentAtStart)return;
                    if(result.draft){
                        revision=result.draft.revision;
                        if(options.reviewing&&options.reviewing())restore(result.draft);
                        else {held=true;offerDraft(result.draft);}
                    }
                    available=true;
                }catch(error){available=false;options.notify(error.message,'error');}
                finally{loading=false;options.refresh();}
            },
            save:async function(recovery){
                if(held)throw Error('Your saved draft is preserved. Resume it or explicitly discard it before saving a replacement.');
                var snapshot={version:1,state:Object.assign({},state(),{imagePositions:options.positions(),textPlacements:options.textPlacements?options.textPlacements():{},responsiveBackgrounds:options.backgrounds?options.backgrounds():{}}),preview:preview(options.document(),options.clean)};
                var before=fingerprint();
                var payload=await encode(snapshot);
                if(payload.length>96*1024*1024)throw Error('This draft is too large. Save fewer media changes at a time.');
                var result=await request(recovery?'save_recovery':'save',{snapshot:payload});revision=result.revision;checkpoint=before;
                options.notify(recovery?'Recovery draft saved. It keeps your previous content version and cannot overwrite newer live content.':'Draft saved. Visitors still see the published page.');options.refresh();
            },
            clear:async function(){if(revision)await request('delete');revision=0;checkpoint='';held=false;reopen.hidden=true;options.refresh();},
            submit:function(){
                if(submitting)return submitting;
                var self=this;
                submitting=(async function(){if(!revision||!saved())await self.save();return request('submit_review');})().finally(function(){submitting=null;});
                return submitting;
            },
            fingerprint:fingerprint
        };
    }
    window.GNCEditorDrafts={create:create,encode:encode,decode:decode,trackUrl:trackUrl,media:function(url){return resources.has(url)?resources.get(url).deref():null;}};
})();
