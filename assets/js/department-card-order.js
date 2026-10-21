/* Preview-only department swaps. The Content Editor publishes the resulting order. */
(function(root,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else root.GNCDepartmentOrder=api;})(typeof globalThis!=='undefined'?globalThis:this,function(){
    'use strict';
    function create(doc,options){
        const grid=doc.querySelector('[data-ve-department-grid]');if(!grid)return null;
        const selector='[data-ve-department-card]',view=doc.defaultView;
        const cards=()=>Array.from(grid.querySelectorAll(selector));
        const order=()=>cards().map(card=>Number(card.getAttribute('data-ve-department-card')));
        let base=JSON.stringify(order()),timer=null,pending=null,drag=null,target=null,ghost=null,suppressUntil=0;
        const listeners=[];
        function listen(node,name,fn,opts){node.addEventListener(name,fn,opts);listeners.push(()=>node.removeEventListener(name,fn,opts));}
        function available(){const s=options.state();return !s.preview&&!s.busy;}
        function clear(){
            clearTimeout(timer);timer=null;pending=null;
            if(drag){drag.card.classList.remove('ve-drag-source');try{drag.card.releasePointerCapture(drag.id);}catch(_){}suppressUntil=Date.now()+450;}
            if(target)target.classList.remove('ve-drag-target');if(ghost)ghost.remove();
            doc.documentElement.classList.remove('ve-dragging');drag=null;target=null;ghost=null;
        }
        function swap(a,b){
            const marker=doc.createComment('department-swap');grid.insertBefore(marker,a);grid.insertBefore(a,b);grid.insertBefore(b,marker);marker.remove();
            options.changed();options.announce('Positions swapped. Publish to save the new order.');
        }
        function follow(x,y){if(ghost)ghost.style.transform='translate3d('+(x-drag.dx)+'px,'+(y-drag.dy)+'px,0)';}
        function lift(){
            if(!pending||!available())return clear();
            drag=pending;pending=null;options.start();view.getSelection()?.removeAllRanges();
            const rect=drag.card.getBoundingClientRect();drag.dx=drag.x-rect.left;drag.dy=drag.y-rect.top;
            ghost=doc.createElement('div');ghost.className='ve-drag-preview';ghost.setAttribute('aria-hidden','true');ghost.setAttribute('inert','');
            ghost.style.width=rect.width+'px';ghost.style.height=rect.height+'px';
            const clone=drag.card.cloneNode(true);
            [clone,...clone.querySelectorAll('*')].forEach(el=>{el.removeAttribute('id');el.removeAttribute('contenteditable');Array.from(el.attributes).forEach(attr=>{if(attr.name.startsWith('data-ve-'))el.removeAttribute(attr.name);});});
            clone.style.width='100%';clone.style.height='100%';clone.style.margin='0';clone.style.opacity='1';clone.style.transform='none';clone.style.transition='none';
            ghost.appendChild(clone);doc.body.appendChild(ghost);follow(drag.x,drag.y);
            drag.card.classList.add('ve-drag-source');doc.documentElement.classList.add('ve-dragging');
            try{drag.card.setPointerCapture(drag.id);}catch(_){}
        }
        cards().forEach(card=>{
            card.style.position='relative';
            const handle=doc.createElement('button');handle.type='button';handle.className='ve-drag-handle';handle.setAttribute('data-ve-department-drag','');
            handle.textContent='⠿ Hold to drag';handle.title='Hold to swap positions, or use arrow keys';handle.setAttribute('aria-label','Reorder department: hold to drag or use arrow keys');card.appendChild(handle);
        });
        listen(doc,'pointerdown',event=>{
            if(!available()||event.button!==0||pending||drag)return;
            const card=event.target.closest(selector);if(!card||card.parentNode!==grid)return;
            const handle=event.target.closest('[data-ve-department-drag]');
            if(!handle&&(event.pointerType==='touch'||event.target.closest('[contenteditable],button,input,textarea,select')))return;
            pending={card,id:event.pointerId,x:event.clientX,y:event.clientY};timer=setTimeout(lift,350);
        });
        listen(doc,'pointermove',event=>{
            if(pending&&event.pointerId===pending.id&&Math.hypot(event.clientX-pending.x,event.clientY-pending.y)>8)return clear();
            if(!drag||event.pointerId!==drag.id)return;
            if(!available())return clear();event.preventDefault();follow(event.clientX,event.clientY);
            const hit=doc.elementFromPoint(event.clientX,event.clientY);let next=hit&&hit.closest(selector);
            if(next===drag.card||next?.parentNode!==grid)next=null;
            if(target!==next){if(target)target.classList.remove('ve-drag-target');target=next;if(target)target.classList.add('ve-drag-target');}
            if(event.clientY<50)view.scrollBy(0,-18);else if(event.clientY>view.innerHeight-50)view.scrollBy(0,18);
        },{passive:false});
        listen(doc,'pointerup',event=>{
            if((drag||pending)&&event.pointerId!==(drag||pending).id)return;
            if(drag&&target&&available()){event.preventDefault();swap(drag.card,target);}clear();
        });
        listen(doc,'pointercancel',clear);
        listen(view,'blur',clear);
        listen(doc,'keydown',event=>{
            if(event.key==='Escape')return clear();
            const handle=event.target.closest('[data-ve-department-drag]');if(!handle||!available())return;
            const delta={ArrowLeft:-1,ArrowUp:-1,ArrowRight:1,ArrowDown:1}[event.key];if(!delta)return;
            event.preventDefault();clear();const list=cards(),card=handle.closest(selector),other=list[list.indexOf(card)+delta];if(other){swap(card,other);handle.focus();}
        });
        return {scope:grid.getAttribute('data-ve-department-grid'),order,dirty:()=>JSON.stringify(order())!==base,
            saved(){base=JSON.stringify(order());},consumeClick(){return !!drag||Date.now()<suppressUntil;},
            dispose(){clear();listeners.forEach(fn=>fn());grid.querySelectorAll('[data-ve-department-drag]').forEach(el=>el.remove());}};
    }
    return {create};
});
