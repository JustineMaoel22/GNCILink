<?php
/**
 * GNC Admin Panel – Department Logo & Head of Office manager
 * Rendered inside the Content Editor's "College Departments" page panel
 * (see content-edit.php). One card per row of the `departments` table.
 *
 * Expects from the including page: $csrfToken, and admin-functions.php loaded.
 * Saves through page-content-handler.php → save_department_media.
 */
$departments = getDepartments();
?>

<div class="dept-media-intro data-card mb-3" style="padding:1rem 1.25rem">
    <strong><i class="bi bi-diagram-3 me-1"></i> Department logos &amp; Head of Office</strong>
    <p class="small text-muted mb-0 mt-1">
        Each department has one logo, stored once. Changes are live as soon as you save and
        appear everywhere the department is shown: the College Departments page, the department's own page,
        and the Programs page.
    </p>
</div>

<?php if (empty($departments)): ?>
    <div class="data-card mb-3">
        <div class="empty-state">
            <i class="bi bi-building"></i>
            <p>No departments found. Run <code>sql/department-media-migration.sql</code> to add them, then refresh.</p>
        </div>
    </div>
<?php else: foreach ($departments as $d):
    $payload = departmentAdminPayload($d);
?>
    <div class="data-card mb-3 dept-media-card"
         data-dept-id="<?= (int)$d['dept_id'] ?>"
         data-current="<?= htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8') ?>">
        <div class="data-card-header">
            <span class="data-card-title"><i class="bi bi-building me-1"></i> <?= htmlspecialchars($d['dept_name']) ?></span>
            <span class="badge-status published ms-auto dept-status-badge">Saved</span>
        </div>

        <div style="padding:0 1.25rem 1.25rem">
            <div class="row g-3">

                <!-- ── Logo ── -->
                <div class="col-12">
                    <div class="dept-media-field">
                        <div class="dept-media-label"><i class="bi bi-image"></i> Logo</div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="dept-logo-preview">
                                <img class="dept-logo-img" alt="" hidden>
                                <i class="bi bi-image dept-logo-placeholder"></i>
                            </div>
                            <div class="flex-grow-1" style="min-width:0">
                                <div class="small text-truncate dept-logo-name">No logo</div>
                                <div class="small text-muted dept-logo-hint">JPG, PNG, GIF, WEBP or SVG · max 5 MB</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-pick-logo">Upload Logo</button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-logo">Remove</button>
                            <input type="file" class="dept-logo-input" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml,.svg" hidden>
                        </div>
                    </div>
                </div>

            </div>

            <div class="dept-alert alert d-none mt-3 mb-0"></div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary btn-sm btn-dept-reset" disabled>Discard Changes</button>
                <button type="button" class="btn-gnc-primary btn-sm btn-dept-save" disabled>Save Changes</button>
            </div>

            <hr class="my-4">

            <!-- ── Head of Office (Dean) ── -->
            <div class="dean-media-label mb-2"><i class="bi bi-person-badge"></i> Head of Office</div>
            <div class="row g-3">

                <!-- Photo -->
                <div class="col-md-4">
                    <div class="dept-media-field h-100">
                        <div class="dept-media-label"><i class="bi bi-image"></i> Photo</div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="dept-logo-preview dean-photo-preview">
                                <img class="dean-photo-img" alt="" hidden>
                                <i class="bi bi-person dean-photo-placeholder"></i>
                            </div>
                            <div class="flex-grow-1" style="min-width:0">
                                <div class="small text-truncate dean-photo-name">No photo</div>
                                <div class="small text-muted dean-photo-hint">JPG, PNG or WEBP · max 5 MB</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-pick-dean-photo">Upload Photo</button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-dean-photo">Remove</button>
                            <input type="file" class="dean-photo-input" accept="image/jpeg,image/png,image/webp" hidden>
                        </div>
                    </div>
                </div>

                <!-- Name, title, bio -->
                <div class="col-md-8">
                    <div class="dept-media-field h-100">
                        <div class="dept-media-label"><i class="bi bi-person-lines-fill"></i> Name &amp; Bio</div>
                        <div class="row g-2">
                            <div class="col-sm-8">
                                <label class="small text-muted mb-1">Name</label>
                                <input type="text" class="form-control form-control-sm dean-name-input" placeholder="e.g. Annaliza V. Maris, LPT, MA">
                            </div>
                            <div class="col-sm-4">
                                <label class="small text-muted mb-1">Title</label>
                                <input type="text" class="form-control form-control-sm dean-title-input" placeholder="e.g. Dean">
                            </div>
                            <div class="col-12">
                                <label class="small text-muted mb-1 mt-1">Bio (shown under "Meet the Dean")</label>
                                <textarea class="form-control form-control-sm dean-bio-input" rows="3" placeholder="A short introduction..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Get Connected -->
                <div class="col-12">
                    <div class="dept-media-field">
                        <div class="dept-media-label"><i class="bi bi-diagram-2"></i> Get Connected</div>
                        <div class="row g-2">
                            <div class="col-sm-6 col-lg-3">
                                <label class="small text-muted mb-1">Contact number</label>
                                <input type="text" class="form-control form-control-sm dean-phone-input" placeholder="(045) 900-4473 Loc. 120">
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label class="small text-muted mb-1">Email address</label>
                                <input type="email" class="form-control form-control-sm dean-email-input" placeholder="info@gnc.edu.ph">
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label class="small text-muted mb-1">Office location</label>
                                <input type="text" class="form-control form-control-sm dean-office-input" placeholder="Limlingan Hall, Floor 2, Room 202">
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label class="small text-muted mb-1">Operating hours</label>
                                <input type="text" class="form-control form-control-sm dean-hours-input" placeholder="Monday - Friday, 8:00AM - 4:00PM">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dean-alert alert d-none mt-3 mb-0"></div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary btn-sm btn-dean-reset" disabled>Discard Changes</button>
                <button type="button" class="btn-gnc-primary btn-sm btn-dean-save" disabled>Save Changes</button>
            </div>
        </div>
    </div>
