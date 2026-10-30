/* Shared, optional device overrides. Legacy backgrounds remain the fallback. */
(function(){
    'use strict';
    if(window.GNCResponsiveBackgrounds)return;
    let published=window.GNC_RESPONSIVE_BACKGROUNDS||{},changes={},forcedDevice=null;
    const targets=new Map(),originals=new WeakMap(),natural=new Map();let scheduled=false;
    const style=document.createElement('style');style.dataset.responsiveBackgrounds='';document.head.append(style);
    const device=()=>forcedDevice||(innerWidth<=767?'mobile':innerWidth<=1024?'tablet':'desktop');
    function localImage(source){try{const u=new URL(source,location.origin);return u.origin===location.origin&&/^\/(assets\/images|uploads)\//.test(u.pathname)?u.pathname:source.startsWith('blob:')?source:null;}catch{return null;}}
    const sourceOf=value=>{const match=/url\(["']?([^"')]+)["']?\)/.exec(value||'');return match?localImage(match[1]):null;};
    function selectorFor(node){
        if(node.id&&/^[a-zA-Z0-9_-]+$/.test(node.id))return '#'+node.id;
        const classes=Array.from(node.classList).filter(name=>/^[a-zA-Z0-9_-]+$/.test(name)&&!/^ve-|^is-|^js-|reveal|active|loaded|framed|skeleton/.test(name));
        for(const name of classes){const selector=node.tagName.toLowerCase()+'.'+name;if(document.querySelectorAll(selector).length===1)return selector;}
        if(!node.parentElement||node===document.body)return node.tagName.toLowerCase();
        const index=Array.from(node.parentElement.children).filter(other=>other.tagName===node.tagName).indexOf(node)+1;
        return selectorFor(node.parentElement)+' > '+node.tagName.toLowerCase()+':nth-of-type('+index+')';
    }
    function imageBackground(node){
        if(node.tagName!=='IMG')return false;
        if(node.closest('[data-ve-org-media="logo"],[data-image-frame-fit="contain"],.student-org-card-logo,.dept-program-card,.uniform-grid,.fcar,.org-gallery,.gnc-navbar,footer,nav'))return false;
        let parent=node;
        for(let i=0;parent&&i<3;i++,parent=parent.parentElement)if(/hero|banner|background|cover|header-image|header-bg/i.test(parent.className||''))return true;
        return false;
    }
    function register(node,pseudo=''){
        if(!node||node.closest('nav,footer,dialog,#mainNavbar')||node.closest('.image-frame-dialog'))return null;
        if(node.matches('.slide-bg')&&node.parentElement?.matches('.hero-slide'))node=node.parentElement.querySelector('.slide-bg');
        const css=getComputedStyle(node,pseudo||null),kind=node.tagName==='IMG'?'image':'background';
        const selector=selectorFor(node)+pseudo;
        const source=kind==='image'?localImage(node.getAttribute('src')||''):sourceOf(css.backgroundImage)||localImage(node.getAttribute('data-bg')||'');
        const configured=published[selector]||changes[selector];
        const emptyEditable=node.matches('.career-panel-bg,[data-ve-sports-background-area]');
        if((!source&&!configured&&!emptyEditable)||(kind==='image'&&!imageBackground(node)))return null;
        if(!targets.has(selector)||targets.get(selector).node!==node)targets.set(selector,{selector,node,pseudo,kind,source,layers:css.backgroundImage==='none'&&source?'url('+JSON.stringify(source)+')':css.backgroundImage,position:kind==='image'?css.objectPosition:css.backgroundPosition,fit:kind==='image'?css.objectFit:css.backgroundSize});
        return targets.get(selector);
    }
    function scan(){
        document.querySelectorAll('section,main img,section img,main [class],section [class],[data-bg],[data-ve-bg]').forEach(node=>{
            if(node.tagName==='IMG'){if(imageBackground(node))register(node);return;}
            if(sourceOf(getComputedStyle(node).backgroundImage)||node.hasAttribute('data-bg'))register(node);
            // CSS-generated layers are common for banners and quote sections.
            if(node.tagName==='SECTION'||/hero|banner|background|quote|header|section|intro/i.test(node.className||''))for(const pseudo of ['::before','::after'])if(sourceOf(getComputedStyle(node,pseudo).backgroundImage))register(node,pseudo);
        });
    }
    function resolve(selector){
        if(targets.get(selector)?.node.isConnected)return targets.get(selector);
        targets.delete(selector);
        const pseudo=selector.endsWith('::before')?'::before':selector.endsWith('::after')?'::after':'';
        try{const node=document.querySelector(pseudo?selector.slice(0,-pseudo.length):selector);return node?register(node,pseudo):null;}catch{return null;}
    }
    function target(node){
        if(!node||node.ownerDocument!==document)return null;
        if(node.closest('[data-ve-img="home-background"]'))node=node.closest('section');
        if(node.closest('[data-ve-history-background]'))node=node.closest('.quote-banner-box');
        if(node.hasAttribute('data-ve-sports-background'))node=document.querySelector('[data-ve-sports-background-area="'+node.getAttribute('data-ve-sports-background')+'"]');
        if(!node)return null;
        const own=Array.from(targets.values()).find(item=>item.node===node&&item.node.isConnected);if(own)return own;
        const self=register(node);if(self)return self;
        return Array.from(targets.values()).find(item=>item.node.isConnected&&node.contains(item.node))||register(node.querySelector?.('.career-panel-bg,[data-ve-sports-background-area]'))||null;
    }
    function value(selector,currentDevice=device()){
        return Object.prototype.hasOwnProperty.call(changes[selector]||{},currentDevice)?changes[selector][currentDevice]?.value:published[selector]?.[currentDevice]||null;
    }
    function frame(target,currentDevice=device()){
        const saved=value(target.selector,currentDevice);if(saved)return {...saved};
        const p=target.position.split(/\s+/),axis=(v)=>v==='left'||v==='top'?0:v==='right'||v==='bottom'?100:v?.endsWith('%')?Number.parseFloat(v):50;
        return {path:target.source,kind:target.kind,fit:target.fit==='contain'?'contain':'cover',x:axis(p[0]),y:axis(p[1]),zoom:1};
    }
    function buildRules(selector,setting){
        const t=resolve(selector);if(!t||!setting)return '';
        const src=setting.path,position=setting.x+'% '+setting.y+'%';
        if(t.kind==='image')return selector+'{content:url('+JSON.stringify(src)+')!important;object-fit:'+setting.fit+'!important;object-position:'+position+'!important;}';
        const layers=t.layers.includes('url(')?t.layers.replace(/url\(["']?[^"')]+["']?\)/g,'url('+JSON.stringify(src)+')'):'url('+JSON.stringify(src)+')'+(t.layers!=='none'?','+t.layers:'');
        const selectors=t.node.matches('.slide-bg')?Array.from(t.node.parentElement.querySelectorAll('.slide-bg')).map(selectorFor):[selector];
        return selectors.map(key=>key+'{background-image:'+layers+'!important;background-position:'+position+'!important;background-size:'+setting.fit+'!important;background-repeat:no-repeat!important;}').join('');
    }
    function remember(node){
        if(originals.has(node))return;
        const original={};for(const prop of ['object-position','object-fit','transform','transform-origin','clip-path','background-position','background-size'])original[prop]=[node.style.getPropertyValue(prop),node.style.getPropertyPriority(prop)];originals.set(node,original);
    }
    function restore(node){
        if(!node.hasAttribute('data-responsive-background-active'))return;
        delete node.dataset.responsiveBackgroundActive;
        for(const [prop,[v,priority]]of Object.entries(originals.get(node)||{})){if(v)node.style.setProperty(prop,v,priority);else node.style.removeProperty(prop);}
    }
    function apply(){
        const keys=new Set([...targets.keys(),...Object.keys(published),...Object.keys(changes)]);let css='';
        for(const d of ['desktop','tablet','mobile']){
            let rules='';for(const key of keys){const setting=value(key,d);if(setting)rules+=buildRules(key,setting);}
            const media=d==='mobile'?'(max-width:767px)':d==='tablet'?'(min-width:768px) and (max-width:1024px)':'(min-width:1025px)';
            if(forcedDevice){if(d===forcedDevice)css+=rules;}else css+='@media '+media+'{'+rules+'}';
        }
        if(style.textContent!==css)style.textContent=css;
        for(const key of keys){const t=resolve(key);if(!t)continue;const setting=value(key);
            if(t.kind==='image'){
                const node=t.node;
                if(setting){remember(node);node.dataset.responsiveBackgroundActive='1';node.style.setProperty('object-position',setting.x+'% '+setting.y+'%','important');node.style.setProperty('transform-origin',setting.x+'% '+setting.y+'%','important');node.style.setProperty('transform','scale('+setting.zoom+')','important');
                    const inset=1-1/setting.zoom;node.style.setProperty('clip-path','inset('+(inset*setting.y)+'% '+(inset*(100-setting.x))+'% '+(inset*(100-setting.y))+'% '+(inset*setting.x)+'%)','important');
                }else restore(node);
            }else if(!t.pseudo){
                const nodes=t.node.matches('.slide-bg')?Array.from(t.node.parentElement.querySelectorAll('.slide-bg')):[t.node];
                for(const node of nodes){if(setting){remember(node);node.dataset.responsiveBackgroundActive='1';node.style.setProperty('background-position',setting.x+'% '+setting.y+'%','important');node.style.setProperty('background-size',setting.fit,'important');}else restore(node);}
            }
            if(t.kind==='background'&&setting&&setting.zoom>1){
                let image=natural.get(setting.path);if(!image){image=new Image();image.onload=schedule;image.src=setting.path;natural.set(setting.path,image);}
                if(image.naturalWidth&&image.naturalHeight){const box=t.node.matches('.slide-bg')?t.node.parentElement:t.node;
                    const computed=t.pseudo?getComputedStyle(t.node,t.pseudo):null,width=computed?parseFloat(computed.width)||box.clientWidth:box.clientWidth,height=computed?parseFloat(computed.height)||box.clientHeight:box.clientHeight;
                    const scale=(setting.fit==='contain'?Math.min:Math.max)(width/image.naturalWidth,height/image.naturalHeight)*setting.zoom;
                    const layers=t.layers.split(/,(?![^()]*\))/).map(layer=>layer.includes('url(')?image.naturalWidth*scale+'px '+image.naturalHeight*scale+'px':'cover');
                    const rule=key+'{background-size:'+layers.join(',')+'!important;}';css+=rule;
                    if(!t.pseudo){const nodes=t.node.matches('.slide-bg')?Array.from(t.node.parentElement.querySelectorAll('.slide-bg')):[t.node];for(const node of nodes)node.style.setProperty('background-size',layers.join(','),'important');}
                }
            }
        }
        if(style.textContent!==css)style.textContent=css;
    }
    function schedule(){if(scheduled)return;scheduled=true;requestAnimationFrame(()=>{scheduled=false;apply();});}
    function stage(t,setting,file,currentDevice=device()){
        if(!t)return;
        changes[t.selector]=changes[t.selector]||{};
        changes[t.selector][currentDevice]={value:setting,file:file||null};apply();
        document.dispatchEvent(new CustomEvent('gnc:background-change',{detail:{selector:t.selector,device:currentDevice}}));
    }
    window.GNCResponsiveBackgrounds={target,frame,value,device,scan,apply,stage,targets,
        setDevice:d=>{forcedDevice=d;apply();},
        setChanges:next=>{changes=next||{};apply();},
        accept:settings=>{published=settings;changes={};apply();},
        reset:()=>{changes={};apply();},
        changes:()=>changes
    };
    function start(){scan();apply();window.addEventListener('resize',schedule);document.addEventListener('load',event=>{if(event.target.tagName==='IMG')schedule();},true);}
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
})();
