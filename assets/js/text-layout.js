/* Optional text display metadata. Unconfigured elements retain their original layout. */
(function () {
    'use strict';
    const published = Object.assign({},window.GNC_TEXT_LAYOUT || {}), settings = JSON.parse(JSON.stringify(published));
    const placed = new Map();
    const fonts = window.GNC_TEXT_FONTS || {};
    const loadedFonts = new Set();
    const fontStyle=document.createElement('style');
    // Rich-text headings and links can have their own font rules. Preserve icon fonts.
    fontStyle.textContent='[data-gnc-text][style*="--gnc-text-font:"] :is(p,h1,h2,h3,h4,h5,h6,ul,ol,li,blockquote,a,strong,em,b,u,span:not([class])){font-family:var(--gnc-text-font)!important}';
    document.head.appendChild(fontStyle);
    function loadFont(key) {
        if (!Object.prototype.hasOwnProperty.call(fonts,key) || loadedFonts.has(key)) return;
        loadedFonts.add(key);
        const link=document.createElement('link');link.rel='stylesheet';
        const query=new URLSearchParams({family:fonts[key].label+':ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600;1,700',display:'swap'});
        link.href='https://fonts.googleapis.com/css2?'+query;
        link.dataset.gncGoogleFont=key;
        link.addEventListener('load',()=>{if(document.fonts)document.fonts.ready.then(schedule);});
        document.head.appendChild(link);
    }
    function applyFont(el,box) {
        if(isMedia(el))return;
        if(Number.isFinite(box.fontSize))el.style.fontSize=clamp(box.fontSize,8,200)+'px';
        if(Object.prototype.hasOwnProperty.call(fonts,box.fontFamily)) {
            loadFont(box.fontFamily);
            const family='"'+fonts[box.fontFamily].label+'", '+fonts[box.fontFamily].fallback;
            el.style.setProperty('--gnc-text-font',family);
            el.style.setProperty('font-family',family,'important');
        }
    }
    const mode = () => innerWidth < 768 ? 'mobile' : innerWidth <= 1024 ? 'tablet' : 'desktop';
    const clamp = (n,min,max) => Math.max(min,Math.min(max,n));
    let controller=null, scheduled=0;
    function scopeKey(id,scope){let out='';for(const seed of [2166136261,3339675911,2862933557]){let n=seed;for(const c of id+':'+scope)n=Math.imul(n^c.charCodeAt(0),16777619);out+=(n>>>0).toString(16).padStart(8,'0');}return out;}
    function targets() {
        const counts={}, result=new Map();
        document.querySelectorAll('[data-gnc-text]').forEach(el => {
            const marker=el.getAttribute('data-gnc-text'), scope=el.closest('[data-gnc-layout-scope]')?.getAttribute('data-gnc-layout-scope');
            const id=scope?scopeKey(marker,scope):marker, n=counts[id]||0; counts[id]=n+1;
            if (/^[a-f0-9]{24}$/.test(id)) result.set(id+(n?'-'+n:''),el);
        });
        return result;
    }
    function section(el) { return el.parentElement.closest('section,article,main,.hero-slide') || el.parentElement; }
    function isMedia(el) { return el.hasAttribute('data-gnc-layout-media'); }
    function restore() {
        placed.forEach((entry,el) => {
            if(entry.style===null)el.removeAttribute('style');else el.setAttribute('style',entry.style);
            if(entry.wrap?.parentNode){entry.wrap.replaceWith(el);}
        }); placed.clear();
    }
    function apply() {
        restore();
        if(window.GNCInlineTextStyle)window.GNCInlineTextStyle.render(targets(),settings);
        targets().forEach((el,id) => {
            const box=settings[id]?.[mode()];
            if(!box || el.closest('[data-gnc-layout-ignore]') || (el.querySelector('[data-gnc-text]')&&!el.hasAttribute('data-gnc-layout-group')))return;
            if(!('x' in box)) {
                placed.set(el,{style:el.getAttribute('style'),wrap:null});
                applyFont(el,box);
                return;
            }
            const boundary=section(el), r=el.getBoundingClientRect(), b=boundary.getBoundingClientRect();
            if(!r.width || !b.width || !b.height)return;
            const style=el.getAttribute('style'), cs=getComputedStyle(el), wrap=document.createElement('span');
            // Reserve the original footprint, so moving/resizing never pushes adjacent content.
            wrap.setAttribute('data-text-layout-slot','');
            wrap.style.cssText='position:relative;display:'+(cs.display==='inline'?'inline-block':'block')+';width:'+r.width+'px;height:'+r.height+'px;max-width:100%;margin:'+cs.margin+';padding:0;border:0;';
            if(isMedia(el))wrap.style.flexShrink='0';
            el.before(wrap);wrap.appendChild(el);
            placed.set(el,{style,wrap});
            const origin=wrap.getBoundingClientRect(), w=Math.min(b.width,Math.max(24,b.width*box.w/100));
            const naturalHeight=isMedia(el)?box.h:Math.max(r.height,box.h);
            const left=clamp(box.centerX?b.left+(b.width-w)/2:origin.left+box.x*b.width/100,b.left,Math.max(b.left,b.right-w));
            const top=clamp(origin.top+box.y*b.height/100,b.top,Math.max(b.top,b.bottom-naturalHeight));
            Object.assign(el.style,{position:'absolute',display:'block',boxSizing:'border-box',margin:'0',left:(left-origin.left)+'px',top:(top-origin.top)+'px',width:w+'px',maxWidth:'none',height:'auto',minHeight:box.h+'px',overflow:'visible',overflowWrap:'anywhere',animation:'none',transition:'none'});
            if(isMedia(el))Object.assign(el.style,{height:box.h+'px',minHeight:'0',maxHeight:'none',objectFit:'contain'});
            else applyFont(el,box);
            // Wrapping text may be taller than its old box; never clip its contents.
            const actual=el.getBoundingClientRect();
            if(actual.bottom>b.bottom && actual.height<=b.height)el.style.top=(b.bottom-actual.height-origin.top)+'px';
        });
        if(controller)controller.refresh();
    }
    function schedule(){cancelAnimationFrame(scheduled);scheduled=requestAnimationFrame(apply);}
    function setChanges(changes) {
        Object.keys(settings).forEach(key=>delete settings[key]);Object.assign(settings,JSON.parse(JSON.stringify(published)));
        Object.entries(changes||{}).forEach(([id,devices])=>Object.entries(devices).forEach(([device,entry])=>{
            settings[id]=settings[id]||{};if(entry.value===null)delete settings[id][device];else settings[id][device]=entry.value;
        }));apply();
    }
    function create(options) {
        let selected=null, enabled=false, drag=null, suppress=false, snapping=true;
        const gridSize=8;
        const grid=document.createElement('div');grid.setAttribute('data-text-layout-control','');grid.className='text-layout-grid';grid.hidden=true;
        const overlay=document.createElement('div');overlay.setAttribute('data-text-layout-control','');overlay.className='text-layout-box';overlay.hidden=true;
        const style=document.createElement('style');style.textContent=`
        .text-layout-box{position:fixed;z-index:2147482000;border:2px solid #176b42;pointer-events:none;box-sizing:border-box}
        .text-layout-box[hidden]{display:none!important}
        .text-layout-grid{position:fixed;z-index:2147481999;pointer-events:none;background-image:linear-gradient(to right,rgba(23,107,66,.14) 1px,transparent 1px),linear-gradient(to bottom,rgba(23,107,66,.14) 1px,transparent 1px);background-size:8px 8px;outline:1px dashed rgba(23,107,66,.35);box-sizing:border-box}
        .text-layout-grid[hidden]{display:none!important}
        .text-layout-box button{position:absolute;pointer-events:auto;touch-action:none;border:2px solid #176b42;background:#fff;color:#0b4228;padding:0;min-width:12px;min-height:12px}
        .text-layout-box .text-layout-move{top:4px;right:4px;height:25px;padding:0 8px;border-radius:5px;font:600 12px system-ui;white-space:nowrap;cursor:move}
        .text-layout-box .text-layout-handle{width:12px;height:12px;border-radius:2px}
        html.ve-previewing .text-layout-box,html.ve-previewing .text-layout-grid{display:none!important}`;
        document.head.appendChild(style);
        function button(label,dir,css){const b=document.createElement('button');b.type='button';b.setAttribute('aria-label',label);b.dataset.direction=dir;b.className=dir==='move'?'text-layout-move':'text-layout-handle';b.style.cssText=css||'';if(dir==='move')b.textContent='↕ Drag text · '+mode();overlay.appendChild(b);return b;}
        button('Drag selected text','move');
        const positions={nw:'left:-7px;top:-7px',n:'left:calc(50% - 6px);top:-7px',ne:'right:-7px;top:-7px',e:'right:-7px;top:calc(50% - 6px)',se:'right:-7px;bottom:-7px',s:'left:calc(50% - 6px);bottom:-7px',sw:'left:-7px;bottom:-7px',w:'left:-7px;top:calc(50% - 6px)'};
        Object.entries(positions).forEach(([dir,css])=>button('Resize text '+dir,dir,css+';cursor:'+dir+'-resize'));
        document.body.appendChild(grid);document.body.appendChild(overlay);
        function identify(el){for(const [id,node] of targets())if(node===el)return id;return null;}
        function refresh(){
            const state=options.state();
            overlay.hidden=!enabled||!selected?.isConnected||state.busy||state.preview;
            grid.hidden=overlay.hidden||!snapping;
            if(overlay.hidden)return;
            const r=selected.getBoundingClientRect();Object.assign(overlay.style,{left:r.left+'px',top:r.top+'px',width:r.width+'px',height:r.height+'px'});
            const b=section(placed.get(selected)?.wrap||selected).getBoundingClientRect();Object.assign(grid.style,{left:b.left+'px',top:b.top+'px',width:b.width+'px',height:b.height+'px'});
            overlay.querySelector('.text-layout-move').textContent='↕ Drag '+(isMedia(selected)?'logo':'text')+' · '+mode()+(snapping?' · 8px grid':'');
            overlay.querySelectorAll('button').forEach(button=>button.setAttribute('aria-label',button.dataset.direction==='move'?'Drag selected '+(isMedia(selected)?'logo':'text'):'Resize '+(isMedia(selected)?'logo':'text')+' '+button.dataset.direction));
        }
        function commit(value,preserveFont=true){
            const id=identify(selected);if(!id)return;
            const current=settings[id]?.[mode()];
            if(value&&preserveFont){
                const typography={};
                if(Number.isFinite(current?.fontSize))typography.fontSize=current.fontSize;
                if(current?.fontFamily)typography.fontFamily=current.fontFamily;
                if(current?.runs)typography.runs=current.runs;
                value=Object.assign(typography,value);
            }
            options.stage(id,mode(),value,published[id]?.[mode()]||null);refresh();
        }
        function changeFontFamily(key){
            if(!selected||isMedia(selected)||options.state().busy||options.state().preview)return;
            if(key!==''&&!Object.prototype.hasOwnProperty.call(fonts,key))return;
            const id=identify(selected);if(!id)return;
            const box=Object.assign({},settings[id]?.[mode()]||{});
            if(key)box.fontFamily=key;else delete box.fontFamily;
            commit(Object.keys(box).length?box:null,false);
        }
        function snapToCenter(){
            if(!selected||!controller.eligible(selected)||options.state().busy||options.state().preview)return;
            const r=selected.getBoundingClientRect(),b=section(placed.get(selected)?.wrap||selected).getBoundingClientRect();
            if(!b.width||!r.width)return;
            const slot=placed.get(selected)?.wrap?.getBoundingClientRect()||r;
            const current=settings[identify(selected)]?.[mode()]||{};
            const width=Math.min(r.width,b.width),left=b.left+(b.width-width)/2;
            commit(Object.assign({y:0,w:clamp(width/b.width*100,1,100),h:r.height},current,{x:clamp((left-slot.left)/b.width*100,-100,100),centerX:true}));
        }
        function changeFontSize(delta){
            if(!selected||isMedia(selected)||options.state().busy||options.state().preview)return;
            const id=identify(selected);if(!id)return;
            const current=settings[id]?.[mode()];
            if(delta===null){if(!current||!('fontSize' in current))return;const box=Object.assign({},current);delete box.fontSize;commit(box,false);return;}
            const r=selected.getBoundingClientRect(),b=section(placed.get(selected)?.wrap||selected).getBoundingClientRect();
            const box=Object.assign({x:0,y:0,w:clamp(r.width/b.width*100,1,100),h:r.height},current||{});
            box.fontSize=clamp(Math.round((parseFloat(getComputedStyle(selected).fontSize)+delta)*100)/100,8,200);
            commit(box);
        }
        function begin(event,dir){
            if(!enabled||!selected||options.state().busy||options.state().preview||event.button!==0)return;
            event.preventDefault();event.stopImmediatePropagation();selected.blur();
            const boundary=section(placed.get(selected)?.wrap||selected), b=boundary.getBoundingClientRect(),r=selected.getBoundingClientRect();
            const slot=placed.get(selected)?.wrap?.getBoundingClientRect()||r;
            drag={dir,x:event.clientX,y:event.clientY,r,b,slot,id:event.pointerId,original:settings[identify(selected)]?.[mode()]||null};
            document.addEventListener('pointermove',move,true);document.addEventListener('pointerup',end,true);document.addEventListener('pointercancel',cancel,true);
            overlay.querySelector('[data-direction="'+dir+'"]').setPointerCapture?.(event.pointerId);
        }
        function move(event){
            if(!drag)return;event.preventDefault();event.stopImmediatePropagation();
            const d=drag,dx=event.clientX-d.x,dy=event.clientY-d.y;let left=d.r.left,top=d.r.top,w=d.r.width,h=d.r.height;
            if(d.dir==='move'){left+=dx;top+=dy;}else{
                if(d.dir.includes('e'))w+=dx;if(d.dir.includes('s'))h+=dy;
                if(d.dir.includes('w')){left+=dx;w-=dx;}if(d.dir.includes('n')){top+=dy;h-=dy;}
            }
            if(snapping){
                const snap=n=>Math.round(n/gridSize)*gridSize;
                if(d.dir==='move'){left=d.b.left+snap(left-d.b.left);top=d.b.top+snap(top-d.b.top);}
                else {
                    if(d.dir.includes('e'))w=d.b.left+snap(left+w-d.b.left)-left;
                    if(d.dir.includes('s'))h=d.b.top+snap(top+h-d.b.top)-top;
                    if(d.dir.includes('w')){const right=left+w;left=d.b.left+snap(left-d.b.left);w=right-left;}
                    if(d.dir.includes('n')){const bottom=top+h;top=d.b.top+snap(top-d.b.top);h=bottom-top;}
                }
            }
            w=clamp(w,Math.min(40,d.b.width),d.b.width);h=clamp(h,24,Math.min(3000,Math.max(24,d.b.height)));
            // A logo grows with its box; keep its proportions instead of stretching the artwork.
            if(isMedia(selected)&&d.dir!=='move'){
                const ratio=d.r.width/d.r.height;
                const horizontal=/[ew]/.test(d.dir),vertical=/[ns]/.test(d.dir);
                if(horizontal&&(!vertical||Math.abs(w/d.r.width-1)>=Math.abs(h/d.r.height-1)))h=w/ratio;
                else w=h*ratio;
                const scale=Math.min(1,d.b.width/w,d.b.height/h,3000/h);w*=scale;h*=scale;
                if(d.dir.includes('w'))left=d.r.right-w;
                if(d.dir.includes('n'))top=d.r.bottom-h;
            }
            left=clamp(left,d.b.left,Math.max(d.b.left,d.b.right-w));top=clamp(top,d.b.top,Math.max(d.b.top,d.b.bottom-h));
            commit({x:clamp((left-d.slot.left)/d.b.width*100,-100,100),y:clamp((top-d.slot.top)/d.b.height*100,-100,100),w:clamp(w/d.b.width*100,1,100),h});
        }
        function finish(){document.removeEventListener('pointermove',move,true);document.removeEventListener('pointerup',end,true);document.removeEventListener('pointercancel',cancel,true);drag=null;suppress=true;setTimeout(()=>{suppress=false;},0);}
        function end(e){if(!drag)return;e.preventDefault();e.stopImmediatePropagation();finish();}
        function cancel(){if(drag){const original=drag.original;commit(original);finish();}}
        overlay.addEventListener('pointerdown',e=>{const dir=e.target.dataset.direction;if(dir)begin(e,dir);},true);
        // In layout mode, dragging the selected text itself is available as well as the grip.
        document.addEventListener('pointerdown',e=>{if(enabled&&selected?.contains(e.target)&&!e.target.closest('[data-text-layout-control]'))begin(e,'move');},true);
        overlay.addEventListener('keydown',e=>{
            if(e.key==='Escape'){cancel();enabled=false;refresh();return;}
            if(!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(e.key)||!selected)return;
            e.preventDefault();const b=section(placed.get(selected)?.wrap||selected).getBoundingClientRect(),r=selected.getBoundingClientRect(),slot=placed.get(selected)?.wrap?.getBoundingClientRect()||r;
            const step=snapping?(e.shiftKey?gridSize*5:gridSize):(e.shiftKey?10:1);commit({x:clamp((r.left-slot.left+(e.key==='ArrowRight'?step:e.key==='ArrowLeft'?-step:0))/b.width*100,-100,100),y:clamp((r.top-slot.top+(e.key==='ArrowDown'?step:e.key==='ArrowUp'?-step:0))/b.height*100,-100,100),w:clamp(r.width/b.width*100,1,100),h:r.height});
        });
        addEventListener('scroll',refresh,{passive:true});addEventListener('resize',refresh);
        controller={refresh,eligible:el=>!!el&&!!identify(el)&&!el.closest('[data-gnc-layout-ignore]')&&!el.querySelector('[data-gnc-text]'),
            changeFontSize,fontSize:()=>selected&&!isMedia(selected)?parseFloat(getComputedStyle(selected).fontSize):null,
            changeFontFamily,fontFamily:()=>settings[identify(selected)]?.[mode()]?.fontFamily||'',
            snapToCenter,
            fonts:()=>Object.entries(fonts).map(([key,font])=>[key,font.label]),
            select(el){if(selected!==el){selected=el;enabled=false;}refresh();},
            toggle(){enabled=!enabled;refresh();},active:()=>enabled,
            snapping:()=>snapping,toggleSnap(){snapping=!snapping;refresh();},
            reset(){if(selected)commit(null);},consume:()=>suppress,
            dispose(){cancel();overlay.remove();grid.remove();style.remove();removeEventListener('scroll',refresh);removeEventListener('resize',refresh);controller=null;}};
        return controller;
    }
    function acceptChanges(changes){Object.entries(changes||{}).forEach(([id,devices])=>Object.entries(devices).forEach(([device,entry])=>{published[id]=published[id]||{};if(entry.value===null)delete published[id][device];else published[id][device]=entry.value;}));setChanges({});}
    window.GNCTextLayout={create,apply,setChanges,acceptChanges,targets,device:mode,snapshot:()=>JSON.parse(JSON.stringify(settings)),baseline:()=>JSON.parse(JSON.stringify(published))};
    addEventListener('resize',schedule);addEventListener('load',schedule);
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',apply);else apply();
    if(document.fonts)document.fonts.ready.then(schedule);
})();
