(() => {
    'use strict';
    const form = document.getElementById('login-form');
    if (!form) return;
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const button = document.getElementById('sign-in-button');
    const label = document.getElementById('btn-text');
    const alertBox = document.getElementById('alert-container');
    const toggle = document.getElementById('show-login-password');
    let submitting = false;
    let countdown = null;

    function fieldError(input, message) {
        document.getElementById(`${input.id}-error`).textContent = message;
        input.classList.toggle('is-invalid', Boolean(message));
        input.setAttribute('aria-invalid', String(Boolean(message)));
    }
    function showAlert(message) {
        clearInterval(countdown);
        countdown = null;
        alertBox.replaceChildren();
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger';
        alert.textContent = message;
        alertBox.append(alert);
    }
    function showCountdown(seconds) {
        showAlert('Too many sign-in attempts. Please wait before trying again.');
        const timer = document.createElement('span');
        timer.setAttribute('role', 'timer');
        timer.setAttribute('aria-live', 'off');
        alertBox.firstElementChild.append(timer);
        const deadline = Date.now() + seconds * 1000;
        function update() {
            const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
            if (remaining === 0) {
                showAlert('The waiting period has ended. You can try signing in again.');
                return;
            }
            const minutes = Math.floor(remaining / 60);
            timer.textContent = ` Time remaining: ${minutes}:${String(remaining % 60).padStart(2, '0')}.`;
        }
        countdown = setInterval(update, 1000);
        update();
    }
    function validate() {
        const address = email.value.trim();
        const emailMessage = !address ? 'Enter your email address.' : !email.checkValidity() ? 'Enter a valid email address.' : '';
        const passwordMessage = !password.value ? 'Enter your password.' : '';
        fieldError(email, emailMessage);
        fieldError(password, passwordMessage);
        return !(emailMessage || passwordMessage);
    }
    email.addEventListener('input', () => fieldError(email, ''));
    password.addEventListener('input', () => fieldError(password, ''));
    toggle.addEventListener('click', () => {
        const visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        toggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        toggle.setAttribute('aria-pressed', String(visible));
        toggle.querySelector('i').className = visible ? 'bi bi-eye-slash' : 'bi bi-eye';
        password.focus();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (submitting) return;
        clearInterval(countdown);
        countdown = null;
        alertBox.replaceChildren();
        if (!validate()) {
            (email.classList.contains('is-invalid') ? email : password).focus();
            return;
        }
        submitting = true;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        form.setAttribute('aria-busy', 'true');
        label.textContent = 'Signing in…';
        let redirecting = false;
        try {
            const response = await fetch('/admin/auth/auth_handler.php', {
                requestGuardNotice: false,
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'login',
                    csrf_token: form.elements.csrf_token.value,
                    email: email.value.trim(),
                    password: password.value,
                    remember_me: document.getElementById('remember-me').checked ? '1' : '0'
                })
            });
            // Session expiry can redirect an AJAX request to the HTML login page.
            if (response.redirected) {
                const destination = new URL(response.url, window.location.href);
                if (destination.origin === window.location.origin && /^\/admin\/login(?:\.php)?$/.test(destination.pathname)) {
                    redirecting = true;
                    window.location.assign(destination.href);
                    return;
                }
            }
            const data = await response.json();
            if (response.ok && typeof data.redirect === 'string' && (data.redirect.startsWith('/admin/') || data.redirect === '/verify-login')) {
                redirecting = true;
                window.location.assign(data.redirect);
                return;
            }
            const retryAfter = Number(response.headers.get('Retry-After'));
            if (response.status === 429 && Number.isFinite(retryAfter) && retryAfter > 0) {
                showCountdown(retryAfter);
            } else {
                showAlert(response.status === 401 ? 'Invalid email or password. Please try again.' : (typeof data.error === 'string' ? data.error : 'Unable to sign in. Please try again.'));
            }
        } catch (_) {
            showAlert('Unable to sign in right now. Please try again.');
        } finally {
            if (!redirecting) {
                submitting = false;
                button.disabled = false;
                button.removeAttribute('aria-busy');
                form.removeAttribute('aria-busy');
                label.textContent = 'Sign In';
            }
        }
    });
})();
