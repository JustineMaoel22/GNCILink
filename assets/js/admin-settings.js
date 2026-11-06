(() => {
    'use strict';
    const forms = [...document.querySelectorAll('[data-settings-form]')];
    let navigating = false;
    const pictureInput = document.getElementById('profile-picture-file');
    const picturePreview = document.querySelector('.settings-profile-preview');
    let pictureChanged = false;
    if (pictureInput && picturePreview) {
        const savedPreview = [...picturePreview.childNodes].map(node => node.cloneNode(true));
        const help = document.getElementById('profile-picture-help');
        const savedHelp = help?.textContent;
        let previewUrl;
        pictureInput.addEventListener('change', () => {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = null;
            const file = pictureInput.files?.[0];
            pictureChanged = false;
            picturePreview.replaceChildren(...savedPreview.map(node => node.cloneNode(true)));
            if (help) help.textContent = savedHelp;
            if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) return;
            const image = document.createElement('img');
            image.alt = 'Preview of your new profile picture';
            previewUrl = URL.createObjectURL(file);
            image.src = previewUrl;
            picturePreview.replaceChildren(image);
            const framing = window.GNCImageFraming;
            const frame = framing?.fileSettings(file);
            if (frame) framing.apply(image, frame);
            pictureChanged = true;
            if (help) help.textContent = 'Preview only. Select Save profile picture to keep this photo.';
        });
        pictureInput.form.addEventListener('submit', () => { navigating = true; });
        window.addEventListener('pagehide', event => {
            if (!event.persisted && previewUrl) URL.revokeObjectURL(previewUrl);
        });
    }
    const changed = form => {
        const input = form.querySelector('[role="switch"]');
        return form.dataset.savedValue !== '' && (input.checked ? '1' : '0') !== form.dataset.savedValue;
    };
    const update = form => {
        const input = form.querySelector('[role="switch"]');
        const dirty = changed(form);
        const busy = form.dataset.saving === '1';
        const known = form.dataset.savedValue !== '';
        const status = form.querySelector('[data-settings-status]');
        form.querySelector('[data-settings-value]').textContent = known ? (input.checked ? 'Enabled' : 'Disabled') : 'Unavailable';
        const button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = !known || busy || (!dirty && form.dataset.initialError !== '1');
        status.textContent = busy ? 'Saving changes…' : dirty ? 'Unsaved changes' : form.dataset.autoSave === '1' ? 'Changes save automatically.' : 'No unsaved changes';
        status.classList.toggle('settings-dirty', dirty);
        const notice = form.querySelector('[data-ai-notice]');
        if (notice) notice.hidden = input.checked;
    };
    forms.forEach(form => {
        const input = form.querySelector('[role="switch"]');
        const button = form.querySelector('button[type="submit"]');
        const feedback = form.querySelector('[data-settings-feedback]');
        update(form);
        input.addEventListener('change', () => {
            feedback.hidden = true;
            delete form.dataset.initialError;
            update(form);
            if (form.dataset.autoSave === '1') form.requestSubmit();
        });
        form.addEventListener('submit', async event => {
            if (form.dataset.saving === '1') { event.preventDefault(); return; }
            if (!changed(form) && form.dataset.initialError !== '1') {event.preventDefault();return;}
            if (form.dataset.directSave !== '1') {
                navigating = true;
                form.dataset.saving = '1';
                button.disabled = true;
                button.textContent = 'Submitting…';
                return; // Preserve the existing approval redirect for Content Editors.
            }
            event.preventDefault();
            const body = new URLSearchParams(new FormData(form));
            form.dataset.saving = '1';
            form.setAttribute('aria-busy', 'true');
            feedback.hidden = true;
            input.disabled = true;
            if (button) button.textContent = 'Saving…';
            update(form);
            try {
                const response = await fetch(form.getAttribute('action'), {
                    method: 'POST', body, credentials: 'same-origin',
                    headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                    signal: AbortSignal.timeout(30000)
                });
                if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Your session may have expired. Sign in again, or reload to check the saved setting.');
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.error || 'Could not save this setting. Please try again.');
                if (!['0', '1'].includes(result.value)) throw new Error('The saved value could not be confirmed. Reload to check this setting.');
                input.checked = result.value === '1';
                form.dataset.savedValue = result.value;
                delete form.dataset.initialError;
                feedback.textContent = result.message || 'Changes saved.';
                feedback.classList.remove('settings-feedback-error');
                feedback.classList.add('settings-feedback-success');
                feedback.setAttribute('role', 'status');
            } catch (error) {
                form.dataset.initialError = '1';
                if (form.dataset.autoSave === '1') {
                    input.checked = form.dataset.savedValue === '1';
                    delete form.dataset.initialError;
                }
                feedback.textContent = (error.name === 'TimeoutError' || error.name === 'TypeError')
                    ? (form.dataset.autoSave === '1' ? 'The save could not be confirmed. Reload to check the current setting before trying again.' : 'The save could not be confirmed. Your selection is still here. Retry or reload to check the current setting.')
                    : error.message;
                feedback.classList.remove('settings-feedback-success');
                feedback.classList.add('settings-feedback-error');
                feedback.setAttribute('role', 'alert');
            } finally {
                delete form.dataset.saving;
                input.disabled = false;
                form.removeAttribute('aria-busy');
                if (button) button.textContent = button.dataset.saveLabel;
                feedback.hidden = false;
                update(form);
            }
        });
    });
    window.addEventListener('beforeunload', event => {
        if (!navigating && (pictureChanged || forms.some(form => changed(form) || form.dataset.saving === '1'))) {
            event.preventDefault();event.returnValue = '';
        }
    });
    window.addEventListener('pageshow', () => {
        navigating = false;
        forms.forEach(form => {
            delete form.dataset.saving;
            form.removeAttribute('aria-busy');
            const button = form.querySelector('button[type="submit"]');
            if (button) button.textContent = button.dataset.saveLabel;
            update(form);
        });
    });
})();
