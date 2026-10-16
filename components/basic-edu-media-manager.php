<?php
/**
 * GNC Admin Panel – Basic Education Departments: Logo & Head of Office manager
 * Rendered inside the Content Editor's "Basic Education Departments" page
 * panel (see content-edit.php). One card per department — CASA & Grade
 * School Montessori, High School Montessori, Junior High School Regular,
 * Senior High School Regular.
 *
 * These four are rows in the same `departments` table used by the College
 * Departments (added by sql/basic-edu-dean-migration.sql), so this panel
 * reuses the exact same backend as department-media-manager.php:
 *   - Logo:          saveDepartmentMedia()  → page-content-handler.php → save_department_media
 *   - Head of Office: saveDepartmentDean()  → page-content-handler.php → save_department_dean
 * There is no PDF section here (College Departments only).
 *
 * Self-contained (own styles + script), so it doesn't depend on
 * department-media-manager.php being loaded on the same page.
 *
 * Expects from the including page: $csrfToken, and admin-functions.php loaded.
 */
$basicEduSlugs = ['casa-gradeschool', 'hs-monte', 'jhs-regular', 'shs-regular'];
$basicEduDepartments = array_values(array_filter(getDepartments(), static function ($department) use ($basicEduSlugs) {
    return in_array((string)($department['slug'] ?? ''), $basicEduSlugs, true);
}));
usort($basicEduDepartments, static function ($a, $b) use ($basicEduSlugs) {
    return array_search($a['slug'], $basicEduSlugs, true) <=> array_search($b['slug'], $basicEduSlugs, true);
});
?>

<div class="basic-edu-media-intro data-card mb-3" style="padding:1rem 1.25rem">
    <strong><i class="bi bi-mortarboard me-1"></i> Logo &amp; Head of Office</strong>
    <p class="small text-muted mb-0 mt-1">
        Each department has one logo, plus a Head of Office (Principal/OIC) — photo, name, title,
        bio, and contact details. Changes are live as soon as you save and appear on that
        department's own page.
    </p>
</div>

<?php if (empty($basicEduDepartments)): ?>
    <div class="data-card mb-3">
        <div class="empty-state">
            <i class="bi bi-building"></i>
            <p>No Basic Education departments found. Run <code>sql/basic-edu-dean-migration.sql</code> to add them, then refresh.</p>
        </div>
    </div>
<?php else: foreach ($basicEduDepartments as $d):
    $payload = departmentAdminPayload($d);
