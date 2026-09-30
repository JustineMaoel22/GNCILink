<?php
/**
 * GNC Admin Panel – Program Curriculum PDF manager
 * Rendered inside the Content Editor's "Programs" page panel
 * (see content-edit.php). One row per program, grouped by department.
 *
 * Expects from the including page: $csrfToken, and admin-functions.php loaded.
 * Saves through page-content-handler.php → save_program_pdf.
 */
$programs = getPrograms();

// Group by department so the list reads like the College Departments page.
$byDepartment = [];
foreach ($programs as $p) {
    $byDepartment[$p['dept_name'] ?? 'Other'][] = $p;
}
?>

<div class="data-card mb-3" style="padding:1rem 1.25rem">
    <strong><i class="bi bi-file-earmark-pdf me-1"></i> Program curriculum PDFs</strong>
    <p class="small text-muted mb-0 mt-1">
        Each program has one curriculum PDF, stored once. It appears in the <em>Program Curriculum</em> section of
        that program's page, where visitors can read it in the page's PDF viewer. Changes are live as soon as you save.
    </p>
</div>

<?php if (empty($programs)): ?>
    <div class="data-card mb-3">
        <div class="empty-state">
            <i class="bi bi-mortarboard"></i>
            <p>No programs found. Run <code>sql/program-pdf-migration.sql</code> to add them, then refresh.</p>
        </div>
    </div>
<?php else: foreach ($byDepartment as $deptName => $deptPrograms): ?>
    <div class="data-card mb-3">
        <div class="data-card-header">
            <span class="data-card-title"><i class="bi bi-building me-1"></i> <?= htmlspecialchars($deptName) ?></span>
        </div>

        <div class="prog-list">
        <?php foreach ($deptPrograms as $p): ?>
            <div class="prog-row"
                 data-program-id="<?= (int)$p['program_id'] ?>"
                 data-current="<?= htmlspecialchars(json_encode(programAdminPayload($p)), ENT_QUOTES, 'UTF-8') ?>">

                <div class="prog-name">
                    <div class="prog-title"><?= htmlspecialchars($p['program_name']) ?></div>
                    <span class="prog-code"><?= htmlspecialchars($p['program_code']) ?></span>
                </div>

                <div class="prog-file">
                    <div class="prog-icon"><i class="bi bi-file-earmark-pdf"></i></div>
                    <div class="prog-file-info">
                        <div class="small text-truncate prog-file-name">No PDF</div>
                        <div class="small text-muted prog-file-hint">PDF only · max 20 MB</div>
                    </div>
                </div>

                <div class="prog-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary prog-pick">Upload PDF</button>
                    <a class="btn btn-sm btn-outline-secondary prog-view d-none" href="#" target="_blank" rel="noopener">View</a>
                    <button type="button" class="btn btn-sm btn-outline-danger prog-remove">Remove</button>
                    <input type="file" class="prog-input" accept="application/pdf,.pdf" hidden>
                    <span class="prog-save-group d-none">
                        <button type="button" class="btn btn-sm btn-outline-secondary prog-reset">Discard</button>
                        <button type="button" class="btn-gnc-primary btn-sm prog-save">Save</button>
                    </span>
                </div>

                <div class="prog-alert alert d-none mb-0"></div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; endif; ?>

