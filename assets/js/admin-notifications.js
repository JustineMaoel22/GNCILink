(() => {
    'use strict';
    const root = document.querySelector('.admin-notifications');
    if (!root) return;
    const toggle = root.querySelector('#notifications-toggle');
    const panel = root.querySelector('#notifications-panel');
    const dot = root.querySelector('.topbar-notif-dot');
    const markAll = root.querySelector('#notifications-read');
    const items = [...root.querySelectorAll('[data-notification-key]')];
    const storageKey = 'gnci-notifications-read-' + root.dataset.user;
    let read = new Set();
    try {
        const saved = JSON.parse(localStorage.getItem(storageKey) || '[]');
        if (Array.isArray(saved)) read = new Set(saved.filter(key => typeof key === 'string'));
    } catch (_) { /* The panel still works when browser storage is unavailable. */ }
    function update() {
        let unread = 0;
        items.forEach(item => {
            const isUnread = !read.has(item.dataset.notificationKey);
            item.classList.toggle('is-unread', isUnread);
            if (isUnread) unread++;
        });
        dot.hidden = unread === 0;
        toggle.setAttribute('aria-label', unread ? `Notifications, ${unread} unread` : 'Notifications');
        markAll.disabled = unread === 0;
    }
    function save() {
        try { localStorage.setItem(storageKey, JSON.stringify([...read].slice(-300))); } catch (_) {}
        update();
    }
    function close() { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); }
    toggle.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        toggle.setAttribute('aria-expanded', String(!panel.hidden));
    });
    markAll.addEventListener('click', () => { items.forEach(item => read.add(item.dataset.notificationKey)); save(); });
    items.forEach(item => item.addEventListener('click', () => { read.add(item.dataset.notificationKey); save(); }));
    document.addEventListener('click', event => { if (!root.contains(event.target)) close(); });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) { close(); toggle.focus(); }
    });
    root.addEventListener('focusout', event => { if (!root.contains(event.relatedTarget)) close(); });
    update();
})();
