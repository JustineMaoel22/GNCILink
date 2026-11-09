(() => {
    'use strict';
    document.querySelectorAll('form[data-auto-filters]').forEach(form => {
        let timer;
        let submitting = false;
        const apply = () => {
            clearTimeout(timer);
            if (!submitting && form.checkValidity()) form.requestSubmit();
        };
        form.addEventListener('change', event => {
            if (event.target.matches('select, input[type="date"], input[type="checkbox"], input[type="radio"]')) apply();
        });
        form.addEventListener('input', event => {
            if (!event.target.matches('input[type="search"], input[type="text"]')) return;
            clearTimeout(timer);
            if (!event.isComposing) timer = setTimeout(apply, 500);
        });
        form.addEventListener('compositionend', () => {
            clearTimeout(timer);
            timer = setTimeout(apply, 500);
        });
        form.addEventListener('submit', event => {
            clearTimeout(timer);
            if (submitting) { event.preventDefault(); return; }
            submitting = true;
            form.setAttribute('aria-busy', 'true');
        });
        window.addEventListener('pageshow', () => {
            submitting = false;
            form.removeAttribute('aria-busy');
        });
        window.addEventListener('pagehide', () => clearTimeout(timer));
    });
})();
