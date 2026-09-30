<?php
$president = homepagePresidentAdminPayload(getHomepagePresident());
?>

<div class="data-card mb-3 president-manager" data-current="<?= htmlspecialchars(json_encode($president), ENT_QUOTES, 'UTF-8') ?>">
    <div class="data-card-header">
        <span class="data-card-title"><i class="bi bi-person-badge me-1"></i> Message from the President</span>
        <span class="badge-status published ms-auto president-status-badge">Saved</span>
    </div>

    <div style="padding:0 1.25rem 1.25rem">
        <div class="row g-3">

            <!-- Photo -->
            <div class="col-md-4">
                <div class="dept-media-field h-100">
                    <div class="dept-media-label"><i class="bi bi-image"></i> Photo</div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="dept-logo-preview president-photo-preview">
                            <img class="president-photo-img" alt="" hidden>
                            <i class="bi bi-person president-photo-placeholder"></i>
                        </div>
                        <div class="flex-grow-1" style="min-width:0">
                            <div class="small text-truncate president-photo-name">No photo</div>
                            <div class="small text-muted president-photo-hint">JPG, PNG or WEBP · max 5 MB</div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-pick-president-photo">Upload Photo</button>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-president-photo">Remove</button>
                        <input type="file" class="president-photo-input" accept="image/jpeg,image/png,image/webp" hidden>
                    </div>
                </div>
            </div>

            <!-- Name, title, org -->
            <div class="col-md-8">
                <div class="dept-media-field h-100">
                    <div class="dept-media-label"><i class="bi bi-person-lines-fill"></i> Nameplate</div>
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label class="small text-muted mb-1">Name</label>
                            <input type="text" class="form-control form-control-sm president-name-input" placeholder="e.g. Geraldine G. Lim, MBA">
                        </div>
                        <div class="col-sm-3">
                            <label class="small text-muted mb-1">Title</label>
                            <input type="text" class="form-control form-control-sm president-title-input" placeholder="e.g. President">
                        </div>
                        <div class="col-sm-3">
                            <label class="small text-muted mb-1">Organization</label>
                            <input type="text" class="form-control form-control-sm president-org-input" placeholder="e.g. Guagua National Colleges, Inc.">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Welcome message -->
            <div class="col-12">
                <div class="dept-media-field">
                    <div class="dept-media-label"><i class="bi bi-chat-quote"></i> Welcome Message</div>
                    <div class="row g-2">
                        <div class="col-sm-4">
                            <label class="small text-muted mb-1">Eyebrow</label>
                            <input type="text" class="form-control form-control-sm president-eyebrow-input" placeholder="e.g. A Warm Welcome to">
                        </div>
                        <div class="col-sm-4">
                            <label class="small text-muted mb-1">Heading</label>
                            <input type="text" class="form-control form-control-sm president-heading-input" placeholder="e.g. Our New Students">
                        </div>
                        <div class="col-sm-4">
                            <label class="small text-muted mb-1">Greeting</label>
                            <input type="text" class="form-control form-control-sm president-greeting-input" placeholder="e.g. Welcome GNCians!">
                        </div>
                        <div class="col-12">
                            <label class="small text-muted mb-1 mt-1">Message</label>
                            <textarea class="form-control form-control-sm president-message-input" rows="4" placeholder="The welcome message shown under the greeting..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="president-alert alert d-none mt-3 mb-0"></div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="button" class="btn btn-outline-secondary btn-sm btn-president-reset" disabled>Discard Changes</button>
            <button type="button" class="btn-gnc-primary btn-sm btn-president-save" disabled>Save Changes</button>
        </div>
    </div>
</div>

