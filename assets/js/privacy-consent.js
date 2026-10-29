(function(){
    'use strict';
    if(window.GNCPrivacy || new URLSearchParams(location.search).has('ce_preview') || /^\/admin(?:\/|$)/.test(location.pathname))return;
    const KEY='gnc_privacy_v1',AGE=180*86400000,CATEGORIES=['analytics','personalization','external'];
    const restricted=!!navigator.globalPrivacyControl||navigator.doNotTrack==='1';
    let choice=null,previousFocus=null;
    const channel=typeof BroadcastChannel==='function'?new BroadcastChannel('gnc-privacy-choices'):null;
    function read(){try{const raw=document.cookie.split('; ').find(row=>row.startsWith(KEY+'='));const saved=raw&&JSON.parse(decodeURIComponent(raw.slice(KEY.length+1)));if(saved&&saved.version===1&&Number.isInteger(saved.at)&&saved.at*1000<=Date.now()+300000&&Date.now()-saved.at*1000<AGE)return saved;}catch(_){}return null;}
    choice=read();
    const allows=key=>!restricted&&!!choice&&choice[key]===true;
    function clearOptional(){
        if(!allows('analytics'))fetch('/api/privacy-preferences.php',{method:'POST',credentials:'same-origin',headers:{'X-GNC-Privacy':'1'}}).catch(()=>{});
        try{if(!allows('analytics')){document.cookie='gnc_vid=; Path=/; Max-Age=0; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'');for(const key of Object.keys(sessionStorage))if(key.startsWith('gnc_pv:'))sessionStorage.removeItem(key);}
            if(!allows('personalization'))for(const key of ['gnc_recommendations_v1','gnci_feedback_v1','gnci_feedback_prompt_v1'])localStorage.removeItem(key);
        }catch(_){}
    }
    function manage(){const dialog=document.getElementById('gncPrivacyDialog');if(!dialog)return;previousFocus=document.activeElement;CATEGORIES.forEach(key=>{const input=dialog.querySelector('[name="'+key+'"]');input.checked=allows(key);input.disabled=restricted;});document.getElementById('gncPrivacySignal').textContent=restricted?'Your browser privacy signal is on. Optional features stay off.':'';if(!dialog.open)dialog.showModal();}
    function close(){const dialog=document.getElementById('gncPrivacyDialog');if(dialog?.open)dialog.close();previousFocus?.focus({preventScroll:true});}
    function save(values){choice={version:1,at:Math.floor(Date.now()/1000)};CATEGORIES.forEach(key=>choice[key]=!restricted&&values[key]===true);document.cookie=KEY+'='+encodeURIComponent(JSON.stringify(choice))+'; Path=/; Max-Age='+Math.floor(AGE/1000)+'; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'');clearOptional();const banner=document.getElementById('gncPrivacyBanner');if(banner)banner.hidden=true;close();updateFrames();window.dispatchEvent(new CustomEvent('gnc:privacychange',{detail:{...choice}}));channel?.postMessage('changed');}
    function sync(){const saved=read();if(JSON.stringify(saved)===JSON.stringify(choice))return;choice=saved;clearOptional();updateFrames();window.dispatchEvent(new CustomEvent('gnc:privacychange',{detail:choice}));}
    if(channel)channel.addEventListener('message',sync);
    window.addEventListener('focus',sync);
    function updateFrames(){document.querySelectorAll('iframe[data-privacy-src]').forEach(frame=>{
        let placeholder=frame.__privacyPlaceholder;
        if(!placeholder){placeholder=document.createElement('div');placeholder.className='gnc-privacy-placeholder';const text=document.createElement('p');text.textContent=(frame.title||'Embedded service')+' is off until you allow embedded services.';const button=document.createElement('button');button.type='button';button.className='gnc-privacy-load';button.textContent='Manage privacy choices';button.addEventListener('click',manage);placeholder.append(text,button);frame.before(placeholder);frame.__privacyPlaceholder=placeholder;}
        if(allows('external')){if(frame.getAttribute('src')!==frame.dataset.privacySrc)frame.src=frame.dataset.privacySrc;frame.hidden=false;placeholder.hidden=true;}
        else{frame.removeAttribute('src');frame.hidden=true;placeholder.hidden=false;}
    });}
    window.GNCPrivacy={allows,manage,setCategory:(key,value)=>{if(!CATEGORIES.includes(key))return;const next=Object.fromEntries(CATEGORIES.map(category=>[category,allows(category)]));next[key]=value===true;save(next);}};
    function boot(){clearOptional();updateFrames();const banner=document.getElementById('gncPrivacyBanner');if(banner)banner.hidden=!!choice||restricted;
        document.addEventListener('click',event=>{const control=event.target.closest('[data-privacy-action]');if(!control)return;const action=control.dataset.privacyAction;if(action==='manage')manage();if(action==='close')close();if(action==='accept')save({analytics:true,personalization:true,external:true});if(action==='reject')save({});});
        document.getElementById('gncPrivacyForm')?.addEventListener('submit',event=>{event.preventDefault();save(Object.fromEntries(CATEGORIES.map(key=>[key,event.target.elements[key].checked])));});
        new MutationObserver(records=>{if(records.some(record=>Array.from(record.addedNodes).some(node=>node.nodeType===1&&(node.matches('iframe[data-privacy-src]')||node.querySelector('iframe[data-privacy-src]')))))updateFrames();}).observe(document.body,{childList:true,subtree:true});
        window.dispatchEvent(new Event('gnc:privacyready'));
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();
