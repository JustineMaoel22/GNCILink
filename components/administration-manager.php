<?php
/**
 * Content Editor → Administration
 * Edit the four tabs of the public Administration page:
 *   Board of Trustees · Corporate Officers · Board of Administration ·
 *   Heads of Student Services
 *
 * Included from content-edit.php for the 'administration' page. Saves go to
 * /admin/action/page-content-handler.php (admin_* actions); every save
 * returns the refreshed sections, which this script re-renders.
 */
$admInitial = administrationAdminPayload();
$admFlags   = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>

<?php if (empty($admInitial)): ?>
    <div class="data-card">
        <div class="empty-state">
            <i class="bi bi-database-exclamation"></i>
            <p>The Administration tables aren't set up yet. Run
               <code>sql/administration-migration.sql</code> once, then reload this page.</p>
        </div>
    </div>
<?php else: ?>

<div id="adm-root">
    <div class="adm-tabs" id="adm-tabs" role="tablist" aria-label="Administration sections"></div>
    <div id="adm-alert" class="alert d-none" role="status"></div>
    <div id="adm-section"></div>
</div>

<!-- ───────── Person modal (Trustees / Officers / Administration) ───────── -->
<div class="modal fade" id="admPersonModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="adm-person-form" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title" id="adm-person-title">Add person</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="adm-person-name">Full name <span class="adm-req">required</span></label>
          <input type="text" class="form-control" id="adm-person-name" maxlength="255" required
                 placeholder="e.g. Ronald Luis S. Goseco, MBA">
          <div class="form-text">Type the name exactly as it should appear on the website, including titles like Dr. or MBA.</div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="adm-person-role">Position</label>
          <input type="text" class="form-control" id="adm-person-role" maxlength="500"
                 placeholder="e.g. Chairman">
          <div class="form-text">Their job title. You can leave this empty.</div>
        </div>
        <div class="mb-1">
          <label class="form-label" for="adm-person-photo">Photo</label>
          <div class="adm-photo-row">
            <img class="adm-photo-preview" id="adm-person-preview" alt="">
            <div class="flex-grow-1">
              <input type="file" class="form-control" id="adm-person-photo" accept="image/jpeg,image/png,image/webp">
              <div class="form-text">Choose a clear, front-facing photo. JPG, PNG or WEBP, up to 5 MB. Not required.</div>
              <div class="form-check mt-2" id="adm-person-remove-wrap">
                <input class="form-check-input" type="checkbox" id="adm-person-remove">
                <label class="form-check-label small" for="adm-person-remove">Remove the current photo</label>
              </div>
            </div>
          </div>
        </div>
        <div class="alert alert-danger d-none mt-3 mb-0" id="adm-person-error"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn-gnc-primary" id="adm-person-save"><i class="bi bi-check2-circle"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ───────── Office modal (Heads of Student Services) ───────── -->
