<?php
/**
 * GNC Admin Panel – College Programs manager (full CRUD).
 * Rendered inside the Content Editor's "Programs" page panel (content-edit.php).
 * Replaces program-pdf-manager.php (the PDF upload is kept here per program).
 *
 * Expects: $csrfToken, admin-functions.php loaded (including the additions
 * with saveProgramFull / deleteProgramFull / programFullAdminPayload).
 *
 * Saves through admin/action/page-content-handler.php:
 *   save_program    – create (program_id=0) or update code/dept/description/image
 *   delete_program  – permanently remove (image + PDF cleaned up if unreferenced)
 *   save_program_pdf – existing curriculum PDF action, unchanged
 */

// publicProgramPageUrl() lives in department-public.php (public-side helpers),
// which the admin pages don't load on their own. Without this the Programs
// panel dies with "Call to undefined function" and cuts the whole page off.
require_once __DIR__ . '/department-public.php';

// NOTE: requires the columns from sql/college-department-page-migration.sql
// (programs.description, programs.image_media_id). Until that migration is
// run this panel shows a setup notice instead of crashing.
$programsMigrationOk = true;
try {
    getDB()->query("SELECT description, image_media_id FROM programs LIMIT 0");
} catch (Exception $e) {
    $programsMigrationOk = false;
}

$allDepartments = getDepartments();
$allPrograms    = [];
if ($programsMigrationOk) {
    foreach (getDB()->query(PROGRAM_FULL_SELECT_SQL . " ORDER BY p.dept_id IS NULL, p.dept_id ASC, p.program_id ASC")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $allPrograms[] = hydrateProgramFull($r);
    }
}
$programCategoryOptions = getProgramCategories();
?>

<?php if (!$programsMigrationOk): ?>
    <div class="data-card mb-3">
        <div class="alert alert-warning m-3 mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Program images and descriptions need a one-time database update.
            Ask your developer to run <code>sql/college-department-page-migration.sql</code>,
            then reload this page.
        </div>
    </div>
<?php else: ?>

<div class="data-card mb-3" style="padding:1rem 1.25rem">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <strong><i class="bi bi-mortarboard me-1"></i> College Programs</strong>
            <p class="small text-muted mb-0 mt-1">
                Programs appear automatically in the "Programs Offered" section of their department's page
                (<code>college-department.php?slug=…</code>). Assign each program to its department,
                write the card description, and upload a card image. The curriculum PDF is optional and
                shows on the program's own page.
            </p>
        </div>
        <button type="button" class="btn-gnc-gold btn-sm" id="btn-add-program">
            <i class="bi bi-plus-lg"></i> Add Program
        </button>
    </div>
</div>

