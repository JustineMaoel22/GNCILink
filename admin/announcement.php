<?php
/**
 * GNC Admin Panel - Announcements
 * /admin/announcement.php
 *
 * Renders the list + the create/edit modal. All writes (create, update,
 * delete) are handled by /admin/action/create-announcement.php, which
 * the modal form and the per-row delete forms POST to directly.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/admin-functions.php';

initSession();
requireLogin();

// Flash from a failed submit (set by the action handler)
$flashErrors = $_SESSION['flash_errors'] ?? [];
$flashOld    = $_SESSION['flash_old'] ?? [];
unset($_SESSION['flash_errors'], $_SESSION['flash_old']);

// ============================================================
// LIST DATA
// ============================================================
$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['search'] ?? '');
$filters      = [];
if ($statusFilter !== '') $filters['status'] = $statusFilter;
if ($search !== '') $filters['search'] = $search;

$announcements = getAnnouncements($filters, 50, 0);

try {
    $categories = getDB()->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Which modal (if any) should auto-open on load
$modalMode    = $_GET['modal'] ?? '';   // '', 'create', or 'edit'
$modalEditing = null;
if ($modalMode === 'edit') {
    $modalEditing = getAnnouncementById((int)($_GET['id'] ?? 0));
}
if (!empty($flashOld)) {
    $modalEditing = array_merge($modalEditing ?? [], $flashOld);
}

$pageTitle = 'Announcements';
include __DIR__ . '/../components/header-admin.php';
?>

<div class="page-header">
    <div>
        <h1>Announcements</h1>
        <p>Manage all published, pending, and draft announcements.</p>
    </div>
    <?php if (hasPermission('create_announcement')): ?>
    <button type="button" class="btn btn-gnc-gold btn-sm" id="btn-new-announcement">
        <i class="bi bi-plus-lg"></i> New Announcement
    </button>
    <?php endif; ?>
</div>

<div class="data-card">
    <div class="data-card-header flex-wrap gap-2">
        <span class="data-card-title"><i class="bi bi-megaphone-fill me-1"></i> All Announcements</span>
        <form method="GET" class="ms-auto d-flex gap-2 flex-wrap" style="max-width:480px;width:100%">
            <input type="text" name="search" id="announcement-search" class="form-control form-control-sm" placeholder="Search title or content..." value="<?= htmlspecialchars($search) ?>" style="max-width:220px">
            <select name="status" class="form-select form-select-sm" style="max-width:140px" onchange="this.form.submit()">
                <option value="">All Status</option>
                <?php foreach (['draft','pending','published','archived'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="announcements-table">
            <thead>
                <tr>
                    <th style="width:64px">Image</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Author</th>
                    <th>Date</th>
                    <th style="width:120px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($announcements)): ?>
                <tr><td colspan="7" class="text-center py-5 text-muted">No announcements found.</td></tr>
                <?php else: foreach ($announcements as $a): ?>
                <tr>
                    <td>
                        <?php if (!empty($a['image_path'])): ?>
                        <img src="<?= htmlspecialchars($a['image_path']) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:6px">
                        <?php else: ?>
                        <div style="width:48px;height:48px;border-radius:6px;background:#eef1ee;display:flex;align-items:center;justify-content:center;color:#c3cbc4">
                            <i class="bi bi-image"></i>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?= htmlspecialchars($a['title']) ?>
                    </td>
                    <td style="font-size:.82rem;color:#666"><?= htmlspecialchars($a['category_name'] ?? '—') ?></td>
                    <td><span class="status-badge <?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
                    <td style="font-size:.82rem"><?= htmlspecialchars(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?: '—') ?></td>
                    <td style="font-size:.78rem;color:#888"><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
                    <td>
                        <?php $isOwner = $a['user_id'] == ($_SESSION['user_id'] ?? null); ?>
                        <?php if (hasPermission('edit_announcement') || (hasPermission('edit_own_announcement') && $isOwner)): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-announcement"
                                data-id="<?= $a['announcement_id'] ?>"
                                data-title="<?= htmlspecialchars($a['title'], ENT_QUOTES) ?>"
                                data-category="<?= (int)($a['category_id'] ?? 0) ?>"
                                data-status="<?= htmlspecialchars($a['status']) ?>"
                                data-image="<?= htmlspecialchars($a['image_path'] ?? '') ?>"
                                data-content-id="ann-content-<?= $a['announcement_id'] ?>"
                                title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <!-- Raw content stashed in a hidden template (not JSON) -->
                        <template id="ann-content-<?= $a['announcement_id'] ?>"><?= $a['content'] ?></template>
                        <?php endif; ?>
                        <?php if (hasPermission('delete_announcement') || (hasPermission('delete_own_announcement') && $isOwner)): ?>
                        <form method="POST" action="/admin/action/create-announcement.php" style="display:inline" onsubmit="return confirm('Delete &quot;<?= htmlspecialchars(addslashes($a['title'])) ?>&quot;? This cannot be undone.');">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="announcement_id" value="<?= $a['announcement_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================
     CREATE / EDIT MODAL — posts to /admin/action/create-announcement.php
============================================================ -->
<div class="modal fade" id="announcementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" action="/admin/action/create-announcement.php" enctype="multipart/form-data" id="announcement-form">
        <div class="modal-header">
          <h5 class="modal-title" id="announcementModalLabel">New Announcement</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <?php if (!empty($flashErrors)): ?>
          <div class="alert alert-danger">
            <ul class="mb-0">
              <?php foreach ($flashErrors as $err): ?>
              <li><?= htmlspecialchars($err) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>

          <style>
            /* Ensure modal footer is always visible */
            #announcementModal .modal-dialog {
              max-height: 90vh;
            }
            #announcementModal .modal-body {
              max-height: calc(90vh - 200px);
              overflow-y: auto;
            }
            #announcementModal .modal-content {
              display: flex;
              flex-direction: column;
              max-height: 90vh;
            }
            #announcementModal .modal-footer {
              flex-shrink: 0;
            }
          </style>

          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
          <input type="hidden" name="action" id="modal-action" value="create">
          <input type="hidden" name="announcement_id" id="modal-announcement-id" value="">

          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Title <span style="color:#dc3545">*</span></label>
              <input type="text" name="title" id="modal-title" class="form-control" required maxlength="500" placeholder="Announcement title">
            </div>
            <div class="col-md-4">
              <label class="form-label">Category</label>
              <select name="category_id" id="modal-category" class="form-select">
                <option value="">None</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Featured Image</label>
              <input type="file" name="image" id="modal-image-input" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" style="display:block !important">
              <div class="form-text">JPG, PNG, GIF or WEBP. Max 10MB.</div>
              <div id="modal-image-preview-wrap" class="mt-2" style="display:none">
                <img id="modal-image-preview" src="" style="max-width:100px;height:80px;object-fit:cover;border-radius:6px;display:block;margin-bottom:6px">
                <div class="form-check" id="modal-remove-image-wrap" style="display:none">
                  <input type="checkbox" class="form-check-input" id="modal-remove-image" name="remove_image" value="1">
                  <label class="form-check-label" for="modal-remove-image" style="font-size:.85rem">Remove current image</label>
                </div>
              </div>
            </div>

            <?php if (hasPermission('publish_announcement')): ?>
            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select name="status" id="modal-status" class="form-select">
                <option value="draft">Save as Draft</option>
                <option value="pending">Submit for Approval</option>
                <option value="published">Publish Now</option>
              </select>
            </div>
            <?php else: ?>
            <div class="col-md-6">
              <div class="alert alert-secondary mb-0" style="font-size:.85rem">
                <i class="bi bi-info-circle"></i> This will be submitted for approval before it appears on the public site.
              </div>
            </div>
            <?php endif; ?>

            <!-- Content goes LAST: it's the field most likely to be resized/grown,
                so nothing below it can be overlapped no matter how tall it gets. -->
            <div class="col-12">
              <label class="form-label">Content <span style="color:#dc3545">*</span></label>
              <div id="modal-content-editor" style="background:#fff"></div>
              <input type="hidden" name="content" id="modal-content-input">
              <style>
                /* Fixed-height, scrollable editor with reduced height for better button visibility */
                #modal-content-editor {
                    height: 150px;
                    overflow: hidden;
                }
                #modal-content-editor .ql-editor {
                    height: 150px;
                    max-height: 150px;
                    overflow-y: auto;
                    resize: none;
                }
                #modal-content-editor .ql-toolbar {
                    border-top: 1px solid #ccc;
                }
              </style>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-gnc-gold" id="modal-save-btn">
            <i class="bi bi-check-lg"></i> <span id="modal-save-label">Create Announcement</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php ob_start(); ?>
