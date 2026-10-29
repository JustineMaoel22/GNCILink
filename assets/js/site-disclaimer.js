(function () {
    'use strict';
    const dialog = document.getElementById('gncSiteDisclaimer');
    if (!dialog || window.top !== window.self || /^\/admin(?:\/|$)/.test(location.pathname)) return;
    const key = 'gnc_site_notice_v1';
    try { if (sessionStorage.getItem(key) === 'dismissed') return; } catch (_) {}
    dialog.addEventListener('close', function () {
        try { sessionStorage.setItem(key, 'dismissed'); } catch (_) {}
    });
    if (typeof dialog.showModal === 'function') dialog.showModal();
})();
