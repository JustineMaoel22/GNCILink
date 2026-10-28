(function () {
    'use strict';
    const dialog=document.getElementById('gncVisitorFeedback');
    if (!dialog || window.top!==window.self || /^\/admin(?:\/|$)/.test(location.pathname) || new URLSearchParams(location.search).has('ce_preview') || !dialog.showModal) return;
    const KEY='gnci_feedback_v1',LEASE='gnci_feedback_prompt_v1',DAY=86400000,WAIT=60000;
    const frame=document.getElementById('gncFeedbackGoogleForm');
    const owner=crypto.randomUUID?crypto.randomUUID():String(Date.now())+Math.random();
    let memory={ms:0,until:0,offeredUntil:0},last=performance.now(),active=visible(),opened=false,complete=false,closing=false,previousFocus,scroll;
    function visible(){return !document.hidden&&document.hasFocus();}
    function read(){
        try { const value=JSON.parse(localStorage.getItem(KEY));if(value&&typeof value==='object')memory={ms:Math.min(WAIT,Math.max(0,Number(value.ms)||0)),until:Number(value.until)||0,offeredUntil:Number(value.offeredUntil)||0}; }catch(_){}
        return memory;
    }
    function write(value){memory=value;try{localStorage.setItem(KEY,JSON.stringify(value));}catch(_){} }
    function suppressed(){const s=read();return s.until>Date.now()||s.offeredUntil>Date.now();}
    function lease(){
        try {const old=JSON.parse(localStorage.getItem(LEASE));if(old&&old.until>Date.now()&&old.owner!==owner)return false;localStorage.setItem(LEASE,JSON.stringify({owner,until:Date.now()+15000}));return JSON.parse(localStorage.getItem(LEASE)).owner===owner;}catch(_){return true;}
    }
    function open(){
        if(opened||suppressed()||!visible()||document.querySelector('dialog[open],.modal.show,.offcanvas.show')||!lease())return;
        // Mark the offer before showing: refresh/navigation cannot repeatedly interrupt this visit.
        write({...read(),offeredUntil:Date.now()+30*60000});
        opened=true;previousFocus=document.activeElement;scroll={left:scrollX,top:scrollY,behavior:'instant'};
        dialog.hidden=false;document.documentElement.classList.add('gnc-feedback-open');dialog.showModal();window.scrollTo(scroll);
        if(!frame.hasAttribute('src'))frame.src=frame.dataset.src;
        dialog.querySelector('.gnc-feedback-close').focus({preventScroll:true});
    }
    function tick(){
        const now=performance.now(),elapsed=active?Math.min(5000,Math.max(0,now-last)):0;last=now;
        const state=read();
        if(state.until>Date.now()||state.offeredUntil>Date.now()){if(dialog.open&&!complete&&state.until>Date.now())close(false);return;}
        if(elapsed)write({...state,ms:Math.min(WAIT,state.ms+elapsed)});
        if(read().ms>=WAIT) {
            if(navigator.locks)navigator.locks.request('gnci-feedback-prompt',{ifAvailable:true},lock=>{if(lock)open();});
            else open();
        }
    }
    function close(dismiss=true){
        if(closing||!dialog.open)return;
        if(dismiss&&!complete)write({ms:0,until:Date.now()+7*DAY,offeredUntil:0});
        closing=true;
        const finish=()=>{dialog.close();dialog.hidden=true;dialog.classList.remove('is-closing');document.documentElement.classList.remove('gnc-feedback-open');closing=false;if(scroll)window.scrollTo(scroll);if(previousFocus?.isConnected)previousFocus.focus({preventScroll:true});};
        if(matchMedia('(prefers-reduced-motion: reduce)').matches)finish();else{dialog.classList.add('is-closing');setTimeout(finish,180);}
    }
    dialog.querySelectorAll('[data-feedback-dismiss]').forEach(button=>button.addEventListener('click',()=>close()));
    dialog.addEventListener('cancel',event=>{event.preventDefault();close();});
    dialog.addEventListener('keydown',event=>{
        if(event.key!=='Tab')return;
        const stops=Array.from(dialog.querySelectorAll('button:not(:disabled),a[href],iframe')).filter(el=>!el.closest('[hidden]'));
        if(event.shiftKey&&document.activeElement===stops[0]){event.preventDefault();stops[stops.length-1].focus();}
        else if(!event.shiftKey&&document.activeElement===stops[stops.length-1]){event.preventDefault();stops[0].focus();}
    });
    document.getElementById('gncFeedbackSubmitted').addEventListener('click',()=>{
        // Explicit visitor acknowledgment: cross-origin iframe loads cannot prove submission.
        complete=true;write({ms:0,until:Date.now()+30*DAY,offeredUntil:0});close(false);
    });
    function activity(){tick();active=visible();last=performance.now();}
    document.addEventListener('visibilitychange',activity);window.addEventListener('focus',activity);window.addEventListener('blur',activity);
    window.addEventListener('pagehide',()=>{tick();active=false;});window.addEventListener('pageshow',()=>{active=visible();last=performance.now();});
    window.addEventListener('storage',event=>{if(event.key===KEY){read();if(memory.until>Date.now()&&dialog.open&&!complete)close(false);}});
    window.GNCVisitorFeedback={due:()=>!opened&&!suppressed()&&read().ms>=WAIT,hasPrompted:()=>opened};
    read();setInterval(tick,1000);
})();
