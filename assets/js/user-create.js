(function () {
    'use strict';
    const form = document.getElementById('create-user-form');
    if (!form) return;
    const role = document.getElementById('new-role'), button = document.getElementById('create-user-button');
    const feedback = document.getElementById('create-user-feedback');
    const fields = Array.from(form.querySelectorAll('input[required], select[required]'));
    const descriptions = {
        'Super Admin': 'Full system access, including user management, activity logs, and content approvals.',
        'Content Editor': 'Create and edit website content. Changes require approval before going live.',
        'The Mirror': 'Create and edit News and Announcements. Changes require approval before going live.'
    };
    const emails = new Set(Array.from(document.querySelectorAll('#existing-users tbody td:nth-child(2)'), cell => cell.textContent.trim().toLowerCase()));
    function updateRole() { document.getElementById('user-role-help').textContent = descriptions[role.value] || 'Choose the access this account needs.'; }
    role.addEventListener('change', updateRole); updateRole();
    function validate(field, show) {
        field.setCustomValidity('');
        if (!field.value.trim()) field.setCustomValidity(field === role ? 'Choose a role.' : field.type === 'email' ? 'Enter an email address.' : 'Enter a ' + (field.name === 'first_name' ? 'first' : 'last') + ' name.');
        else if (field.type === 'email') {
            if (field.validity.typeMismatch || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) field.setCustomValidity('Enter a complete email address, such as name@example.com.');
            else if (emails.has(field.value.trim().toLowerCase())) field.setCustomValidity('An account already uses this email address. Use a different email or manage the existing account below.');
        }
        const invalid = !field.validity.valid;
        if (show || field.dataset.touched || field.classList.contains('is-invalid')) {
            field.classList.toggle('is-invalid', invalid); field.setAttribute('aria-invalid', String(invalid));
            const errorId = field === role ? 'user-role-error' : field.id + '-error';
            document.getElementById(errorId).textContent = invalid ? field.validationMessage : '';
        }
        return !invalid;
    }
    fields.forEach(field => {
        field.addEventListener('blur', () => { field.dataset.touched = 'true'; validate(field, true); });
        field.addEventListener('input', () => validate(field, false));
        field.addEventListener('change', () => validate(field, false));
    });
    let confirming = false;
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (confirming || button.disabled) return;
        fields.filter(field => field.type !== 'select-one').forEach(field => { field.value = field.value.trim(); });
        const invalid = fields.filter(field => !validate(field, true));
        if (invalid.length) {
            feedback.textContent = 'Please check the highlighted fields. The account has not been created.';
            feedback.classList.remove('d-none'); invalid[0].focus(); return;
        }
        feedback.classList.add('d-none'); confirming = true; button.disabled = true;
        const name = form.elements.first_name.value + ' ' + form.elements.last_name.value;
        try {
            const message = 'Create an account for ' + name + ' (' + form.elements.email.value + ') as ' + role.value + '?' + (role.value === 'Super Admin' ? ' This role has full system access.' : '');
            const accepted = window.AdminDialogs ? await window.AdminDialogs.confirm(message, {title: 'Create user?', confirmText: 'Create user', cancelText: 'Review details'}) : window.confirm(message);
            if (!accepted) { button.disabled = false; button.focus(); return; }
            form.setAttribute('aria-busy', 'true'); button.querySelector('span').textContent = 'Creating user…';
            HTMLFormElement.prototype.submit.call(form);
        } catch (_) {
            feedback.textContent = 'The account could not be submitted. Your entries are still here. Please try again.';
            feedback.classList.remove('d-none'); feedback.focus(); button.disabled = false; form.setAttribute('aria-busy', 'false'); button.querySelector('span').textContent = 'Create user';
        } finally { confirming = false; }
    });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) { button.disabled = false; form.setAttribute('aria-busy', 'false'); button.querySelector('span').textContent = 'Create user'; }
    });
    if (form.querySelector('.is-invalid')) form.querySelector('.is-invalid').focus();

    const dialog = document.getElementById('temporary-password-dialog');
    if (!dialog) return;
    const input = document.getElementById('temporary-password'), status = document.getElementById('password-copy-status');
    const toggle = document.getElementById('toggle-temporary-password'), copy = document.getElementById('copy-temporary-password');
    const done = document.getElementById('close-temporary-password');
    let copied = false, closing = false;
    toggle.addEventListener('click', function () {
        const reveal = input.type === 'password'; input.type = reveal ? 'text' : 'password';
        toggle.textContent = reveal ? 'Hide' : 'Show'; toggle.setAttribute('aria-pressed', String(reveal));
    });
    copy.addEventListener('click', async function () {
        copy.disabled = true; done.disabled = true;
        try {
            if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(input.value);
            else {
                const hidden = input.type === 'password';
                try { input.type = 'text'; input.focus(); input.select(); if (!document.execCommand('copy')) throw new Error('Copy unavailable'); }
                finally { if (hidden) input.type = 'password'; }
            }
            copied = true; copy.textContent = 'Copied'; status.textContent = 'Password copied. Share it privately with the account owner.';
        } catch (_) { status.textContent = 'Copy did not work. Choose Show, select the password, and copy it manually.'; }
        finally { copy.disabled = false; done.disabled = false; copy.focus(); }
    });
    async function requestClose() {
        if (closing || copy.disabled) return;
        closing = true;
        try {
            if (copied || (window.AdminDialogs ? await window.AdminDialogs.confirm('Have you saved the temporary password securely? It is not emailed and will not be shown again after closing.', {title: 'Save the password before closing', confirmText: 'Close and erase', cancelText: 'Keep password open'}) : window.confirm('Close and erase the temporary password? Save it securely first; it will not be shown again.'))) dialog.close();
        } finally { closing = false; }
    }
    done.addEventListener('click', requestClose);
    dialog.addEventListener('cancel', function (event) { event.preventDefault(); requestClose(); });
    dialog.addEventListener('close', function () { input.value = ''; input.removeAttribute('value'); input.type = 'password'; dialog.remove(); button.focus({preventScroll: true}); });
    window.addEventListener('beforeunload', function (event) { if (dialog.open) { event.preventDefault(); event.returnValue = ''; } });
    dialog.showModal();
})();
