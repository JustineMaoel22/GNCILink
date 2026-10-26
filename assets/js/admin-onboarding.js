(function () {
    'use strict';
    const source = document.getElementById('adminOnboardingData');
    if (!source || window.self !== window.top) return;
    const config = JSON.parse(source.textContent);
    config.steps = config.steps.filter(step => !step.optional || document.querySelector(step.selector));
    let dialog, card, ring, title, description, progress, error, back, next, skip;
    let index = 0, busy = false, previousFocus, sidebarWasOpen, overlayWasOpen, mainScroll;
    const sidebar = document.getElementById('gnc-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    function node(tag, className, text) {
        const el = document.createElement(tag); el.className = className || '';
        if (text) el.textContent = text;
        return el;
    }
    function place() {
        if (!dialog || !dialog.open) return;
        const step = config.steps[index];
        const target = document.querySelector(step.selector);
        const mobile = sidebar && getComputedStyle(sidebar).position === 'fixed' && innerWidth < 992;
        const inSidebar = target && sidebar && sidebar.contains(target);
        if (sidebar && mobile) sidebar.classList.toggle('open', inSidebar || sidebarWasOpen);
        if (overlay && mobile) overlay.classList.toggle('open', inSidebar || overlayWasOpen);
        let r = target && target.getBoundingClientRect();
        if (target && r && (r.top < 0 || r.bottom > innerHeight)) {
            target.scrollIntoView({block: 'nearest', behavior: 'instant'});
            r = target.getBoundingClientRect();
        }
        if (!r || r.width === 0 || r.right < 0 || r.left >= innerWidth) r = {left:innerWidth / 2, top:12, width:0, height:0, right:innerWidth / 2, bottom:12};
        Object.assign(ring.style, {left:Math.max(0,r.left-4)+'px', top:Math.max(0,r.top-4)+'px', width:r.width+8+'px', height:r.height+8+'px'});
        const c = card.getBoundingClientRect();
        let x = r.right + 16, y = r.top;
        if (x + c.width > innerWidth - 12) { x = Math.max(12, Math.min(r.left, innerWidth-c.width-12)); y = r.bottom + 16; }
        if (y + c.height > innerHeight-12) y = Math.max(12,r.top-c.height-16);
        Object.assign(card.style, {left:Math.max(12,Math.min(x,innerWidth-c.width-12))+'px', top:Math.max(12,Math.min(y,innerHeight-c.height-12))+'px'});
    }
    function render() {
        const step = config.steps[index];
        title.textContent = step.title; description.textContent = step.text;
        progress.textContent = 'Step '+(index+1)+' of '+config.steps.length;
        back.disabled = index === 0; next.textContent = index === config.steps.length-1 ? 'Finish' : 'Next';
        error.textContent = config.persist ? '' : 'Progress saving is unavailable. You can still explore this guide.';
        place();
        // Sidebar transitions may still be running when the step changes.
        setTimeout(place, 320);
    }
    function close() {
        dialog.close(); dialog.remove(); dialog = null;
        if (sidebar) sidebar.classList.toggle('open', sidebarWasOpen);
        if (overlay) overlay.classList.toggle('open', overlayWasOpen);
        window.scrollTo({left:mainScroll.x, top:mainScroll.y, behavior:'instant'});
        if (previousFocus && previousFocus.isConnected) previousFocus.focus({preventScroll:true});
    }
    async function finish(status) {
        if (busy) return;
        busy = true; [back,next,skip].forEach(button => button.disabled = true);
        error.textContent = '';
        try {
            if (config.persist) {
                const response = await fetch('/admin/action/onboarding-handler.php', {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({csrf_token:config.csrf,status})});
                const result = await response.json();
                if (!response.ok || !result.success) throw Error(result.error || 'Could not save your tutorial progress. Please try again.');
            }
            close();
        } catch (e) {
            error.textContent = e.message || 'Could not save your tutorial progress. Please try again.';
        } finally {
            busy = false;
            if (dialog) { [next,skip].forEach(button => button.disabled = false); back.disabled = index===0; place(); }
        }
    }
    function start() {
        if (dialog || document.querySelector('dialog[open]')) return;
        index=0; previousFocus=document.activeElement;
        sidebarWasOpen=sidebar && sidebar.classList.contains('open'); overlayWasOpen=overlay && overlay.classList.contains('open');
        mainScroll={x:scrollX,y:scrollY};
        dialog=node('dialog','gnc-tour');dialog.setAttribute('aria-labelledby','gncTourTitle');dialog.setAttribute('aria-describedby','gncTourText');
        ring=node('div','gnc-tour-ring');ring.setAttribute('aria-hidden','true');
        card=node('div','gnc-tour-card');progress=node('div','gnc-tour-progress');progress.setAttribute('aria-live','polite');
        title=node('h2');title.id='gncTourTitle';description=node('p');description.id='gncTourText';
        error=node('p','gnc-tour-error');error.setAttribute('role','alert');
        if (!config.persist) error.textContent='Progress saving is unavailable. You can still explore this guide.';
        const actions=node('div','gnc-tour-actions');
        skip=node('button','btn btn-outline-secondary','Skip');back=node('button','btn btn-outline-secondary','Back');next=node('button','btn btn-gnc-gold','Next');
        [skip,back,next].forEach(button=>{button.type='button';actions.appendChild(button);});
        skip.addEventListener('click',()=>finish('skipped'));
        back.addEventListener('click',()=>{if(index>0){index--;render();}});
        next.addEventListener('click',()=>{if(index===config.steps.length-1)finish('completed');else{index++;render();}});
        [progress,title,description,error,actions].forEach(el=>card.appendChild(el));
        dialog.append(ring,card);document.body.appendChild(dialog);dialog.showModal();render();next.focus();
        dialog.addEventListener('cancel',event=>{event.preventDefault();finish('skipped');});
    }
    window.addEventListener('resize',place);
    document.addEventListener('scroll',place,true);
    document.querySelectorAll('[data-start-onboarding]').forEach(button=>button.addEventListener('click',start));
    if (config.auto) start();
})();