<!-- ── Add form ── -->
<div class="data-card mb-3" id="program-create-card" style="display:none">
    <div class="data-card-header">
        <span class="data-card-title"><i class="bi bi-journal-plus me-1"></i> New Program</span>
    </div>
    <div style="padding:0 1.25rem 1.25rem">
        <div class="row g-2">
            <div class="col-lg-4">
                <label class="small text-muted mb-1">Program *</label>
                <select class="form-select form-select-sm" id="create-program-code">
                    <?php
                    $usedCodes = array_column($allPrograms, 'program_code');
                    foreach ($programCategoryOptions as $code => $label):
                        if (in_array($code, $usedCodes, true)) continue; // already added
                    ?>
                        <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-4">
                <label class="small text-muted mb-1">Department</label>
                <select class="form-select form-select-sm" id="create-program-dept">
                    <option value="0">— Unassigned —</option>
                    <?php foreach ($allDepartments as $d): ?>
                        <option value="<?= (int)$d['dept_id'] ?>"><?= htmlspecialchars($d['dept_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-4">
                <label class="small text-muted mb-1">Card image <span class="text-muted fw-normal">(JPG, PNG, WEBP · max 5 MB)</span></label>
                <input type="file" class="form-control form-control-sm" id="create-program-image"
                       accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="col-12">
                <label class="small text-muted mb-1">Card description <span class="text-muted fw-normal">(shown on the department page)</span></label>
                <textarea class="form-control form-control-sm" id="create-program-description" rows="2"
                          maxlength="5000" placeholder="A short summary shown on the program card..."></textarea>
            </div>
        </div>
        <div class="alert d-none mt-3 mb-0" id="program-create-alert"></div>
        <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-program-create-cancel">Cancel</button>
            <button type="button" class="btn-gnc-primary btn-sm" id="btn-program-create-save">Add Program</button>
        </div>
    </div>
</div>

<?php if (empty($allPrograms)): ?>
    <div class="data-card mb-3">
        <div class="empty-state">
            <i class="bi bi-mortarboard"></i>
            <p>No programs yet. Click "Add Program" above to create the first one.</p>
        </div>
    </div>
<?php else: foreach ($allPrograms as $p):
    $payload = programFullAdminPayload($p);
?>
    <div class="data-card mb-3 program-media-card"
         data-program-id="<?= (int)$p['program_id'] ?>"
         data-current="<?= htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8') ?>">
        <div class="data-card-header">
            <span class="data-card-title"><i class="bi bi-book me-1"></i> <span class="program-title-text"><?= htmlspecialchars($p['program_name']) ?></span></span>
            <span class="badge-status ms-auto <?= !empty($p['dept_id']) ? 'published' : 'draft' ?>">
                <?= !empty($p['dept_id']) ? 'Assigned to ' . htmlspecialchars((string)$p['dept_name']) : 'Unassigned' ?>
            </span>
        </div>
        <div style="padding:0 1.25rem 1.25rem">
            <div class="row g-3">

                <!-- Card image -->
                <div class="col-md-3">
                    <div class="program-media-field h-100">
                        <div class="program-media-label"><i class="bi bi-image"></i> Card Image</div>
                        <div class="program-img-preview">
                            <img class="program-img" alt="" hidden>
                            <i class="bi bi-image program-img-placeholder"></i>
                        </div>
                        <div class="small text-truncate program-img-name mt-2">No image</div>
                        <div class="d-flex gap-2 mt-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-pick-program-img">Upload</button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-program-img">Remove</button>
                            <input type="file" class="program-img-input" accept="image/jpeg,image/png,image/webp" hidden>
                        </div>
                    </div>
                </div>

                <!-- Fields -->
                <div class="col-md-9">
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label class="small text-muted mb-1">Department</label>
                            <select class="form-select form-select-sm program-dept-input">
                                <option value="0">— Unassigned —</option>
                                <?php foreach ($allDepartments as $d): ?>
                                    <option value="<?= (int)$d['dept_id'] ?>"><?= htmlspecialchars($d['dept_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="small text-muted mb-1">Page address</label>
                            <div class="form-text pt-1">
                                <code><?= htmlspecialchars(publicProgramPageUrl($p)) ?></code>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="small text-muted mb-1">Card description <span class="text-muted fw-normal">(shown on the department page)</span></label>
                            <textarea class="form-control form-control-sm program-description-input" rows="3"
                                      maxlength="5000" placeholder="A short summary shown on the program card..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="program-alert alert d-none mt-3 mb-0"></div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <button type="button" class="btn btn-outline-danger btn-sm btn-program-delete">
                    <i class="bi bi-trash"></i> Delete Program
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm btn-program-reset" disabled>Discard Changes</button>
                    <button data-content-publish type="button" class="btn-gnc-primary btn-sm btn-program-save" disabled>Save Changes</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; endif; ?>

<style>
.program-media-field {
    border: 1px solid rgba(0, 0, 0, .1);
    border-radius: 10px;
    padding: .85rem;
    height: 100%;
}
.program-media-label {
    font-size: .8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--gnc-green, #1F5E2C);
    margin-bottom: .6rem;
}
.program-img-preview {
    width: 100%;
    aspect-ratio: 16 / 10;
    border-radius: 8px;
    background: #eceae4;
    color: #aaa;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    font-size: 1.8rem;
}
.program-img-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>

<script>
(function () {
    var AJAX_URL = '/admin/action/page-content-handler.php';
    var csrf = <?= json_encode($csrfToken ?? '') ?>;
    var IMG_MAX = 5 * 1024 * 1024;
    var IMG_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    function apiPost(fields) {
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
        return fetch(AJAX_URL, { method: 'POST', body: fd }).then(function (res) {
            return res.json().catch(function () { return { success: false, error: 'Unexpected response from the server.' }; });
        });
    }
    function apiPostMultipart(fd) {
        return fetch(AJAX_URL, { method: 'POST', body: fd }).then(function (res) {
            return res.json().catch(function () { return { success: false, error: 'Unexpected response from the server.' }; });
        });
    }
    function showAlert(el, msg, isError) {
        el.className = 'alert mt-3 mb-0 ' + (isError ? 'alert-danger' : 'alert-success');
        el.textContent = msg;
        if (!isError) setTimeout(function () { el.classList.add('d-none'); }, 5000);
    }
    function hideAlert(el) { el.classList.add('d-none'); }

    /* ── Add form ── */
    var createCard  = document.getElementById('program-create-card');
    var createAlert = document.getElementById('program-create-alert');
    var addBtn      = document.getElementById('btn-add-program');

    addBtn.addEventListener('click', function () {
        createCard.style.display = '';
        addBtn.disabled = true;
    });
    document.getElementById('btn-program-create-cancel').addEventListener('click', function () {
        createCard.style.display = 'none';
        addBtn.disabled = false;
        hideAlert(createAlert);
    });
    document.getElementById('btn-program-create-save').addEventListener('click', function () {
        var fd = new FormData();
        fd.append('action', 'save_program');
        fd.append('csrf_token', csrf);
        fd.append('program_id', '0');
        fd.append('program_code', document.getElementById('create-program-code').value);
        fd.append('dept_id', document.getElementById('create-program-dept').value);
        fd.append('description', document.getElementById('create-program-description').value);
        var img = document.getElementById('create-program-image').files[0];
        if (img) {
            if (IMG_TYPES.indexOf(img.type) === -1 || img.size > IMG_MAX) {
                showAlert(createAlert, 'Invalid image. Use JPG, PNG or WEBP, max 5 MB.', true);
                return;
            }
            fd.append('image', img);
        }

        var btn = this, original = btn.textContent;
        btn.disabled = true; btn.textContent = 'Adding…';

        apiPostMultipart(fd).then(function (data) {
            if (data.success) {
                window.location.reload();
            } else {
                showAlert(createAlert, data.error || 'Could not add the program.', true);
                btn.disabled = false; btn.textContent = original;
            }
        }).catch(function () {
            showAlert(createAlert, 'Network error. Please try again.', true);
            btn.disabled = false; btn.textContent = original;
        });
    });

    /* ── One card per program ── */
    document.querySelectorAll('.program-media-card').forEach(function (card) {
        var current = JSON.parse(card.dataset.current);
        var $ = function (sel) { return card.querySelector(sel); };

        var pending = { file: null, remove: false };
        var previewUrl = null;
        var imgEl = $('.program-img'), imgPh = $('.program-img-placeholder');
        var imgName = $('.program-img-name');
        var pickBtn = $('.btn-pick-program-img'), rmBtn = $('.btn-remove-program-img'), input = $('.program-img-input');
        var deptSel = $('.program-dept-input'), descTa = $('.program-description-input');
        var saveBtn = $('.btn-program-save'), resetBtn = $('.btn-program-reset'), alertEl = $('.program-alert');
        var titleText = $('.program-title-text');
        var deptBadge = card.querySelector('.badge-status');

        function revokePreview() {
            if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
        }
        function fill() {
            deptSel.value = current.dept_id ? String(current.dept_id) : '0';
            descTa.value  = current.description || '';
        }
        function dirty() {
            var textDirty = deptSel.value !== (current.dept_id ? String(current.dept_id) : '0')
                || descTa.value !== (current.description || '');
            return !!(textDirty || pending.file || pending.remove);
        }
        function render() {
            var showUrl = null, name = 'No image';
            if (pending.file) {
                revokePreview();
                previewUrl = URL.createObjectURL(pending.file);
                showUrl = previewUrl; name = pending.file.name;
            } else if (pending.remove) {
                name = 'Will be removed when you save';
            } else if (current.image_url) {
                showUrl = current.image_url; name = current.image_name || 'Current image';
            }
            if (showUrl) { imgEl.src = showUrl; imgEl.hidden = false; imgPh.hidden = true; }
            else         { imgEl.removeAttribute('src'); imgEl.hidden = true; imgPh.hidden = false; }
            imgName.textContent = name; imgName.title = name;
            pickBtn.textContent = showUrl ? 'Replace' : 'Upload';
            rmBtn.disabled = !showUrl;

            saveBtn.disabled = !dirty();
            resetBtn.disabled = !dirty();
        }

        pickBtn.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () {
            var f = input.files[0]; input.value = '';
            if (!f) return;
            if (IMG_TYPES.indexOf(f.type) === -1) { showAlert(alertEl, 'Invalid image type. Allowed: JPG, PNG, WEBP.', true); return; }
            if (f.size > IMG_MAX) { showAlert(alertEl, 'The image is too large. Maximum size is 5 MB.', true); return; }
            hideAlert(alertEl); pending = { file: f, remove: false }; render();
        });
        rmBtn.addEventListener('click', function () {
            hideAlert(alertEl); pending = { file: null, remove: !!current.image_url }; render();
        });
        deptSel.addEventListener('change', function () { hideAlert(alertEl); render(); });
        descTa.addEventListener('input', function () { hideAlert(alertEl); render(); });
        resetBtn.addEventListener('click', function () {
            pending = { file: null, remove: false }; fill(); hideAlert(alertEl); render();
        });
        saveBtn.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('action', 'save_program');
            fd.append('csrf_token', csrf);
            fd.append('program_id', current.program_id);
            fd.append('program_code', current.program_code);
            fd.append('dept_id', deptSel.value);
            fd.append('description', descTa.value);
            if (pending.file) fd.append('image', pending.file);
            if (pending.remove) fd.append('remove_image', '1');

            var btn = this, original = btn.textContent;
            btn.disabled = true; resetBtn.disabled = true; btn.textContent = 'Saving…';

            apiPostMultipart(fd).then(function (data) {
                if (data.success) {
                    current = data.program;
                    pending = { file: null, remove: false };
                    revokePreview();
                    fill(); render();
                    titleText.textContent = current.name || '';
                    deptBadge.className = 'badge-status ms-auto ' + (current.dept_id ? 'published' : 'draft');
                    deptBadge.textContent = current.dept_id ? 'Assigned to ' + (current.dept_name || '') : 'Unassigned';
                    showAlert(alertEl, 'Saved. The department page now shows this program.', false);
                } else {
                    showAlert(alertEl, data.error || 'Could not save.', true);
                    render();
                }
            }).catch(function () {
                showAlert(alertEl, 'Network error. Please try again.', true);
                render();
            }).finally(function () { btn.textContent = original; });
        });

        $('.btn-program-delete').addEventListener('click', async function () {
            if (!await window.AdminDialogs.confirm('Delete "' + (current.name || 'this program') + '" permanently?\n\n'
                + 'Its card image and curriculum PDF will be removed if nothing else uses them.')) {
                return;
            }
            var btn = this;
            btn.disabled = true;
            apiPost({ action: 'delete_program', program_id: current.program_id }).then(function (data) {
                if (data.success) {
                    card.style.transition = 'opacity .3s';
                    card.style.opacity = '0';
                    setTimeout(function () { card.remove(); }, 300);
                } else {
                    window.AdminDialogs.alert(data.error || 'Could not delete the program.');
                    btn.disabled = false;
                }
            }).catch(function () {
                window.AdminDialogs.alert('Network error. Please try again.');
                btn.disabled = false;
            });
        });

        fill(); render();
    });
})();
</script>

<?php endif; ?>