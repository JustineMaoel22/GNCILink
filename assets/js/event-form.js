(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('eventModal');
        const form = document.getElementById('eventForm');
        if (!modal || !form) return;
        const el = id => document.getElementById(id);
        const start = el('eventStartDate'), end = el('eventEndDate');
        const startTime = el('eventStartTime'), endTime = el('eventEndTime');
        const allDay = el('eventAllDay'), multiDay = el('eventMultiDay');
        const status = el('eventStatus'), save = el('eventSubmitBtn');
        const alert = el('eventFormAlert');
        let baseline = '', allowClose = false, confirming = false, opened = false;
        const statusLabels = {draft: 'Save draft', pending: 'Submit for approval', published: 'Publish event', archived: 'Save hidden event'};
        const intent = {draft: 'Only saved in the admin panel.', pending: 'Not live until approved.', published: 'Visible on the public website.', archived: 'Hidden from the public website.'};

        function saveLabel() {
            if (!save.disabled) save.textContent = statusLabels[status.value] || 'Save Event';
            el('eventSaveIntent').textContent = intent[status.value] || '';
            form.setAttribute('aria-busy', String(save.disabled));
        }
        status.addEventListener('change', saveLabel);
        new MutationObserver(saveLabel).observe(save, {attributes: true, attributeFilter: ['disabled']});

        function snapshot() {
            const values = Array.from(form.querySelectorAll('input,select,textarea'))
                .filter(field => field.id !== 'csrfToken' && !field.classList.contains('gnc-ms-search-input'))
                .map(field => [field.name || field.id, field.type === 'checkbox' ? field.checked : field.value]);
            return JSON.stringify(values);
        }
        function dirty() { return opened && baseline !== snapshot(); }

        function schedule() {
            el('eventEndDateField').classList.toggle('d-none', !multiDay.checked);
            end.disabled = !multiDay.checked;
            end.required = multiDay.checked;
            end.min = start.value;
            startTime.required = !allDay.checked;
            startTime.disabled = endTime.disabled = allDay.checked;
            el('eventTimeRow').classList.toggle('d-none', allDay.checked);
            endTime.min = !multiDay.checked || end.value === start.value ? startTime.value : '';
            const dateLabel = value => new Date(value + 'T00:00:00').toLocaleDateString(undefined, {month: 'short', day: 'numeric', year: 'numeric'});
            let summary = start.value ? dateLabel(start.value) : 'Choose a start date';
            if (multiDay.checked) summary += end.value ? ' – ' + dateLabel(end.value) : ' · Choose an end date';
            summary += allDay.checked ? ' · All day' : ' · ' + (startTime.value || 'Choose a start time') + (endTime.value ? ' – ' + endTime.value : '');
            el('eventScheduleSummary').textContent = summary;
        }

        const fields = [el('eventTitle'), el('eventLocation'), el('eventDescription'), start, end, startTime, endTime].filter(Boolean);
        fields.forEach(field => {
            const error = document.createElement('div');
            error.className = 'invalid-feedback'; error.id = field.id + 'Error';
            error.setAttribute('aria-live', 'polite');
            field.after(error);
            field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
        });
        function validate(showErrors) {
            fields.forEach(field => field.setCustomValidity(''));
            if (!el('eventTitle').value.trim()) el('eventTitle').setCustomValidity('Enter an event name.');
            if (multiDay.checked && end.value && start.value && end.value <= start.value) end.setCustomValidity('Choose a date after the start date, or switch off Multi-day event.');
            if (!allDay.checked && endTime.value && startTime.value && (!multiDay.checked || end.value === start.value) && endTime.value < startTime.value) endTime.setCustomValidity('End time must be on or after the start time.');
            let first = null;
            fields.forEach(field => {
                const invalid = !field.disabled && !field.validity.valid;
                if (invalid && !first) first = field;
                if (showErrors || field.classList.contains('is-invalid')) {
                    field.classList.toggle('is-invalid', invalid);
                    field.setAttribute('aria-invalid', String(invalid));
                    el(field.id + 'Error').textContent = invalid ? (field.validity.valueMissing ? 'Please fill in this field.' : field.validationMessage) : '';
                }
            });
            return first;
        }
        form.addEventListener('input', function () { schedule(); validate(false); });
        form.addEventListener('change', function () { schedule(); validate(false); });

        // Validate before the shared publishing confirmation or the AJAX handler.
        window.addEventListener('submit', function (event) {
            if (event.target !== form) return;
            if (save.disabled) { event.preventDefault(); event.stopImmediatePropagation(); return; }
            schedule();
            const first = validate(true);
            if (first) {
                event.preventDefault(); event.stopImmediatePropagation();
                alert.textContent = 'Please check the highlighted fields. Your event has not been saved.';
                alert.classList.remove('d-none');
                first.focus(); first.scrollIntoView({block: 'center', behavior: 'instant'});
                return;
            }
            alert.classList.add('d-none');
        }, true);
        form.addEventListener('formdata', function (event) {
            // A timed single-day event needs an end date for the existing storage format.
            if (!multiDay.checked && !allDay.checked && endTime.value) event.formData.set('end_date', start.value);
        });

        function setupAudiences() {
            window.GncAdminFormAudience.enhance([
                ['event-dept-multiselect', 'eventDeptLabel', 'Search departments…'],
                ['event-program-multiselect', 'eventProgramLabel', 'Search academic programs…']
            ]);
        }
        modal.addEventListener('show.bs.modal', function () {
            allowClose = false;
            multiDay.checked = !!end.value && end.value !== start.value;
            fields.forEach(field => { field.setCustomValidity(''); field.classList.remove('is-invalid'); field.removeAttribute('aria-invalid'); el(field.id + 'Error').textContent = ''; });
            schedule(); setupAudiences(); saveLabel();
            baseline = snapshot(); opened = true;
        });
        modal.addEventListener('hide.bs.modal', async function (event) {
            if (allowClose) return;
            if (save.disabled) { event.preventDefault(); return; }
            if (!dirty()) return;
            event.preventDefault();
            if (confirming) return;
            confirming = true;
            try {
                const discard = window.AdminDialogs ? await window.AdminDialogs.confirm('Discard your unsaved event changes?', {title: 'Discard changes?', confirmText: 'Discard changes', cancelText: 'Keep editing', danger: true}) : window.confirm('Discard your unsaved event changes?');
                if (discard) { allowClose = true; bootstrap.Modal.getInstance(modal).hide(); }
            } finally { confirming = false; }
        });
        modal.addEventListener('hidden.bs.modal', function () {
            opened = false;
            ['event-dept-multiselect', 'event-program-multiselect'].forEach(id => el(id)._gncMsInstance?.closeDropdown());
        });
        form.addEventListener('event-form:saved', function () { opened = false; });
        window.addEventListener('beforeunload', function (event) {
            if (dirty()) { event.preventDefault(); event.returnValue = ''; }
        });
        // Bring server/network errors into view even when the form is scrolled.
        new MutationObserver(function () {
            if (!alert.classList.contains('d-none') && !alert.textContent.startsWith('Please check the highlighted fields.')) { alert.scrollIntoView({block: 'nearest'}); alert.focus(); }
        }).observe(alert, {attributes: true, attributeFilter: ['class']});
    });
})();
