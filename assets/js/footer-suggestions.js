(function(){
    'use strict';
    const section=document.getElementById('gncFooterSuggestions'),links=document.getElementById('gncFooterSuggestionLinks'),engine=window.GNCRecommendations;
    if(!section||!links||!engine||window.top!==window.self||new URLSearchParams(location.search).has('ce_preview'))return;
    const key='gnc_recommendations_v1',rotationKey='gnc_footer_suggestions_v1',day=86400000;
    let items=[],current=null;
    const allowed=()=>!!window.GNCPrivacy?.allows('personalization')&&!navigator.globalPrivacyControl&&navigator.doNotTrack!=='1';
    function profile(){
        if(!allowed())return {history:{},shown:{}};
        try{
            const saved=JSON.parse(localStorage.getItem(key));
            if(!saved||saved.version!==1||Date.now()-(saved.updated||0)>=30*day)return {history:{},shown:{}};
            return {history:Object.fromEntries(Object.entries(saved.history||{}).filter(([,h])=>h&&Number.isFinite(h.last)&&Date.now()-h.last<30*day).sort((a,b)=>b[1].last-a[1].last).slice(0,80)),shown:saved.shown||{}};
        }catch(_){return {history:{},shown:{}};}
    }
    function visit(){
        if(!allowed()||!current)return;
        const saved=profile(),now=Date.now(),old=saved.history[current.id]||{visits:0,last:0,seconds:0};
        saved.history[current.id]={visits:Math.min(50,Math.max(0,Number(old.visits)||0)+(now-old.last>30*60*1000?1:0)),last:now,seconds:Math.min(600,Math.max(0,Number(old.seconds)||0))};
        try{localStorage.setItem(key,JSON.stringify({...saved,consent:true,version:1,updated:now}));}catch(_){}
    }
    function render(){
        const saved=profile();
        const route=engine.canonical(location.href);
        // Context affects recommendations without recording a visit when consent is off.
        const interests=Object.assign({},saved.history);
        if(current)interests[current.id]={...interests[current.id],visits:Math.max(3,Number(interests[current.id]?.visits)||0),last:Date.now()};
        const ranked=engine.rank(items,interests,current?.id,saved.shown,Date.now(),Math.min(items.length,100));
        let previous=null;
        try{if(allowed())previous=JSON.parse(sessionStorage.getItem(rotationKey));else sessionStorage.removeItem(rotationKey);}catch(_){}
        if(previous&&(!Array.isArray(previous.ids)||typeof previous.route!=='string'))previous=null;
        const chosen=ranked.slice(0,4);
        // Reserve one discovery card for page-specific variety while retaining the strongest matches.
        if(ranked.length>4){
            let hash=2166136261;for(const char of route)hash=Math.imul(hash^char.charCodeAt(0),16777619);
            const alternatives=ranked.slice(3,Math.min(ranked.length,12));
            const fresh=previous?.route!==route?alternatives.filter(item=>!previous?.ids?.includes(item.id)):[];
            const pool=fresh.length?fresh:alternatives;
            const retained=previous?.route===route&&ranked.find(item=>item.id===previous.ids?.[3]&&!chosen.slice(0,3).some(top=>top.id===item.id));
            chosen[3]=retained||pool[(hash>>>0)%pool.length];
        }
        links.replaceChildren();
        chosen.forEach(item=>{
            const link=document.createElement('a');
            try{const url=new URL(item.url,location.origin);if(url.origin!==location.origin||!/^https?:$/.test(url.protocol))return;link.href=url.pathname+url.search+url.hash;}catch(_){return;}
            link.className='gnc-suggestion-link';
            const category=document.createElement('span');category.className='gnc-suggestion-category';category.textContent=item.category||'Explore GNC';
            const title=document.createElement('span');title.className='gnc-suggestion-title';title.textContent=item.title;
            const arrow=document.createElement('i');arrow.className='bi bi-arrow-up-right';arrow.setAttribute('aria-hidden','true');
            link.append(category,title,arrow);links.append(link);
        });
        links.dataset.count=String(links.children.length);section.hidden=!links.children.length;
        if(allowed())try{sessionStorage.setItem(rotationKey,JSON.stringify({route,ids:chosen.map(item=>item.id)}));}catch(_){}
    }
    window.addEventListener('gnc:privacychange',()=>{visit();render();});
    window.addEventListener('gnc:recommendation-profile-change',render);
    window.addEventListener('storage',event=>{if(event.key===key)render();});
    fetch('/api/recommendations.php',{credentials:'omit',signal:AbortSignal.timeout(10000)})
        .then(response=>{if(!response.ok)throw Error('Suggestions unavailable');return response.json();})
        .then(data=>{
            items=Array.isArray(data.items)?data.items:[];const path=engine.canonical(location.href);
            current=items.find(item=>(item.aliases||[item.url]).some(alias=>engine.canonical(alias)===path));
            visit();render();
        }).catch(()=>{section.hidden=true;});
})();
