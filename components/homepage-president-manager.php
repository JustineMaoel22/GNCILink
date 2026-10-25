<?php
$president = homepagePresidentAdminPayload(getHomepagePresident());
?>

<div class="data-card mb-3 president-manager" data-current="<?= htmlspecialchars(json_encode($president), ENT_QUOTES, 'UTF-8') ?>">
    <div class="data-card-header">
        <span class="data-card-title"><i class="bi bi-person-badge me-1"></i> Message from the President</span>
        <span class="badge-status published ms-auto president-status-badge">All changes saved</span>
    </div>

    <div style="padding:0 1.25rem 1.25rem">
        <p class="pm-intro">This is the welcome message on your homepage. Change anything you like, then click <strong>Publish</strong> and confirm to put your changes on the website.</p>

        <div class="row g-4">
            <!-- ═══ Left: things to edit ═══ -->
            <div class="col-xl-5">
                <div class="row g-3">

                    <!-- Photo -->
                    <div class="col-12">
                        <div class="dept-media-field">
                            <div class="dept-media-label"><span class="pm-num">1</span> Photo of the President <span class="pm-opt">optional</span></div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="dept-logo-preview president-photo-preview">
                                    <img class="president-photo-img" alt="" hidden>
                                    <i class="bi bi-person president-photo-placeholder"></i>
                                </div>
                                <div class="flex-grow-1" style="min-width:0">
                                    <div class="text-truncate president-photo-name fw-semibold">No photo chosen yet</div>
                                    <div class="pm-help president-photo-hint">JPG, PNG or WEBP, up to 5 MB</div>
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-3 flex-wrap">
                                <button type="button" class="btn btn-outline-secondary btn-pick-president-photo"><i class="bi bi-upload"></i> Choose a photo</button>
                                <button type="button" class="btn btn-outline-danger btn-remove-president-photo"><i class="bi bi-trash"></i> Remove photo</button>
                                <input type="file" id="president-photo" name="president_photo" aria-label="President photo" class="president-photo-input" data-photo-check accept="image/jpeg,image/png,image/webp" hidden>
                            </div>
                            <div class="pm-help mt-2">A clear, front-facing photo works best.</div>
                        </div>
                    </div>

                    <!-- Name, title, org -->
                    <div class="col-12">
                        <div class="dept-media-field">
                            <div class="dept-media-label"><span class="pm-num">2</span> Name and position</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="president-name">Full name</label>
                                    <input type="text" id="president-name" name="president_name" class="form-control president-name-input" placeholder="e.g. Geraldine G. Lim, MBA">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="president-title">Position</label>
                                    <input type="text" id="president-title" name="president_title" class="form-control president-title-input" placeholder="e.g. President">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="president-org">School name</label>
                                    <input type="text" id="president-org" name="president_org" class="form-control president-org-input" placeholder="e.g. Guagua National Colleges, Inc.">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Welcome message -->
                    <div class="col-12">
                        <div class="dept-media-field">
                            <div class="dept-media-label"><span class="pm-num">3</span> The welcome message</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="president-eyebrow">Small line above the heading</label>
                                    <input type="text" id="president-eyebrow" name="president_eyebrow" class="form-control president-eyebrow-input" placeholder="e.g. A Warm Welcome to">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="president-heading">Big heading</label>
                                    <input type="text" id="president-heading" name="president_heading" class="form-control president-heading-input" placeholder="e.g. Our New Students">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="president-greeting">Greeting</label>
                                    <input type="text" id="president-greeting" name="president_greeting" class="form-control president-greeting-input" placeholder="e.g. Welcome GNCians!">
                                </div>
                                <div class="col-12">
                                    <label class="form-label d-flex justify-content-between" for="president-message">
                                        <span>Message</span><span class="pm-help president-count">0 words</span>
                                    </label>
                                    <textarea id="president-message" name="president_message" class="form-control president-message-input" rows="6" placeholder="Type the President's welcome message here..."></textarea>
                                    <div class="pm-help">Tip: press Enter twice to start a new paragraph.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ Right: live preview ═══ -->
            <div class="col-xl-7">
                <div class="pm-preview-wrap">
                    <div class="dept-media-label mb-2"><i class="bi bi-eye"></i> What visitors will see <span class="pm-opt">updates as you type</span></div>
                    <div class="pm-preview" aria-live="polite">
                        <div class="pm-pv-label"><span class="pm-pv-line"></span>MESSAGE FROM THE PRESIDENT</div>
                        <div class="pm-pv-grid">
                            <div class="pm-pv-card">
                                <div class="pm-pv-seal"></div>
                                <div class="pm-pv-photo">
                                    <img class="pm-pv-img" alt="" hidden>
                                    <i class="bi bi-person pm-pv-ph"></i>
                                </div>
                                <div class="pm-pv-plate">
                                    <div class="pm-pv-name"></div>
                                    <div class="pm-pv-title"></div>
                                    <div class="pm-pv-org"></div>
                                </div>
                            </div>
                            <div class="pm-pv-text">
                                <div class="pm-pv-eyebrow"></div>
                                <h4 class="pm-pv-heading"></h4>
                                <div class="pm-pv-rule"></div>
                                <div class="pm-pv-greeting"></div>
                                <div class="pm-pv-message"></div>
                            </div>
                        </div>
                    </div>
                    <div class="pm-help mt-2">This is a close copy of the homepage section. Spacing and fonts may differ slightly on the real site.</div>
                </div>
            </div>
        </div>

        <div class="president-alert alert d-none mt-3 mb-0" role="status"></div>

        <div class="pm-footer">
            <span class="pm-dirty d-none" aria-live="polite"><i class="bi bi-pencil-fill"></i> You have changes that are not saved yet</span>
            <div class="d-flex gap-2 flex-wrap ms-auto">
                <button type="button" class="btn btn-outline-secondary btn-president-reset" disabled><i class="bi bi-arrow-counterclockwise"></i> Undo my changes</button>
                <button data-content-publish type="button" class="btn-gnc-primary btn-president-save" disabled><i class="bi bi-check2-circle"></i> Save Changes</button>
            </div>
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

