/* Shared by the Programs and Athletics facility carousels. */
(function(root,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else root.GNCFacilityCoverflow=api;})(typeof globalThis!=='undefined'?globalThis:this,function(){
    'use strict';
    const states=new WeakMap();
    function layout(slides,dots,active){
        if(!slides.length)return;
        const stage=slides[0].parentNode;
        let state=states.get(stage);if(!state){state={previous:new WeakMap(),timer:null,settle:null};states.set(stage,state);}
        const side=distance=>distance<0?'hide-l':'hide-r';
        function put(slide,position,instant){
            if(instant)slide.classList.add('is-jump');slide.setAttribute('data-pos',position);
            if(instant){void slide.offsetWidth;slide.classList.remove('is-jump');}
        }
        if(state.timer){clearTimeout(state.timer);state.settle();state.timer=null;}
        let settleNeeded=false;
        slides.forEach((slide,index)=>{
            let distance=((index-active)%slides.length+slides.length)%slides.length;
            if(distance>slides.length/2)distance-=slides.length;
            const visible=Math.abs(distance)<=1,old=state.previous.get(slide);
            if(visible)put(slide,String(distance),old!==undefined&&Math.abs(distance-old)>1);
            else if(old!==undefined&&Math.abs(old)<=1&&(old<0)!==(distance<0)){put(slide,side(old),false);settleNeeded=true;}
            else put(slide,side(distance),old!==undefined&&old!==distance&&Math.abs(old)>1&&(old<0)!==(distance<0));
            state.previous.set(slide,distance);slide.tabIndex=distance===0?0:-1;slide.setAttribute('aria-hidden',visible?'false':'true');
            const image=slide.querySelector('img');if(image&&Math.abs(distance)<=3&&image.loading==='lazy')image.loading='eager';
        });
        state.settle=()=>{slides.forEach(slide=>{const position=slide.getAttribute('data-pos'),desired=side(state.previous.get(slide));if(position&&position.startsWith('hide')&&position!==desired)put(slide,desired,true);});state.timer=null;};
        if(settleNeeded)state.timer=setTimeout(state.settle,420);
        dots.forEach((dot,index)=>{dot.classList.toggle('is-active',index===active);dot.setAttribute('aria-selected',String(index===active));});
    }
    return {layout};
});