?>
    <div class="data-card mb-3 basic-edu-dean-card"
         data-dept-id="<?= (int)$d['dept_id'] ?>"
         data-current="<?= htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8') ?>">
        <div class="data-card-header">
            <span class="data-card-title"><i class="bi bi-building me-1"></i> <?= htmlspecialchars($d['dept_name']) ?></span>
            <span class="badge-status published ms-auto basic-edu-dean-status-badge">Saved</span>
        </div>

        <div style="padding:0 1.25rem 1.25rem">

            <!-- ── Logo ── -->
            <div class="basic-edu-dean-field mb-3">
                <div class="basic-edu-dean-label"><i class="bi bi-image"></i> Logo</div>
                <div class="d-flex align-items-center gap-3">
                    <div class="basic-edu-dean-photo-preview basic-edu-logo-preview">
                        <img class="basic-edu-logo-img" alt="" hidden>
                        <i class="bi bi-image basic-edu-logo-placeholder"></i>
                    </div>
                    <div class="flex-grow-1" style="min-width:0">
                        <div class="small text-truncate basic-edu-logo-name">No logo</div>
                        <div class="small text-muted basic-edu-logo-hint">JPG, PNG, GIF, WEBP or SVG · max 5 MB</div>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-pick-logo">Upload Logo</button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-logo">Remove</button>
                    <input type="file" class="basic-edu-logo-input" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml,.svg" hidden>
                </div>
            </div>

            <hr style="margin: 1.25rem 0; border-top: 1px solid #e5e5e5;">

            <div class="basic-edu-dean-label mb-2" style="font-size:.85rem"><i class="bi bi-person-badge"></i> Head of Office</div>

            <div class="row g-3">

                <!-- ── Photo ── -->
                <div class="col-md-4">
                    <div class="basic-edu-dean-field h-100">
                        <div class="basic-edu-dean-label"><i class="bi bi-image"></i> Photo</div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="basic-edu-dean-photo-preview">
                                <img class="basic-edu-dean-photo-img" alt="" hidden>
                                <i class="bi bi-person-fill basic-edu-dean-photo-placeholder"></i>
                            </div>
                            <div class="flex-grow-1" style="min-width:0">
                                <div class="small text-truncate basic-edu-dean-photo-name">No photo</div>
                                <div class="small text-muted basic-edu-dean-photo-hint">JPG, PNG or WEBP · max 5 MB</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-pick-dean-photo">Upload Photo</button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-dean-photo">Remove</button>
                            <input type="file" class="basic-edu-dean-photo-input" data-photo-check accept="image/jpeg,image/png,image/webp" hidden>
                        </div>
                    </div>
                </div>

                <!-- ── Name & Bio ── -->
                <div class="col-md-8">
                    <div class="basic-edu-dean-field h-100">
                        <div class="basic-edu-dean-label"><i class="bi bi-person-lines-fill"></i> Name &amp; Bio</div>
                        <div class="row g-2">
                            <div class="col-sm-7">
                                <label class="small text-muted mb-1">Name</label>
                                <input type="text" class="form-control form-control-sm basic-edu-dean-name" value="<?= htmlspecialchars($payload['dean_name'] ?? '') ?>">
                            </div>
                            <div class="col-sm-5">
                                <label class="small text-muted mb-1">Title</label>
                                <input type="text" class="form-control form-control-sm basic-edu-dean-title" placeholder="Principal / OIC" value="<?= htmlspecialchars($payload['dean_title'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="small text-muted mb-1">Bio (shown under "Meet the Officer-in-Charge")</label>
                                <textarea class="form-control form-control-sm basic-edu-dean-bio" rows="3"><?= htmlspecialchars($payload['dean_bio'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Get Connected ── -->
                <div class="col-12">
                    <div class="basic-edu-dean-field">
                        <div class="basic-edu-dean-label"><i class="bi bi-diagram-2"></i> Get Connected</div>
                        <div class="row g-2">
                            <div class="col-sm-6 col-lg-3">
                                <label class="small text-muted mb-1">Contact number</label>
                                <input type="text" class="form-control form-control-sm basic-edu-dean-phone" value="<?= htmlspecialchars($payload['dean_phone'] ?? '') ?>">
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label class="small text-muted mb-1">Email address</label>
                                <input type="text" class="form-control form-control-sm basic-edu-dean-email" value="<?= htmlspecialchars($payload['dean_email'] ?? '') ?>">
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label class="small text-muted mb-1">Office location</label>
                                <input type="text" class="form-control form-control-sm basic-edu-dean-office" value="<?= htmlspecialchars($payload['dean_office'] ?? '') ?>">
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label class="small text-muted mb-1">Operating hours</label>
                                <input type="text" class="form-control form-control-sm basic-edu-dean-hours" placeholder="Monday - Friday&#10;8:00AM - 4:00PM" value="<?= htmlspecialchars($payload['dean_hours'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="basic-edu-dean-alert alert d-none mt-3 mb-0"></div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary btn-sm btn-basic-edu-dean-reset" disabled>Discard Changes</button>
                <button type="button" class="btn-gnc-primary btn-sm btn-basic-edu-dean-save" disabled>Save Changes</button>
            </div>
        </div>
    </div>
<?php endforeach; endif; ?>

<style>
.basic-edu-dean-field {
    border: 1px solid rgba(0, 0, 0, .1);
    border-radius: 10px;
    padding: .85rem;
}
.basic-edu-dean-label {
    font-size: .8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--gnc-green, #1F5E2C);
    margin-bottom: .6rem;
}
.basic-edu-dean-photo-preview {
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
.basic-edu-dean-photo-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.basic-edu-logo-preview img {
    object-fit: contain;
    padding: 4px;
}
</style>

<script>
(function () {
    var AJAX_URL = '/admin/action/page-content-handler.php';
    var csrf = <?= json_encode($csrfToken ?? '') ?>;
    var PHOTO_MAX = 5 * 1024 * 1024;
    var PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    var LOGO_MAX = 5 * 1024 * 1024;
    var LOGO_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

    document.querySelectorAll('.basic-edu-dean-card').forEach(function (card) {
        var current = JSON.parse(card.dataset.current);
        var pending = { logoFile: null, removeLogo: false, photoFile: null, removePhoto: false };
        var logoPreviewUrl = null, photoPreviewUrl = null;

        var $ = function (sel) { return card.querySelector(sel); };

        // Logo elements
        var logoImg = $('.basic-edu-logo-img'), logoPh = $('.basic-edu-logo-placeholder');
        var logoName = $('.basic-edu-logo-name'), logoHint = $('.basic-edu-logo-hint');
        var pickLogo = $('.btn-pick-logo'), rmLogo = $('.btn-remove-logo'), logoInput = $('.basic-edu-logo-input');

        // Head of Office elements
        var photoImg = $('.basic-edu-dean-photo-img'), photoPh = $('.basic-edu-dean-photo-placeholder');
        var photoName = $('.basic-edu-dean-photo-name'), photoHint = $('.basic-edu-dean-photo-hint');
        var pickPhoto = $('.btn-pick-dean-photo'), rmPhoto = $('.btn-remove-dean-photo'), photoInput = $('.basic-edu-dean-photo-input');
        var nameInput = $('.basic-edu-dean-name'), titleInput = $('.basic-edu-dean-title'), bioInput = $('.basic-edu-dean-bio');
        var phoneInput = $('.basic-edu-dean-phone'), emailInput = $('.basic-edu-dean-email'),
            officeInput = $('.basic-edu-dean-office'), hoursInput = $('.basic-edu-dean-hours');

        var saveBtn = $('.btn-basic-edu-dean-save'), resetBtn = $('.btn-basic-edu-dean-reset');
        var badge = $('.basic-edu-dean-status-badge'), alertEl = $('.basic-edu-dean-alert');

        var textFields = [
            [nameInput, 'dean_name'], [titleInput, 'dean_title'], [bioInput, 'dean_bio'],
            [phoneInput, 'dean_phone'], [emailInput, 'dean_email'], [officeInput, 'dean_office'], [hoursInput, 'dean_hours']
        ];

        function showAlert(msg, isError) {
            alertEl.className = 'basic-edu-dean-alert alert mt-3 mb-0 ' + (isError ? 'alert-danger' : 'alert-success');
            alertEl.textContent = msg;
            if (!isError) setTimeout(function () { alertEl.classList.add('d-none'); }, 4000);
        }
        function clearAlert() { alertEl.classList.add('d-none'); }

        function revokeLogoPreview() { if (logoPreviewUrl) { URL.revokeObjectURL(logoPreviewUrl); logoPreviewUrl = null; } }
        function revokePhotoPreview() { if (photoPreviewUrl) { URL.revokeObjectURL(photoPreviewUrl); photoPreviewUrl = null; } }

        function textDirty() {
            return textFields.some(function (pair) {
                return (pair[0].value || '') !== (current[pair[1]] || '');
            });
        }
        function logoDirty() { return !!(pending.logoFile || pending.removeLogo); }
        function photoDirty() { return !!(pending.photoFile || pending.removePhoto); }

        function render() {
            // ── Logo ──
            var showLogoUrl = null, lName = 'No logo', lHint = 'JPG, PNG, GIF, WEBP or SVG · max 5 MB';
            if (pending.logoFile) {
                revokeLogoPreview();
                logoPreviewUrl = URL.createObjectURL(pending.logoFile);
                showLogoUrl = logoPreviewUrl; lName = pending.logoFile.name; lHint = 'New logo — not saved yet';
            } else if (pending.removeLogo) {
                lHint = 'Will be removed when you save';
            } else if (current.logo_url) {
                showLogoUrl = current.logo_url; lName = current.logo_name || 'Current logo'; lHint = 'Current logo';
            }
            if (showLogoUrl) { logoImg.src = showLogoUrl; logoImg.hidden = false; logoPh.hidden = true; }
            else              { logoImg.removeAttribute('src'); logoImg.hidden = true; logoPh.hidden = false; }
            logoName.textContent = lName; logoName.title = lName; logoHint.textContent = lHint;
            pickLogo.textContent = showLogoUrl ? 'Replace Logo' : 'Upload Logo';
            rmLogo.disabled = !showLogoUrl;

            // ── Head of Office photo ──
            var showUrl = null, name = 'No photo', hint = 'JPG, PNG or WEBP · max 5 MB';
            if (pending.photoFile) {
                revokePhotoPreview();
                photoPreviewUrl = URL.createObjectURL(pending.photoFile);
                showUrl = photoPreviewUrl; name = pending.photoFile.name; hint = 'New photo — not saved yet';
            } else if (pending.removePhoto) {
                hint = 'Will be removed when you save';
            } else if (current.dean_photo_url) {
                showUrl = current.dean_photo_url; name = current.dean_photo_name || 'Current photo'; hint = 'Current photo';
            }
            if (showUrl) { photoImg.src = showUrl; photoImg.hidden = false; photoPh.hidden = true; }
            else         { photoImg.removeAttribute('src'); photoImg.hidden = true; photoPh.hidden = false; }
            photoName.textContent = name; photoName.title = name; photoHint.textContent = hint;
            pickPhoto.textContent = showUrl ? 'Replace Photo' : 'Upload Photo';
            rmPhoto.disabled = !showUrl;

            // ── Dirty state (combined) ──
            var dirty = logoDirty() || photoDirty() || textDirty();
            saveBtn.disabled = !dirty; resetBtn.disabled = !dirty;
            badge.classList.toggle('draft', dirty); badge.classList.toggle('published', !dirty);
            badge.textContent = dirty ? 'Unsaved changes' : 'Saved';
        }

        // Logo handlers
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

        // Head of Office photo handlers
        pickPhoto.addEventListener('click', function () { photoInput.click(); });
        photoInput.addEventListener('change', function () {
            var f = photoInput.files[0]; photoInput.value = '';
            if (!f) return;
            if (PHOTO_TYPES.indexOf(f.type) === -1) { showAlert('Invalid image type. Allowed: JPG, PNG or WEBP.', true); return; }
            if (f.size > PHOTO_MAX) { showAlert('The photo is too large. Maximum size is 5 MB.', true); return; }
            clearAlert(); pending.photoFile = f; pending.removePhoto = false; render();
        });
        rmPhoto.addEventListener('click', function () {
            clearAlert(); pending.photoFile = null; pending.removePhoto = !!current.dean_photo_url; render();
        });

        textFields.forEach(function (pair) { pair[0].addEventListener('input', function () { clearAlert(); render(); }); });

        resetBtn.addEventListener('click', function () {
            pending = { logoFile: null, removeLogo: false, photoFile: null, removePhoto: false };
            textFields.forEach(function (pair) { pair[0].value = current[pair[1]] || ''; });
            clearAlert(); render();
        });

        function postForm(fd) {
            return fetch(AJAX_URL, { method: 'POST', body: fd })
                .then(function (res) { return res.json().catch(function () { return { success: false, error: 'Unexpected response from the server.' }; }); });
        }

        saveBtn.addEventListener('click', function () {
            var needsLogoSave = logoDirty();
            var needsDeanSave = photoDirty() || textDirty();

            saveBtn.disabled = true; resetBtn.disabled = true;
            var originalLabel = saveBtn.textContent; saveBtn.textContent = 'Saving…';
            clearAlert();

            var chain = Promise.resolve();

            if (needsLogoSave) {
                chain = chain.then(function () {
                    var fd = new FormData();
                    fd.append('action', 'save_department_media');
                    fd.append('csrf_token', csrf);
                    fd.append('dept_id', current.dept_id);
                    if (pending.logoFile) fd.append('logo', pending.logoFile);
                    if (pending.removeLogo) fd.append('remove_logo', '1');
                    return postForm(fd).then(function (data) {
                        if (!data.success) throw new Error(data.error || 'Could not save the logo.');
                        current = Object.assign({}, current, data.department);
                        pending.logoFile = null; pending.removeLogo = false;
                        revokeLogoPreview();
                    });
                });
            }

            if (needsDeanSave) {
                chain = chain.then(function () {
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
                    if (pending.photoFile) fd.append('dean_photo', pending.photoFile);
                    if (pending.removePhoto) fd.append('remove_photo', '1');
                    return postForm(fd).then(function (data) {
                        if (!data.success) throw new Error(data.error || 'Could not save the Head of Office.');
                        current = Object.assign({}, current, data.department);
                        pending.photoFile = null; pending.removePhoto = false;
                        revokePhotoPreview();
                    });
                });
            }

            chain
                .then(function () {
                    render();
                    showAlert('Saved. The change is now live on this department\'s page.', false);
                })
                .catch(function (err) {
                    showAlert(err && err.message ? err.message : 'Network error. Please try again.', true);
                    render(); // re-enable buttons, keep whatever pending changes remain
                })
                .finally(function () { saveBtn.textContent = originalLabel; });
        });

        render();
    });
})();
</script>