<div class="modal fade" id="admOfficeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <form class="modal-content" id="adm-office-form" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title" id="adm-office-title">Add office</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="adm-office-name">Office name <span class="adm-req">required</span></label>
            <input type="text" class="form-control" id="adm-office-name" maxlength="255" required placeholder="e.g. Registrar Office">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="adm-office-head">Name of the head of office <span class="adm-req">required</span></label>
            <input type="text" class="form-control" id="adm-office-head" maxlength="255" required placeholder="e.g. Julie B. Domingo, MBA">
          </div>
          <div class="col-12">
            <label class="form-label" for="adm-office-role">Their position <span class="adm-opt">optional</span></label>
            <input type="text" class="form-control" id="adm-office-role" maxlength="500" placeholder="e.g. Chief Registrar">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="adm-office-phone">Phone number <span class="adm-opt">optional</span></label>
            <input type="text" class="form-control" id="adm-office-phone" maxlength="100" placeholder="(045) 900-4473 Loc. 106">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="adm-office-email">Email address <span class="adm-opt">optional</span></label>
            <input type="email" class="form-control" id="adm-office-email" maxlength="255" placeholder="registrar@gnc.edu.ph">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="adm-office-location">Where the office is <span class="adm-opt">optional</span></label>
            <input type="text" class="form-control" id="adm-office-location" maxlength="255" placeholder="BETA Hall | Floor 2 | Room 200">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="adm-office-hours">Office hours <span class="adm-opt">optional</span></label>
            <textarea class="form-control" id="adm-office-hours" rows="2"
                      placeholder="Monday - Friday | 8:00AM - 5:00PM&#10;Saturday | 8:00AM - 3:00PM"></textarea>
            <div class="form-text">Press Enter to start a new line for each day or time.</div>
          </div>
          <div class="col-12">
            <label class="form-label" for="adm-office-services">Services offered <span class="adm-opt">optional</span></label>
            <textarea class="form-control" id="adm-office-services" rows="6"
                      placeholder="Enrollment Services&#10;Student Records Services"></textarea>
            <div class="form-text">Type one service per line (press Enter between them). Leave empty if there is nothing to list.</div>
          </div>
          <div class="col-12">
            <label class="form-label" for="adm-office-photo">Photo of the head <span class="adm-opt">optional</span></label>
            <div class="adm-photo-row">
              <img class="adm-photo-preview" id="adm-office-preview" alt="">
              <div class="flex-grow-1">
                <input type="file" class="form-control" id="adm-office-photo" accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPG, PNG or WEBP, up to 5 MB.</div>
                <div class="form-check mt-2" id="adm-office-remove-wrap">
                  <input class="form-check-input" type="checkbox" id="adm-office-remove">
                  <label class="form-check-label small" for="adm-office-remove">Remove the current photo</label>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="alert alert-danger d-none mt-3 mb-0" id="adm-office-error"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn-gnc-primary" id="adm-office-save"><i class="bi bi-check2-circle"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<style>
