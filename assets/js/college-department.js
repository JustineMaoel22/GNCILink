(function () {
    var els = document.querySelectorAll('[data-reveal]');
    if (!els.length) return;
    var showAll = function () { els.forEach(function (el) { el.classList.add('is-visible'); }); };
    if (!('IntersectionObserver' in window) ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches) { showAll(); return; }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    els.forEach(function (el) { io.observe(el); });
})();