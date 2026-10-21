(function () {
    'use strict';
    if (window.GNCLoading || new URLSearchParams(location.search).has('ce_preview')) return;

    const pending = new Map();
    let indicator, showTimer, navigationEnd;
    function render(immediate = false) {
        if (!indicator && document.body) {
            indicator = document.createElement('div');
            indicator.id = 'gncLoadingFeedback';
            indicator.hidden = true;
            indicator.setAttribute('role', 'status');
            indicator.setAttribute('aria-live', 'polite');
            indicator.setAttribute('aria-atomic', 'true');
            document.body.appendChild(indicator);
        }
        clearTimeout(showTimer);
        if (!indicator) return;
        if (!pending.size) {
            indicator.hidden = true;
            indicator.textContent = '';
            return;
        }
        const show = () => {
            indicator.textContent = Array.from(pending.values()).pop();
            indicator.hidden = false;
        };
        // Quick operations finish without flashing a spinner.
        if (indicator.hidden && !immediate) showTimer = setTimeout(show, 180);
        else show();
    }
    function begin(message = 'Loading...', immediate = false) {
        const token = Symbol();
        pending.set(token, message);
        render(immediate);
        let finished = false;
        return () => {
            if (finished) return;
            finished = true;
            pending.delete(token);
            render();
        };
    }
    window.GNCLoading = { begin };
    function navigating(message) {
        if (navigationEnd) navigationEnd();
        const finish = begin(message, true);
        // A cancelled navigation or a download must not leave stale feedback.
        const timer = setTimeout(finish, 20000);
        navigationEnd = () => { clearTimeout(timer); finish(); };
    }
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        if (navigationEnd) navigationEnd();
        pending.clear();
        render();
    });
    document.addEventListener('DOMContentLoaded', () => render(), { once: true });

    // Observe after page handlers so cancelled links and confirmation dialogs stay idle.
    window.addEventListener('click', event => {
        const link = event.target.closest && event.target.closest('a[href]');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        if (link.hasAttribute('download') || link.hasAttribute('data-no-loading')) return;
        const target = link.getAttribute('target') || document.querySelector('base[target]')?.target;
        if (target && target !== '_self') return;
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)) return;
        if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
        queueMicrotask(() => { if (!event.defaultPrevented) navigating('Loading page...'); });
    });
    window.addEventListener('submit', event => {
        const form = event.target;
        const target = event.submitter?.formTarget || form.target || document.querySelector('base[target]')?.target;
        const method = event.submitter?.getAttribute('formmethod') || form.method;
        if ((target && target !== '_self') || method === 'dialog' || form.hasAttribute('data-no-loading')) return;
        queueMicrotask(() => {
            if (!event.defaultPrevented) navigating(method.toLowerCase() === 'get' ? 'Loading results...' : 'Submitting...');
        });
    });

    // Keep the indicator active until every overlapping request has settled.
    if (window.fetch) {
        const originalFetch = window.fetch;
        window.fetch = function (input, options) {
            const url = new URL(typeof input === 'string' || input instanceof URL ? input : input.url, location.href);
            // Visitor analytics run in the background without user interaction.
            if (url.pathname === '/api/track-visit.php') return originalFetch.apply(this, arguments);
            const method = String(options?.method || input?.method || 'GET').toUpperCase();
            const finish = begin(method === 'GET' || method === 'HEAD' ? 'Loading...' : 'Processing request...');
            try {
                return originalFetch.apply(this, arguments).finally(finish);
            } catch (error) {
                finish();
                throw error;
            }
        };
    }
})();
