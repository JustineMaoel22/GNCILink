(() => {
    'use strict';
    const toggle = document.getElementById('show-passwords');
    toggle?.addEventListener('change', () => {
        document.querySelectorAll('input[autocomplete="new-password"],input[autocomplete="current-password"]').forEach(input => {
            input.type = toggle.checked ? 'text' : 'password';
        });
    });

    const password = document.getElementById('new_password');
    const confirm = document.getElementById('confirm_password');
    const meter = document.getElementById('password-strength');
    const label = document.getElementById('password-strength-label');
    function updateStrength() {
        if (!password) return;
        const value = password.value;
        const common = /^(password|qwerty|welcome|admin|letmein)/i.test(value);
        let score = value.length < 12 ? 1 : value.length < 16 ? 2 : value.length < 22 ? 3 : 4;
        if (common || new Set(value).size < 4) score = 1;
        if (!value) score = 0;
        if (meter) meter.value = score;
        if (label) label.textContent = ['Enter a new password', 'Too weak', 'Fair — try a longer phrase', 'Good', 'Strong estimate'][score];
        if (confirm) confirm.setCustomValidity(confirm.value && confirm.value !== value ? 'The new passwords do not match.' : '');
    }
    password?.addEventListener('input', updateStrength);
    confirm?.addEventListener('input', updateStrength);

    document.querySelectorAll('[data-auth-form]').forEach(form => {
        const button = form.querySelector('button[type="submit"]');
        if (!button) return;
        const originalText = button.textContent;
        form.addEventListener('submit', () => {
            if (button.disabled) return;
            button.disabled = true;
            button.textContent = button.dataset.loadingText || 'Please wait…';
            form.setAttribute('aria-busy', 'true');
        });
        window.addEventListener('pageshow', () => {
            button.disabled = false;
            button.textContent = originalText;
            form.removeAttribute('aria-busy');
        });
    });
})();
