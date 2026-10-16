(function (root) {
    'use strict';
    const DAY = 86400000;
    const themes = {
        computing: /\b(computer|computing|information technology|software|programming|digital|bsit|bscs)\b/i,
        health: /\b(nursing|nurse|pharmacy|medical|health|laboratory|bsn|bsmls)\b/i,
        education: /\b(education|teaching|teacher|beed|bsed|montessori)\b/i,
        business: /\b(business|accountancy|accounting|finance|financial|entrepreneurship)\b/i,
        engineering: /\b(engineering|engineer|civil|architecture|construction)\b/i,
        hospitality: /\b(tourism|hospitality|hotel|travel)\b/i
    };
    function canonical(raw) {
        try {
            const u = new URL(raw, 'https://gnc.invalid');
            let path = u.pathname.replace(/\/index\.php$/, '/').replace(/\/$/, '') || '/';
            const keys = /\/(program|college-department)\.php$/.test(path) ? ['slug'] : /\/(news-article|announcement-article|event)\.php$/.test(path) ? ['id','slug'] : [];
            const params = new URLSearchParams();
            keys.forEach(k => { if (u.searchParams.has(k)) params.set(k,u.searchParams.get(k)); });
            return path + (params.size ? '?' + params : '');
        } catch (e) { return ''; }
    }
    function features(item) {
        const tags = new Set(item.tags || []);
        tags.add('type:'+item.category);
        const text = item.title+' '+item.description;
        Object.keys(themes).forEach(k => { if (themes[k].test(text)) tags.add('topic:'+k); });
        const stop = new Set(['the','and','for','with','from','school','college','national','guagua','bachelor','science','arts','program','students','education']);
        (text.toLowerCase().match(/[a-z]{4,}/g) || []).forEach(w => { if (!stop.has(w)) tags.add('word:'+w); });
        return [...tags];
    }
    function similarity(a,b) {
        const set = new Set(b);
        let score=0;
        a.forEach(t => { if (set.has(t)) score += t.startsWith('program:') ? 5 : /^(dept|topic):/.test(t) ? 3 : t.startsWith('word:') ? .15 : 1; });
        return score;
    }
    function rank(items, history, currentId, impressions, now = Date.now(), limit = 4) {
        const byId = new Map(items.map(i => [i.id,i]));
        const vectors = new Map(items.map(i => [i.id,features(i)]));
        const interests=[];
        Object.entries(history || {}).slice(0,80).forEach(([id,h]) => {
            if (!byId.has(id) || now-h.last>30*DAY || !Number.isFinite(h.last)) return;
            const weight=(1+Math.log1p(Math.min(50,Math.max(0,h.visits || 0)))+.5*Math.min(1,(h.seconds || 0)/120)) * Math.exp(-Math.max(0,now-h.last)/(7*DAY));
            interests.push({id,weight});
        });
        const total=interests.reduce((s,h)=>s+h.weight,0) || 1;
        const scored=items.filter(i=>i.id!==currentId).map(item=>{
            let score=.35*Math.log1p(Math.max(0,item.popular || 0)) + 2*Math.exp(-Math.max(0,now-(item.date || 0))/(14*DAY));
            interests.forEach(h=>{ score+=8*h.weight/total*similarity(vectors.get(item.id),vectors.get(h.id)); });
            if (history[item.id]) score*=.65;
            const seen=impressions[item.id];
            if (seen && now-seen<7*DAY) score*=.45+.55*Math.min(1,(now-seen)/(7*DAY));
            return {item,score};
        });
        const chosen=[], categories={};
        while (chosen.length<limit && scored.length) {
            scored.sort((a,b)=>(b.score/(1+(categories[b.item.category] || 0)*.65))-(a.score/(1+(categories[a.item.category] || 0)*.65)) || a.item.id.localeCompare(b.item.id));
            const next=scored.shift().item;
            if (chosen.some(i=>canonical(i.url)===canonical(next.url))) continue;
            chosen.push(next); categories[next.category]=(categories[next.category] || 0)+1;
        }
        return chosen;
    }
    const api={canonical,features,similarity,rank};
    if (typeof module !== 'undefined' && module.exports) module.exports=api;
    else root.GNCRecommendations=api;
})(typeof window === 'undefined' ? globalThis : window);
