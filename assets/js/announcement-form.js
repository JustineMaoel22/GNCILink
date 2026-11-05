(function () {
    'use strict';
    window.GncAnnouncementForm = {
        init: function ({quill, kind = 'announcement'}) {
            const news = kind === 'news', noun = news ? 'news article' : 'announcement';
            const el = id => document.getElementById(id);
            const form = el(news ? 'news-form' : 'announcement-form'), modal = el(news ? 'newsModal' : 'announcementModal');
            if (!form || !modal || form.dataset.uxReady) return;
            form.dataset.uxReady = 'true';
            const title = el('modal-title'), editor = quill.root, content = el('modal-content-input');
            const status = el('modal-status'), destination = el('publish-destination');
            const save = el('modal-save-btn'), label = el('modal-save-label'), alert = el('announcement-form-alert');
            const image = el('modal-image-input'), gallery = el('modal-gallery-input'), link = el('facebook-link');
            let baseline = '', opened = false, submitting = false, allowClose = false, confirming = false;
            const fields = [title, image, gallery, link, news ? el('modal-author') : null].filter(Boolean);
            const audienceIds = news ? [] : ['modal-category-multiselect', 'modal-dept-multiselect', 'modal-program-multiselect'];
            editor.setAttribute('aria-labelledby', 'ann-message-label');
            editor.setAttribute('aria-describedby', 'ann-message-help ann-message-error');
            editor.setAttribute('aria-required', 'true');
            editor.setAttribute('role', 'textbox');
            editor.setAttribute('aria-multiline', 'true');
            fields.forEach(field => {
                const error = document.createElement('div');
                error.id = field.id + '-error'; error.className = 'invalid-feedback';
                error.setAttribute('aria-live', 'polite'); field.after(error);
                field.setAttribute('aria-describedby', error.id);
            });
            function state() { return status?.value || 'pending'; }
            function updateSave() {
                const current = state(), target = destination?.value || 'website';
                const names = {draft: 'Save draft', pending: 'Submit for approval', archived: 'Save archived ' + noun, published: target === 'facebook' ? 'Publish to Facebook' : target === 'both' ? 'Publish to both' : 'Publish ' + noun};
                if (!save.disabled) label.textContent = names[current] || 'Save ' + noun;
                const hints = {draft: 'Only saved in the admin panel.', pending: 'Not live until approved.', archived: 'Hidden from the public website.', published: target === 'facebook' ? 'Facebook only. Hidden from the website.' : target === 'both' ? 'Website and connected Facebook Page.' : 'Visible on the public website.'};
                el('ann-save-intent').textContent = hints[current] || '';
                if (status && current === 'published' && target !== 'website') el('modal-status-help').textContent = hints.published + ' You will confirm the destination before publishing.';
                form.setAttribute('aria-busy', String(save.disabled));
            }
            form.addEventListener('change', updateSave);
            new MutationObserver(updateSave).observe(save, {attributes: true, attributeFilter: ['disabled']});
            function snapshot() {
                return JSON.stringify([editor.innerHTML, Array.from(form.querySelectorAll('input[name],select[name],textarea[name]'))
                    .filter(field => !['csrf_token', 'allow_incomplete', 'facebook_confirmed', 'facebook_request_key', 'content'].includes(field.name) && !field.classList.contains('gnc-ms-search-input'))
                    .map(field => [field.name, field.type === 'file' ? Array.from(field.files || []).map(file => [file.name, file.size, file.lastModified]) : field.type === 'checkbox' ? field.checked : field.value])]);
            }
            function dirty() { return opened && snapshot() !== baseline; }
            function validate(showErrors) {
                content.value = editor.innerHTML;
                fields.forEach(field => field.setCustomValidity(''));
                const override = !!form.querySelector('[name="allow_incomplete"][value="1"]');
                if (!title.value.trim() && !override) title.setCustomValidity('Enter a headline.');
                const missingMessage = !quill.getText().trim() && !override;
                const messageError = el('ann-message-error');
                if (showErrors || editor.getAttribute('aria-invalid') === 'true') {
                    editor.setAttribute('aria-invalid', String(missingMessage));
                    el('modal-content-editor').classList.toggle('ann-message-invalid', missingMessage);
                    messageError.textContent = missingMessage ? (news ? 'Write the story for your article.' : 'Write a message for your announcement.') : '';
                }
                [image, gallery].forEach(field => {
                    const files = Array.from(field.files || []);
                    if (files.some(file => !/\.(jpe?g|png|gif|webp)$/i.test(file.name))) field.setCustomValidity('Choose JPG, PNG, GIF, or WEBP pictures.');
                    if (files.some(file => file.size > 5 * 1024 * 1024)) field.setCustomValidity('Each picture must be 5 MB or smaller.');
                });
                const savedCount = el('modal-gallery-existing').querySelectorAll('.gallery-thumb-wrap:not(.marked-remove)').length;
                if ((news ? gallery.files.length : savedCount + gallery.files.length) > 10) gallery.setCustomValidity(news ? 'Choose a maximum of 10 gallery pictures at a time.' : 'Keep a maximum of 10 gallery pictures, including existing pictures.');
                const errors = fields.filter(field => !field.disabled && !field.validity.valid);
                fields.forEach(field => {
                    if (showErrors || field.classList.contains('is-invalid')) {
                        const invalid = errors.includes(field);
                        field.classList.toggle('is-invalid', invalid); field.setAttribute('aria-invalid', String(invalid));
                        el(field.id + '-error').textContent = invalid ? field.validationMessage : '';
                    }
                });
                const first = errors.includes(title) ? title : missingMessage ? editor : errors[0];
                return {first, onlyIncomplete: errors.every(field => field === title && !title.value.trim())};
            }
            form.addEventListener('input', () => validate(false));
            quill.on('text-change', () => validate(false));
            window.addEventListener('submit', function (event) {
                if (event.target !== form) return;
                if (submitting || save.disabled) { event.preventDefault(); event.stopImmediatePropagation(); return; }
                const result = validate(true);
                // Preserve the shared "Publish anyway" decision for incomplete content.
                if (result.first && !(state() === 'published' && result.onlyIncomplete)) {
                    event.preventDefault(); event.stopImmediatePropagation();
                    alert.textContent = 'Please check the highlighted fields. Your ' + noun + ' has not been saved.';
                    alert.classList.remove('d-none'); result.first.focus(); result.first.scrollIntoView({block: 'center'});
                    return;
                }
                if (destination && destination.value !== 'website' && state() !== 'published') {
                    event.preventDefault(); event.stopImmediatePropagation();
                    alert.textContent = 'Choose Publish now to send to Facebook, or choose Website Only to save a draft or submit for approval.';
                    alert.classList.remove('d-none'); destination.focus(); destination.scrollIntoView({block: 'center'}); return;
                }
                alert.classList.add('d-none');
            }, true);
            form.addEventListener(kind + '-form:submitting', function () { submitting = true; });
            modal.addEventListener('show.bs.modal', function () {
                allowClose = false; submitting = false;
                fields.forEach(field => { field.setCustomValidity(''); field.classList.remove('is-invalid'); field.removeAttribute('aria-invalid'); el(field.id + '-error').textContent = ''; });
                editor.removeAttribute('aria-invalid'); el('modal-content-editor').classList.remove('ann-message-invalid'); el('ann-message-error').textContent = '';
                alert.classList.add('d-none');
                if (!news) window.GncAdminFormAudience.enhance([
                    ['modal-category-multiselect', 'ann-category-label', 'Search categories…'],
                    ['modal-dept-multiselect', 'ann-dept-label', 'Search departments…'],
                    ['modal-program-multiselect', 'ann-program-label', 'Search academic programs…']
                ]);
                updateSave(); baseline = snapshot(); opened = true;
            });
            modal.addEventListener('shown.bs.modal', function () { baseline = snapshot(); updateSave(); });
            modal.addEventListener('hide.bs.modal', async function (event) {
                if (allowClose) return;
                if (submitting || save.disabled) { event.preventDefault(); return; }
                if (!dirty()) return;
                event.preventDefault(); if (confirming) return;
                confirming = true;
                try {
                    const message = 'Discard your unsaved ' + noun + ' changes?';
                    const discard = window.AdminDialogs ? await window.AdminDialogs.confirm(message, {title: 'Discard changes?', confirmText: 'Discard changes', cancelText: 'Keep editing', danger: true}) : window.confirm(message);
                    if (discard) { allowClose = true; bootstrap.Modal.getInstance(modal).hide(); }
                } finally { confirming = false; }
            });
            modal.addEventListener('hidden.bs.modal', function () { opened = false; audienceIds.forEach(id => el(id)._gncMsInstance?.closeDropdown()); });
            window.addEventListener('beforeunload', function (event) { if (dirty() && !submitting) { event.preventDefault(); event.returnValue = ''; } });
            window.addEventListener('pageshow', function (event) { if (event.persisted) { submitting = false; save.disabled = false; updateSave(); } });
        }
    };
})();