<style>
.president-manager .dept-media-field {
    border: 1px solid rgba(0, 0, 0, .1);
    border-radius: 10px;
    padding: .85rem;
    height: 100%;
}
.president-manager .dept-media-label {
    font-size: .8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--gnc-green, #1F5E2C);
    margin-bottom: .6rem;
}
.president-manager .dept-logo-preview {
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
.president-manager .dept-logo-preview img {
    width: 100%;
    height: 100%;
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

    var card = document.querySelector('.president-manager');
    if (!card) return;

    var current = JSON.parse(card.dataset.current);
    var pending = { photoFile: null, removePhoto: false };
    var previewUrl = null;

    var $ = function (sel) { return card.querySelector(sel); };
    var photoImg = $('.president-photo-img'), photoPh = $('.president-photo-placeholder');
    var photoName = $('.president-photo-name'), photoHint = $('.president-photo-hint');
    var pickPhoto = $('.btn-pick-president-photo'), rmPhoto = $('.btn-remove-president-photo'), photoInput = $('.president-photo-input');
    var nameInput = $('.president-name-input'), titleInput = $('.president-title-input'), orgInput = $('.president-org-input');
    var eyebrowInput = $('.president-eyebrow-input'), headingInput = $('.president-heading-input'), greetingInput = $('.president-greeting-input');
    var messageInput = $('.president-message-input');
    var saveBtn = $('.btn-president-save'), resetBtn = $('.btn-president-reset');
    var badge = $('.president-status-badge'), alertEl = $('.president-alert');

    function showAlert(msg, isError) {
        alertEl.className = 'president-alert alert mt-3 mb-0 ' + (isError ? 'alert-danger' : 'alert-success');
        alertEl.textContent = msg;
        if (!isError) setTimeout(function () { alertEl.classList.add('d-none'); }, 4000);
    }
    function clearAlert() { alertEl.classList.add('d-none'); }
    function revokePreview() {
        if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
    }

    function fillFields() {
        nameInput.value     = current.name     || '';
        titleInput.value    = current.title    || '';
        orgInput.value      = current.org      || '';
        eyebrowInput.value  = current.eyebrow  || '';
        headingInput.value  = current.heading  || '';
        greetingInput.value = current.greeting || '';
        messageInput.value  = current.message  || '';
    }

    function render() {
        // Photo
        var showUrl = null, pName = 'No photo', pHint = 'JPG, PNG or WEBP · max 5 MB';
        if (pending.photoFile) {
            revokePreview();
            previewUrl = URL.createObjectURL(pending.photoFile);
            showUrl = previewUrl; pName = pending.photoFile.name; pHint = 'New photo — not saved yet';
        } else if (pending.removePhoto) {
            pHint = 'Will be removed when you save';
        } else if (current.photo_url) {
            showUrl = current.photo_url; pName = current.photo_name || 'Current photo'; pHint = 'Current photo';
        }
        if (showUrl) { photoImg.src = showUrl; photoImg.hidden = false; photoPh.hidden = true; }
        else         { photoImg.removeAttribute('src'); photoImg.hidden = true; photoPh.hidden = false; }
        photoName.textContent = pName; photoName.title = pName; photoHint.textContent = pHint;
        pickPhoto.textContent = showUrl ? 'Replace Photo' : 'Upload Photo';
        rmPhoto.disabled = !showUrl;

        // Dirty state
        var textDirty =
            nameInput.value     !== (current.name     || '') ||
            titleInput.value    !== (current.title    || '') ||
            orgInput.value      !== (current.org      || '') ||
            eyebrowInput.value  !== (current.eyebrow  || '') ||
            headingInput.value  !== (current.heading  || '') ||
            greetingInput.value !== (current.greeting || '') ||
            messageInput.value  !== (current.message  || '');
        var dirty = !!(textDirty || pending.photoFile || pending.removePhoto);
        saveBtn.disabled = !dirty; resetBtn.disabled = !dirty;
        badge.classList.toggle('draft', dirty); badge.classList.toggle('published', !dirty);
        badge.textContent = dirty ? 'Unsaved changes' : 'Saved';
    }

    pickPhoto.addEventListener('click', function () { photoInput.click(); });

    photoInput.addEventListener('change', function () {
        var f = photoInput.files[0]; photoInput.value = '';
        if (!f) return;
        if (PHOTO_TYPES.indexOf(f.type) === -1) { showAlert('Invalid image type. Allowed: JPG, PNG, WEBP.', true); return; }
        if (f.size > PHOTO_MAX) { showAlert('The photo is too large. Maximum size is 5 MB.', true); return; }
        clearAlert(); pending.photoFile = f; pending.removePhoto = false; render();
    });

    rmPhoto.addEventListener('click', function () {
        clearAlert(); pending.photoFile = null; pending.removePhoto = !!current.photo_url; render();
    });

    [nameInput, titleInput, orgInput, eyebrowInput, headingInput, greetingInput, messageInput].forEach(function (el) {
        el.addEventListener('input', function () { clearAlert(); render(); });
    });

    resetBtn.addEventListener('click', function () {
        pending = { photoFile: null, removePhoto: false };
        fillFields();
        clearAlert(); render();
    });

    saveBtn.addEventListener('click', function () {
        var fd = new FormData();
        fd.append('action', 'save_homepage_president');
        fd.append('csrf_token', csrf);
        fd.append('name', nameInput.value);
        fd.append('title', titleInput.value);
        fd.append('org', orgInput.value);
        fd.append('eyebrow', eyebrowInput.value);
        fd.append('heading', headingInput.value);
        fd.append('greeting', greetingInput.value);
        fd.append('message', messageInput.value);
        if (pending.photoFile) fd.append('photo', pending.photoFile);
        if (pending.removePhoto) fd.append('remove_photo', '1');

        saveBtn.disabled = true; resetBtn.disabled = true;
        var originalLabel = saveBtn.textContent; saveBtn.textContent = 'Saving…';

        fetch(AJAX_URL, { method: 'POST', body: fd })
            .then(function (res) { return res.json().catch(function () { return { success: false, error: 'Unexpected response from the server.' }; }); })
            .then(function (data) {
                if (data.success) {
                    current = data.president;
                    pending = { photoFile: null, removePhoto: false };
                    revokePreview();
                    fillFields();
                    render();
                    showAlert('Saved. The change is now live on the homepage.', false);
                } else {
                    showAlert(data.error || 'Could not save.', true);
                    render();
                }
            })
            .catch(function () { showAlert('Network error. Please try again.', true); render(); })
            .finally(function () { saveBtn.textContent = originalLabel; });
    });

    fillFields();
    render();
})();
</script>