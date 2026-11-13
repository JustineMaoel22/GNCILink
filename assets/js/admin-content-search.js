/* Refresh only server-rendered list results; keep editors and search controls intact. */
window.GNCContentSearch = {
    init({page, form, tableId, dataId, kind = 'content'}) {
        if (!page || !form || !window.fetch || !window.AbortController) return;
        const table = document.getElementById(tableId);
        const pagination = page.querySelector('[data-list-pagination]');
        const source = document.getElementById(dataId);
        if (!table || !pagination || !source) return;
        const facebook = kind === 'facebook';
        const headingSelector = facebook ? '.fb-results' : '.ann-list-heading';
        const chipsSelector = facebook ? '.fb-active-filters' : '.news-active-filters';
        const status = document.createElement('div');
        status.className = 'small py-2'; status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite'); form.after(status);
        let timer, controller, sequence = 0;
        function cancel() {
            clearTimeout(timer); timer = null; sequence++;
            controller?.abort();
            form.removeAttribute('aria-busy'); table.removeAttribute('aria-busy');
        }
        function queryUrl() {
            const url = new URL(location.href);
            url.search = new URLSearchParams(new FormData(form)).toString(); url.hash = '';
            return url;
        }
        function restoreFilters(url) {
            for (const input of form.elements) {
                if (!input.name || !input.matches('input,select')) continue;
                input.value = url.searchParams.get(input.name) ?? (input.tagName === 'SELECT' ? input.options[0]?.value || '' : '');
            }
        }
        async function load(url, historyMode = 'push') {
            cancel(); const request = sequence;
            controller = new AbortController();
            const activeController = controller;
            const timeout = setTimeout(() => activeController.abort(), 25000);
            form.setAttribute('aria-busy', 'true'); table.setAttribute('aria-busy', 'true');
            status.replaceChildren(document.createTextNode('Updating results…'));
            try {
                const response = await fetch(url, {credentials: 'same-origin', headers: {'Accept': 'text/html'}, signal: activeController.signal, cache: 'no-store'});
                if (!response.ok) throw Error('Results could not be loaded. Please try again.');
                const html = await response.text();
                if (request !== sequence) return;
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const freshTable = doc.getElementById(tableId), freshSource = doc.getElementById(dataId);
                const freshPagination = doc.querySelector('[data-list-pagination]');
                const freshForm = doc.getElementById(form.id);
                if (!freshTable || !freshSource || !freshPagination || !freshForm) throw Error('Your session may have expired. Refresh the page or sign in again.');
                if (doc.querySelector('[data-list-error]')) throw Error('Results are temporarily unavailable. Please try again.');
                const records = JSON.parse(freshSource.textContent);
                if (facebook ? (!records || typeof records.posts !== 'object' || !Array.isArray(records.pinned)) : !Array.isArray(records)) throw Error('Results could not be read. Please try again.');
                // The server normalizes reversed/invalid date ranges. Keep the controls
                // and browser history consistent with the results it actually returned.
                for (const name of ['from', 'to']) {
                    const current = form.elements.namedItem(name), normalized = freshForm.elements.namedItem(name);
                    if (!current || !normalized) continue;
                    const requested = url.searchParams.get(name) || '';
                    if (current.value === requested) current.value = normalized.value;
                    if (normalized.value) url.searchParams.set(name, normalized.value);
                    else url.searchParams.delete(name);
                }
                // Existing bulk-selection listeners observe this same table node.
                table.querySelectorAll('button,.content-action-wrap,a').forEach(node => window.bootstrap?.Tooltip?.getInstance(node)?.dispose());
                const freshRows = facebook ? freshTable : freshTable.querySelector('tbody');
                freshRows.querySelectorAll('script').forEach(node => node.remove());
                (facebook ? table : table.querySelector('tbody')).replaceChildren(...freshRows.childNodes);
                pagination.replaceChildren(...freshPagination.childNodes);
                const heading = page.querySelector(headingSelector);
                heading.replaceChildren(...doc.querySelector(headingSelector).childNodes);
                const oldChips = form.querySelector(chipsSelector), chips = freshForm.querySelector(chipsSelector);
                if (oldChips) { if (chips) oldChips.replaceWith(chips); else oldChips.remove(); }
                else if (chips) form.append(chips);
                page.querySelector('[data-list-error]')?.remove();
                source.textContent = freshSource.textContent;
                page.dispatchEvent(new CustomEvent('content-list:updated', {detail: {records}}));
                window.GNCContentRowActions?.refresh(table);
                const returnQuery = url.searchParams.toString();
                page.querySelectorAll('form[method="post"],form[method="POST"]').forEach(post => {
                    let field = post.querySelector('[name="return_query"]');
                    if (!field && form.id === 'news-query') {
                        field = document.createElement('input'); field.type = 'hidden'; field.name = 'return_query'; post.append(field);
                    }
                    if (field) field.value = returnQuery;
                });
                if (historyMode === 'push' && url.href !== location.href) history.pushState(null, '', url);
                status.textContent = facebook ? `${pagination.querySelector('p').textContent}.` : heading.querySelector('[role="status"]').textContent;
            } catch (error) {
                if (request !== sequence) return;
                status.replaceChildren(document.createTextNode(error.name === 'AbortError' ? 'The search timed out. ' : `${error.message} `));
                const retry = document.createElement('button');
                retry.type = 'button'; retry.className = 'btn btn-sm btn-outline-secondary'; retry.textContent = 'Retry search';
                retry.addEventListener('click', () => load(queryUrl())); status.append(retry);
            } finally {
                clearTimeout(timeout);
                if (request === sequence) { form.removeAttribute('aria-busy'); table.removeAttribute('aria-busy'); }
            }
        }
        form.addEventListener('submit', event => { event.preventDefault(); if (form.checkValidity()) load(queryUrl()); });
        function schedule(event) {
            if (!event.target.matches('input[type="search"],input[type="text"]')) return;
            cancel(); status.textContent = 'Waiting to search…';
            if (!event.isComposing) timer = setTimeout(() => { if (form.checkValidity()) load(queryUrl()); }, 400);
        }
        form.addEventListener('input', schedule);
        form.addEventListener('compositionend', schedule);
        form.addEventListener('change', event => {
            if (!event.target.matches('select,input[type="date"]')) return;
            cancel();
            if (form.checkValidity()) load(queryUrl());
            else status.textContent = 'Check the filter values before searching.';
        });
        page.addEventListener('click', event => {
            const link = event.target.closest('[data-list-pagination] a,.ann-empty-row a,.fb-empty a,.fb-active-filters a');
            if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target) return;
            // The page's legacy <base> URL may differ from its canonical URL.
            const url = new URL(link.getAttribute('href'), location.href);
            if (url.origin !== location.origin || ![location.pathname, new URL(form.action).pathname].includes(url.pathname)) return;
            url.pathname = location.pathname;
            event.preventDefault(); restoreFilters(url); load(url);
        });
        window.addEventListener('popstate', () => { const url = new URL(location.href); restoreFilters(url); load(url, 'none'); });
        window.addEventListener('pagehide', cancel);
        window.addEventListener('pageshow', event => {
            if (event.persisted) { const url = new URL(location.href); restoreFilters(url); load(url, 'none'); }
        });
        return {
            cancel,
            pending: () => Boolean(timer) || form.hasAttribute('aria-busy'),
            refresh: () => load(new URL(location.href), 'none'),
            apply: () => { if (form.checkValidity()) return load(queryUrl()); }
        };
    }
};