<?php endforeach; endif; ?>

<style>
.dept-media-field {
    border: 1px solid rgba(0, 0, 0, .1);
    border-radius: 10px;
    padding: .85rem;
    height: 100%;
}
.dept-media-label {
    font-size: .8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--gnc-green, #1F5E2C);
    margin-bottom: .6rem;
}
.dept-logo-preview {
    width: 72px;
    height: 72px;
    flex: 0 0 72px;
    border-radius: 10px;
    background: #f1f1f1;
    color: #aaa;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    font-size: 1.6rem;
}
.dept-logo-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 4px;
}
.dean-media-label {
    font-size: .8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--gnc-green, #1F5E2C);
}
</style>

<script>
(function () {
    var AJAX_URL = '/admin/action/page-content-handler.php';
    var csrf = <?= json_encode($csrfToken ?? '') ?>;
    var LOGO_MAX = 5 * 1024 * 1024;
    var LOGO_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

    function fmtSize(bytes) {
        if (!bytes) return '';
        return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    document.querySelectorAll('.dept-media-card').forEach(function (card) {
        var current = JSON.parse(card.dataset.current);
        var pending = { logoFile: null, removeLogo: false };
        var logoPreviewUrl = null;

        var $ = function (sel) { return card.querySelector(sel); };
        var logoImg = $('.dept-logo-img'), logoPh = $('.dept-logo-placeholder');
        var logoName = $('.dept-logo-name'), logoHint = $('.dept-logo-hint');
        var pickLogo = $('.btn-pick-logo'), rmLogo = $('.btn-remove-logo'), logoInput = $('.dept-logo-input');
        var saveBtn = $('.btn-dept-save'), resetBtn = $('.btn-dept-reset');
        var badge = $('.dept-status-badge'), alertEl = $('.dept-alert');

        function showAlert(msg, isError) {
            alertEl.className = 'dept-alert alert mt-3 mb-0 ' + (isError ? 'alert-danger' : 'alert-success');
            alertEl.textContent = msg;
            if (!isError) setTimeout(function () { alertEl.classList.add('d-none'); }, 4000);
        }
        function clearAlert() { alertEl.classList.add('d-none'); }

        function revokeLogoPreview() {
            if (logoPreviewUrl) { URL.revokeObjectURL(logoPreviewUrl); logoPreviewUrl = null; }
        }

        function render() {
            // ── Logo ──
            var showUrl = null, name = 'No logo', hint = 'JPG, PNG, GIF, WEBP or SVG · max 5 MB';
            if (pending.logoFile) {
                revokeLogoPreview();
                logoPreviewUrl = URL.createObjectURL(pending.logoFile);
                showUrl = logoPreviewUrl; name = pending.logoFile.name; hint = 'New logo — not saved yet';
            } else if (pending.removeLogo) {
                hint = 'Will be removed when you save';
            } else if (current.logo_url) {
                showUrl = current.logo_url; name = current.logo_name || 'Current logo'; hint = 'Current logo';
            }
            if (showUrl) { logoImg.src = showUrl; logoImg.hidden = false; logoPh.hidden = true; }
            else         { logoImg.removeAttribute('src'); logoImg.hidden = true; logoPh.hidden = false; }
            logoName.textContent = name; logoName.title = name; logoHint.textContent = hint;
            pickLogo.textContent = showUrl ? 'Replace Logo' : 'Upload Logo';
            rmLogo.disabled = !showUrl;

            // ── Dirty state ──
            var dirty = !!(pending.logoFile || pending.removeLogo);
            saveBtn.disabled = !dirty; resetBtn.disabled = !dirty;
            badge.classList.toggle('draft', dirty); badge.classList.toggle('published', !dirty);
            badge.textContent = dirty ? 'Unsaved changes' : 'Saved';
        }

        pickLogo.addEventListener('click', function () { logoInput.click(); });

        logoInput.addEventListener('change', function () {
            var f = logoInput.files[0]; logoInput.value = '';
            if (!f) return;
            var isSvg = /\.svg$/i.test(f.name);
            if (LOGO_TYPES.indexOf(f.type) === -1 && !isSvg) { showAlert('Invalid image type. Allowed: JPG, PNG, GIF, WEBP, SVG.', true); return; }
            if (f.size > LOGO_MAX) { showAlert('The logo is too large. Maximum size is 5 MB.', true); return; }
            clearAlert(); pending.logoFile = f; pending.removeLogo = false; render();
        });

        rmLogo.addEventListener('click', function () {
            clearAlert(); pending.logoFile = null; pending.removeLogo = !!current.logo_url; render();
        });
        resetBtn.addEventListener('click', function () {
            pending = { logoFile: null, removeLogo: false };
            clearAlert(); render();
        });

        saveBtn.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('action', 'save_department_media');
            fd.append('csrf_token', csrf);
            fd.append('dept_id', current.dept_id);
            if (pending.logoFile) fd.append('logo', pending.logoFile);
            if (pending.removeLogo) fd.append('remove_logo', '1');

            saveBtn.disabled = true; resetBtn.disabled = true;
            var originalLabel = saveBtn.textContent; saveBtn.textContent = 'Saving…';

            fetch(AJAX_URL, { method: 'POST', body: fd })
                .then(function (res) { return res.json().catch(function () { return { success: false, error: 'Unexpected response from the server.' }; }); })
                .then(function (data) {
                    if (data.success) {
                        current = data.department;
                        pending = { logoFile: null, removeLogo: false };
                        revokeLogoPreview();
                        render();
                        showAlert('Saved. The change is now live on every page that uses this department.', false);
                    } else {
                        showAlert(data.error || 'Could not save.', true);
                        render(); // re-enable buttons, keep pending changes so nothing is lost
                    }
                })
                .catch(function () { showAlert('Network error. Please try again.', true); render(); })
                .finally(function () { saveBtn.textContent = originalLabel; });
        });

        render();

        // ── Head of Office (Dean) ──
        var deanPending = { photoFile: null, removePhoto: false };
        var deanPreviewUrl = null;

        var photoImg = $('.dean-photo-img'), photoPh = $('.dean-photo-placeholder');
        var photoName = $('.dean-photo-name'), photoHint = $('.dean-photo-hint');
        var pickPhoto = $('.btn-pick-dean-photo'), rmPhoto = $('.btn-remove-dean-photo'), photoInput = $('.dean-photo-input');
        var nameInput = $('.dean-name-input'), titleInput = $('.dean-title-input'), bioInput = $('.dean-bio-input');
        var phoneInput = $('.dean-phone-input'), emailInput = $('.dean-email-input');
        var officeInput = $('.dean-office-input'), hoursInput = $('.dean-hours-input');
        var deanSaveBtn = $('.btn-dean-save'), deanResetBtn = $('.btn-dean-reset');
        var deanAlertEl = $('.dean-alert');

        function showDeanAlert(msg, isError) {
            deanAlertEl.className = 'dean-alert alert mt-3 mb-0 ' + (isError ? 'alert-danger' : 'alert-success');
            deanAlertEl.textContent = msg;
            if (!isError) setTimeout(function () { deanAlertEl.classList.add('d-none'); }, 4000);
        }
        function clearDeanAlert() { deanAlertEl.classList.add('d-none'); }
        function revokeDeanPreview() {
            if (deanPreviewUrl) { URL.revokeObjectURL(deanPreviewUrl); deanPreviewUrl = null; }
        }

        function fillDeanFields() {
            nameInput.value   = current.dean_name   || '';
            titleInput.value  = current.dean_title  || '';
            bioInput.value    = current.dean_bio    || '';
            phoneInput.value  = current.dean_phone  || '';
            emailInput.value  = current.dean_email  || '';
            officeInput.value = current.dean_office || '';
            hoursInput.value  = current.dean_hours  || '';
        }

        function renderDean() {
            // Photo
            var showUrl = null, pName = 'No photo', pHint = 'JPG, PNG or WEBP · max 5 MB';
            if (deanPending.photoFile) {
                revokeDeanPreview();
                deanPreviewUrl = URL.createObjectURL(deanPending.photoFile);
                showUrl = deanPreviewUrl; pName = deanPending.photoFile.name; pHint = 'New photo — not saved yet';
            } else if (deanPending.removePhoto) {
                pHint = 'Will be removed when you save';
            } else if (current.dean_photo_url) {
                showUrl = current.dean_photo_url; pName = current.dean_photo_name || 'Current photo'; pHint = 'Current photo';
            }
            if (showUrl) { photoImg.src = showUrl; photoImg.hidden = false; photoPh.hidden = true; }
            else         { photoImg.removeAttribute('src'); photoImg.hidden = true; photoPh.hidden = false; }
            photoName.textContent = pName; photoName.title = pName; photoHint.textContent = pHint;
            pickPhoto.textContent = showUrl ? 'Replace Photo' : 'Upload Photo';
            rmPhoto.disabled = !showUrl;

            // Dirty state: a text field differs from `current`, or a photo change is pending
            var textDirty =
                nameInput.value   !== (current.dean_name   || '') ||
                titleInput.value  !== (current.dean_title  || '') ||
                bioInput.value    !== (current.dean_bio    || '') ||
                phoneInput.value  !== (current.dean_phone  || '') ||
                emailInput.value  !== (current.dean_email  || '') ||
                officeInput.value !== (current.dean_office || '') ||
                hoursInput.value  !== (current.dean_hours  || '');
            var dirty = !!(textDirty || deanPending.photoFile || deanPending.removePhoto);
            deanSaveBtn.disabled = !dirty; deanResetBtn.disabled = !dirty;
        }

        pickPhoto.addEventListener('click', function () { photoInput.click(); });

        photoInput.addEventListener('change', function () {
            var f = photoInput.files[0]; photoInput.value = '';
            if (!f) return;
            if (['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) === -1) {
                showDeanAlert('Invalid image type. Allowed: JPG, PNG, WEBP.', true); return;
            }
            if (f.size > LOGO_MAX) { showDeanAlert('The photo is too large. Maximum size is 5 MB.', true); return; }
            clearDeanAlert(); deanPending.photoFile = f; deanPending.removePhoto = false; renderDean();
        });

        rmPhoto.addEventListener('click', function () {
            clearDeanAlert(); deanPending.photoFile = null; deanPending.removePhoto = !!current.dean_photo_url; renderDean();
        });

        [nameInput, titleInput, bioInput, phoneInput, emailInput, officeInput, hoursInput].forEach(function (el) {
            el.addEventListener('input', function () { clearDeanAlert(); renderDean(); });
        });

        deanResetBtn.addEventListener('click', function () {
            deanPending = { photoFile: null, removePhoto: false };
            fillDeanFields();
            clearDeanAlert(); renderDean();
        });

        deanSaveBtn.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('action', 'save_department_dean');
            fd.append('csrf_token', csrf);
            fd.append('dept_id', current.dept_id);
            fd.append('dean_name', nameInput.value);
            fd.append('dean_title', titleInput.value);
            fd.append('dean_bio', bioInput.value);
            fd.append('dean_phone', phoneInput.value);
            fd.append('dean_email', emailInput.value);
            fd.append('dean_office', officeInput.value);
            fd.append('dean_hours', hoursInput.value);
            if (deanPending.photoFile) fd.append('dean_photo', deanPending.photoFile);
            if (deanPending.removePhoto) fd.append('remove_photo', '1');

            deanSaveBtn.disabled = true; deanResetBtn.disabled = true;
            var originalLabel = deanSaveBtn.textContent; deanSaveBtn.textContent = 'Saving…';

            fetch(AJAX_URL, { method: 'POST', body: fd })
                .then(function (res) { return res.json().catch(function () { return { success: false, error: 'Unexpected response from the server.' }; }); })
                .then(function (data) {
                    if (data.success) {
                        current = data.department;
                        deanPending = { photoFile: null, removePhoto: false };
                        revokeDeanPreview();
                        fillDeanFields();
                        renderDean();
                        showDeanAlert('Saved. The change is now live on the department\'s page.', false);
                    } else {
                        showDeanAlert(data.error || 'Could not save.', true);
                        renderDean();
                    }
                })
                .catch(function () { showDeanAlert('Network error. Please try again.', true); renderDean(); })
                .finally(function () { deanSaveBtn.textContent = originalLabel; });
        });

        fillDeanFields();
        renderDean();
    });
})();
</script>