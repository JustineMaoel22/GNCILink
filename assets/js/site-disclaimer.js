(function () {
    'use strict';
    const dialog = document.getElementById('gncSiteDisclaimer');
    if (!dialog || window.top !== window.self || /^\/admin(?:\/|$)/.test(location.pathname)) return;
    const key = 'gnc_site_notice_v1';
    try { if (sessionStorage.getItem(key) === 'dismissed') return; } catch (_) {}
    dialog.addEventListener('close', function () {
        try { sessionStorage.setItem(key, 'dismissed'); } catch (_) {}
    });
    if (typeof dialog.showModal !== 'function') return;
    const observer = new MutationObserver(showWhenReady);
    function showWhenReady() {
        if (document.readyState !== 'complete'
            || document.documentElement.classList.contains('gnc-intro-on')
            || document.getElementById('gncPageEntrance')) return;
        observer.disconnect();
        window.removeEventListener('load', showWhenReady);
        if (!dialog.open) dialog.showModal();
    }
    // Both the homepage intro and the shared page entrance remove their
    // overlays when finished. Watch for that completion instead of guessing a delay.
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'], childList: true, subtree: true });
    window.addEventListener('load', showWhenReady);
    showWhenReady();
})();