.adm-tabs { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:1rem; }
.adm-tab { border:1px solid #d9d9d9; background:#fff; color:#444; border-radius:999px; padding:.4rem 1rem; font-size:.88rem; cursor:pointer; }
.adm-tab.active { background:var(--gnc-green-light, #145c3a); border-color:var(--gnc-green-light, #145c3a); color:#fff; }
.adm-count { opacity:.7; margin-left:.35rem; font-size:.78rem; }
.adm-list { display:flex; flex-direction:column; gap:.5rem; padding:0 1.25rem 1.25rem; }
.adm-item { display:flex; align-items:center; gap:.75rem; border:1px solid #e8e8e8; border-radius:10px; padding:.55rem .75rem; background:#fff; }
.adm-item.dragging { opacity:.4; }
.adm-item.drag-over { border-color:var(--gnc-gold, #c9a227); box-shadow:0 0 0 2px rgba(201,162,39,.25); }
.adm-handle { cursor:grab; color:#999; }
.adm-num { width:1.6rem; text-align:center; color:#888; font-size:.82rem; }
.adm-thumb { width:48px; height:48px; border-radius:8px; object-fit:cover; background:#f1f1f1; flex-shrink:0; }
.adm-info { min-width:0; flex:1; }
.adm-name { font-weight:600; font-size:.92rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.adm-sub { color:#777; font-size:.8rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.adm-actions { display:flex; gap:.25rem; flex-shrink:0; }
.adm-actions .btn { padding:.2rem .5rem; }
.adm-meta-form { padding:0 1.25rem 1.25rem; }
.adm-photo-row { display:flex; gap:1rem; align-items:flex-start; }
.adm-photo-preview { width:84px; height:84px; border-radius:10px; object-fit:cover; background:#f1f1f1; flex-shrink:0; }
.adm-empty { padding:1.25rem; text-align:center; color:#888; font-size:.9rem; }
.adm-archived-box { margin:0 1.25rem 1.25rem; border-top:1px solid #eee; padding-top:.85rem; }
.adm-archived-box > summary { cursor:pointer; font-weight:600; font-size:.9rem; color:#555; user-select:none; }
.adm-archived-box .adm-list { padding:0; margin-top:.5rem; }
.adm-archived-box .adm-empty { padding:.75rem; }
.adm-item.adm-archived { background:#fafafa; border-style:dashed; }
.adm-archived .adm-thumb { opacity:.55; filter:grayscale(1); }
.adm-archived .adm-name { color:#777; }
.adm-badge-archived { font-size:.72rem; color:#777; background:#ececec; border-radius:999px; padding:.15rem .6rem; white-space:nowrap; flex-shrink:0; }

/* ── Easier-to-use Administration editor ── */
#adm-root .form-label, #admPersonModal .form-label, #admOfficeModal .form-label { font-weight:600; font-size:.95rem; color:#1f2a24; }
#adm-root .form-control, #admPersonModal .form-control, #admOfficeModal .form-control { min-height:44px; font-size:1rem; }
#adm-root textarea.form-control, #admPersonModal textarea.form-control, #admOfficeModal textarea.form-control { min-height:0; line-height:1.55; }
#adm-root .form-text, #admPersonModal .form-text, #admOfficeModal .form-text { font-size:.85rem; color:#5d6963; }
#adm-root .btn, #admPersonModal .btn, #admOfficeModal .btn, #adm-root .btn-gnc-gold, #adm-root .btn-gnc-primary,
#admPersonModal .btn-gnc-primary, #admOfficeModal .btn-gnc-primary { min-height:42px; display:inline-flex; align-items:center; justify-content:center; gap:.4rem; font-size:.92rem; }
.adm-req, .adm-opt { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; padding:.1rem .5rem; border-radius:20px; margin-left:.3rem; vertical-align:middle; }
.adm-req { background:#fdecc8; color:#7a5600; }
.adm-opt { background:#eef0ee; color:#5d6963; }

.adm-note { display:flex; gap:.7rem; align-items:flex-start; background:#fff8e1; border:1px solid #f2dc9b; border-radius:12px; padding:.8rem 1rem; margin-bottom:1rem; font-size:.92rem; line-height:1.5; color:#4a3b00; }
.adm-note i { margin-top:.2rem; color:#c9a227; }
.adm-hint { font-size:.88rem; color:#5d6963; padding:0 1.25rem; margin:0 0 .75rem; }
#adm-alert { position:sticky; top:.5rem; z-index:20; display:flex; gap:.5rem; align-items:center; font-weight:600; box-shadow:0 6px 18px rgba(0,0,0,.12); }
#adm-alert.d-none { display:none !important; }

.adm-tab { display:inline-flex; align-items:center; gap:.45rem; padding:.55rem 1.1rem; font-size:.95rem; font-weight:600; min-height:44px; }
.adm-tab:hover { border-color:var(--gnc-green-light, #145c3a); }
.adm-count { background:rgba(0,0,0,.08); border-radius:20px; padding:0 .5rem; opacity:1; font-size:.78rem; }
.adm-tab.active .adm-count { background:rgba(255,255,255,.25); }

.adm-item { flex-wrap:wrap; padding:.75rem .9rem; }
.adm-item:hover { border-color:rgba(9,64,36,.3); }
.adm-num { width:1.8rem; height:1.8rem; line-height:1.8rem; border-radius:50%; background:var(--gnc-green, #094024); color:#fff; font-weight:700; }
.adm-thumb { width:56px; height:56px; }
.adm-name { font-size:1rem; }
.adm-sub { font-size:.85rem; }
.adm-handle { font-size:1.2rem; padding:.3rem; }
.adm-actions { gap:.4rem; flex-wrap:wrap; }
.adm-actions .btn { padding:.3rem .75rem; }
.adm-move { display:inline-flex; gap:.25rem; }
.adm-empty-state { text-align:center; padding:2rem 1.25rem 2.25rem; color:#5d6963; }
.adm-empty-state > i { font-size:2.6rem; color:#b9c2bd; display:block; margin-bottom:.5rem; }
.adm-empty-state p { font-size:1rem; margin-bottom:1rem; }
.adm-archived-box > summary { font-size:.95rem; padding:.4rem 0; }
:is(#adm-root, #admPersonModal, #admOfficeModal) :focus-visible { outline:3px solid rgba(233,185,60,.9); outline-offset:2px; }

@media (max-width:767px) {
  .adm-item .adm-actions { width:100%; justify-content:flex-start; padding-top:.5rem; border-top:1px solid #f0f0f0; }
  .adm-tab { flex:1 1 45%; justify-content:center; }
}
@media (prefers-reduced-motion: reduce) { #adm-alert { box-shadow:none; } }
</style>

<script>
(function () {
    var URL_ = '/admin/action/page-content-handler.php';
    var CSRF = <?= json_encode($csrfToken ?? '') ?>;
    var PLACEHOLDER = '/assets/images/logos/gnc-logo-v1.svg';

    var state = { sections: <?= json_encode($admInitial, $admFlags) ?>, active: null, dragId: null, archivedOpen: false };
    state.active = state.sections.length ? state.sections[0].key : null;

    // Modals live on <body> so they always stack above the admin layout.
    ['admPersonModal', 'admOfficeModal'].forEach(function (id) {
        var m = document.getElementById(id);
        if (m && m.parentNode !== document.body) document.body.appendChild(m);
    });

    var $ = function (id) { return document.getElementById(id); };
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function current() {
        return state.sections.filter(function (s) { return s.key === state.active; })[0];
    }
    function itemsOf(sec) { return sec.layout === 'offices' ? sec.offices : sec.people; }
    // Live cards (shown on the website, orderable) vs archived ones (hidden, restorable).
    function activeOf(sec)   { return itemsOf(sec).filter(function (x) { return !x.archived; }); }
    function archivedOf(sec) { return itemsOf(sec).filter(function (x) { return  x.archived; }); }

    // ── alerts ──
    var alertTimer = null;
    function flash(msg, isError) {
        var el = $('adm-alert');
        el.className = 'alert mb-3 ' + (isError ? 'alert-danger' : 'alert-success');
        el.innerHTML = '<i class="bi ' + (isError ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill') + '"></i> <span>' + esc(msg) + '</span>';
        clearTimeout(alertTimer);
        alertTimer = setTimeout(function () { el.classList.add('d-none'); }, 7000);
    }

    // ── network ──
    function send(action, fields, file) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('csrf_token', CSRF);
        Object.keys(fields || {}).forEach(function (k) { fd.append(k, fields[k]); });
        if (file) fd.append('photo', file);
        return fetch(URL_, { method: 'POST', body: fd })
            .then(function (res) {
                return res.json().catch(function () { return { success: false, error: 'Something went wrong on the server. Please try again, or refresh the page.' }; });
            })
            .then(function (data) {
                if (!data.success) throw new Error(data.error || 'Something went wrong. Please try again.');
                if (data.sections) state.sections = data.sections;
                return data;
            });
    }

    // ── rendering ──
    function renderTabs() {
        $('adm-tabs').innerHTML = state.sections.map(function (s) {
            return '<button type="button" class="adm-tab' + (s.key === state.active ? ' active' : '') +
                '" role="tab" data-key="' + esc(s.key) + '" title="' + activeOf(s).length + ' shown on the website">' +
                '<i class="bi ' + (s.layout === 'offices' ? 'bi-building' : 'bi-people') + '"></i> ' + esc(s.title) +
                '<span class="adm-count">' + activeOf(s).length + '</span></button>';
        }).join('');
    }

    function actBtn(act, cls, icon, label, disabled, text) {
        return '<button type="button" class="btn ' + cls + ' btn-sm" data-act="' + act + '" title="' + label +
            '" aria-label="' + label + '"' + (disabled ? ' disabled' : '') + '><i class="bi ' + icon + '"></i>' +
            (text ? '<span>' + text + '</span>' : '') + '</button>';
    }

    function rowHtml(it, i, count, sec) {
        var isOffices = sec.layout === 'offices';
        var title = isOffices ? it.office : it.name;
        var sub   = isOffices ? (it.name + (it.role ? ' — ' + it.role : '')) : it.role;
        var thumb = '<img class="adm-thumb" src="' + esc(it.image || PLACEHOLDER) + '" alt="">';
        var info  = '<div class="adm-info"><div class="adm-name">' + esc(title) + '</div>' +
                    '<div class="adm-sub">' + esc(sub) + '</div></div>';

        if (it.archived) {
            return '<div class="adm-item adm-archived" data-id="' + it.id + '">' + thumb + info +
                '<span class="adm-badge-archived">Archived ' + esc(String(it.archived_at || '').slice(0, 10)) + '</span>' +
                '<div class="adm-actions">' +
                actBtn('restore', 'btn-outline-success', 'bi-arrow-counterclockwise', 'Restore to the website', false, 'Restore') +
                actBtn('delete',  'btn-outline-danger',  'bi-trash', 'Delete for good', false, 'Delete') +
                '</div></div>';
        }
        return '<div class="adm-item" draggable="true" data-id="' + it.id + '">' +
            '<span class="adm-handle" title="Drag to change the order"><i class="bi bi-grip-vertical"></i></span>' +
            '<span class="adm-num">' + (i + 1) + '</span>' + thumb + info +
            '<div class="adm-actions">' +
            '<span class="adm-move">' +
            actBtn('up',   'btn-outline-secondary', 'bi-arrow-up',   'Move up (show earlier)',   i === 0) +
            actBtn('down', 'btn-outline-secondary', 'bi-arrow-down', 'Move down (show later)', i === count - 1) +
            '</span>' +
            actBtn('edit', 'btn-outline-primary', 'bi-pencil', 'Change the details or photo', false, 'Edit') +
            (sec.can_archive ? actBtn('archive', 'btn-outline-secondary', 'bi-archive', 'Archive (hidden from the website, you can restore it later)', false, 'Archive') : '') +
            actBtn('delete', 'btn-outline-danger', 'bi-trash', 'Delete for good', false, 'Delete') +
            '</div></div>';
    }

    function renderSection() {
        var sec = current();
        if (!sec) { $('adm-section').innerHTML = ''; return; }
        var isOffices = sec.layout === 'offices';
        var active = activeOf(sec), archived = archivedOf(sec);

        var rows = active.map(function (it, i) { return rowHtml(it, i, active.length, sec); }).join('');

        var archivedBlock = '';
        if (sec.can_archive) {
            archivedBlock =
              '<details class="adm-archived-box" id="adm-archived-box"' + (state.archivedOpen ? ' open' : '') + '>' +
                '<summary><i class="bi bi-archive"></i> Archived<span class="adm-count">' + archived.length + '</span></summary>' +
                '<p class="text-muted small mt-2 mb-2">Archived cards are hidden from the website but not deleted. Click “Restore” to put one back at the end of the list.</p>' +
                (archived.length
                    ? '<div class="adm-list">' + archived.map(function (it, i) { return rowHtml(it, i, archived.length, sec); }).join('') + '</div>'
                    : '<div class="adm-empty">Nothing archived.</div>') +
              '</details>';
        }

        var addLabel = isOffices ? 'Add an office' : 'Add a person';
        var noun = isOffices ? 'office' : 'person';

        $('adm-section').innerHTML =
            '<div class="adm-note"><i class="bi bi-lightning-charge-fill"></i><span><strong>Changes go live right away.</strong> ' +
              'There is no separate Publish step here. As soon as you save, visitors see the change on the website.</span></div>' +
            '<div class="data-card mb-3">' +
              '<div class="data-card-header"><span class="data-card-title">Heading above the cards</span></div>' +
              '<form class="adm-meta-form" id="adm-meta-form">' +
                '<div class="mb-3"><label class="form-label" for="adm-meta-title">Heading</label>' +
                '<input type="text" class="form-control" id="adm-meta-title" maxlength="255" required value="' + esc(sec.title) + '"></div>' +
                '<div class="mb-3"><label class="form-label" for="adm-meta-desc">Short text under the heading <span class="adm-opt">optional</span></label>' +
                '<textarea class="form-control" id="adm-meta-desc" rows="3" maxlength="2000">' + esc(sec.desc) + '</textarea></div>' +
                '<div class="d-flex justify-content-end"><button type="submit" class="btn-gnc-primary btn-sm" id="adm-meta-save"><i class="bi bi-check2-circle"></i> Save heading</button></div>' +
              '</form>' +
            '</div>' +
            '<div class="data-card">' +
              '<div class="data-card-header">' +
                '<span class="data-card-title">' + (isOffices ? 'Offices' : 'People') + ' shown on the website <span class="adm-count">' + active.length + '</span></span>' +
                '<button type="button" class="btn-gnc-gold btn-sm ms-auto" id="adm-add"><i class="bi bi-plus-lg"></i> ' + addLabel + '</button>' +
              '</div>' +
              (rows
                ? '<p class="adm-hint mt-2"><i class="bi bi-hand-index-thumb"></i> Use the arrows, or drag a card by its handle, to change the order. The first card shows first on the website.</p>' +
                  '<div class="adm-list" id="adm-list">' + rows + '</div>'
                : '<div class="adm-empty-state"><i class="bi ' + (isOffices ? 'bi-building' : 'bi-people') + '"></i>' +
                  '<p><strong>Nothing here yet.</strong><br>Click the button below to add the first ' + noun + '.</p>' +
                  '<button type="button" class="btn-gnc-gold adm-add-btn"><i class="bi bi-plus-lg"></i> ' + addLabel + '</button></div>') +
              archivedBlock +
            '</div>';
    }

    function render() { renderTabs(); renderSection(); }

    // ── tabs ──
    $('adm-tabs').addEventListener('click', function (e) {
        var b = e.target.closest('.adm-tab');
        if (!b) return;
        state.active = b.dataset.key;
        render();
    });

    // ── section heading save ──
    $('adm-section').addEventListener('submit', function (e) {
        if (e.target.id !== 'adm-meta-form') return;
        e.preventDefault();
        var btn = $('adm-meta-save');
        btn.disabled = true;
        send('admin_save_section', {
            section_key: state.active,
            title: $('adm-meta-title').value,
            description: $('adm-meta-desc').value
        }).then(function (d) { render(); flash(d.message || 'Saved. The new heading is now on the website.', false); })
          .catch(function (err) { flash(err.message, true); })
          .finally(function () { btn.disabled = false; });
    });

    // ── list actions ──
    function findItem(id) {
        return itemsOf(current()).filter(function (x) { return String(x.id) === String(id); })[0];
    }
    function saveOrder(ids) {
        return send('admin_reorder', { section_key: state.active, order: JSON.stringify(ids) })
            .then(function () { render(); })
            .catch(function (err) { flash(err.message, true); render(); });
    }
    function move(id, delta) {
        var ids = activeOf(current()).map(function (x) { return x.id; });
        var i = ids.indexOf(Number(id)), j = i + delta;
        if (i < 0 || j < 0 || j >= ids.length) return;
        ids.splice(j, 0, ids.splice(i, 1)[0]);
        saveOrder(ids);
    }

    $('adm-section').addEventListener('click', function (e) {
        if (e.target.closest('#adm-add, .adm-add-btn')) {
            return current().layout === 'offices' ? openOffice(null) : openPerson(null);
        }
        var btn = e.target.closest('[data-act]');
        if (!btn) return;
        var row = btn.closest('.adm-item');
        var id = row.dataset.id, act = btn.dataset.act, sec = current();
        if (act === 'up')   return move(id, -1);
        if (act === 'down') return move(id, 1);
        if (act === 'edit') {
            return sec.layout === 'offices' ? openOffice(findItem(id)) : openPerson(findItem(id));
        }
        if (act === 'archive' || act === 'restore') {
            var isOff = sec.layout === 'offices';
            var fields = isOff ? { office_id: id } : { person_id: id };
            fields.archived = act === 'archive' ? '1' : '0';
            btn.disabled = true;
            if (act === 'archive') state.archivedOpen = true;   // so the card can be seen where it went
            return send(isOff ? 'admin_archive_office' : 'admin_archive_person', fields)
                .then(function (d) { render(); flash(d.message || 'Done.', false); })
                .catch(function (err) { btn.disabled = false; flash(err.message, true); });
        }
        if (act === 'delete') {
            var it = findItem(id);
            var label = sec.layout === 'offices' ? it.office : it.name;
            var confirmMsg = it.archived
                ? 'Delete “' + label + '” for good?\n\nThis cannot be undone.'
                : (sec.can_archive
                    ? 'Delete “' + label + '” for good?\n\nThis cannot be undone.\n\nTip: if you may need it again, click Cancel and use “Archive” instead.'
                    : 'Delete “' + label + '”?\n\nIt will be removed from the website right away.');
            if (!window.confirm(confirmMsg)) return;
            btn.disabled = true;
            send(sec.layout === 'offices' ? 'admin_delete_office' : 'admin_delete_person',
                 sec.layout === 'offices' ? { office_id: id } : { person_id: id })
                .then(function (d) { render(); flash(d.message || 'Deleted.', false); })
                .catch(function (err) { btn.disabled = false; flash(err.message, true); });
        }
    });

    // ── remember if the Archived box is open (the 'toggle' event doesn't bubble, so listen in capture) ──
    $('adm-section').addEventListener('toggle', function (e) {
        if (e.target && e.target.id === 'adm-archived-box') state.archivedOpen = e.target.open;
    }, true);

    // ── drag & drop ──
    var listEl = null;
    $('adm-section').addEventListener('dragstart', function (e) {
        var row = e.target.closest && e.target.closest('.adm-item');
        if (!row) return;
        state.dragId = row.dataset.id;
        row.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', state.dragId); } catch (x) {}
    });
    $('adm-section').addEventListener('dragover', function (e) {
        var row = e.target.closest && e.target.closest('.adm-item');
        if (!row || state.dragId === null) return;
        e.preventDefault();
        document.querySelectorAll('.adm-item.drag-over').forEach(function (r) { r.classList.remove('drag-over'); });
        if (row.dataset.id !== state.dragId) row.classList.add('drag-over');
    });
    $('adm-section').addEventListener('drop', function (e) {
        var row = e.target.closest && e.target.closest('.adm-item');
        if (!row || state.dragId === null) return;
        e.preventDefault();
        var ids = activeOf(current()).map(function (x) { return x.id; });
        var from = ids.indexOf(Number(state.dragId)), to = ids.indexOf(Number(row.dataset.id));
        state.dragId = null;
        if (from < 0 || to < 0 || from === to) { render(); return; }
        ids.splice(to, 0, ids.splice(from, 1)[0]);
        saveOrder(ids);
    });
    $('adm-section').addEventListener('dragend', function () {
        state.dragId = null;
        document.querySelectorAll('.adm-item.dragging, .adm-item.drag-over').forEach(function (r) {
            r.classList.remove('dragging', 'drag-over');
        });
    });

    // ── modal helpers ──
    function modal(id) { return bootstrap.Modal.getOrCreateInstance($(id)); }
    function showError(id, msg) {
        var el = $(id);
        el.textContent = msg || '';
        el.classList.toggle('d-none', !msg);
    }
    var MAX_BYTES = 5 * 1024 * 1024;
    function wirePhoto(fileId, previewId, removeId, removeWrapId) {
        $(fileId).addEventListener('change', function () {
            var f = this.files[0];
            if (!f) return;
            if (f.size > MAX_BYTES) { this.value = ''; flash('That photo is too big (over 5 MB). Please choose a smaller one.', true); return; }
            var r = new FileReader();
            r.onload = function (ev) { $(previewId).src = ev.target.result; $(removeId).checked = false; };
            r.readAsDataURL(f);
        });
        $(removeId).addEventListener('change', function () {
            if (this.checked) { $(fileId).value = ''; $(previewId).src = PLACEHOLDER; }
        });
    }
    wirePhoto('adm-person-photo', 'adm-person-preview', 'adm-person-remove');
    wirePhoto('adm-office-photo', 'adm-office-preview', 'adm-office-remove');

    // ── person modal ──
    var editingPerson = null;
    function openPerson(p) {
        editingPerson = p;
        $('adm-person-title').textContent = p ? 'Edit this person' : 'Add a new person';
        $('adm-person-name').value = p ? p.name : '';
        $('adm-person-role').value = p ? p.role : '';
        $('adm-person-photo').value = '';
        $('adm-person-remove').checked = false;
        $('adm-person-preview').src = (p && p.image) || PLACEHOLDER;
        $('adm-person-remove-wrap').style.display = (p && p.image) ? '' : 'none';
        showError('adm-person-error', '');
        modal('admPersonModal').show();
    }
    $('adm-person-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = $('adm-person-save');
        btn.disabled = true;
        send('admin_save_person', {
            section_key: state.active,
            person_id: editingPerson ? editingPerson.id : 0,
            name: $('adm-person-name').value,
            role: $('adm-person-role').value,
            remove_photo: $('adm-person-remove').checked ? '1' : '0'
        }, $('adm-person-photo').files[0] || null)
            .then(function (d) { modal('admPersonModal').hide(); render(); flash(d.message || 'Saved.', false); })
            .catch(function (err) { showError('adm-person-error', err.message); })
            .finally(function () { btn.disabled = false; });
    });

    // ── office modal ──
    var editingOffice = null;
    function openOffice(o) {
        editingOffice = o;
        var c = (o && o.contact) || {};
        $('adm-office-title').textContent = o ? 'Edit this office' : 'Add a new office';
        $('adm-office-name').value     = o ? o.office : '';
        $('adm-office-head').value     = o ? o.name : '';
        $('adm-office-role').value     = o ? o.role : '';
        $('adm-office-phone').value    = c.phone || '';
        $('adm-office-email').value    = c.email || '';
        $('adm-office-location').value = c.location || '';
        $('adm-office-hours').value    = c.hours || '';
        $('adm-office-services').value = o ? (o.services || []).join('\n') : '';
        $('adm-office-photo').value = '';
        $('adm-office-remove').checked = false;
        $('adm-office-preview').src = (o && o.image) || PLACEHOLDER;
        $('adm-office-remove-wrap').style.display = (o && o.image) ? '' : 'none';
        showError('adm-office-error', '');
        modal('admOfficeModal').show();
    }
    $('adm-office-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = $('adm-office-save');
        btn.disabled = true;
        send('admin_save_office', {
            section_key: state.active,
            office_id: editingOffice ? editingOffice.id : 0,
            office_name: $('adm-office-name').value,
            head_name: $('adm-office-head').value,
            head_role: $('adm-office-role').value,
            phone: $('adm-office-phone').value,
            email: $('adm-office-email').value,
            location: $('adm-office-location').value,
            hours: $('adm-office-hours').value,
            services: $('adm-office-services').value,
            remove_photo: $('adm-office-remove').checked ? '1' : '0'
        }, $('adm-office-photo').files[0] || null)
            .then(function (d) { modal('admOfficeModal').hide(); render(); flash(d.message || 'Saved.', false); })
            .catch(function (err) { showError('adm-office-error', err.message); })
            .finally(function () { btn.disabled = false; });
    });

    render();
})();
</script>

<?php endif; ?>