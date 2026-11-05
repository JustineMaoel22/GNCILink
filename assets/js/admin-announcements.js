(() => {
    'use strict';
    const page = document.querySelector('.announcement-management');
    if (!page) return;
    const source = document.getElementById('ann-preview-data');
    const dialog = document.getElementById('ann-preview-dialog');
    const records = new Map(JSON.parse(source.textContent).map(item => [String(item.id), item]));
    let trigger;
    page.querySelectorAll('[data-preview-id]').forEach(button => {
        button.addEventListener('click', () => {
            const item = records.get(button.dataset.previewId);
            if (!item) return;
            trigger = button;
            document.getElementById('ann-preview-title').textContent = item.title;
            document.getElementById('ann-preview-meta').textContent = `${item.author || 'Former account'} · ${item.created} · Website: ${item.status}`;
            document.getElementById('ann-preview-message').textContent = item.text;
            const images = document.getElementById('ann-preview-images');
            images.replaceChildren();
            item.images.forEach(path => {
                const url = new URL(path, location.origin);
                if (url.origin !== location.origin || !['http:', 'https:'].includes(url.protocol)) return;
                const img = document.createElement('img');
                img.src = url.href; img.alt = 'Announcement image'; img.loading = 'lazy'; images.append(img);
            });
            dialog.showModal();
            dialog.querySelector('[data-ann-preview-close]').focus();
        });
    });
    dialog.querySelector('[data-ann-preview-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('keydown', event => {if (event.key === 'Escape') {event.preventDefault(); dialog.close();}});
    dialog.addEventListener('close', () => trigger?.focus());
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const r = dialog.getBoundingClientRect();
        if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close();
    });
    page.querySelectorAll('.ann-inline-form').forEach(form => {
        form.addEventListener('submit', async event => {
            const action = form.elements.namedItem('action').value;
            if (!['archive', 'unarchive', 'pin', 'unpin'].includes(action)) return;
            event.preventDefault();
            if (form.dataset.busy) return;
            form.dataset.busy = '1';
            try {
                const messages = {
                    archive: 'Archive this announcement? It will be hidden from the website and removed from the slideshow, while its saved content is kept. Existing Facebook posts are unchanged.',
                    unarchive: 'Restore this announcement as a draft? It stays hidden until published.',
                    pin: 'Pin this announcement to the homepage slideshow? Only published content appears to visitors.',
                    unpin: 'Remove this announcement from the homepage slideshow? The announcement itself is kept.'
                };
                const queued = page.querySelector('.ann-review-note');
                const message = queued ? 'Submit this '+(action === 'archive' ? 'archive' : 'restore')+' request for Super Admin approval? Published content remains unchanged until approval.' : messages[action];
                if (await AdminDialogs.confirm(message, {title: 'Confirm announcement action', confirmText: queued ? 'Submit for approval' : 'Continue'})) {
                    form.querySelector('button').disabled = true;
                    HTMLFormElement.prototype.submit.call(form);
                } else delete form.dataset.busy;
            } catch (_) { delete form.dataset.busy; }
        });
    });
    const filters = document.getElementById('ann-query');
    if (location.hash === '#pinned-content') document.getElementById('pinned-content')?.setAttribute('open', '');
    document.getElementById('ann-draft-preview')?.addEventListener('click', event => {
        trigger = event.currentTarget;
        document.getElementById('ann-preview-title').textContent = document.getElementById('modal-title').value.trim() || 'Untitled announcement';
        document.getElementById('ann-preview-meta').textContent = 'Unsaved content preview';
        document.getElementById('ann-preview-message').textContent = document.querySelector('#modal-content-editor .ql-editor')?.innerText || 'No message entered yet.';
        document.getElementById('ann-preview-images').replaceChildren();
        dialog.showModal();dialog.querySelector('[data-ann-preview-close]').focus();
    });
    filters.addEventListener('submit', () => {
        filters.setAttribute('aria-busy', 'true');
        filters.querySelector('[type="submit"]').textContent = 'Loading…';
    });
})();
