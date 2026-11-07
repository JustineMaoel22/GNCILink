(() => {
    'use strict';
    const page = document.querySelector('.news-management');
    const records = new Map(JSON.parse(document.getElementById('news-preview-data').textContent).map(item => [String(item.id), item]));
    const dialog = document.getElementById('ann-preview-dialog');
    const edit = document.getElementById('news-preview-edit');
    const previewMessage = document.getElementById('news-preview-message');
    const images = document.getElementById('ann-preview-images');
    const previewHome = dialog.parentNode;
    let trigger, selectedId;
    function addImage(path, draft = false) {
        if (!path) return;
        const url = new URL(path, location.origin);
        if (!(draft && ['blob:', 'data:'].includes(url.protocol)) && (url.origin !== location.origin || !['http:', 'https:'].includes(url.protocol))) return;
        const image = document.createElement('img'); image.src = url.href; image.alt = 'Article picture'; images.append(image);
    }
    function openPreview(item, button, draft = false) {
        trigger = button; selectedId = String(item.id || '');
        document.getElementById('news-preview-label').textContent = draft ? 'Unsaved article preview' : 'News article preview';
        document.getElementById('ann-preview-title').textContent = item.title || 'Untitled article';
        document.getElementById('ann-preview-meta').textContent = `${item.author} · ${item.category} · ${item.status} · ${item.published ? 'Published ' + item.published : 'Created ' + item.created}`;
        images.replaceChildren(); (item.images || []).forEach(path => addImage(path, draft));
        // Saved HTML is sanitized by renderSectionContent() on the server.
        // The draft uses a conservative DOM allowlist before rendering.
        previewMessage.replaceChildren();
        previewMessage.innerHTML = GncRichText.previewHtml(item.html);
        edit.hidden = draft || !item.canEdit;
        if (draft) document.getElementById('newsModal').append(dialog);
        dialog.showModal();
    }
    page.querySelectorAll('[data-news-preview]').forEach(button => button.addEventListener('click', () => openPreview(records.get(button.dataset.newsPreview), button)));
    page.querySelector('[data-news-preview-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); dialog.close(); }
    });
    dialog.addEventListener('close', () => { previewHome.append(dialog); trigger?.focus({preventScroll: true}); });
    edit.addEventListener('click', () => {
        dialog.close();
        const button = page.querySelector(`.btn-edit-news[data-id="${selectedId}"]`);
        if (button) button.click();
    });
    document.getElementById('news-draft-preview').addEventListener('click', event => {
        const status = document.getElementById('modal-status');
        const category = document.getElementById('modal-category');
        const paths = [];
        const cover = document.getElementById('modal-image-preview');
        if (!document.getElementById('modal-remove-image').checked && cover.getAttribute('src')) paths.push(cover.src);
        page.querySelectorAll('#modal-gallery-existing .gallery-thumb-wrap:not(.marked-remove) img,#modal-gallery-new img').forEach(image => paths.push(image.src));
        openPreview({title: document.getElementById('modal-title').value, html: document.querySelector('#modal-content-editor .ql-editor').innerHTML,
            author: document.getElementById('modal-author').value || 'School editor', category: category.selectedOptions[0]?.textContent || 'Others',
            status: {draft:'Draft',pending:'Waiting for approval',published:'Published',archived:'Archived'}[status.value], created:'Not saved yet', images: paths}, event.currentTarget, true);
    });
    const query = page.querySelector('#news-query');
    const returnQuery = new URLSearchParams(new FormData(query));
    // The server's normalized filters include the current page.
    const currentPage = new URLSearchParams(location.search).get('page');
    if (currentPage && /^\d+$/.test(currentPage)) returnQuery.set('page', currentPage);
    page.querySelectorAll('form[method="POST"],form[method="post"]').forEach(form => {
        if (!form.querySelector('[name="return_query"]')) { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'return_query'; input.value = returnQuery.toString(); form.append(input); }
    });
    page.querySelectorAll('.ann-inline-form').forEach(form => form.addEventListener('submit', async event => {
        event.preventDefault(); if (form.dataset.busy) return; form.dataset.busy = '1';
        const action = form.elements.namedItem('action').value;
        try {
            if (action === 'archive') {
                const queued = Boolean(page.querySelector('.ann-review-note'));
                if (!await AdminDialogs.confirm(queued ? 'Submit an archive request for approval? The published article stays unchanged until approved.' : 'Archive this article? It will be hidden from the website and removed from pinned content. The article and pictures remain saved.', {title: 'Archive article?', confirmText: queued ? 'Submit for approval' : 'Archive'})) return;
            }
            form.querySelector('button[type="submit"]').disabled = true;
            HTMLFormElement.prototype.submit.call(form);
        } finally { delete form.dataset.busy; }
    }));
    if (location.hash === '#pinned-content') page.querySelector('#pinned-content')?.setAttribute('open','');
})();
