/* Transport bounds only: never replay a mutation or cache private responses. */
(function () {
    'use strict';
    if (window.GNCRequestGuard || !window.fetch) return;
    window.GNCRequestGuard=true;
    const nativeFetch=window.fetch.bind(window), cooldowns=new Map();
    let notice, noticeTimer;
    function inform(message) {
        if (!document.body) return;
        if (!notice) {
            notice=document.createElement('div');notice.setAttribute('role','status');
            notice.setAttribute('aria-live','polite');notice.className='alert alert-warning';
            Object.assign(notice.style,{position:'fixed',bottom:'16px',left:'16px',right:'16px',zIndex:'11000'});
            document.body.appendChild(notice);
        }
        notice.textContent=message;notice.hidden=false;clearTimeout(noticeTimer);
        noticeTimer=setTimeout(()=>{notice.hidden=true;},10000);
    }
    window.fetch=async function(input,options) {
        const url=new URL(input instanceof Request?input.url:String(input),location.href);
        if (url.origin!==location.origin || !/^https?:$/.test(url.protocol)) return nativeFetch(input,options);
        const init=Object.assign({},options), method=String(init.method||(input instanceof Request?input.method:'GET')).toUpperCase();
        const key=url.pathname;
        const until=cooldowns.get(key)||0;
        if (until>Date.now()) {
            const seconds=Math.ceil((until-Date.now())/1000), message='Please wait '+seconds+' seconds before trying again.';
            inform(message);
            return new Response(JSON.stringify({success:false,ok:false,error:message,message,retry_after:seconds}),{status:429,headers:{'Content-Type':'application/json','Retry-After':String(seconds)}});
        }
        cooldowns.delete(key);
        const caller=init.signal||(input instanceof Request?input.signal:null);
        // Longer uploads/publishing can include server-side media processing and approval replay.
        const milliseconds=method==='GET'||method==='HEAD'?30000:120000;
        const timeout=typeof AbortSignal.timeout==='function'?AbortSignal.timeout(milliseconds):(()=>{
            const controller=new AbortController();setTimeout(()=>controller.abort(),milliseconds);return controller.signal;
        })();
        if (caller && typeof AbortSignal.any==='function') init.signal=AbortSignal.any([caller,timeout]);
        else if (caller) {
            const controller=new AbortController(), abort=()=>controller.abort(caller.aborted?caller.reason:timeout.reason);
            if(caller.aborted)abort();else caller.addEventListener('abort',abort,{once:true});
            timeout.addEventListener('abort',abort,{once:true});init.signal=controller.signal;
        } else init.signal=timeout;
        try {
            const response=await nativeFetch(input,init);
            if(response.status===429 || (response.status===503 && response.headers.has('Retry-After'))) {
                const value=response.headers.get('Retry-After'), numeric=Number(value);
                const seconds=Math.min(86400,Math.max(1,Number.isFinite(numeric)?numeric:(Date.parse(value)-Date.now())/1000||60));
                if(cooldowns.size>=100)cooldowns.delete(cooldowns.keys().next().value);
                cooldowns.set(key,Date.now()+seconds*1000);
                inform('This service is busy. Please wait '+Math.ceil(seconds)+' seconds before trying again.');
            }
            return response;
        } catch(error) {
            if(init.signal.aborted && !caller?.aborted) inform('The request took too long. Check whether your change was saved before trying again.');
            throw error;
        }
    };
})();