<script>
// NOTE: this runs via $extraScripts (echoed in footer-admin.php), AFTER
// quill.min.js and bootstrap.bundle.min.js have loaded. Putting it here
// directly (before footer-admin.php is included) would throw a
// "Quill is not defined" error and silently kill the rest of the script,
// which is why the New Announcement button would do nothing.
const modalQuill = initQuill('modal-content-editor', 'modal-content-input', { placeholder: 'Write the announcement content here…' });

const announcementModalEl = document.getElementById('announcementModal');
const announcementModal   = new bootstrap.Modal(announcementModalEl);

const modalTitleInput   = document.getElementById('modal-title');
const modalCategory     = document.getElementById('modal-category');
const modalStatus       = document.getElementById('modal-status');
const modalActionInput  = document.getElementById('modal-action');
const modalIdInput      = document.getElementById('modal-announcement-id');
const modalLabel        = document.getElementById('announcementModalLabel');
const modalSaveLabel    = document.getElementById('modal-save-label');
const modalImageInput   = document.getElementById('modal-image-input');
const modalImagePreview = document.getElementById('modal-image-preview');
const modalImagePreviewWrap = document.getElementById('modal-image-preview-wrap');
const modalRemoveWrap   = document.getElementById('modal-remove-image-wrap');
const modalRemoveCheck  = document.getElementById('modal-remove-image');

