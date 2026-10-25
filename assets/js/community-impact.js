(function(){
    'use strict';
    const section=document.getElementById('community-impact');if(!section)return;
    const status=document.createElement('p');status.className='campus-recent-empty';status.setAttribute('role','status');section.append(status);
    let pending;
    async function showPage(url,pushHistory){
        if(pending)pending.abort();const request=new AbortController();pending=request;
        section.setAttribute('aria-busy','true');status.textContent='';
        try{
            const response=await fetch(url,{signal:request.signal});if(!response.ok)throw Error('Impact request failed');
            const doc=new DOMParser().parseFromString(await response.text(),'text/html');
            if(request.signal.aborted)return;
            const content=doc.querySelector('[data-community-impact-content]');if(!content)throw Error('Impact content missing');
            section.querySelector('[data-community-impact-content]').replaceWith(content);
            if(pushHistory){history.pushState({},'',url);section.scrollIntoView({block:'start',behavior:'instant'});section.querySelector('[aria-current="page"]')?.focus({preventScroll:true});}
        }catch(error){if(error.name!=='AbortError')status.textContent='Unable to load community impact news. Please try again.';}
        finally{if(pending===request){pending=null;section.removeAttribute('aria-busy');}}
    }
    section.addEventListener('click',event=>{
        const link=event.target.closest('.campus-recent-pagination a');
        if(!link||event.defaultPrevented||event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
        // The editor owns its preview and unsaved changes; use its own navigation.
        if(section.hasAttribute('data-community-impact-preview'))return;
        event.preventDefault();if(link.getAttribute('aria-current')!=='page')showPage(link.href,true);
    });
    window.addEventListener('popstate',()=>showPage(location.href,false));
})();
