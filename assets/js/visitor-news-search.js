/* Use the published server-rendered feed without navigating away from the search field. */
(() => {
    'use strict';
    const form = document.getElementById('na-search-form'), input = document.getElementById('na-search');
    if (!form || !input || !window.fetch || !window.AbortController || window.top !== window.self || new URLSearchParams(location.search).has('ce_preview')) return;
    const feed = document.querySelector('.na-feed');
    const status = document.createElement('p');
    status.className = 'na-note'; status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite');
    status.hidden = true; form.closest('.na-toolbar').after(status);
    let timer, controller, sequence = 0;
    const fields = ['type','department','program','category','sort'];
    function cancel() {
        clearTimeout(timer); sequence++; controller?.abort();
        form.removeAttribute('aria-busy'); document.getElementById('na-feed-results').removeAttribute('aria-busy');
    }
    function currentUrl(submitter) {
        const url = new URL(location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        if (submitter?.name) url.searchParams.set(submitter.name, submitter.value);
        url.searchParams.delete('page'); url.hash = '';
        return url;
    }
    function hydrateFilters(freshForm, preserveInput) {
        for (const name of fields) {
            form.querySelectorAll(`input[type="hidden"][name="${name}"]`).forEach(node => node.remove());
            const field = freshForm.querySelector(`input[type="hidden"][name="${name}"]`);
            if (field) form.append(field.cloneNode(true));
        }
        if (!preserveInput) input.value = freshForm.querySelector('[name="q"]').value;
        const dropdown = form.querySelector('.na-filter-group');
        window.bootstrap?.Dropdown?.getInstance(dropdown.querySelector('[data-bs-toggle="dropdown"]'))?.dispose();
        dropdown.replaceChildren(...freshForm.querySelector('.na-filter-group').childNodes);
    }
    function revealImages(root) {
        root.querySelectorAll('.skeleton-wrap img').forEach(img => {
            const reveal = () => { img.classList.add('is-loaded'); img.closest('.skeleton-wrap')?.classList.add('is-loaded'); img.closest('.skeleton-group')?.classList.add('is-loaded'); };
            if (img.complete) reveal();
            else { img.addEventListener('load', reveal, {once:true}); img.addEventListener('error', reveal, {once:true}); }
        });
    }
    async function load(url, historyMode = 'push', preserveInput = false) {
        cancel(); const request = sequence;
        controller = new AbortController(); const active = controller;
        const timeout = setTimeout(() => active.abort(), 25000);
        form.setAttribute('aria-busy','true'); document.getElementById('na-feed-results').setAttribute('aria-busy','true');
        status.hidden = true; status.replaceChildren();
        try {
            const response = await fetch(url, {credentials:'same-origin', cache:'no-store', headers:{Accept:'text/html'}, signal:active.signal});
            if (!response.ok) throw Error('Results could not be loaded. Please try again.');
            const html = await response.text();
            if (request !== sequence) return;
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const selectors = ['#na-feed-results','#na-pinned-results','#na-category-results','.na-tabs','.na-feed-title'];
            if (!doc.getElementById('na-search-form') || selectors.some(selector => !doc.querySelector(selector))) throw Error('Results could not be read. Please try again.');
            const keepFocus = document.activeElement === input;
            const selection = [input.selectionStart, input.selectionEnd];
            document.getElementById('gncPinnedCarousel') && window.bootstrap?.Carousel?.getInstance(document.getElementById('gncPinnedCarousel'))?.dispose();
            selectors.forEach(selector => {
                const old = document.querySelector(selector), fresh = doc.querySelector(selector);
                fresh.querySelectorAll('script').forEach(node => node.remove());
                old.replaceChildren(...fresh.childNodes);
            });
            const programs = document.querySelector('#calendarProgramWidget .gnc-programs-list');
            if (programs && doc.querySelector('#calendarProgramWidget .gnc-programs-list')) programs.replaceChildren(...doc.querySelector('#calendarProgramWidget .gnc-programs-list').childNodes);
            hydrateFilters(doc.getElementById('na-search-form'), preserveInput);
            document.documentElement.classList.add('na-pagination-visit');
            revealImages(feed);
            const carousel = document.getElementById('gncPinnedCarousel');
            if (carousel && window.bootstrap?.Carousel) bootstrap.Carousel.getOrCreateInstance(carousel);
            document.dispatchEvent(new CustomEvent('news-feed:updated'));
            document.dispatchEvent(new CustomEvent('calendar:updated'));
            if (keepFocus) {
                input.focus({preventScroll:true});
                try { input.setSelectionRange(...selection); } catch (_) {}
            }
            if (historyMode === 'push' && url.href !== location.href) history.pushState(null,'',url);
            status.textContent = document.querySelector('.na-summary-count')?.textContent || (document.querySelector('.na-empty h3')?.textContent ?? 'Results updated.');
            status.hidden = true;
        } catch (error) {
            if (request !== sequence) return;
            status.hidden = false;
            status.replaceChildren(document.createTextNode(error.name === 'AbortError' ? 'The search timed out. ' : `${error.message} `));
            const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'na-empty-btn'; retry.textContent = 'Retry search';
            retry.addEventListener('click', () => load(url, historyMode, preserveInput)); status.append(retry);
        } finally {
            clearTimeout(timeout);
            if (request === sequence) { form.removeAttribute('aria-busy'); document.getElementById('na-feed-results').removeAttribute('aria-busy'); }
        }
    }
    form.addEventListener('submit', event => { event.preventDefault(); if (form.checkValidity()) load(currentUrl(event.submitter), 'push', true); });
    function schedule(event) {
        cancel();
        if (!event.isComposing) timer = setTimeout(() => { if (form.checkValidity()) load(currentUrl(), 'push', true); }, 400);
    }
    input.addEventListener('input', schedule); input.addEventListener('compositionend', schedule);
    feed.addEventListener('click', event => {
        const link = event.target.closest('.na-tabs a,.na-pagination a,.na-chips a,.na-empty a,#na-category-results a,#calendarProgramWidget a');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target) return;
        const url = new URL(link.getAttribute('href'), document.baseURI);
        const action = new URL(form.action);
        if (url.origin !== location.origin || ![location.pathname,action.pathname].includes(url.pathname)) return;
        event.preventDefault(); url.pathname = location.pathname; url.hash = ''; load(url);
    });
    window.addEventListener('popstate', () => load(new URL(location.href), 'none'));
    window.addEventListener('pagehide', cancel);
    window.addEventListener('pageshow', event => { if (event.persisted) load(new URL(location.href), 'none'); });
})();
