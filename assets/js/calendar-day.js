(function(){
    'use strict';
    if(window.GNCCalendarDay)return;window.GNCCalendarDay=true;
    const node=(tag,text,className)=>{const element=document.createElement(tag);if(text!==undefined)element.textContent=text;if(className)element.className=className;return element;};
    let modal,heading,content,dialog,trigger,pending;
    const dateLabel=date=>new Intl.DateTimeFormat('en-US',{month:'long',day:'numeric',year:'numeric'}).format(new Date(date.slice(0,10)+'T12:00:00'));
    function when(event){
        const start=event.start_date,end=event.end_date;
        let label=dateLabel(start);if(end&&end.slice(0,10)!==start.slice(0,10))label+=' – '+dateLabel(end);
        const clock=value=>value&&value.slice(11,19)!=='00:00:00'?new Intl.DateTimeFormat('en-US',{hour:'numeric',minute:'2-digit'}).format(new Date(value.replace(' ','T'))):'';
        const from=clock(start),to=clock(end);
        return label+' · '+(from||to?[from,to].filter(Boolean).join(' – '):'All day');
    }
    function initialize(){
        modal=node('div',undefined,'modal fade gnc-day-modal');modal.id='gncCalendarDayModal';modal.tabIndex=-1;modal.setAttribute('aria-labelledby','gncCalendarDayHeading');modal.setAttribute('aria-hidden','true');
        const box=node('div',undefined,'modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg'),panel=node('div',undefined,'modal-content');
        const header=node('div',undefined,'modal-header');heading=node('h2',undefined,'modal-title');heading.id='gncCalendarDayHeading';
        const close=node('button',undefined,'btn-close');close.type='button';close.dataset.bsDismiss='modal';close.setAttribute('aria-label','Close event details');
        header.append(heading,close);content=node('div',undefined,'modal-body');content.setAttribute('aria-live','polite');
        panel.append(header,content);box.append(panel);modal.append(box);document.body.append(modal);
        dialog=new bootstrap.Modal(modal);
        modal.addEventListener('show.bs.modal',()=>document.documentElement.classList.add('gnc-calendar-day-open'));
        modal.addEventListener('hide.bs.modal',()=>{const old=pending;pending=null;old?.abort();});
        modal.addEventListener('hidden.bs.modal',()=>{document.documentElement.classList.remove('gnc-calendar-day-open');if(trigger?.isConnected)trigger.focus({preventScroll:true});});
    }
    async function load(date,scope){
        pending?.abort();const request=new AbortController();pending=request;
        content.replaceChildren(node('p','Loading events…','gnc-day-message'));content.setAttribute('aria-busy','true');
        const timer=setTimeout(()=>request.abort(),15000);
        try{
            const params=new URLSearchParams(scope);params.set('date',date);
            const response=await fetch('/api/calendar-day.php?'+params,{credentials:'omit',signal:request.signal});
            if(!response.ok)throw Error('Unable to load events');const result=await response.json();
            if(pending!==request)return;content.replaceChildren();
            if(!result.events.length){content.append(node('p','No events scheduled for this date.','gnc-day-message'));return;}
            content.append(node('p',`${result.events.length} ${result.events.length===1?'event':'events'} scheduled`,'gnc-day-count'));
            for(const event of result.events){
                const card=node('article',undefined,'gnc-day-event');card.style.setProperty('--event-color',/^#[0-9a-f]{6}$/i.test(event.color)?event.color:'#6c757d');
                card.append(node('span',event.category,'gnc-day-category'),node('h3',event.title,'gnc-day-event-title'),node('p',when(event),'gnc-day-meta'));
                if(event.location){const venue=node('p',undefined,'gnc-day-meta');venue.append(node('strong','Location: '),document.createTextNode(event.location));card.append(venue);}
                if(event.description)card.append(node('p',event.description,'gnc-day-description'));
                content.append(card);
            }
        }catch(error){
            if(pending!==request)return;
            const message=node('p','Event details could not be loaded. Please try again.','gnc-day-message');const retry=node('button','Try again','btn btn-outline-secondary btn-sm');retry.type='button';retry.addEventListener('click',()=>load(date,scope));content.replaceChildren(message,retry);
        }finally{clearTimeout(timer);if(pending===request)content.removeAttribute('aria-busy');}
    }
    function open(cell){
        if(!window.bootstrap?.Modal)return;if(!modal)initialize();trigger=cell;
        const date=cell.dataset.calendarDate;heading.textContent=dateLabel(date);dialog.show();load(date,cell.closest('[data-calendar-scope]')?.dataset.calendarScope||'');
    }
    document.addEventListener('click',event=>{const cell=event.target.closest('[data-calendar-date]');if(cell&&!event.defaultPrevented)open(cell);});
    document.addEventListener('keydown',event=>{const cell=event.target.closest('[data-calendar-date]');if(cell&&(event.key==='Enter'||event.key===' ')){event.preventDefault();open(cell);}});
})();
