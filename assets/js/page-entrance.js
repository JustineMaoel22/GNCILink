(function () {
    'use strict';
    const overlay = document.getElementById('gncPageEntrance');
    if (!overlay) return;
    const root = document.documentElement;
    const editorPreview = window.top !== window.self || new URLSearchParams(location.search).has('ce_preview');
    // Listing filters and pagination return directly to results without replaying the opening wipe.
    const newsResults = /\/news-and-announcement\.php$/.test(location.pathname)
        && (location.hash === '#na-results'
            || ['page', 'category', 'department', 'program', 'type'].some(key => new URLSearchParams(location.search).has(key)));
    if (newsResults) root.classList.add('na-pagination-visit');
    const achievementResults = /\/student-achievement\.php$/.test(location.pathname) && location.hash === '#more-achievements';
    const orgResults = /\/student-org(?:\.php)?$/.test(location.pathname)
        && ['category', 'p'].some(key => new URLSearchParams(location.search).has(key));
    if (editorPreview) root.classList.add('gnc-editor-preview');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (editorPreview || newsResults || achievementResults || orgResults
        || motion.matches || root.classList.contains('gnc-intro-on')) {
        overlay.remove();
        return;
    }
    const openingTitle = overlay.dataset.openingTitle;
    // Cover the page while its heading is still being parsed. Never show a
    // provisional document title and then replace it during the animation.
    root.classList.add('gnc-entrance-pending');
    function startEntrance() {
        if (!overlay.isConnected) return;
        if (motion.matches) { remove(); return; }
        const heading = document.querySelector('main h1, section h1, .hero-slideshow h1');
        overlay.querySelector('span').textContent = openingTitle
            || (heading && heading.textContent.trim()) || document.title;
        root.classList.add('gnc-shared-entrance');
        root.classList.remove('gnc-entrance-pending');
        setTimeout(remove, 2000);
    }
    function remove() {
        root.classList.remove('gnc-entrance-pending');
        overlay.remove();
        motion.removeEventListener('change', remove);
    }
    overlay.addEventListener('animationend', event => {
        if (event.target === overlay) remove();
    });
    motion.addEventListener('change', remove, { once: true });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startEntrance, { once: true });
    else startEntrance();
    // Also clean up if animation events are suppressed or the tab is backgrounded.
    setTimeout(remove, 5000);
    window.addEventListener('pageshow', event => { if (event.persisted) remove(); });
})();
