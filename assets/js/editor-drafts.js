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
        var revision=0, checkpoint='', route='', available=true, loading=false, fileIds=new WeakMap(), serial=0;
        function identity(blob){if(!fileIds.has(blob))fileIds.set(blob,++serial);return {$file:fileIds.get(blob)};}
        function state(){return options.adapter().draftState ? options.adapter().draftState() : {};}
        function fingerprint(){return JSON.stringify(plain({state:state(), positions:options.positions(),textPlacements:options.textPlacements?options.textPlacements():{}},identity));}
        function saved(){return !!checkpoint && checkpoint===fingerprint();}
        function fields(action){return {action:action,page:options.page,route:route,revision:revision};}
        async function request(action, extra){var result=await options.send(Object.assign(fields(action),extra));if(!result.ok)throw Error(result.error);return result;}
        return {
            supported:function(){return !!options.adapter().draftState;},
            available:function(){return available && !loading;},
            saved:saved,
            unsaved:function(){return !saved() && options.pending()>0;},
            hasDraft:function(){return revision>0;},
            load:async function(url){
                if(!options.adapter().draftState)return;
                var target=new URL(url || '/',location.origin);['ce_preview','_','_ve','add_org'].forEach(function(key){target.searchParams.delete(key);});target.hash='';target.searchParams.sort();
                route=target.pathname+target.search;revision=0;checkpoint='';loading=true;
                var documentAtStart=options.document();
                try{
                    var result=await request('load');
                    if(options.document()!==documentAtStart)return;
                    if(result.draft){
                        revision=result.draft.revision;
                        var snapshot=decode(result.draft.snapshot);
                        options.adapter().draftState(snapshot.state);
                        options.setPositions(snapshot.state.imagePositions || {});
                        if(options.setTextPlacements)options.setTextPlacements(snapshot.state.textPlacements||{});
                        restorePreview(options.document(),snapshot.preview,options.clean);
                        if(options.adapter().draftRebind)options.adapter().draftRebind();
                        checkpoint=fingerprint();options.notify('Draft restored. These changes are not live.');
                    }
                    available=true;
                }catch(error){available=false;options.notify(error.message,'error');}
                finally{loading=false;options.refresh();}
            },
            save:async function(){
                var snapshot={version:1,state:Object.assign({},state(),{imagePositions:options.positions(),textPlacements:options.textPlacements?options.textPlacements():{}}),preview:preview(options.document(),options.clean)};
                var before=fingerprint();
                var payload=await encode(snapshot);
                if(payload.length>96*1024*1024)throw Error('This draft is too large. Save fewer media changes at a time.');
                var result=await request('save',{snapshot:payload});revision=result.revision;checkpoint=before;
                options.notify('Draft saved. Visitors still see the published page.');options.refresh();
            },
            clear:async function(){if(revision)await request('delete');revision=0;checkpoint='';options.refresh();},
            submit:async function(){await this.save();return request('submit_review');},
            fingerprint:fingerprint
        };
    }
    window.GNCEditorDrafts={create:create,encode:encode,decode:decode,trackUrl:trackUrl};
})();
