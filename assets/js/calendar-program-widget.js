(function () {
    'use strict';
    if (window.top!==window.self || new URLSearchParams(location.search).has('ce_preview')) return;
    function init() {
        const section=document.getElementById('events'), widget=document.getElementById('calendarProgramWidget');
        const toggle=document.getElementById('calendarProgramToggle'), panel=document.getElementById('calendarProgramPanel');
        if (!section || !widget || !toggle || !panel) return;
        widget.hidden=false;
        function close(restoreFocus=false) {
            panel.hidden=true;toggle.setAttribute('aria-expanded','false');
            if (restoreFocus && !widget.hidden) toggle.focus({preventScroll:true});
        }
        toggle.addEventListener('click',() => {
            const open=panel.hidden;panel.hidden=!open;toggle.setAttribute('aria-expanded',String(open));
            if(open){
                const bounds=toggle.getBoundingClientRect(),nav=document.getElementById('mainNavbar');
                const below=innerHeight-bounds.bottom-24,above=bounds.top-Math.max(16,nav?nav.getBoundingClientRect().bottom:16)-12;
                const up=below<200&&above>below;panel.classList.toggle('opens-up',up);
                panel.style.maxHeight=Math.max(120,Math.min(480,up?above:below))+'px';
                const selected=panel.querySelector('.gnc-calendar-department-active,.gnc-programs-list-item-active,.gnc-calendar-filter-all-active'),list=panel.querySelector('.gnc-programs-list');
                if(selected&&list)list.scrollTop=selected.offsetTop-list.offsetTop;
            }
        });
        widget.addEventListener('click',event => {if(event.target.closest('.gnc-program-widget-close'))close(true);});
        document.addEventListener('click',event => {if(!widget.contains(event.target))close();});
        document.addEventListener('keydown',event => {if(event.key==='Escape'&&!panel.hidden){event.preventDefault();close(true);}});
        document.addEventListener('calendar:updated',() => close(widget.contains(document.activeElement)));
        function visibility(visible) {if(!visible)close();}
        if('IntersectionObserver' in window) {
            new IntersectionObserver(entries => visibility(entries[0].isIntersecting),{rootMargin:'-80px 0px -80px 0px'}).observe(section);
        } else {
            const update=()=>{const bounds=section.getBoundingClientRect();visibility(bounds.top<innerHeight-80&&bounds.bottom>80);};
            window.addEventListener('scroll',update,{passive:true});window.addEventListener('resize',update);update();
        }
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
