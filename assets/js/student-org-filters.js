(function () {
    'use strict';
    if (window.top !== window.self || new URLSearchParams(location.search).has('ce_preview')) return;
    let directory = document.querySelector('.student-org-directory');
    if (!directory) return;
    let pending;
    async function update(url, push) {
        if (pending) pending.abort();
        const controller = new AbortController();
        pending = controller;
        directory.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url.href, {signal: controller.signal, credentials: 'same-origin'});
            if (!response.ok) throw new Error('Could not load organizations');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = page.querySelector('.student-org-directory');
            if (!next) throw new Error('Organization directory missing');
            if (controller !== pending) return;
            next.classList.add('org-filter-results');
            directory.replaceWith(next);
            directory = next;
            if (push) history.pushState(null, '', url.pathname + url.search + url.hash);
            const active = directory.querySelector(url.searchParams.has('p') ? '.student-org-pagination [aria-current="page"], .student-org-filters [aria-current]' : '.student-org-filters [aria-current], .student-org-filters a');
            if (active && push) active.focus({preventScroll: true});
        } catch (error) {
            if (error.name !== 'AbortError' && controller === pending) location.assign(url.href);
        } finally {
            if (controller === pending) { directory.removeAttribute('aria-busy'); pending = null; }
        }
    }
    document.addEventListener('click', event => {
        const link = event.target.closest('a');
        if (!link || !link.closest('.student-org-filters, .student-org-pagination') || !directory.contains(link)
            || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
            || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || url.pathname.replace(/\.php$/, '') !== location.pathname.replace(/\.php$/, '')) return;
        // Hosted and local Apache may already have removed the PHP extension.
        url.pathname = location.pathname;
        event.preventDefault();
        update(url, true);
    });
    window.addEventListener('popstate', () => update(new URL(location.href), false));
})();