<style>
.prog-list { padding: 0 1.25rem .5rem; }
.prog-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .6rem 1.25rem;
    padding: .85rem 0;
    border-top: 1px solid rgba(0, 0, 0, .08);
}
.prog-row:first-child { border-top: 0; }
.prog-name { flex: 1 1 260px; min-width: 0; }
.prog-title { font-weight: 600; }
.prog-code {
    display: inline-block;
    margin-top: .15rem;
    padding: .05rem .5rem;
    border-radius: 999px;
    background: #eef3ee;
    color: var(--gnc-green, #1F5E2C);
    font-size: .72rem;
    font-weight: 600;
    letter-spacing: .03em;
}
.prog-file { flex: 1 1 220px; min-width: 0; display: flex; align-items: center; gap: .75rem; }
.prog-file-info { min-width: 0; }
.prog-icon {
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    border-radius: 10px;
    background: #f1f1f1;
    color: #aaa;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}
.prog-icon.has-file { color: #c0392b; background: #fdecea; }
.prog-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; }
.prog-save-group { display: inline-flex; gap: .4rem; margin-left: .5rem; }
.prog-alert { flex: 1 1 100%; }
</style>

<script>
(function () {
    var AJAX_URL = '/admin/action/page-content-handler.php';
    var csrf = <?= json_encode($csrfToken ?? '') ?>;
    var PDF_MAX = 20 * 1024 * 1024;

    function fmtSize(bytes) {
        if (!bytes) return '';
        return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    document.querySelectorAll('.prog-row').forEach(function (row) {
        var current = JSON.parse(row.dataset.current);
        var pending = { file: null, remove: false };

        var $ = function (sel) { return row.querySelector(sel); };
        var icon = $('.prog-icon'), nameEl = $('.prog-file-name'), hintEl = $('.prog-file-hint');
        var pick = $('.prog-pick'), view = $('.prog-view'), rm = $('.prog-remove'), input = $('.prog-input');
        var saveGroup = $('.prog-save-group'), saveBtn = $('.prog-save'), resetBtn = $('.prog-reset');
        var alertEl = $('.prog-alert');

        function showAlert(msg, isError) {
            alertEl.className = 'prog-alert alert mb-0 ' + (isError ? 'alert-danger' : 'alert-success');
            alertEl.textContent = msg;
            if (!isError) setTimeout(function () { alertEl.classList.add('d-none'); }, 4000);
        }
        function clearAlert() { alertEl.classList.add('d-none'); }

        function render() {
            var has = false, name = 'No PDF', hint = 'PDF only · max 20 MB', href = null;
            if (pending.file) {
                has = true; name = pending.file.name;
                hint = 'New PDF (' + fmtSize(pending.file.size) + ') — not saved yet';
            } else if (pending.remove) {
                hint = 'Will be removed when you save';
            } else if (current.pdf_url) {
                has = true; name = current.pdf_name || 'Current PDF';
                hint = 'Current PDF' + (current.pdf_size ? ' · ' + fmtSize(current.pdf_size) : '');
                href = current.pdf_url;
            }
            nameEl.textContent = name; nameEl.title = name; hintEl.textContent = hint;
            icon.classList.toggle('has-file', has);
            pick.textContent = has ? 'Replace PDF' : 'Upload PDF';
            rm.disabled = !has;
            if (href) { view.href = href; view.classList.remove('d-none'); }
            else      { view.classList.add('d-none'); view.removeAttribute('href'); }

            var dirty = !!(pending.file || pending.remove);
            saveGroup.classList.toggle('d-none', !dirty);
        }

        pick.addEventListener('click', function () { input.click(); });

        input.addEventListener('change', function () {
            var f = input.files[0]; input.value = '';
            if (!f) return;
            var isPdf = f.type === 'application/pdf' || /\.pdf$/i.test(f.name);
            if (!isPdf) { showAlert('Invalid file. Only PDF files (.pdf) can be uploaded here.', true); return; }
            if (f.size > PDF_MAX) { showAlert('The PDF is too large. Maximum size is 20 MB.', true); return; }
            clearAlert(); pending = { file: f, remove: false }; render();
        });

        rm.addEventListener('click', function () {
            clearAlert(); pending = { file: null, remove: !!current.pdf_url }; render();
        });

        resetBtn.addEventListener('click', function () {
            pending = { file: null, remove: false }; clearAlert(); render();
        });

        saveBtn.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('action', 'save_program_pdf');
            fd.append('csrf_token', csrf);
            fd.append('program_id', current.program_id);
            if (pending.file)   fd.append('pdf', pending.file);
            if (pending.remove) fd.append('remove_pdf', '1');

            var wasRemoval = !!(pending.remove && !pending.file);
            saveBtn.disabled = true; resetBtn.disabled = true;
            var label = saveBtn.textContent; saveBtn.textContent = 'Saving…';

            fetch(AJAX_URL, { method: 'POST', body: fd })
                .then(function (res) { return res.json().catch(function () { return { success: false, error: 'Unexpected response from the server.' }; }); })
                .then(function (data) {
                    if (data.success) {
                        current = data.program;
                        pending = { file: null, remove: false };
                        render();
                        showAlert(wasRemoval ? 'Saved. The curriculum PDF was removed from this program\'s page.' : 'Saved. The curriculum PDF is now live on this program\'s page.', false);
                    } else {
                        showAlert(data.error || 'Could not save.', true);
                        render(); // keep the pending change so nothing is lost
                    }
                })
                .catch(function () { showAlert('Network error. Please try again.', true); render(); })
                .finally(function () { saveBtn.disabled = false; resetBtn.disabled = false; saveBtn.textContent = label; });
        });

        render();
    });
})();
</script>