(function () {
    'use strict';
    const config = JSON.parse(document.getElementById('enrollment-steps-config').textContent);
    const form = document.getElementById('enrollment-steps-form');
    const list = document.getElementById('enrollment-step-list');
    const publish = document.getElementById('enrollment-publish');
    const add = document.getElementById('enrollment-add-step');
    const status = document.getElementById('enrollment-save-status');
    let dirty = false, saving = false;
    function changed() { dirty = true; publish.disabled = false; status.textContent = 'Unpublished changes. Publish steps to show them on the website.'; }
    function renumber() {
        const rows = Array.from(list.children);
        rows.forEach((row, index) => {
            row.querySelector('[data-number]').textContent = index + 1;
            row.querySelector('[data-up]').disabled = index === 0;
            row.querySelector('[data-down]').disabled = index === rows.length - 1;
        });
        document.getElementById('enrollment-empty').hidden = rows.length !== 0;
        add.disabled = rows.length >= 40;
    }
    function addStep(step) {
        const row = document.getElementById('enrollment-step-template').content.firstElementChild.cloneNode(true);
        row.dataset.id = step.step_id || Array.from(crypto.getRandomValues(new Uint8Array(16)), b => b.toString(16).padStart(2, '0')).join('');
        ['title', 'location', 'instruction'].forEach(key => { row.querySelector('[data-' + key + ']').value = step[key] || ''; });
        const select = row.querySelector('[data-illustration]'), upload = row.querySelector('[data-upload]'), preview = row.querySelector('[data-preview]');
        select.add(new Option('No illustration', ''));
        Object.entries(config.illustrations).forEach(([path, label]) => select.add(new Option(label, path)));
        if (step.illustration && !config.illustrations[step.illustration]) select.add(new Option('Current uploaded illustration', step.illustration));
        select.value = step.illustration || '';
        let objectUrl = '';
        function image() {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = upload.files[0] ? URL.createObjectURL(upload.files[0]) : '';
            preview.hidden = !(objectUrl || select.value);
            if (!preview.hidden) preview.src = objectUrl || select.value; else preview.removeAttribute('src');
        }
        select.addEventListener('change', () => { upload.value = ''; image(); });
        upload.addEventListener('change', image);
        row.addEventListener('input', changed); row.addEventListener('change', changed);
        row.querySelector('[data-up]').addEventListener('click', () => { if (row.previousElementSibling) list.insertBefore(row, row.previousElementSibling); renumber(); changed(); });
        row.querySelector('[data-down]').addEventListener('click', () => { if (row.nextElementSibling) list.insertBefore(row.nextElementSibling, row); renumber(); changed(); });
        row.querySelector('[data-remove]').addEventListener('click', async () => {
            if (!await AdminDialogs.confirm('Remove this enrollment step? It will be removed from the website when you publish.', {confirmText:'Remove step', danger:true})) return;
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            row.remove(); renumber(); changed();
        });
        list.appendChild(row); image(); renumber();
        return row;
    }
    config.steps.forEach(addStep); renumber();
    add.addEventListener('click', () => { if (list.children.length >= 40) return; const row = addStep({}); changed(); row.querySelector('[data-title]').focus(); });
    window.GNCEnrollmentStepsEditor = {hasChanges: () => dirty, discard: () => { dirty = false; }};
    const editorUrl = new URL(location.href);
    if (editorUrl.searchParams.get('add') === '1') {
        editorUrl.searchParams.delete('add');
        history.replaceState(null, '', editorUrl.pathname + editorUrl.search + editorUrl.hash);
        add.click();
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving || !dirty || !form.reportValidity()) return;
        if (!await AdminDialogs.confirm('Publish Basic Education enrollment steps? These changes will be visible on the website.', {confirmText:'Publish steps'})) return;
        const body = new FormData(), steps = [];
        for (const row of list.children) {
            const step = {step_id:row.dataset.id, illustration:row.querySelector('[data-illustration]').value};
            ['title','location','instruction'].forEach(key => { step[key] = row.querySelector('[data-' + key + ']').value; });
            steps.push(step);
            const file = row.querySelector('[data-upload]').files[0];
            if (file) body.append('illustration_' + step.step_id, file);
        }
        body.append('steps', JSON.stringify(steps)); body.append('revision', config.revision); body.append('csrf_token', config.csrf);
        saving = true; publish.disabled = true;
        // Prevent edits while a published snapshot is in flight.
        Array.from(form.elements).forEach(control => { control.disabled = true; });
        status.textContent = 'Publishing steps...';
        try {
            const response = await fetch('/admin/enrollment-steps.php', {method:'POST', body});
            const result = await response.json();
            if (!response.ok || !result.success) throw Error(result.error || 'Could not publish the steps.');
            dirty = false; location.reload();
        } catch (error) {
            Array.from(form.elements).forEach(control => { control.disabled = false; });
            renumber(); saving = false; publish.disabled = !dirty;
            status.textContent = 'Changes were not published. ' + error.message;
            AdminDialogs.alert(error.message);
        }
    });
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
})();
