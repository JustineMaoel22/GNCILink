(function bootFeedback() {
    'use strict';
    const permitted=()=>!!window.GNCPrivacy?.allows('personalization')&&!!window.GNCPrivacy?.allows('external');
    const dialog=document.getElementById('gncVisitorFeedback');
    if (!dialog || window.top!==window.self || /^\/admin(?:\/|$)/.test(location.pathname) || new URLSearchParams(location.search).has('ce_preview') || !dialog.showModal) return;
    const KEY='gnci_feedback_v1',LEASE='gnci_feedback_prompt_v1',DAY=86400000,WAIT=60000;
    const form=document.getElementById('gncFeedbackRatings');
    const questions=document.getElementById('gncFeedbackQuestions');
    const status=document.getElementById('gncFeedbackStatus');
    const submittedButton=document.getElementById('gncFeedbackSubmitted');
    const trigger=document.getElementById('gncFeedbackTrigger');
    const owner=crypto.randomUUID?crypto.randomUUID():String(Date.now())+Math.random();
    let memory={ms:0,until:0,offeredUntil:0},last=performance.now(),active=visible(),opened=false,manualOpen=false,closing=false,previousFocus,scroll;
    let verified=false,busy=false,csrf='',schema='',controller=null,generation=0;
    function visible(){return !document.hidden&&document.hasFocus();}
    function read(){
        try {
            const value=JSON.parse(localStorage.getItem(KEY));
            if(value&&typeof value==='object') {
                // The former acknowledgment button did not verify a response.
                memory=value.submitted===true&&value.verified!==true?{ms:0,until:0,offeredUntil:0}:{ms:Math.min(WAIT,Math.max(0,Number(value.ms)||0)),until:Number(value.until)||0,offeredUntil:Number(value.offeredUntil)||0};
            }
        }catch(_){}
        return memory;
    }
    function write(value){memory=value;if(!permitted())return;try{localStorage.setItem(KEY,JSON.stringify(value));}catch(_){} }
    function suppressed(){const s=read();return s.until>Date.now()||s.offeredUntil>Date.now();}
    function lease(){
        try {const old=JSON.parse(localStorage.getItem(LEASE));if(old&&old.until>Date.now()&&old.owner!==owner)return false;localStorage.setItem(LEASE,JSON.stringify({owner,until:Date.now()+15000}));return JSON.parse(localStorage.getItem(LEASE)).owner===owner;}catch(_){return true;}
    }
    function controls(disabled){
        submittedButton.disabled=disabled;
        form.querySelector('button[type="submit"]').disabled=disabled;
    }
    async function request(action,answers){
        const current=generation;
        controller=new AbortController();
        const timer=setTimeout(()=>controller?.abort(),30000);
        try{
            const perform=()=>fetch('/api/visitor-feedback.php',{method:'POST',credentials:'same-origin',
                headers:{'Content-Type':'application/json','X-GNC-Feedback':'1'},
                body:JSON.stringify({action,csrf,answers}),signal:controller.signal});
            // Serialize first-use requests across tabs so the signed identity
            // cookie cannot be overwritten by a second simultaneous status call.
            const response=navigator.locks?await navigator.locks.request('gnci-feedback-identity',perform):await perform();
            const data=await response.json();
            if(current!==generation||!permitted())throw Error('Feedback permission changed.');
            if(!response.ok||data.ok!==true)throw Error(data.error||'Could not verify your response. Please try again.');
            csrf=data.csrf;
            return data;
        }finally{clearTimeout(timer);controller=null;}
    }
    function render(data,checking=false){
        verified=data.submitted===true;
        document.getElementById('gncFeedbackTitle').textContent=verified?'You already submitted':'Rate Your Experience';
        document.getElementById('gncFeedbackDescription').textContent=verified?'Thank you for rating your experience with GNCILink.':'Help us improve GNCILink by rating your experience.';
        document.getElementById('gncFeedbackHelp').hidden=verified;
        dialog.querySelector('.gnc-feedback-secondary').textContent=verified?'Close':'Not Now';
        form.hidden=verified||data.pending===true;
        if(verified){
            form.reset();
            write({ms:0,until:Date.now()+30*DAY,offeredUntil:0,submitted:true,verified:true});
            status.textContent='Your response is saved. This browser cannot submit another rating.';
            return;
        }
        if(data.pending){status.textContent='Your response is being checked. Use I\'ve submitted to check again; please do not submit another response.';return;}
        if(!Array.isArray(data.questions)||data.questions.length!==6)throw Error('Feedback questions are unavailable.');
        const nextSchema=JSON.stringify(data.questions);
        if(schema!==nextSchema){
            questions.replaceChildren();
            let previousSection='';
            data.questions.forEach(question=>{
                if(question.section&&question.section!==previousSection){const heading=document.createElement('h3');heading.textContent=question.section;questions.append(heading);previousSection=question.section;}
                const fieldset=document.createElement('fieldset'),legend=document.createElement('legend'),options=document.createElement('div');
                legend.textContent=question.title+' *';fieldset.append(legend);options.className='gnc-feedback-rating-options';
                for(let value=1;value<=5;value++){
                    const label=document.createElement('label'),input=document.createElement('input');
                    input.type='radio';input.name=question.id;input.value=String(value);input.required=true;
                    label.append(input,document.createTextNode(String(value)));options.append(label);
                }
                fieldset.append(options);questions.append(fieldset);
            });
            schema=nextSchema;
        }
        status.textContent=checking?'No completed response was found. Answer all six questions and press Submit ratings.':'';
    }
    async function checkSubmission(checking=false){
        if(busy)return;
        busy=true;controls(true);status.textContent='Checking your response...';
        try{render(await request('status'),checking);}
        catch(error){status.textContent=error.name==='AbortError'?'Verification timed out. Please check again.':error.message;}
        finally{busy=false;controls(false);}
    }
    form.addEventListener('submit',async event=>{
        event.preventDefault();
        if(busy||verified||!form.reportValidity())return;
        const answers={};new FormData(form).forEach((value,key)=>{answers[key]=Number(value);});
        if(Object.keys(answers).length!==6){status.textContent='Please answer all six rating questions.';return;}
        busy=true;controls(true);status.textContent='Saving your ratings...';
        try{
            const data=await request('submit',answers);
            render(data);
            if(data.submitted!==true&&!data.pending)status.textContent='Your response was not confirmed. Use I\'ve submitted to check before trying again.';
        }catch(error){status.textContent=error.name==='AbortError'?'Saving timed out. Use I\'ve submitted to check whether your response was saved.':error.message;}
        finally{busy=false;controls(false);}
    });
    submittedButton.addEventListener('click',()=>{if(verified)close(false);else checkSubmission(true);});
    function open(manual=false){
        if(!permitted()||dialog.open||closing||!visible()||document.querySelector('dialog[open],.modal.show,.offcanvas.show'))return;
        if(!manual&&(opened||suppressed()||!lease()))return;
        // Mark the offer before showing: refresh/navigation cannot repeatedly interrupt this visit.
        if(!manual){write({...read(),offeredUntil:Date.now()+30*60000});opened=true;}
        manualOpen=manual;previousFocus=document.activeElement;scroll={left:scrollX,top:scrollY,behavior:'instant'};
        dialog.hidden=false;document.documentElement.classList.add('gnc-feedback-open');dialog.showModal();window.scrollTo(scroll);
        checkSubmission();
        dialog.querySelector('.gnc-feedback-close').focus({preventScroll:true});
    }
    function tick(){
        if(!permitted()){last=performance.now();return;}
        const now=performance.now(),elapsed=active?Math.min(5000,Math.max(0,now-last)):0;last=now;
        const state=read();
        if(state.until>Date.now()||state.offeredUntil>Date.now()){if(dialog.open&&!manualOpen&&!verified&&state.until>Date.now())close(false);return;}
        if(elapsed)write({...state,ms:Math.min(WAIT,state.ms+elapsed)});
        if(read().ms>=WAIT) {
            if(navigator.locks)navigator.locks.request('gnci-feedback-prompt',{ifAvailable:true},lock=>{if(lock)open();});
            else open();
        }
    }
    function close(dismiss=true){
        if(closing||!dialog.open)return;
        if(dismiss&&!manualOpen&&!verified)write({ms:0,until:Date.now()+7*DAY,offeredUntil:0});
        closing=true;
        const finish=()=>{dialog.close();dialog.hidden=true;dialog.classList.remove('is-closing');document.documentElement.classList.remove('gnc-feedback-open');closing=false;if(scroll)window.scrollTo(scroll);if(previousFocus?.isConnected)previousFocus.focus({preventScroll:true});};
        if(matchMedia('(prefers-reduced-motion: reduce)').matches)finish();else{dialog.classList.add('is-closing');setTimeout(finish,180);}
    }
    if(trigger){
        trigger.hidden=false;
        trigger.addEventListener('click',()=>{
            if(!permitted()){window.GNCPrivacy?.manage();return;}
            open(true);
        });
    }
    dialog.querySelectorAll('[data-feedback-dismiss]').forEach(button=>button.addEventListener('click',()=>close()));
    dialog.addEventListener('cancel',event=>{event.preventDefault();close();});
    dialog.addEventListener('keydown',event=>{
        if(event.key!=='Tab')return;
        const stops=Array.from(dialog.querySelectorAll('button:not(:disabled),input:checked,input:not([type="radio"]),input[type="radio"]:first-of-type')).filter(el=>!el.closest('[hidden]'));
        if(event.shiftKey&&document.activeElement===stops[0]){event.preventDefault();stops[stops.length-1].focus();}
        else if(!event.shiftKey&&document.activeElement===stops[stops.length-1]){event.preventDefault();stops[0].focus();}
    });
    function activity(){tick();active=visible();last=performance.now();}
    document.addEventListener('visibilitychange',activity);window.addEventListener('focus',activity);window.addEventListener('blur',activity);
    window.addEventListener('pagehide',()=>{tick();active=false;});window.addEventListener('pageshow',()=>{active=visible();last=performance.now();});
    window.addEventListener('storage',event=>{if(permitted()&&event.key===KEY){read();if(dialog.open)checkSubmission();}});
    window.GNCVisitorFeedback={due:()=>!opened&&!suppressed()&&read().ms>=WAIT,hasPrompted:()=>opened};
    window.addEventListener('gnc:privacychange',()=>{if(!permitted()){generation++;controller?.abort();verified=false;csrf='';schema='';questions.replaceChildren();form.hidden=true;memory={ms:0,until:0,offeredUntil:0};if(dialog.open)close(false);}});
    if(permitted())read();setInterval(tick,1000);
})();
