(() => {
    'use strict';
    const form = document.getElementById('logs-filters');
    form.addEventListener('submit', () => {
        if (!form.checkValidity()) return;
        document.getElementById('logs-loading').hidden = false;
        document.querySelector('.logs-page').setAttribute('aria-busy', 'true');
        form.querySelector('[type="submit"]').textContent = 'Loading…';
    });
    window.addEventListener('pageshow', () => {
        document.getElementById('logs-loading').hidden = true;
        document.querySelector('.logs-page').removeAttribute('aria-busy');
        form.querySelector('[type="submit"]').textContent = 'Apply filters';
    });
    const dialog = document.getElementById('activity-dialog');
    if (typeof dialog.showModal !== 'function') return;
    const logs = JSON.parse(document.getElementById('activity-log-data').textContent);
    let trigger;
    const open = (index, link) => {
        const entry = logs[index];
        if (!entry) return;
        trigger = link;
        Object.entries(entry).forEach(([key, value]) => {
            const field = document.getElementById(`detail-${key}`);
            if (field) field.textContent = value;
        });
        dialog.showModal();
        document.getElementById('logs-close').focus();
    };
    document.querySelectorAll('[data-log-open]').forEach(link => {
        link.addEventListener('click', event => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            open(link.dataset.logOpen, link);
        });
    });
    document.querySelectorAll('[data-log-row]').forEach(row => {
        row.addEventListener('click', event => {
            if (event.target.closest('a, button') || window.getSelection().toString()) return;
            open(row.dataset.logRow, row.querySelector('[data-log-open]'));
        });
    });
    document.getElementById('logs-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            event.preventDefault();
            dialog.close();
        }
    });
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const rect = dialog.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
    });
    dialog.addEventListener('close', () => trigger?.focus());
})();
