(function () {
    'use strict';
    const section=document.getElementById('gncRecommendations'), engine=window.GNCRecommendations;
    if (!section || !engine || window.top!==window.self || new URLSearchParams(location.search).has('ce_preview')) return;
    const KEY='gnc_recommendations_v1', DAY=86400000;
    const $=id=>document.getElementById(id);
    const privacy=$('gncRecPrivacy'), settings=$('gncRecSettings');
    let state={consent:null,history:{},shown:{}}, items=[], current=null, selected=[], observed=false, lastTick=Date.now(), counted=false;
    const restricted=!!navigator.globalPrivacyControl || navigator.doNotTrack==='1';
    function load() {
        try {
            const saved=JSON.parse(localStorage.getItem(KEY));
            if (saved && saved.version===1 && Date.now()-(saved.updated || 0)<30*DAY) {
                state={consent:saved.consent===true ? true : false,history:saved.history || {},shown:saved.shown || {}};
                state.history=Object.fromEntries(Object.entries(state.history).filter(([,h])=>h && Number.isFinite(h.last) && Date.now()-h.last<30*DAY).sort((a,b)=>b[1].last-a[1].last).slice(0,80));
                state.shown=Object.fromEntries(Object.entries(state.shown).filter(([,t])=>Number.isFinite(t)&&Date.now()-t<7*DAY).slice(-100));
            } else localStorage.removeItem(KEY);
            if (restricted) { state={consent:false,history:{},shown:{}}; localStorage.removeItem(KEY); }
        } catch (e) { state={consent:null,history:{},shown:{}}; }
    }
    function save() {
        try { localStorage.setItem(KEY,JSON.stringify({...state,version:1,updated:Date.now()})); return true; }
        catch (e) { $('gncRecStatus').textContent='Your browser cannot save this preference. Personalization works for this page only.'; return false; }
    }
    function visit() {
        if (!state.consent || !current || counted) return;
        const now=Date.now(), old=state.history[current.id] || {visits:0,last:0,seconds:0};
        state.history[current.id]={visits:Math.min(50,old.visits+(now-old.last>30*60*1000 ? 1 : 0)),last:now,seconds:Math.min(600,old.seconds || 0)};
        state.history=Object.fromEntries(Object.entries(state.history).sort((a,b)=>b[1].last-a[1].last).slice(0,80));
        counted=true; save();
    }
    function tick() {
        const now=Date.now(), elapsed=Math.min(15,Math.max(0,(now-lastTick)/1000)); lastTick=now;
        if (state.consent && current && !document.hidden && document.hasFocus() && state.history[current.id]) {
            state.history[current.id].seconds=Math.min(600,state.history[current.id].seconds+elapsed); save();
        }
    }
    function markShown() {
        if (!observed || !state.consent) return;
        selected.forEach(i=>{state.shown[i.id]=Date.now();});
        state.shown=Object.fromEntries(Object.entries(state.shown).sort((a,b)=>a[1]-b[1]).slice(-100)); save();
    }
    function render(recordImpressions = true) {
        selected=engine.rank(items,state.consent ? state.history : {},current && current.id,state.consent ? state.shown : {});
        const cards=$('gncRecCards'); cards.replaceChildren();
        selected.forEach((item,index)=>{
            const a=document.createElement('a'); a.className='gnc-rec-card'; a.href=item.url; a.style.animationDelay=(index*40)+'ms';
            const img=document.createElement('img'); img.src=item.image; img.alt=''; img.loading='lazy'; img.width=300; img.height=160;
            img.addEventListener('error',()=>{img.onerror=null;img.src='/assets/images/logos/gnc-logo-v1.svg';},{once:true});
            const body=document.createElement('div'); body.className='gnc-rec-body';
            const category=document.createElement('span'); category.className='gnc-rec-category'; category.textContent=item.category;
            const title=document.createElement('h3'); title.textContent=item.title;
            const desc=document.createElement('p'); desc.textContent=item.description;
            body.append(category,title,desc); a.append(img,body); cards.append(a);
        });
        section.hidden=!selected.length;
        $('gncRecSummary').textContent=state.consent ? 'Suggestions based on your recent interests.' : 'Explore popular pages and recent updates.';
        $('gncRecEnable').disabled=restricted;
        $('gncRecStatus').textContent=restricted ? 'Your browser privacy signal is enabled. We use general suggestions.' : state.consent ? 'Personalization is on for this browser.' : 'Personalization is off.';
        if (recordImpressions) markShown();
    }
    settings.addEventListener('click',()=>{privacy.hidden=!privacy.hidden; settings.setAttribute('aria-expanded',String(!privacy.hidden));});
    $('gncRecEnable').addEventListener('click',()=>{ if(restricted)return; state.consent=true; counted=false; visit();save();render(); });
    $('gncRecDisable').addEventListener('click',()=>{state={consent:false,history:{},shown:{}}; counted=false;save();render();});
    window.addEventListener('storage',e=>{if(e.key===KEY){load();render(false);}});
    load();
    fetch('/api/recommendations.php',{credentials:'omit',signal:AbortSignal.timeout(10000)})
        .then(r=>{if(!r.ok)throw Error('Catalog unavailable');return r.json();})
        .then(data=>{
            items=Array.isArray(data.items) ? data.items : [];
            const path=engine.canonical(location.href);
            current=items.find(i=>(i.aliases || [i.url]).some(a=>engine.canonical(a)===path)) || null;
            visit(); render();
            if (state.consent===null) {privacy.hidden=false;settings.setAttribute('aria-expanded','true');}
            if ('IntersectionObserver' in window) {
                const observer=new IntersectionObserver(entries=>{if(entries.some(e=>e.isIntersecting)){observed=true;markShown();observer.disconnect();}},{threshold:.3});
                observer.observe(section);
            }
            setInterval(tick,10000);
            document.addEventListener('visibilitychange',()=>{lastTick=Date.now();});
            window.addEventListener('pagehide',()=>{if(state.consent)save();});
        }).catch(()=>{section.hidden=true;});
})();