/* ── Easier-to-use additions ── */
.president-manager .dept-media-label { font-size: .95rem; text-transform: none; letter-spacing: 0; display: flex; align-items: center; gap: .5rem; }
.president-manager .dept-logo-preview { width: 84px; height: 84px; flex: 0 0 84px; }
.president-manager .form-label { font-size: .92rem; font-weight: 600; color: #1f2a24; margin-bottom: .3rem; }
.president-manager .form-control { min-height: 44px; font-size: 1rem; }
.president-manager textarea.form-control { min-height: 0; line-height: 1.6; }
.president-manager .btn, .president-manager .btn-gnc-primary { min-height: 44px; display: inline-flex; align-items: center; justify-content: center; gap: .4rem; font-size: .95rem; }
.president-manager .btn-gnc-primary:disabled { opacity: .5; cursor: not-allowed; }
.pm-intro { font-size: .95rem; line-height: 1.55; color: #3b4640; margin: 0 0 1rem; }
.pm-help { font-size: .85rem; line-height: 1.45; color: #5d6963; }
.pm-opt { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: .1rem .5rem; border-radius: 20px; background: #eef0ee; color: #5d6963; }
.pm-num { width: 24px; height: 24px; border-radius: 50%; background: var(--gnc-green, #094024); color: #fff; font-size: .78rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }

.pm-preview-wrap { position: sticky; top: 1rem; }
.pm-preview { container-type: inline-size; background: #f9f6f1; border: 1px solid #e6e1d8; border-radius: 12px; padding: 1.25rem 1.5rem 1.75rem; }
.pm-pv-label { display: flex; align-items: center; gap: .6rem; font-family: 'Inter', sans-serif; font-weight: 700; font-size: clamp(10px, 1.9cqw, 13px); letter-spacing: .08em; color: #1F5E2C; margin-bottom: .9rem; }
.pm-pv-line { width: 30px; height: 2px; background: #e9b93c; display: inline-block; }
.pm-pv-grid { display: grid; grid-template-columns: minmax(0, 38%) minmax(0, 1fr); gap: clamp(1rem, 5cqw, 3rem); align-items: center; }

/* Green photo card with nameplate */
.pm-pv-card { position: relative; background: #0b4228; border-radius: 16px; overflow: hidden; aspect-ratio: 425 / 535; box-shadow: 0 7px 0 #e9b93c, 0 16px 28px rgba(0,0,0,.14); margin-bottom: 7px; }
.pm-pv-seal { position: absolute; inset: 8% 4% 22%; background: url('/assets/images/logos/gnc-logo-v2.svg') center / contain no-repeat; opacity: 50%; filter: brightness(2); }
.pm-pv-photo { position: absolute; inset: 0 0 24% 0; display: flex; align-items: flex-end; justify-content: center; color: rgba(255,255,255,.3); font-size: 4rem; }
.pm-pv-photo img { width: 100%; height: 100%; object-fit: cover; object-position: top center; }
.pm-pv-plate { position: absolute; left: 0; right: 0; bottom: 0; background: #0b4228; border: 1px solid #e9b93c; border-radius: 16px; text-align: center; padding: clamp(.5rem, 2.2cqw, 1rem) .6rem clamp(.6rem, 2.4cqw, 1.1rem); }
.pm-pv-name { font-family: 'Noto Serif', serif; font-weight: 700; color: #e9b93c; font-size: clamp(12px, 2.6cqw, 21px); line-height: 1.25; }
.pm-pv-title { font-family: 'Inter', sans-serif; font-weight: 700; color: #fff; text-transform: uppercase; font-size: clamp(10px, 1.8cqw, 14px); margin-top: .2rem; }
.pm-pv-org { font-family: 'Inter', sans-serif; color: rgba(255,255,255,.75); text-transform: uppercase; font-size: clamp(9px, 1.6cqw, 12.5px); margin-top: .15rem; }
.pm-pv-plate .pm-empty { color: rgba(255,255,255,.45) !important; }

/* Text side */
.pm-pv-eyebrow { font-family: 'Noto Serif', serif; color: #1F5E2C; font-size: clamp(14px, 3cqw, 26px); line-height: 1.2; }
.pm-pv-heading { font-family: 'Noto Serif', serif; font-weight: 700; color: #1F5E2C; font-size: clamp(22px, 5cqw, 46px); line-height: 1.12; margin: 0; }
.pm-pv-rule { width: clamp(36px, 8cqw, 76px); height: 3px; background: #e9b93c; margin: .8rem 0 1rem; }
.pm-pv-greeting { font-family: 'Inter', sans-serif; font-weight: 700; color: #1F5E2C; font-size: clamp(13px, 2.4cqw, 19px); margin-bottom: .6rem; }
.pm-pv-message p { font-family: 'Inter', sans-serif; font-style: italic; color: #333; font-size: clamp(12px, 2.2cqw, 17px); line-height: 1.75; margin: 0 0 .8rem; overflow-wrap: anywhere; }
.pm-empty { color: #a2a39b !important; font-style: italic; font-weight: 400 !important; }

@container (max-width: 480px) {
    .pm-pv-grid { grid-template-columns: 1fr; }
    /* width:100% is needed: with auto side margins the card would shrink to 0 wide (its photo is absolutely positioned) and vanish */
    .pm-pv-card { width: 100%; max-width: 260px; margin: 0 auto 7px; }
    /* Same as the real homepage on a phone: the text under the photo is centred */
    .pm-pv-text { text-align: center; }
    .pm-pv-rule { margin-left: auto; margin-right: auto; }
}

.pm-footer { position: sticky; bottom: 0; z-index: 5; display: flex; align-items: center; flex-wrap: wrap; gap: .75rem; margin: 1.25rem -1.25rem -1.25rem; padding: .9rem 1.25rem; background: rgba(255,255,255,.97); border-top: 1px solid #e6e6e0; box-shadow: 0 -6px 14px rgba(0,0,0,.05); }
.pm-dirty { font-size: .85rem; font-weight: 600; color: #8a6100; background: #fff3cd; padding: .3rem .75rem; border-radius: 20px; }
.president-manager :focus-visible { outline: 3px solid rgba(233,185,60,.9); outline-offset: 2px; }
@media (max-width: 1199px) { .pm-preview-wrap { position: static; } }
@media (max-width: 767px) { .pm-footer { position: static; box-shadow: none; } .pm-footer .d-flex { width: 100%; } .pm-footer .btn, .pm-footer .btn-gnc-primary { flex: 1 1 45%; } }
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
    // What the fields actually contain right after they are filled in. Compared against
    // (not the raw saved text) because the browser rewrites some text when it is put into a
    // field, e.g. Windows line breaks (\r\n) in the message become \n. Comparing against the
    // raw saved text made the page look "changed" the moment it loaded.
    var base = {};

    var $ = function (sel) { return card.querySelector(sel); };
    var photoImg = $('.president-photo-img'), photoPh = $('.president-photo-placeholder');
    var photoName = $('.president-photo-name'), photoHint = $('.president-photo-hint');
    var pickPhoto = $('.btn-pick-president-photo'), rmPhoto = $('.btn-remove-president-photo'), photoInput = $('.president-photo-input');
    var nameInput = $('.president-name-input'), titleInput = $('.president-title-input'), orgInput = $('.president-org-input');
    var eyebrowInput = $('.president-eyebrow-input'), headingInput = $('.president-heading-input'), greetingInput = $('.president-greeting-input');
    var messageInput = $('.president-message-input');
    var saveBtn = $('.btn-president-save'), resetBtn = $('.btn-president-reset');
    var badge = $('.president-status-badge'), alertEl = $('.president-alert');
    var dirtyNote = $('.pm-dirty'), countEl = $('.president-count');
    var pvEyebrow = $('.pm-pv-eyebrow'), pvHeading = $('.pm-pv-heading'), pvGreeting = $('.pm-pv-greeting');
    var pvMessage = $('.pm-pv-message'), pvName = $('.pm-pv-name'), pvTitle = $('.pm-pv-title'), pvOrg = $('.pm-pv-org');
    var pvImg = $('.pm-pv-img'), pvPh = $('.pm-pv-ph');
    function setPv(el, value, placeholder) {
        var has = value.trim() !== '';
        el.textContent = has ? value : placeholder;
        el.classList.toggle('pm-empty', !has);
    }
    // Message: a blank line starts a new paragraph (textContent only, never markup)
    function setPvMsg(el, value, placeholder) {
        var has = value.trim() !== '';
        var parts = has ? value.trim().split(/\n\s*\n/) : [placeholder];
        el.textContent = '';
        parts.forEach(function (t) {
            var p = document.createElement('p');
            p.textContent = t.trim();
            if (!has) p.className = 'pm-empty';
            el.appendChild(p);
        });
    }

    function showAlert(msg, isError) {
        if (window.ContentEditorNotice && window.ContentEditorNotice(msg, isError)) { alertEl.classList.add('d-none'); return; }
        alertEl.className = 'president-alert alert mt-3 mb-0 ' + (isError ? 'alert-danger' : 'alert-success');
        alertEl.textContent = msg;
        if (!isError) setTimeout(function () { alertEl.classList.add('d-none'); }, 7000);
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
        base = {
            name: nameInput.value, title: titleInput.value, org: orgInput.value,
            eyebrow: eyebrowInput.value, heading: headingInput.value,
            greeting: greetingInput.value, message: messageInput.value
        };
    }

    function render() {
        // Photo
        var showUrl = null, pName = 'No photo chosen yet', pHint = 'JPG, PNG or WEBP, up to 5 MB';
        if (pending.photoFile) {
            revokePreview();
            previewUrl = URL.createObjectURL(pending.photoFile);
            showUrl = previewUrl; pName = pending.photoFile.name; pHint = 'New photo. Click Save Changes to use it.';
        } else if (pending.removePhoto) {
            pHint = 'The photo will be removed when you click Save Changes.';
        } else if (current.photo_url) {
            showUrl = current.photo_url; pName = current.photo_name || 'Current photo'; pHint = 'This is the photo on the website now.';
        }
        if (showUrl) { photoImg.src = showUrl; photoImg.hidden = false; photoPh.hidden = true; }
        else         { photoImg.removeAttribute('src'); photoImg.hidden = true; photoPh.hidden = false; }
        photoName.textContent = pName; photoName.title = pName; photoHint.textContent = pHint;
        pickPhoto.innerHTML = showUrl ? '<i class="bi bi-arrow-repeat"></i> Change photo' : '<i class="bi bi-upload"></i> Choose a photo';
        rmPhoto.disabled = !showUrl;

        // Dirty state
        var textDirty =
            nameInput.value     !== base.name     ||
            titleInput.value    !== base.title    ||
            orgInput.value      !== base.org      ||
            eyebrowInput.value  !== base.eyebrow  ||
            headingInput.value  !== base.heading  ||
            greetingInput.value !== base.greeting ||
            messageInput.value  !== base.message;
        var dirty = !!(textDirty || pending.photoFile || pending.removePhoto);
        saveBtn.disabled = !dirty; resetBtn.disabled = !dirty;
        badge.classList.toggle('draft', dirty); badge.classList.toggle('published', !dirty);
        badge.textContent = dirty ? 'Changes not saved yet' : 'All changes saved';
        dirtyNote.classList.toggle('d-none', !dirty);
        card.dataset.dirty = dirty ? '1' : '';

        // Word count + live preview (textContent only, so typed text is never treated as markup)
        var msg = messageInput.value.trim();
        var words = msg ? msg.split(/\s+/).length : 0;
        countEl.textContent = words + (words === 1 ? ' word' : ' words');
        setPv(pvEyebrow, eyebrowInput.value, 'Small line above the heading');
        setPv(pvHeading, headingInput.value, 'Big heading');
        setPv(pvGreeting, greetingInput.value, 'Greeting');
        setPvMsg(pvMessage, messageInput.value, 'Your welcome message will show up here as you type it.');
        setPv(pvName, nameInput.value, 'Full name');
        setPv(pvTitle, titleInput.value, 'Position');
        setPv(pvOrg, orgInput.value, 'School name');
        if (showUrl) { pvImg.src = showUrl; pvImg.hidden = false; pvPh.hidden = true; }
        else         { pvImg.removeAttribute('src'); pvImg.hidden = true; pvPh.hidden = false; }
    }

    pickPhoto.addEventListener('click', function () { photoInput.click(); });

    photoInput.addEventListener('change', function () {
        var f = photoInput.files[0]; photoInput.value = '';
        if (!f) return;
        if (PHOTO_TYPES.indexOf(f.type) === -1) { showAlert('That file type is not supported. Please choose a JPG, PNG or WEBP picture.', true); return; }
        if (f.size > PHOTO_MAX) { showAlert('That photo is too big (over 5 MB). Please choose a smaller one.', true); return; }
        clearAlert(); pending.photoFile = f; pending.removePhoto = false; render();
    });

    rmPhoto.addEventListener('click', function () {
        clearAlert(); pending.photoFile = null; pending.removePhoto = !!current.photo_url; render();
    });

    [nameInput, titleInput, orgInput, eyebrowInput, headingInput, greetingInput, messageInput].forEach(function (el) {
        el.addEventListener('input', function () { clearAlert(); render(); });
    });

    resetBtn.addEventListener('click', async function () {
        if (!await window.AdminDialogs.confirm('Undo all your changes?\n\nEverything will go back to what is on the website now.')) return;
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
        var originalLabel = saveBtn.innerHTML; saveBtn.textContent = 'Saving…';

        fetch(AJAX_URL, { method: 'POST', body: fd })
            .then(function (res) { return res.json().catch(function () { return { success: false, error: 'Something went wrong on the server. Please try again, or refresh the page.' }; }); })
            .then(function (data) {
                if (data.success) {
                    current = data.president;
                    pending = { photoFile: null, removePhoto: false };
                    revokePreview();
                    fillFields();
                    render();
                    showAlert('Done! Your change is now live on the homepage.', false);
                } else {
                    showAlert(data.error || 'We could not save your changes. Please try again.', true);
                    render();
                }
            })
            .catch(function () { showAlert('We could not reach the server. Please check your internet connection and try again.', true); render(); })
            .finally(function () { saveBtn.innerHTML = originalLabel; });
    });

    // Warn before leaving the page with changes that were never saved.
    window.addEventListener('beforeunload', function (e) {
        if (window.__veLeaving) return;
        if (card.dataset.dirty === '1') { e.preventDefault(); e.returnValue = ''; }
    });

    fillFields();
    render();
})();
</script>
