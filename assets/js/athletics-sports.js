(function(root,factory){const layout=typeof module==='object'&&module.exports?require('./facility-coverflow.js'):root.GNCFacilityCoverflow;const api=factory(layout);if(typeof module==='object'&&module.exports)module.exports=api;else {root.GNCSportsSlideshow=api;api.mount(root.document);}})(typeof globalThis!=='undefined'?globalThis:this,function(coverflow){
    'use strict';
    function refresh(doc){
        const original=doc.querySelector('[data-sports-original]');if(!original)return;
        const track=original.parentNode,viewport=track.parentNode;
        original.querySelectorAll('.is-duplicate').forEach(el=>el.remove());
        track.querySelectorAll('.athletics-sports-group.is-copy').forEach(el=>el.remove());
        const cards=Array.from(original.children);if(!cards.length)return;
        function duplicate(card){
            const clone=card.cloneNode(true);clone.classList.add('is-duplicate');clone.setAttribute('aria-hidden','true');clone.setAttribute('inert','');
            [clone,...clone.querySelectorAll('*')].forEach(el=>{
                el.removeAttribute('id');el.removeAttribute('contenteditable');
                Array.from(el.attributes).forEach(attr=>{if(attr.name.startsWith('data-ve-'))el.removeAttribute(attr.name);});
                Array.from(el.classList).filter(name=>name.startsWith('ve-')).forEach(name=>el.classList.remove(name));
            });return clone;
        }
        const cardWidth=cards[0].getBoundingClientRect().width||360;
        const repeats=Math.max(1,Math.ceil((viewport.clientWidth||1440)/(cardWidth*cards.length)));
        for(let i=1;i<repeats;i++)cards.forEach(card=>original.appendChild(duplicate(card)));
        const copy=original.cloneNode(false);copy.removeAttribute('data-sports-original');
        Array.from(copy.attributes).forEach(attr=>{if(attr.name.startsWith('data-ve-'))copy.removeAttribute(attr.name);});
        copy.classList.add('is-copy');copy.setAttribute('aria-hidden','true');copy.setAttribute('inert','');
        Array.from(original.children).forEach(card=>copy.appendChild(duplicate(card)));track.appendChild(copy);
    }
    function mount(doc){
        mountFacilities(doc);
        refresh(doc);const button=doc.querySelector('[data-sports-pause]'),slideshow=doc.querySelector('.athletics-slideshow');
        if(button&&!button.__sportsBound){button.__sportsBound=true;button.addEventListener('click',()=>{
            const paused=button.getAttribute('aria-pressed')!=='true';button.setAttribute('aria-pressed',String(paused));button.textContent=paused?'Play slideshow':'Pause slideshow';slideshow.classList.toggle('is-paused',paused);
        });}
        let frame=null;doc.defaultView.addEventListener('resize',()=>{if(frame!==null)doc.defaultView.cancelAnimationFrame(frame);frame=doc.defaultView.requestAnimationFrame(()=>{frame=null;refresh(doc);});});
    }
    function refreshFacilities(doc,selected){
        const root=doc.querySelector('.athletics-facilities-carousel');if(!root)return;
        const slides=Array.from(root.querySelectorAll('.athletics-facility-slide')),count=slides.length;
        let active=selected===undefined?slides.findIndex(slide=>slide.getAttribute('data-ve-facility')===root.__activeFacility):selected;
        active=count?((Math.max(0,active)%count)+count)%count:0;
        if(selected!==undefined&&count)active=((selected%count)+count)%count;
        root.__activeFacility=count?slides[active].getAttribute('data-ve-facility'):null;
        root.__activeFacilityIndex=active;
        slides.forEach((slide,index)=>{
            slide.setAttribute('data-facility-index',String(index));slide.setAttribute('aria-label',(index+1)+' of '+count);
        });
        const dots=root.querySelector('.athletics-facilities-dots'),controls=root.querySelector('.athletics-facilities-controls');
        if(controls)controls.hidden=count<2;
        if(dots&&dots.children.length!==count){Array.from(dots.children).forEach(button=>button.remove());slides.forEach((slide,index)=>{
            const dot=doc.createElement('button');dot.type='button';dot.className='fcar-dot';dot.setAttribute('data-facility-index',String(index));dot.setAttribute('aria-label','Show facility '+(index+1));dot.setAttribute('aria-current',String(index===active));dots.appendChild(dot);
        });}
        if(dots)Array.from(dots.children).forEach((dot,index)=>dot.setAttribute('aria-current',String(index===active)));
        coverflow.layout(slides,dots?Array.from(dots.children):[],active);
    }
    function mountFacilities(doc){
        const root=doc.querySelector('.athletics-facilities-carousel');if(!root)return;refreshFacilities(doc);
        if(root.__facilitiesBound)return;root.__facilitiesBound=true;
        const view=doc.defaultView,stage=root.querySelector('.athletics-facilities-stage');
        let timer=null,onScreen=true,hovered=false,focused=false,startX=null,dragMoved=false,lightbox=null;
        const editing=()=>!!doc.querySelector('[data-ve-sports-preview]')&&!doc.documentElement.classList.contains('ve-previewing');
        function pause(){if(timer!==null)view.clearInterval(timer);timer=null;}
        function auto(){
            pause();if(editing()||doc.hidden||!onScreen||hovered||focused||lightbox||root.querySelectorAll('.athletics-facility-slide').length<2||view.matchMedia?.('(prefers-reduced-motion: reduce)').matches)return;
            timer=view.setInterval(()=>refreshFacilities(doc,root.__activeFacilityIndex+1),5000);
        }
        function go(index){refreshFacilities(doc,index);auto();}
        function openPhoto(index){
            if(editing())return;const slides=Array.from(root.querySelectorAll('.athletics-facility-slide'));
            if(!slides[index]?.querySelector('img')?.getAttribute('src'))return;
            const lastFocus=doc.activeElement,previousOverflow=doc.body.style.overflow;
            lightbox=doc.createElement('dialog');lightbox.className='sports-facility-lightbox';lightbox.setAttribute('aria-label','Facility photo');
            const figure=doc.createElement('figure'),image=doc.createElement('img'),caption=doc.createElement('figcaption');figure.appendChild(image);figure.appendChild(caption);
            function button(label,text){const button=doc.createElement('button');button.type='button';button.setAttribute('aria-label',label);button.textContent=text;return button;}
            const previous=button('Previous photo','‹'),next=button('Next photo','›'),close=button('Close','×');close.className='sports-facility-lightbox-close';
            function show(value){index=((value%slides.length)+slides.length)%slides.length;const source=slides[index].querySelector('img');image.src=source.src;image.alt=source.alt;caption.textContent=source.alt;previous.hidden=next.hidden=slides.length<2;}
            previous.addEventListener('click',()=>show(index-1));next.addEventListener('click',()=>show(index+1));close.addEventListener('click',()=>lightbox.close());
            lightbox.appendChild(previous);lightbox.appendChild(figure);lightbox.appendChild(next);lightbox.appendChild(close);
            lightbox.addEventListener('keydown',event=>{if(event.key==='ArrowLeft'){event.preventDefault();show(index-1);}else if(event.key==='ArrowRight'){event.preventDefault();show(index+1);}});
            lightbox.addEventListener('click',event=>{if(event.target===lightbox)lightbox.close();});
            lightbox.addEventListener('close',()=>{lightbox.remove();lightbox=null;doc.body.style.overflow=previousOverflow;lastFocus?.focus();auto();});
            show(index);doc.body.appendChild(lightbox);pause();doc.body.style.overflow='hidden';lightbox.showModal();close.focus();
        }
        root.addEventListener('click',event=>{
            const target=event.target;if(target.closest('[contenteditable], [data-ve-facility-upload]')||(dragMoved&&target.closest('.athletics-facilities-stage')))return;
            if(target.closest('[data-facility-prev]'))go(root.__activeFacilityIndex-1);
            else if(target.closest('[data-facility-next]'))go(root.__activeFacilityIndex+1);
            else {const choice=target.closest('[data-facility-index]');if(choice){const index=Number(choice.getAttribute('data-facility-index'));if(choice.matches('.athletics-facility-slide')&&index===root.__activeFacilityIndex)openPhoto(index);else go(index);}}
        });
        root.addEventListener('keydown',event=>{
            if(event.target.closest('[contenteditable]'))return;
            if(event.key==='ArrowLeft'||event.key==='ArrowRight'){event.preventDefault();go(root.__activeFacilityIndex+(event.key==='ArrowLeft'?-1:1));}
            else if((event.key==='Enter'||event.key===' ')&&event.target.matches('.fcar-stage,.fcar-slide')){event.preventDefault();openPhoto(root.__activeFacilityIndex);}
        });
        if(stage){
            stage.addEventListener('pointerdown',event=>{if(!editing()){startX=event.clientX;dragMoved=false;pause();}});
            stage.addEventListener('pointermove',event=>{if(startX===null)return;const distance=event.clientX-startX;if(Math.abs(distance)>40){dragMoved=true;startX=null;refreshFacilities(doc,root.__activeFacilityIndex+(distance<0?1:-1));}});
            stage.addEventListener('pointerup',()=>{startX=null;auto();});stage.addEventListener('pointercancel',()=>{startX=null;dragMoved=false;auto();});
        }
        root.addEventListener('mouseenter',()=>{hovered=true;pause();});root.addEventListener('mouseleave',()=>{hovered=false;dragMoved=false;auto();});
        root.addEventListener('focusin',()=>{focused=true;pause();});root.addEventListener('focusout',()=>{focused=false;auto();});
        doc.addEventListener('visibilitychange',auto);view.addEventListener('pagehide',pause);
        if(view.IntersectionObserver)new view.IntersectionObserver(entries=>{onScreen=entries[0].isIntersecting;auto();},{threshold:.25}).observe(root);
        if(view.MutationObserver)new view.MutationObserver(auto).observe(doc.documentElement,{attributes:true,attributeFilter:['class']});
        auto();
    }
    return {refresh,mount,refreshFacilities};
});
