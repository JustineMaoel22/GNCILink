(function () {
    'use strict';
    const overlay = document.getElementById('gncPageEntrance');
    if (!overlay) return;
    const root = document.documentElement;
    const editorPreview = window.top !== window.self || new URLSearchParams(location.search).has('ce_preview');
    if (editorPreview) root.classList.add('gnc-editor-preview');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (editorPreview
        || motion.matches || root.classList.contains('gnc-intro-on')) {
        overlay.remove();
        return;
    }
    const openingTitle = overlay.dataset.openingTitle;
    overlay.querySelector('span').textContent = openingTitle || document.title;
    root.classList.add('gnc-shared-entrance');
    function updateTitle() {
        if (openingTitle) return;
        const heading = document.querySelector('main h1, section h1, .hero-slideshow h1');
        if (heading && heading.textContent.trim()) overlay.querySelector('span').textContent = heading.textContent.trim();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', updateTitle, { once: true });
    else updateTitle();
    const remove = () => overlay.remove();
    overlay.addEventListener('animationend', event => {
        if (event.target === overlay) remove();
    });
    motion.addEventListener('change', remove, { once: true });
    // Also clean up if animation events are suppressed or the tab is backgrounded.
    setTimeout(() => { remove(); motion.removeEventListener('change', remove); }, 2000);
    window.addEventListener('pageshow', event => { if (event.persisted) remove(); });
})();
