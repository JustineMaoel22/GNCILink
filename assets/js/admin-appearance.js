(() => {
    'use strict';
    const key = 'gnci-admin-font-size-' + document.currentScript.dataset.user;
    const sizes = ['standard', 'large', 'extra-large'];
    const apply = value => {
        const size = sizes.includes(value) ? value : 'standard';
        document.documentElement.dataset.adminFontSize = size;
        const select = document.getElementById('admin-font-size');
        if (select) select.value = size;
    };
    try { apply(localStorage.getItem(key)); } catch (_) { apply('standard'); }
    document.addEventListener('DOMContentLoaded', () => {
        const select = document.getElementById('admin-font-size');
        if (!select) return;
        select.value = document.documentElement.dataset.adminFontSize;
        select.addEventListener('change', () => {
            apply(select.value);
            const status = document.getElementById('admin-font-size-status');
            try {
                localStorage.setItem(key, select.value);
                status.textContent = 'Font size saved.';
            } catch (_) {
                status.textContent = 'Font size applied. Browser storage is unavailable, so it may reset when you leave this page.';
            }
        });
    }, { once: true });
    window.addEventListener('storage', event => {
        if (event.storageArea === localStorage && (event.key === key || event.key === null)) apply(event.newValue);
    });
})();
