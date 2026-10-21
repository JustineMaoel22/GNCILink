/* Non-destructive framing shared by public pages, upload forms and the Visual Editor. */
(function () {
    'use strict';
    if (window.GNCImageFraming) return;
    const config = window.GNC_IMAGE_FRAMING_CONFIG || {};
    const settings = Object.assign({}, config.settings || {});
    const hashedSettings = config.hashedSettings || {}, resolvedPaths = new Set();
    const files = new WeakMap(), sources = new Map(), uploadTargets = new WeakMap(), framed = new WeakMap(), previews = new WeakMap();
    const styledSources = new WeakMap(), originalStyles = new WeakMap();
    const backgroundObservers = new WeakMap(), observedBackgrounds = new WeakSet();
    const hashes = new WeakMap(), digests = new WeakMap(), fileFrames = new Map(), documents = new Set(), scheduled = new WeakSet();
    const nativeObjectURL = URL.createObjectURL.bind(URL), nativeFetch = window.fetch.bind(window);
    const clamp = (n, lo, hi) => Math.max(lo, Math.min(hi, n));
    const copy = value => ({x:value.x, y:value.y, zoom:value.zoom});
    let queue = Promise.resolve(), modalOpen = false, hoverButton, hoverTarget;

    function pathOf(src) {
        try {
            const url = new URL(src, location.origin);
            return url.origin === location.origin && /^\/(uploads|assets\/images)\//.test(url.pathname) ? url.pathname : null;
        } catch (_) { return null; }
    }
    function sourceOf(el) {
        if (!el || el.nodeType !== 1) return '';
        if (el.tagName === 'IMG') return el.currentSrc || el.src;
        const bg = el.ownerDocument.defaultView.getComputedStyle(el).backgroundImage;
        const match = /url\(["']?([^"')]+)["']?\)/.exec(bg);
        return match ? match[1] : (el.getAttribute('data-bg') || '');
    }
    function usable(src) { return !!pathOf(src) || sources.has(src); }
    function cropped(el) {
        if (!el || !usable(sourceOf(el))) return false;
        // The editor and its iframe have separate API instances. A framed
        // background uses pixel sizes, so the other instance cannot rely on
        // background-size: cover to recognize it as an editable crop.
        if (framed.has(el) || el.hasAttribute('data-image-framed')) return true;
        const css = el.ownerDocument.defaultView.getComputedStyle(el);
        return el.tagName === 'IMG' ? css.objectFit === 'cover' : css.backgroundSize.split(',').some(size => size.trim() === 'cover');
    }
    function findTarget(el) {
        if (!el) return null;
        if (cropped(el)) return el;
        for (const child of el.querySelectorAll('img,[data-bg],[data-ve-bg]')) if (cropped(child)) return child;
        return null;
    }
    function position(value, fallback) {
        const bits = String(value || '').split(/\s+/);
        function axis(v, i) {
            if (v === 'left' || v === 'top') return 0;
            if (v === 'right' || v === 'bottom') return 100;
            return v && v.endsWith('%') ? clamp(parseFloat(v),0,100) : fallback[i];
        }
        return {x:axis(bits[0],0), y:axis(bits[1],1), zoom:1};
    }
    function current(el, src) {
        const file = sources.get(src);
        if (file && files.has(file)) return copy(files.get(file));
        const saved = settings[pathOf(src)];
        if (saved) return copy(saved);
        if (!el) return {x:50,y:50,zoom:1};
        const css = el.ownerDocument.defaultView.getComputedStyle(el);
        return position(el.tagName === 'IMG' ? css.objectPosition : css.backgroundPosition, [50,50]);
    }
    const naturalImages = new Map();
    function backgroundLayers(value) {
        const layers=[];let depth=0,quote='',start=0;
        for(let i=0;i<value.length;i++){
            const c=value[i];if(quote){if(c===quote&&value[i-1]!=='\\')quote='';continue;}
            if(c==='"'||c==="'")quote=c;else if(c==='(')depth++;else if(c===')')depth--;else if(c===','&&depth===0){layers.push(value.slice(start,i));start=i+1;}
        }
        layers.push(value.slice(start));return layers;
    }
    function natural(src) {
        if (!naturalImages.has(src)) naturalImages.set(src, new Promise((resolve,reject) => {
            const img = new Image(); img.onload = () => resolve(img); img.onerror = () => { naturalImages.delete(src); reject(Error('The image could not be loaded.')); }; img.src = src;
        }));
        return naturalImages.get(src);
    }
    function apply(el, value) {
        if (!el || !el.isConnected) return;
        const frame = copy(value), src = sourceOf(el);
        if (!originalStyles.has(el)) {
            const saved={};['object-position','transform','transform-origin','clip-path','background-size','background-position'].forEach(key=>saved[key]=[el.style.getPropertyValue(key),el.style.getPropertyPriority(key)]);originalStyles.set(el,saved);
        }
        styledSources.set(el,src);
        framed.set(el, frame);
        el.setAttribute('data-image-framed','');
        if (el.tagName === 'IMG') {
            el.style.setProperty('object-position', frame.x+'% '+frame.y+'%', 'important');
            // At natural zoom, object-position handles the crop on its own.
            // Avoid a composited clip edge exposing the dark card underneath.
            if (frame.zoom === 1) {
                el.style.setProperty('transform', 'none', 'important');
                el.style.setProperty('clip-path', 'none', 'important');
                return;
            }
            el.style.setProperty('transform-origin', frame.x+'% '+frame.y+'%', 'important');
            el.style.setProperty('transform','scale('+frame.zoom+')','important');
            // Clip the scaled picture back to its original layout box; no wrapper or layout change.
            const inset = (1-1/frame.zoom);
            const css=el.ownerDocument.defaultView.getComputedStyle(el);
            const radii=['borderTopLeftRadius','borderTopRightRadius','borderBottomRightRadius','borderBottomLeftRadius'].map(key=>{
                const radius=css[key],number=parseFloat(radius)||0;
                return ((radius.includes('%')?number*Math.min(el.clientWidth,el.clientHeight)/100:number)/frame.zoom)+'px';
            });
            el.style.setProperty('clip-path','inset('+(inset*frame.y)+'% '+(inset*(100-frame.x))+'% '+(inset*(100-frame.y))+'% '+(inset*frame.x)+'% round '+radii.join(' ')+')','important');
        } else {
            const observer=backgroundObservers.get(el.ownerDocument);
            if(observer&&!observedBackgrounds.has(el)){observedBackgrounds.add(el);observer.observe(el);}
            natural(src).then(img => {
                if (!el.isConnected || sourceOf(el) !== src || framed.get(el) !== frame) return;
                const w=el.clientWidth, h=el.clientHeight;
                if (!w || !h) return;
                const scale=Math.max(w/img.naturalWidth,h/img.naturalHeight)*frame.zoom;
                // Preserve overlay gradients when the background has multiple layers.
                const bg=el.ownerDocument.defaultView.getComputedStyle(el).backgroundImage;
                const layers=backgroundLayers(bg);
                el.style.setProperty('background-size', layers.map(layer=>layer.includes('url(') ? (img.naturalWidth*scale)+'px '+(img.naturalHeight*scale)+'px' : 'cover').join(', '),'important');
                el.style.setProperty('background-position',layers.map(layer=>layer.includes('url(') ? frame.x+'% '+frame.y+'%' : 'center').join(', '),'important');
            }).catch(()=>{});
        }
    }
    function applyKnown(el) {
        const src=sourceOf(el), file=sources.get(src), value=previews.get(el) || file && files.get(file) || settings[pathOf(src)];
        const path=pathOf(src);
        if (!value && path && Object.keys(hashedSettings).length && !resolvedPaths.has(path)) {
            resolvedPaths.add(path);
            crypto.subtle.digest('SHA-256',new TextEncoder().encode(path)).then(buffer=>{
                const digest=Array.from(new Uint8Array(buffer),n=>n.toString(16).padStart(2,'0')).join('');
                if(hashedSettings[digest])set(path,hashedSettings[digest]);
            }).catch(()=>{});
        }
        if (styledSources.has(el) && styledSources.get(el)!==src) {
            const saved=originalStyles.get(el);if(saved)Object.entries(saved).forEach(([key,[v,priority]])=>{if(v)el.style.setProperty(key,v,priority);else el.style.removeProperty(key);});
            framed.delete(el);styledSources.delete(el);originalStyles.delete(el);el.removeAttribute('data-image-framed');
        }
        if (value && cropped(el)) apply(el,value);
    }
    function scan(doc) {
        doc.querySelectorAll('img,[data-bg],[data-ve-bg],[style*="background"]').forEach(applyKnown);
        // CSS backgrounds without inline attributes are candidates only if their file has saved framing.
        if (Object.keys(settings).length || Object.keys(hashedSettings).length) doc.querySelectorAll('section,header,article,div,a').forEach(el=>{ if (el.tagName!=='IMG' && cropped(el)) applyKnown(el); });
    }
    function schedule(doc) {
        if (scheduled.has(doc)) return;
        scheduled.add(doc); requestAnimationFrame(()=>{scheduled.delete(doc);scan(doc);});
    }
    function set(path,value) { settings[path]=copy(value); documents.forEach(schedule); }
    function watch(doc) {
        if (!doc || documents.has(doc)) return;
        documents.add(doc); schedule(doc);
        doc.addEventListener('load',e=>{if(e.target.tagName==='IMG')applyKnown(e.target);},true);
        new MutationObserver(records=>{
            if (records.some(r=>r.type==='childList' || r.attributeName!=='style' || !framed.has(r.target))) schedule(doc);
        }).observe(doc.documentElement,{subtree:true,childList:true,attributes:true,attributeFilter:['src','srcset','data-bg','class','style']});
        const ro=new ResizeObserver(entries=>{entries.forEach(entry=>{if(framed.has(entry.target))apply(entry.target,framed.get(entry.target));});});
        backgroundObservers.set(doc,ro);
        doc.querySelectorAll('[data-bg],[data-ve-bg]').forEach(el=>ro.observe(el));
        doc.defaultView.addEventListener('resize',()=>{doc.querySelectorAll('img,[data-bg],[data-ve-bg]').forEach(el=>{if(framed.has(el))apply(el,framed.get(el));});});
        if (config.admin && doc===document) installHover(doc);
    }
    function element(tag,attrs={},text) {
        const el=document.createElement(tag);
        Object.entries(attrs).forEach(([key,value])=>el.setAttribute(key,value));
        if(text!==undefined)el.textContent=text;return el;
    }
    function baseRatio(target,input) {
        // Sports images can extend beyond their clipped card; crop to the visible card.
        if(target&&target.closest)target=target.closest('[data-ve-sport]')||target;
        if(target){const rect=target.getBoundingClientRect();if(rect.width>0&&rect.height>0)return rect.width/rect.height;}
        const name=((input&&input.name)||'')+' '+((input&&input.id)||'');
        return (input&&input.hasAttribute('data-photo-check'))||/photo|dean|president|portrait/i.test(name)?3/4:16/9;
    }
    function snapshot(el) {
        if (!el) return ()=>{};
        const style=el.getAttribute('style'), previous=framed.get(el), wasFramed=el.hasAttribute('data-image-framed');
        return ()=>{if(style===null)el.removeAttribute('style');else el.setAttribute('style',style);if(previous)framed.set(el,previous);else framed.delete(el);if(wasFramed)el.setAttribute('data-image-framed','');else el.removeAttribute('data-image-framed');};
    }
    function visualEditor() {
        try { return window.VEImageFraming || (window.parent !== window && window.parent.VEImageFraming) || null; }
        catch (_) { return null; }
    }
    async function dialog({src,target=null,ratio=16/9,file=null}) {
        const picture=await natural(src), original=current(target,src), value=copy(original), undo=snapshot(target);
        const editor = !file && visualEditor();
        return new Promise(resolve=>{
            modalOpen=true;if(hoverButton)hoverButton.hidden=true;
            const box=element('dialog',{class:'image-frame-dialog','aria-labelledby':'imageFrameTitle'});
            const title=element('h2',{id:'imageFrameTitle'},'Reposition image');
            const help=element('p',{class:'image-frame-help'},'Drag the photo to position it. Use + and − to zoom. The frame keeps its aspect ratio.');
            const preview=element('div',{class:'image-frame-preview',tabindex:'0','aria-label':'Image preview. Drag to move the image, or use the arrow keys.'});
            preview.style.aspectRatio=String(ratio);preview.style.setProperty('--image-frame-ratio',String(ratio));
            const img=element('img',{src,alt:'',draggable:'false'});preview.append(img);
            const controls=element('div',{class:'image-frame-controls'});
            const minus=element('button',{type:'button','aria-label':'Zoom out'},'−'), plus=element('button',{type:'button','aria-label':'Zoom in'},'+');
            const zoom=element('input',{type:'range',min:'1',max:'4',step:'0.01','aria-label':'Image zoom'}), output=element('output',{},'100%');
            const reset=element('button',{type:'button'},'Reset');controls.append(minus,zoom,plus,output,reset);
            const note=element('p',{class:'image-frame-note'},file?'Position and zoom will be saved with your upload. The original file stays unchanged.':editor?'This position stays in the preview until you click Publish.':'Save updates this photo’s display settings wherever it appears. Each website container keeps its own aspect ratio. The original file stays unchanged.');
            const error=element('p',{class:'image-frame-error',role:'alert'});
            const actions=element('div',{class:'image-frame-actions'}),cancel=element('button',{type:'button'},'Cancel'),save=element('button',{type:'button',class:'image-frame-save'},file||editor?'Use position':'Save position');actions.append(cancel,save);
            box.append(title,help,preview,controls,note,error,actions);document.body.append(box);
            const priorFocus=document.activeElement;
            function paint(){
                const w=preview.clientWidth,h=preview.clientHeight,k=Math.max(w/picture.naturalWidth,h/picture.naturalHeight)*value.zoom;
                const iw=picture.naturalWidth*k,ih=picture.naturalHeight*k;
                Object.assign(img.style,{width:iw+'px',height:ih+'px',left:(w-iw)*value.x/100+'px',top:(h-ih)*value.y/100+'px'});
                zoom.value=value.zoom;output.textContent=Math.round(value.zoom*100)+'%';minus.disabled=value.zoom<=1;plus.disabled=value.zoom>=4;
                if(target){previews.set(target,copy(value));apply(target,value);}
            }
            let previewObserver;
            function finish(result){if(target)previews.delete(target);if(!result)undo();if(previewObserver)previewObserver.disconnect();box.close();box.remove();modalOpen=false;if(priorFocus&&priorFocus.isConnected)priorFocus.focus({preventScroll:true});resolve(result);}
            let drag;
            preview.addEventListener('pointerdown',e=>{if(e.button!==0)return;e.preventDefault();preview.focus();preview.setPointerCapture(e.pointerId);drag={id:e.pointerId,x:e.clientX,y:e.clientY,value:copy(value)};preview.classList.add('is-dragging');});
            preview.addEventListener('pointermove',e=>{
                if(!drag||e.pointerId!==drag.id)return;
                const w=preview.clientWidth,h=preview.clientHeight,k=Math.max(w/picture.naturalWidth,h/picture.naturalHeight)*value.zoom;
                const dx=picture.naturalWidth*k-w,dy=picture.naturalHeight*k-h;
                value.x=dx>0.01?clamp(drag.value.x-(e.clientX-drag.x)*100/dx,0,100):50;
                value.y=dy>0.01?clamp(drag.value.y-(e.clientY-drag.y)*100/dy,0,100):50;paint();
            });
            function release(){drag=null;preview.classList.remove('is-dragging');}
            preview.addEventListener('pointerup',release);preview.addEventListener('pointercancel',release);preview.addEventListener('lostpointercapture',release);
            preview.addEventListener('keydown',e=>{const moves={ArrowLeft:[2,0],ArrowRight:[-2,0],ArrowUp:[0,2],ArrowDown:[0,-2]};if(moves[e.key]){e.preventDefault();value.x=clamp(value.x+moves[e.key][0],0,100);value.y=clamp(value.y+moves[e.key][1],0,100);paint();}});
            function changeZoom(n){value.zoom=clamp(n,1,4);paint();}
            minus.addEventListener('click',()=>changeZoom(value.zoom-.1));plus.addEventListener('click',()=>changeZoom(value.zoom+.1));zoom.addEventListener('input',()=>changeZoom(Number(zoom.value)));reset.addEventListener('click',()=>{Object.assign(value,{x:50,y:50,zoom:1});paint();});
            cancel.addEventListener('click',()=>finish(null));box.addEventListener('cancel',e=>{e.preventDefault();if(!save.disabled)finish(null);});
            save.addEventListener('click',async()=>{
                error.textContent='';save.disabled=true;cancel.disabled=true;
                try{
                    if(file){files.set(file,copy(value));fileFrames.set(await hash(file),copy(value));sources.forEach((f,url)=>{if(f===file)settings[url]=copy(value);});}
                    else if(editor){editor.stage(pathOf(src),copy(value),copy(original));}
                    else{
                        const path=pathOf(src),fd=new FormData();fd.set('csrf_token',config.csrf||'');fd.set('path',path||'');fd.set('framing',JSON.stringify(value));
                        const response=await nativeFetch('/admin/action/image-framing-handler.php',{method:'POST',body:fd});const result=await response.json();if(!response.ok||!result.success)throw Error(result.error||'Could not save image position.');
                        set(path,value);documents.forEach(doc=>{try{const api=doc.defaultView.GNCImageFraming;if(api&&api!==window.GNCImageFraming)api.set(path,value);}catch(_){}});
                    }
                    finish(copy(value));documents.forEach(schedule);
                }catch(e){error.textContent=e.message;save.disabled=false;cancel.disabled=false;}
            });
            previewObserver=new ResizeObserver(()=>{if(box.isConnected)paint();});previewObserver.observe(preview);
            box.showModal();paint();preview.focus();
        });
    }
    function hash(file){if(!hashes.has(file))hashes.set(file,file.arrayBuffer().then(data=>crypto.subtle.digest('SHA-256',data)).then(buffer=>{const digest=Array.from(new Uint8Array(buffer),n=>n.toString(16).padStart(2,'0')).join('');digests.set(file,digest);return digest;}).catch(error=>{hashes.delete(file);throw error;}));return hashes.get(file);}
    function uploadPreparationError(file,cause){
        const error=new Error('Could not read image "'+(file.name||'selected image')+'". Choose this image again before publishing.');
        error.code='IMAGE_UPLOAD_PREPARATION';error.cause=cause;return error;
    }
    async function cacheUpload(file){
        // Keep staged uploads independent of subsequent changes to the file on disk.
        let bytes;try{bytes=await file.arrayBuffer();}catch(error){throw uploadPreparationError(file,error);}
        const cached=new File([bytes],file.name,{type:file.type,lastModified:file.lastModified});
        if(files.has(file))files.set(cached,copy(files.get(file)));
        if(digests.has(file)){const digest=digests.get(file);digests.set(cached,digest);hashes.set(cached,Promise.resolve(digest));}
        return cached;
    }
    async function edit(target) {
        target=findTarget(target);if(!target)return;
        const src=sourceOf(target),file=sources.get(src)||null;
        try{return await dialog({src,target,file,ratio:baseRatio(target)});}catch(e){
            if(window.AdminDialogs)window.AdminDialogs.alert(e.message,{title:'Image preview unavailable'});
            else console.error('Image preview unavailable:',e.message);
        }
    }
    function installHover(doc){
        if(hoverButton)return;
        function ensure(){if(!document.body)return false;hoverButton=element('button',{type:'button',class:'image-frame-trigger',hidden:'','aria-label':'Reposition image'},'↔ Reposition');document.body.append(hoverButton);hoverButton.addEventListener('click',()=>{if(hoverTarget)edit(hoverTarget);});return true;}
        doc.addEventListener('pointerover',e=>{
            if(modalOpen||doc.documentElement.classList.contains('ve-previewing')||document.body.classList.contains('ve-previewing')||e.target===hoverButton||e.target.closest('.image-frame-dialog'))return;
            if(!hoverButton&&!ensure())return;
            const target=cropped(e.target)?e.target:null;
            if(!target){hoverButton.hidden=true;return;}
            const rect=target.getBoundingClientRect();if(rect.width<45||rect.height<40)return;
            if(target.hasAttribute('data-ve-sports-background-area')){
                if(target.classList.contains('ve-selected')){hoverButton.hidden=true;return;}
                hoverTarget=target;hoverButton.hidden=false;
                hoverButton.style.top=Math.max(6,Math.min(innerHeight-44,rect.bottom-48))+'px';
                hoverButton.style.left=Math.max(6,Math.min(innerWidth-hoverButton.offsetWidth-6,rect.left+(rect.width-hoverButton.offsetWidth)/2))+'px';return;
            }
            hoverTarget=target;hoverButton.hidden=false;hoverButton.style.top=Math.max(6,rect.top+6)+'px';hoverButton.style.left=Math.max(6,Math.min(innerWidth-125,rect.right-120))+'px';
        });
        doc.addEventListener('scroll',()=>{if(hoverButton)hoverButton.hidden=true;},true);
    }
    if(config.admin){
        // Associate all existing preview mechanisms with the original File, without changing its bytes.
        URL.createObjectURL=function(file){const url=nativeObjectURL(file);if(file instanceof File)sources.set(url,file);return url;};
        const revoke=URL.revokeObjectURL.bind(URL);
        URL.revokeObjectURL=function(url){sources.delete(url);naturalImages.delete(url);return revoke(url);};
        const read=FileReader.prototype.readAsDataURL;
        FileReader.prototype.readAsDataURL=function(file){this.addEventListener('load',()=>{if(file instanceof File&&typeof this.result==='string'){sources.set(this.result,file);schedule(document);}},{once:true});return read.call(this,file);};
        document.addEventListener('change',e=>{
            const input=e.target;if(input.tagName!=='INPUT'||input.type!=='file'||!input.files.length)return;
            if(input.hasAttribute('data-no-image-framing'))return;
            const selected=Array.from(input.files),pictures=selected.filter(f=>/^image\/(jpeg|png|webp|gif)$/.test(f.type));
            if(!pictures.length||pictures.every(f=>files.has(f)))return;
            e.stopImmediatePropagation();
            queue=queue.catch(()=>{}).then(async()=>{
                // Empty editor slots have no image for findTarget() to discover.
                // Keep the assigned slot so its portrait/logo dimensions still
                // determine the upload preview instead of the landscape fallback.
                const assignedTarget=uploadTargets.get(input);
                const target=findTarget(assignedTarget)||assignedTarget||findTarget(input.closest('form,.modal,.card')||input.parentElement);
                for(const file of pictures){if(files.has(file))continue;const src=nativeObjectURL(file);sources.set(src,file);const value=await dialog({src,target:null,file,ratio:baseRatio(target,input)});URL.revokeObjectURL(src);sources.delete(src);if(!value){input.value='';break;}}
                input.dispatchEvent(new Event('change',{bubbles:true}));
            }).catch(err=>{console.error('Image framing:',err);input.value='';input.dispatchEvent(new Event('change',{bubbles:true}));});
        },true);
        async function payload(fd){
            const values={};
            for(const [,file] of fd.entries())if(file instanceof File&&file.size&&/^image\//.test(file.type)){
                let digest;try{digest=await hash(file);}catch(error){throw uploadPreparationError(file,error);}
                const value=files.get(file)||fileFrames.get(digest);if(value)values[digest]=value;
            }
            if(Object.keys(values).length)fd.set('image_framing',JSON.stringify(values));return fd;
        }
        window.fetch=function(input,options){if(options&&options.body instanceof FormData)return payload(options.body).then(body=>nativeFetch(input,Object.assign({},options,{body})));return nativeFetch(input,options);};
        const nativeSend=XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send=function(body){if(body instanceof FormData){payload(body).then(value=>nativeSend.call(this,value));return;}return nativeSend.call(this,body);};
        // Native forms already have computed hashes after the upload preview has been accepted.
        document.addEventListener('submit',e=>{
            const form=e.target;if(!(form instanceof HTMLFormElement))return;
            const values={};form.querySelectorAll('input[type=file]').forEach(input=>Array.from(input.files||[]).forEach(file=>{const value=files.get(file),digest=digests.get(file);if(value&&digest)values[digest]=value;}));
            let hidden=form.querySelector('input[name=image_framing]');
            if(Object.keys(values).length){if(!hidden){hidden=element('input',{type:'hidden',name:'image_framing'});form.append(hidden);}hidden.value=JSON.stringify(values);}else if(hidden)hidden.remove();
        },true);
    }
    window.GNCImageFraming={findTarget,edit,watch,set,apply,sourceOf,settings,cacheUpload,
        setUploadTarget:(input,target)=>{if(target)uploadTargets.set(input,target);else uploadTargets.delete(input);},
        fileSettings:file=>files.get(file),
        ready:Promise.resolve()
    };
    watch(document);
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>schedule(document));
})();
