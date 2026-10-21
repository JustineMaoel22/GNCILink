/* Shared visual feedback for every Content Editor canvas. Does not save content. */
(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.GNCEditorFeedback = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';
    const TEXT = '[data-ve-field],[data-ve-section],[data-ve-admission-item-text]';
    const ICON = '[data-ve-icon],[data-ve-org-icon]';
    const MEDIA = '[data-ve-img],[data-ve-bg],[data-ve-basic-image],[data-ve-program-image],[data-ve-basic-logo-id],[data-ve-org-media],[data-ve-org-page-media],[data-ve-achievement-image],[data-image-framed],[data-ve-sport-image],[data-ve-team-image],[data-ve-facility-image]';
    const CARD = '[data-ve-person],[data-ve-office],[data-ve-slide],[data-ve-program-card],[data-ve-org-card],[data-ve-achievement-id],[data-ve-item],[data-ve-pick],[data-ve-post],[data-ve-department-card],[data-ve-sport],[data-ve-team],[data-ve-facility]';
    const LAYOUT = '[data-ve-grid],[data-ve-program-grid],[data-ve-org-gallery],[data-ve-org-categories],[data-ve-department-grid],[data-ve-sports-grid],[data-ve-teams-grid],[data-ve-facilities-grid]';
    const EXTRA = '[data-ve-payment-link],[data-ve-linkkey],[data-ve-fee-pdf],[data-ve-fee-manage],[data-ve-sports-speed-controls]';
    const TARGETS = [TEXT,ICON,MEDIA,CARD,LAYOUT,EXTRA].join(',');
    const CONTROLS = '.ve-drag-handle,.ve-add-tile,[data-ve-admission-item-tools],.ve-admission-category-tools,[data-ve-admission-add-item],[data-ve-admission-add-category],[data-ve-admission-undo-banner]';
    const KEYS = ['data-ve-person','data-ve-office','data-ve-slide','data-ve-program-card','data-ve-org-card','data-ve-achievement-id','data-ve-item','data-ve-key','data-ve-id','data-ve-org-gallery','data-ve-department-card','data-ve-sport','data-ve-team','data-ve-facility'];
    const classes = value => String(value || '').split(/\s+/).filter(name => name && !/^(ve-|is-)/.test(name)).sort().join(' ');
    function identity(el) {
        for (const key of KEYS) if (el.hasAttribute(key)) return key + ':' + el.getAttribute(key);
        return el.tagName;
    }
    function mediaValue(el) {
        const images = [el,...el.querySelectorAll('img,video,source,[data-bg]')].filter(node => node.matches('img,video,source,[data-bg]') || node === el);
        return images.map(node => {
            const style = node.style || {}, src = node.getAttribute('src') || '';
            return [node.tagName,src,node.getAttribute('srcset'),node.getAttribute('poster'),node.getAttribute('data-bg') || style.backgroundImage || '',style.objectPosition || '',node.hasAttribute('data-image-framed') ? style.transform || '' : '',node.matches(MEDIA) ? style.opacity || '' : ''];
        });
    }
    function signature(el, serializeText) {
        if (el.matches(TEXT)) return JSON.stringify([serializeText(el),el.getAttribute('href')]);
        if (el.matches('[data-ve-payment-link],[data-ve-linkkey]')) return el.getAttribute('href') || '';
        if (el.matches('[data-ve-fee-pdf],[data-ve-fee-manage]')) {
            const api = el.ownerDocument.defaultView.GNCTuitionFees;
            if (api) return JSON.stringify(Object.entries(api.categories).map(([key,category]) => [key,category.documents.map(doc => [doc.id || doc._key,doc.title,doc.url])]));
        }
        if (el.matches(ICON)) return JSON.stringify(Array.from(el.children).map(node => [node.tagName,classes(node.getAttribute('class')),node.textContent,node.getAttribute('src')]));
        return JSON.stringify([serializeText(el),mediaValue(el),Array.from(el.children).filter(node => node.matches(CARD+', [data-ve-org-gallery]')).map(identity)]);
    }
    function create(options) {
        const doc = options.document, ui = options.uiDocument, frame = options.frame;
        const records = new Map(), marked = new Set();
        let disposed = false, scheduled = null;
        const view = ui.defaultView, state = options.state;
        const hint = ui.createElement('div');
        hint.className = 've-edit-feedback-hint'; hint.setAttribute('role','tooltip');hint.textContent = 'Click to edit';hint.hidden = true;
        ui.body.appendChild(hint);
        const style = doc.createElement('style');
        style.setAttribute('data-ve-feedback-style','');
        style.textContent = 'html:not(.ve-previewing) .ve-feedback-dirty{outline:2px solid #d3a63a!important;outline-offset:4px!important;box-shadow:0 0 0 4px rgba(211,166,58,.12)}'
            +'html:not(.ve-previewing) img.ve-feedback-dirty,html:not(.ve-previewing) [data-ve-slide].ve-feedback-dirty{outline-offset:-3px!important}'
            +'html:not(.ve-previewing) .ve-feedback-dirty.ve-hover{outline-style:dashed!important}';
        doc.head.appendChild(style);
        function setMarked(el, on, draft) {
            if (el.classList.contains('ve-feedback-dirty') !== on) el.classList.toggle('ve-feedback-dirty',on);
            if (on) {
                const value = draft ? 'draft' : 'unsaved';
                if (el.getAttribute('data-ve-feedback') !== value) el.setAttribute('data-ve-feedback',value);
                marked.add(el);
            } else { if (el.hasAttribute('data-ve-feedback')) el.removeAttribute('data-ve-feedback');marked.delete(el); }
        }
        function sectionOf(el) { const node = el.closest('[data-ve-section]');return node && node.__ve && node.__ve.sec; }
        function refresh(initial) {
            if (disposed) return;
            const status = state(), pending = status.pending > 0;
            const nodes = Array.from(doc.querySelectorAll(TARGETS)).filter(el => !el.closest('template,.ve-drag-preview,.is-duplicate,.is-copy') && !el.closest(CONTROLS));
            const changes = [];
            const forced = new Set(options.forcedTargets ? options.forcedTargets() : []);
            for (const el of nodes) {
                const value = signature(el,options.serializeText), section = sectionOf(el);
                const draft = !!(section && (section.has_draft || section.review === 'pending'));
                let record = records.get(el);
                if (!record) { record = {base:value,added:!initial && pending && !status.busy};records.set(el,record); }
                if ((!pending && !status.busy) || (el.matches(TEXT) && section && !section.dirty && !draft && !status.busy)) { record.base=value;record.added=false; }
                const changed = (pending || status.busy) && (record.added || record.base!==value || forced.has(el));
                if (changed || (draft && el === section.el)) changes.push({el,draft:!changed && draft});
            }
            for (const el of records.keys()) if (!el.isConnected || el.ownerDocument !== doc) { records.delete(el);marked.delete(el); }
            const leafChanges = changes.filter(change => !changes.some(other => other!==change && change.el.contains(other.el)));
            const wanted = new Set(leafChanges.map(change => change.el));
            for (const el of Array.from(marked)) if (!wanted.has(el)) setMarked(el,false,false);
            leafChanges.forEach(change => setMarked(change.el,true,change.draft));
            if (status.preview || status.busy) hide();
            return leafChanges.length;
        }
        function schedule() {
            if (scheduled !== null || disposed) return;
            scheduled = view.requestAnimationFrame(() => { scheduled=null;refresh(false); });
        }
        function hide() { hint.hidden=true; }
        function show(event) {
            const status=state(), target=event.target;
            if (disposed || status.preview || status.busy || !target.closest || target.closest(CONTROLS)) return hide();
            const el = target.closest(TARGETS);
            if (!el || el.closest('.ve-locked') || (el.matches(TEXT) && el.getAttribute('contenteditable')==='false')) return hide();
            const button = target.closest('button,input,textarea,select');
            if (button && !button.matches(TEXT+','+ICON+','+MEDIA+','+EXTRA)) return hide();
            if (el.matches('[data-ve-field],[data-ve-section]') && !el.__ve) return hide();
            const r=el.getBoundingClientRect(),fr=frame.getBoundingClientRect();
            if (!r.width || !r.height || r.bottom<0 || r.top>doc.defaultView.innerHeight) return hide();
            hint.hidden=false;
            const sx=fr.width/(doc.defaultView.innerWidth || fr.width),sy=fr.height/(doc.defaultView.innerHeight || fr.height);
            hint.style.left=Math.max(8,Math.min(fr.left+r.left*sx,view.innerWidth-hint.offsetWidth-8))+'px';
            let top=fr.top+r.top*sy-hint.offsetHeight-7;
            if (top<fr.top+4) top=fr.top+r.bottom*sy+7;
            hint.style.top=Math.max(fr.top+4,Math.min(top,view.innerHeight-hint.offsetHeight-8))+'px';
        }
        const listeners=[['mouseover',show],['mouseleave',hide],['pointerdown',hide],['keydown',hide],['scroll',hide]];
        listeners.forEach(([name,fn])=>doc.addEventListener(name,fn,true));
        view.addEventListener('resize',hide);
        const Observer=doc.defaultView.MutationObserver;
        const observer=Observer ? new Observer(mutations=>{
            if (mutations.some(m=>m.type!=='attributes' || m.attributeName!=='class' || classes(m.oldValue)!==classes(m.target.getAttribute('class')))) schedule();
        }) : null;
        refresh(true);
        if (observer) observer.observe(doc.body,{subtree:true,childList:true,characterData:true,attributes:true,attributeOldValue:true,attributeFilter:['src','srcset','poster','href','data-bg','style','class']});
        return {
            refresh:()=>refresh(false),hide,
            dispose() {
                disposed=true;if(observer)observer.disconnect();
                if(scheduled!==null)view.cancelAnimationFrame(scheduled);
                listeners.forEach(([name,fn])=>doc.removeEventListener(name,fn,true));view.removeEventListener('resize',hide);
                for(const el of Array.from(marked))setMarked(el,false,false);
                records.clear();hint.remove();style.remove();
            }
        };
    }
    return {create,signature,targets:TARGETS};
});
