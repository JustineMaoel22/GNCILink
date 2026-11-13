(function () {
    'use strict';
    const source=document.getElementById('scholarshipCatalogData');
    if(!source)return;
    const initial=JSON.parse(source.textContent),$=id=>document.getElementById(id);
    let entries=initial.entries,categories=initial.categories,selected=initial.selected,category=initial.category;
    const preview=!!document.getElementById('veScholarshipData');
    function show(id,group,historyUpdate=false,focus=false) {
        let entry=entries.find(item=>item.id===id);
        category=entry?entry.category:(categories.some(item=>item.id===group)?group:categories[0].id);
        if(!entry)entry=entries.find(item=>item.category===category);
        selected=entry?entry.id:null;
        const label=categories.find(item=>item.id===category).label;
        $('scholarshipTitle').textContent=entry?entry.title:label;
        $('scholarshipCategory').textContent=(entry&&entry.pdf?'PDF Document · ':'')+label;
        $('scholarshipDetails').setAttribute('data-scholarship-entry',selected||'');
        const pdf=entry&&entry.pdf||'';
        const upload=document.querySelector('[data-ve-scholarship-upload]');
        if(upload){upload.disabled=!entry;$('scholarshipUploadLabel').textContent=pdf?'Replace PDF':'Upload PDF';}
        ['scholarshipPdf','scholarshipPdfNote','scholarshipDownload'].forEach(id=>{$(id).hidden=!pdf;});
        if(pdf){
            const frame=$('scholarshipPdfFrame');
            if(frame.getAttribute('src')!==pdf+'#view=FitH'){
                const next=frame.cloneNode(false);next.src=pdf+'#view=FitH';next.title=entry.title+' (PDF)';frame.replaceWith(next);
            }
            $('scholarshipDownload').href=pdf;$('scholarshipOpenPdf').href=pdf;
        }else{
            $('scholarshipPdfFrame').removeAttribute('src');$('scholarshipDownload').removeAttribute('href');$('scholarshipOpenPdf').removeAttribute('href');
        }
        $('scholarshipEmpty').hidden=!!pdf;
        $('scholarshipEmptyTitle').textContent=entry?'Scholarship document coming soon':'No scholarships available in this category yet.';
        document.querySelectorAll('[data-sch-category]').forEach(group=>{
            group.querySelector('summary').classList.toggle('is-active',group.dataset.schCategory===category);
            if(group.dataset.schCategory===category)group.open=true;
        });
        document.querySelectorAll('[data-sch-select]').forEach(link=>{
            if(link.dataset.schSelect===selected)link.setAttribute('aria-current','true');else link.removeAttribute('aria-current');
        });
        if(historyUpdate){const url=new URL(location.href);url.searchParams.set('category',category);if(selected)url.searchParams.set('scholarship',selected);else url.searchParams.delete('scholarship');history.pushState(null,'',url);}
        if(focus)$('scholarshipTitle').focus({preventScroll:true});
        if(focus&&!preview&&matchMedia('(max-width:767px)').matches){
            document.querySelector('.sch-navigation').open=false;
            $('scholarshipDetails').scrollIntoView({block:'start',behavior:matchMedia('(prefers-reduced-motion:reduce)').matches?'auto':'smooth'});
        }
    }
    function filter() {
        const term=$('scholarshipSearch').value.trim().toLocaleLowerCase();let matches=0;
        if(term)document.querySelector('.sch-navigation').open=true;
        document.querySelectorAll('[data-sch-category]').forEach(group=>{
            const record=categories.find(item=>item.id===group.dataset.schCategory);
            const categoryMatch=record.label.toLocaleLowerCase().includes(term);let count=0;
            group.querySelectorAll('[data-sch-select]').forEach(link=>{
                const item=entries.find(entry=>entry.id===link.dataset.schSelect);
                const text=item.title.toLocaleLowerCase();
                link.hidden=!!term&&!categoryMatch&&!text.includes(term);if(!link.hidden)count++;
            });
            group.hidden=!!term&&!categoryMatch&&!count;
            if(!group.hidden){matches++;if(term)group.open=true;}
        });
        $('scholarshipSearchStatus').hidden=matches>0;
    }
    function render(nextEntries,nextCategories) {
        entries=nextEntries;categories=nextCategories;
        const nav=$('scholarshipNavigation');nav.replaceChildren();
        categories.forEach(record=>{
            const group=document.createElement('details');group.className='sch-category-group';group.dataset.schCategory=record.id;
            const summary=document.createElement('summary');summary.className='fee-category';
            const icon=document.createElement('i');icon.className='bi bi-'+(record.icon||'mortarboard');icon.setAttribute('aria-hidden','true');
            const label=document.createElement('span');label.textContent=record.label;
            const arrow=document.createElement('i');arrow.className='bi bi-chevron-down';arrow.setAttribute('aria-hidden','true');summary.append(icon,label,arrow);
            const list=document.createElement('div');list.className='fee-document-list';
            entries.filter(entry=>entry.category===record.id).forEach(entry=>{
                const link=document.createElement('a');link.className='fee-document-choice';link.dataset.schSelect=entry.id;link.textContent=entry.title;
                link.href='?category='+encodeURIComponent(record.id)+'&scholarship='+encodeURIComponent(entry.id);list.append(link);
            });
            group.append(summary,list);nav.append(group);
        });
        show(selected,category);filter();
    }
    $('scholarshipNavigation').addEventListener('click',event=>{
        const link=event.target.closest('[data-sch-select]');
        if(link){if(event.ctrlKey||event.metaKey||event.shiftKey||event.altKey||event.button!==0)return;event.preventDefault();show(link.dataset.schSelect,null,!preview,true);return;}
        const summary=event.target.closest('.sch-category-group > summary');
        if(summary){event.preventDefault();const group=summary.parentElement;const opened=!group.open;group.open=opened;if(opened)show(null,group.dataset.schCategory,!preview);}
    });
    $('scholarshipSearch').addEventListener('input',filter);
    const mobileNavigation=matchMedia('(max-width:767px)');
    mobileNavigation.addEventListener('change',()=>{if(!mobileNavigation.matches)document.querySelector('.sch-navigation').open=true;});
    window.addEventListener('popstate',()=>{const params=new URLSearchParams(location.search);show(params.get('scholarship'),params.get('category'));});
    window.GNCScholarships={render,show:(id,group)=>show(id,group),selection:()=>({id:selected,category})};
    const hashMap={'#collegeModal':'college','#basicEdModal':'basic','#athletic':'athletic','#gov-pane':'government'};
    if(hashMap[location.hash]){const kind=hashMap[location.hash];const entry=entries.find(item=>item.legacy===kind);show(entry&&entry.id,entry?entry.category:kind);}else show(selected,category);
})();