function resetModalForm() {
    document.getElementById('announcement-form').reset();
    modalQuill.setContents([]);
    modalActionInput.value = 'create';
    modalIdInput.value = '';
    modalLabel.textContent = 'New Announcement';
    modalSaveLabel.textContent = 'Create Announcement';
    modalImagePreviewWrap.style.display = 'none';
    modalRemoveWrap.style.display = 'none';
    modalImagePreview.src = '';
}

function openCreateModal() {
    resetModalForm();
    announcementModal.show();
}

function openEditModal(btn) {
    resetModalForm();
    modalActionInput.value = 'update';
    modalIdInput.value = btn.dataset.id;
    modalLabel.textContent = 'Edit Announcement';
    modalSaveLabel.textContent = 'Save Changes';

    modalTitleInput.value = btn.dataset.title || '';
    if (modalCategory) modalCategory.value = btn.dataset.category || '';
    if (modalStatus) modalStatus.value = btn.dataset.status || 'draft';

    const contentTpl = document.getElementById(btn.dataset.contentId);
    if (contentTpl) {
        modalQuill.root.innerHTML = contentTpl.innerHTML;
    }

    if (btn.dataset.image) {
        modalImagePreview.src = btn.dataset.image;
        modalImagePreviewWrap.style.display = 'block';
        modalRemoveWrap.style.display = 'block';
        modalRemoveCheck.checked = false;
    }

    announcementModal.show();
}

document.getElementById('btn-new-announcement')?.addEventListener('click', openCreateModal);

document.querySelectorAll('.btn-edit-announcement').forEach(btn => {
    btn.addEventListener('click', () => openEditModal(btn));
});

modalImageInput?.addEventListener('change', () => {
    const file = modalImageInput.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        modalImagePreview.src = e.target.result;
        modalImagePreviewWrap.style.display = 'block';
        if (modalRemoveCheck) modalRemoveCheck.checked = false;
    };
    reader.readAsDataURL(file);
});

document.getElementById('announcement-form').addEventListener('submit', (e) => {
    document.getElementById('modal-content-input').value = modalQuill.root.innerHTML;
    if (!modalQuill.getText().trim()) {
        e.preventDefault();
        showToast('Please write some content for the announcement.', 'error');
    }
});

initTableSearch('announcement-search', 'announcements-table');

// Auto-reopen the modal on load: validation error, or ?modal=create / ?modal=edit&id=N
<?php if ($modalMode === 'create'): ?>
openCreateModal();
<?php if (!empty($flashErrors)): ?>
modalTitleInput.value = <?= json_encode($flashOld['title'] ?? '') ?>;
modalQuill.root.innerHTML = <?= json_encode($flashOld['content'] ?? '') ?>;
if (modalCategory) modalCategory.value = <?= json_encode($flashOld['category_id'] ?? '') ?>;
if (modalStatus) modalStatus.value = <?= json_encode($flashOld['status'] ?? 'draft') ?>;
<?php endif; ?>
<?php elseif ($modalMode === 'edit' && !empty($modalEditing)): ?>
resetModalForm();
modalActionInput.value = 'update';
modalIdInput.value = <?= json_encode($modalEditing['announcement_id'] ?? ($_GET['id'] ?? '')) ?>;
modalLabel.textContent = 'Edit Announcement';
modalSaveLabel.textContent = 'Save Changes';
modalTitleInput.value = <?= json_encode($modalEditing['title'] ?? '') ?>;
modalQuill.root.innerHTML = <?= json_encode($modalEditing['content'] ?? '') ?>;
if (modalCategory) modalCategory.value = <?= json_encode($modalEditing['category_id'] ?? '') ?>;
if (modalStatus) modalStatus.value = <?= json_encode($modalEditing['status'] ?? 'draft') ?>;
<?php if (!empty($modalEditing['image_path'])): ?>
modalImagePreview.src = <?= json_encode($modalEditing['image_path']) ?>;
modalImagePreviewWrap.style.display = 'block';
modalRemoveWrap.style.display = 'block';
<?php endif; ?>
announcementModal.show();
<?php endif; ?>
</script>
<?php $extraScripts = ob_get_clean(); ?>

<?php include __DIR__ . '/../components/footer-admin.php'; ?>