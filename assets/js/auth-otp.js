(() => {
    'use strict';
    const form = document.getElementById('otpForm');
    if (!form) return;
    const digits = Array.from(form.querySelectorAll('.otp-digit'));
    const verify = document.getElementById('verifyBtn');
    const resend = document.getElementById('resendBtn');
    const error = document.getElementById('otp-error');
    let submitting = false;

    function paint() { digits.forEach(digit => digit.classList.toggle('filled', Boolean(digit.value))); }
    function fill(text, start = 0) {
        const numeric = text.replace(/\D/g, '');
        if (numeric.length >= 6) start = 0;
        const values = numeric.slice(0, 6 - start);
        for (let i = 0; i < values.length; i++) digits[start + i].value = values[i];
        paint();
        digits[Math.min(start + values.length, 5)].focus();
        error.textContent = '';
    }
    digits.forEach((digit, index) => {
        digit.addEventListener('input', () => {
            if (digit.value.length > 1) fill(digit.value, index);
            else { digit.value = digit.value.replace(/\D/g, ''); paint(); if (digit.value && index < 5) digits[index + 1].focus(); }
            error.textContent = '';
        });
        digit.addEventListener('keydown', event => {
            if (event.key === 'Backspace' && !digit.value && index > 0) digits[index - 1].focus();
            if (event.key === 'ArrowLeft' && index > 0) { event.preventDefault(); digits[index - 1].focus(); }
            if (event.key === 'ArrowRight' && index < 5) { event.preventDefault(); digits[index + 1].focus(); }
        });
        digit.addEventListener('paste', event => { event.preventDefault(); fill(event.clipboardData.getData('text'), index); });
    });
    digits[0].focus();

    const countdown = document.getElementById('countdown');
    const expiresAt = performance.now() + Number(form.dataset.expiresIn || 0) * 1000;
    function updateCountdown() {
        const remaining = Math.max(0, Math.ceil((expiresAt - performance.now()) / 1000));
        countdown.textContent = remaining ? `${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')} remaining` : 'expired';
    }
    updateCountdown();
    setInterval(updateCountdown, 1000);

    const resendTimer = document.getElementById('resendTimer');
    const resendAt = performance.now() + 60000;
    function updateResend() {
        const remaining = Math.max(0, Math.ceil((resendAt - performance.now()) / 1000));
        if (remaining) resendTimer.textContent = remaining;
        else { resend.disabled = false; resend.textContent = 'Resend code'; clearInterval(resendInterval); }
    }
    const resendInterval = setInterval(updateResend, 1000);

    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        if (event.submitter === resend) {
            const action = document.createElement('input');
            action.type = 'hidden'; action.name = 'action'; action.value = 'resend_otp';
            form.append(action);
            submitting = true;
            resend.disabled = true;
            resend.textContent = 'Sending…';
            return;
        }
        if (digits.some(digit => !/^[0-9]$/.test(digit.value))) {
            event.preventDefault();
            error.textContent = 'Enter all six digits of your verification code.';
            digits.find(digit => !/^[0-9]$/.test(digit.value))?.focus();
            return;
        }
        submitting = true;
        verify.disabled = true;
        verify.textContent = 'Verifying…';
        form.setAttribute('aria-busy', 'true');
    });
})();
