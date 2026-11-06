(() => {
    'use strict';
    const dialog = document.getElementById('approval-dialog');
    const content = document.getElementById('approval-dialog-content');
    const loading = document.getElementById('approval-loading');
    const failure = document.getElementById('approval-load-error');
    let opener, fetching = false;
    const show = section => {
        // DOMParser parses noscript contents as elements; use the JS confirmation
        // here so fallback checkboxes cannot block the imported review forms.
        section.querySelectorAll('noscript').forEach(element => element.remove());
        content.replaceChildren(section);
        dialog.showModal();
        section.querySelector('[data-review-close]').focus();
    };
    if (typeof dialog.showModal === 'function') {
        const selected = document.getElementById('submission-review');
        if (selected) show(selected);
        document.addEventListener('click', async event => {
            const link = event.target.closest('[data-review-open]');
            if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            if (fetching) return;
            fetching = true;
            opener = link;
            loading.hidden = false;
            failure.hidden = true;
            link.setAttribute('aria-busy', 'true');
            try {
                const response = await fetch(link.href, {credentials: 'same-origin', signal: AbortSignal.timeout(30000)});
                if (!response.ok) throw new Error('Review unavailable');
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const section = page.getElementById('submission-review');
                if (!section || !section.querySelector('.approval-review-actions')) throw new Error('Review unavailable');
                show(document.importNode(section, true));
            } catch (_) {
                document.getElementById('approval-retry').href = link.href;
                failure.hidden = false;
            } finally {
                fetching = false;
                loading.hidden = true;
                link.removeAttribute('aria-busy');
            }
        });
        dialog.addEventListener('click', event => {
            if (event.target.closest('[data-review-close]')) {event.preventDefault(); dialog.close();}
            if (event.target !== dialog) return;
            const r = dialog.getBoundingClientRect();
            if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close();
        });
        dialog.addEventListener('keydown', event => {
            if (event.key === 'Escape') {event.preventDefault(); dialog.close();}
        });
        dialog.addEventListener('close', () => {
            if (opener) opener.focus();
            else {
                const url = new URL(location.href);
                url.searchParams.delete('id');url.searchParams.delete('source');url.hash = '';
                history.replaceState(null, '', url);
                document.querySelector('[data-review-open]')?.focus();
            }
        });
    }
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!form.matches('[data-review-confirm]')) return;
        event.preventDefault();
        if (form.dataset.submitting || !form.reportValidity()) return;
        form.dataset.submitting = 'true';
        try {
            const accepted = await AdminDialogs.confirm(form.dataset.reviewConfirm, {
                title: form.dataset.reviewLabel, confirmText: form.dataset.reviewLabel,
                danger: ['reject', 'delete'].includes(form.elements.action.value)
            });
            if (accepted) {
                // Disable all review controls so a second action cannot be queued.
                form.closest('.approval-review').querySelectorAll('button[type="submit"]').forEach(button => {button.disabled = true;});
                form.querySelector('button[type="submit"]').textContent = 'Saving…';
                HTMLFormElement.prototype.submit.call(form);
            } else delete form.dataset.submitting;
        } catch (_) { delete form.dataset.submitting; }
    });
    const filters = document.getElementById('approval-filters');
    filters.addEventListener('submit', () => {
        filters.setAttribute('aria-busy', 'true');
        filters.querySelector('[type="submit"]').textContent = 'Loading…';
    });
    window.addEventListener('pageshow', () => {
        filters.removeAttribute('aria-busy');
        filters.querySelector('[type="submit"]').textContent = 'Apply filters';
    });
})();
