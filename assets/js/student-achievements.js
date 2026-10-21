(function () {
    'use strict';
    document.querySelectorAll('#achievementYear, #achievementCategory').forEach(function (filter) {
        filter.addEventListener('change', function () { filter.form.requestSubmit(); });
    });
    function mountAchieverSlideshow(dialog) {
        if (dialog.__achieverSlideshow) return;
        dialog.__achieverSlideshow = true;
        const slides=Array.from(dialog.querySelectorAll('[data-achiever-slide]'));
        const dots=Array.from(dialog.querySelectorAll('[data-achiever-index]'));
        const status=dialog.querySelector('[data-achiever-status]');
        let index=0;
        function show(next,announce) {
            if (!slides.length) return;
            index=(next+slides.length)%slides.length;
            slides.forEach((slide,i)=>{slide.hidden=i!==index;});
            dots.forEach((dot,i)=>dot.setAttribute('aria-pressed',String(i===index)));
            if(status)status.textContent=announce?'Achiever photo '+(index+1)+' of '+slides.length:'';
        }
        dialog.addEventListener('click',event=>{
            const step=event.target.closest('[data-achiever-step]');
            const dot=event.target.closest('[data-achiever-index]');
            if(step)show(index+Number(step.dataset.achieverStep),true);
            if(dot)show(Number(dot.dataset.achieverIndex),true);
        });
        dialog.addEventListener('keydown',event=>{
            if(event.key!=='ArrowLeft'&&event.key!=='ArrowRight')return;
            if(event.target.closest('input,textarea,[contenteditable="true"]'))return;
            event.preventDefault();event.stopPropagation();show(index+(event.key==='ArrowLeft'?-1:1),true);
        });
        dialog.addEventListener('close',()=>show(0,false));
        show(0,false);
    }
    window.GNCAchievementModal={mount:mountAchieverSlideshow};
    function openAchievement(dialog) {
        if (!dialog || dialog.open) return;
        mountAchieverSlideshow(dialog);
        dialog.showModal();
    }
    document.addEventListener('click', function (event) {
        const close = event.target.closest('[data-achievement-close]');
        if (close) { event.preventDefault(); close.closest('dialog').close(); return; }
        const link = event.target.closest('[data-achievement-open]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        // The visual editor handles canvas selections; its Preview mode opens the same dialog.
        if (document.getElementById('veAchievementData') && !document.documentElement.classList.contains('ve-previewing')) return;
        const dialog = document.getElementById('achievement-dialog-' + link.dataset.achievementOpen);
        if (dialog) { event.preventDefault(); openAchievement(dialog); }
    });
    document.querySelectorAll('.achievement-modal').forEach(function (dialog) {
        mountAchieverSlideshow(dialog);
        dialog.addEventListener('click', function (event) {
            if (event.target !== dialog) return;
            const bounds=dialog.getBoundingClientRect();
            if (event.clientX<bounds.left || event.clientX>bounds.right || event.clientY<bounds.top || event.clientY>bounds.bottom) dialog.close();
        });
        if (dialog.hasAttribute('open')) { dialog.removeAttribute('open'); openAchievement(dialog); }
    });
    const carousel = document.querySelector('.achievement-carousel');
    if (!carousel) return;
    const slides = Array.from(carousel.querySelectorAll('.achievement-slide'));
    const dots = Array.from(carousel.querySelectorAll('[data-achievement-index]'));
    const status = carousel.querySelector('[data-achievement-status]');
    let current = 0;
    function show(index) {
        current = (index + slides.length) % slides.length;
        slides.forEach((slide, i) => { slide.hidden = i !== current; });
        dots.forEach((dot, i) => dot.setAttribute('aria-pressed', String(i === current)));
        if (status) status.textContent = 'Achievement ' + (current + 1) + ' of ' + slides.length + ': ' + slides[current].querySelector('h4').textContent;
    }
    carousel.addEventListener('click', function (event) {
        const step = event.target.closest('[data-achievement-step]');
        const dot = event.target.closest('[data-achievement-index]');
        if (step) show(current + Number(step.dataset.achievementStep));
        if (dot) show(Number(dot.dataset.achievementIndex));
    });
    carousel.addEventListener('keydown', function (event) {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        event.preventDefault();
        show(current + (event.key === 'ArrowLeft' ? -1 : 1));
    });
}());
