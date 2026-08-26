<?php
/**
 * GNC Admin Panel - News
 * /admin/news.php
 *
 * Renders the list + the create/edit modal. All writes (create, update,
 * delete) are handled by /admin/action/create-news.php, which the modal
 * form and the per-row delete forms POST to directly.
 *
 * Mirrors /admin/announcement.php's structure and styling so News behaves
 * identically to Announcements from an admin's point of view, with two
 * differences: a fixed News-specific category list (not the shared
 * `categories` table) and a multi-image gallery on top of the single
 * cover image.
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
$statusFilter   = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$search         = trim($_GET['search'] ?? '');
$filters        = [];
if ($statusFilter !== '') $filters['status'] = $statusFilter;
if ($categoryFilter !== '') $filters['category'] = $categoryFilter;
if ($search !== '') $filters['search'] = $search;

$newsList       = getNews($filters, 50, 0);
$newsCategories = getNewsCategories();

// Which modal (if any) should auto-open on load
$modalMode    = $_GET['modal'] ?? '';   // '', 'create', or 'edit'
$modalEditing = null;
$modalGallery = [];
if ($modalMode === 'edit') {
    $editId       = (int)($_GET['id'] ?? 0);
    $modalEditing = getNewsById($editId);
    if ($modalEditing) {
        $modalGallery = getNewsImages($editId);
    }
}
if (!empty($flashOld)) {
    $modalEditing = array_merge($modalEditing ?? [], $flashOld);
}

$pageTitle = 'News';
include __DIR__ . '/../components/header-admin.php';
?>

<div class="page-header">
    <div>
        <h1>News</h1>
        <p>Manage all published, pending, and draft news articles.</p>
    </div>
    <?php if (hasPermission('create_news')): ?>
    <button type="button" class="btn btn-gnc-gold btn-sm" id="btn-new-news">
        <i class="bi bi-plus-lg"></i> Create News
    </button>
    <?php endif; ?>
</div>

<style>
    .news-category-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .02em;
        background: rgba(201, 162, 39, 0.14);
        color: #8a6d1f;
        border: 1px solid rgba(201, 162, 39, 0.35);
        white-space: nowrap;
    }
    @media (max-width: 768px) {
        #news-table th:nth-child(3),
        #news-table td:nth-child(3) {
            display: none; /* hide Category column on small screens */
        }
    }
</style>

<div class="data-card">
    <div class="data-card-header flex-wrap gap-3 align-items-center">
        <span class="data-card-title"><i class="bi bi-newspaper me-1"></i> All News</span>
        <form method="GET" class="ms-auto d-flex flex-wrap align-items-center gap-2">
            <input type="text" name="search" id="news-search" class="form-control form-control-sm" placeholder="Search title or content..." value="<?= htmlspecialchars($search) ?>" style="width:220px">
            <select name="category" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($newsCategories as $code => $label): ?>
                <option value="<?= htmlspecialchars($code) ?>" <?= $categoryFilter === $code ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-select form-select-sm" style="width:140px" onchange="this.form.submit()">
                <option value="">All Status</option>
                <?php foreach (['draft','pending','published','archived'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <style>
        @media (max-width: 767px) {
            .data-card-header form[method="GET"] {
                width: 100%;
                margin-left: 0 !important;
            }
            .data-card-header form[method="GET"] input,
            .data-card-header form[method="GET"] select {
                width: 100% !important;
            }
        }
    </style>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="news-table">
            <thead>
                <tr>
                    <th style="width:64px">Image</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Author</th>
                    <th>Status</th>
                    <th>Posted By</th>
                    <th>Date</th>
                    <th style="width:120px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($newsList)): ?>
                <tr><td colspan="8" class="text-center py-5 text-muted">No news articles found.</td></tr>
                <?php else: foreach ($newsList as $n): ?>
                <tr>
                    <td>
                        <?php if (!empty($n['image_path'])): ?>
                        <img src="<?= htmlspecialchars($n['image_path']) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:6px">
                        <?php else: ?>
                        <div style="width:48px;height:48px;border-radius:6px;background:#eef1ee;display:flex;align-items:center;justify-content:center;color:#c3cbc4">
                            <i class="bi bi-image"></i>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?= htmlspecialchars($n['title']) ?>
                    </td>
                    <td><span class="news-category-badge"><?= htmlspecialchars($n['category'] ?? 'Others') ?></span></td>
                    <td style="font-size:.82rem;color:#666"><?= htmlspecialchars($n['author'] ?? '—') ?></td>
                    <td><span class="status-badge <?= $n['status'] ?>"><?= ucfirst($n['status']) ?></span></td>
                    <td style="font-size:.82rem"><?= htmlspecialchars(trim(($n['first_name'] ?? '') . ' ' . ($n['last_name'] ?? '')) ?: '—') ?></td>
                    <td style="font-size:.78rem;color:#888"><?= date('M d, Y', strtotime($n['created_at'])) ?></td>
                    <td>
                        <?php $isOwner = $n['user_id'] == ($_SESSION['user_id'] ?? null); ?>
                        <?php if (hasPermission('edit_news') || (hasPermission('edit_own_news') && $isOwner)): ?>
                        <?php $rowGallery = getNewsImages($n['news_id']); ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-news"
                                data-id="<?= $n['news_id'] ?>"
                                data-title="<?= htmlspecialchars($n['title'], ENT_QUOTES) ?>"
                                data-author="<?= htmlspecialchars($n['author'] ?? '', ENT_QUOTES) ?>"
                                data-category="<?= htmlspecialchars($n['category'] ?? 'Others', ENT_QUOTES) ?>"
                                data-status="<?= htmlspecialchars($n['status']) ?>"
                                data-image="<?= htmlspecialchars($n['image_path'] ?? '') ?>"
                                data-gallery="<?= htmlspecialchars(json_encode($rowGallery), ENT_QUOTES) ?>"
                                data-content-id="news-content-<?= $n['news_id'] ?>"
                                title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <!-- Raw content stashed in a hidden template (not JSON) -->
                        <template id="news-content-<?= $n['news_id'] ?>"><?= $n['content'] ?></template>
                        <?php endif; ?>
                        <?php if (hasPermission('delete_news') || (hasPermission('delete_own_news') && $isOwner)): ?>
                        <form method="POST" action="/admin/action/create-news.php" style="display:inline" onsubmit="return confirm('Delete &quot;<?= htmlspecialchars(addslashes($n['title'])) ?>&quot;? This cannot be undone.');">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="news_id" value="<?= $n['news_id'] ?>">
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
     CREATE / EDIT MODAL — posts to /admin/action/create-news.php
============================================================ -->
<div class="modal fade" id="newsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" action="/admin/action/create-news.php" enctype="multipart/form-data" id="news-form">
        <div class="modal-header">
          <h5 class="modal-title" id="newsModalLabel">Create News</h5>
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
            #newsModal .modal-dialog {
              max-height: 90vh;
            }
            #newsModal .modal-body {
              max-height: calc(90vh - 200px);
              overflow-y: auto;
            }
            #newsModal .modal-content {
              display: flex;
              flex-direction: column;
              max-height: 90vh;
            }
            #newsModal .modal-footer {
              flex-shrink: 0;
            }
            .gallery-thumb-wrap {
              position: relative;
              width: 78px;
              height: 78px;
              border-radius: 6px;
              overflow: hidden;
              background: #eef1ee;
            }
            .gallery-thumb-wrap img {
              width: 100%;
              height: 100%;
              object-fit: cover;
              display: block;
            }
            .gallery-thumb-remove {
              position: absolute;
              top: 2px;
              right: 2px;
              width: 20px;
              height: 20px;
              border-radius: 50%;
              background: rgba(0,0,0,.6);
              color: #fff;
              border: none;
              font-size: .7rem;
              line-height: 20px;
              text-align: center;
              padding: 0;
              cursor: pointer;
            }
            .gallery-thumb-wrap.marked-remove img {
              opacity: .35;
            }
          </style>

          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
          <input type="hidden" name="action" id="modal-action" value="create">
          <input type="hidden" name="news_id" id="modal-news-id" value="">

          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Title <span style="color:#dc3545">*</span></label>
              <input type="text" name="title" id="modal-title" class="form-control" required maxlength="500" placeholder="News title">
            </div>
            <div class="col-md-4">
              <label class="form-label">Category</label>
              <select name="category" id="modal-category" class="form-select">
                <?php foreach ($newsCategories as $code => $label): ?>
                <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Author</label>
              <input type="text" name="author" id="modal-author" class="form-control" maxlength="255" placeholder="e.g. GNC Press Office">
              <div class="form-text">Optional byline shown on the public site.</div>
            </div>

            <?php if (hasPermission('publish_news')): ?>
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

            <div class="col-md-6" style="min-width:220px">
              <label class="form-label">Cover Image</label>
              <input type="file" name="image" id="modal-image-input" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" style="display:block !important">
              <div class="form-text">Used as the thumbnail on the listing and homepage card. JPG, PNG, GIF or WEBP. Max 10MB.</div>
              <div id="modal-image-preview-wrap" class="mt-2" style="display:none">
                <img id="modal-image-preview" src="" style="max-width:100px;height:80px;object-fit:cover;border-radius:6px;display:block;margin-bottom:6px">
                <div class="form-check" id="modal-remove-image-wrap" style="display:none">
                  <input type="checkbox" class="form-check-input" id="modal-remove-image" name="remove_image" value="1">
                  <label class="form-check-label" for="modal-remove-image" style="font-size:.85rem">Remove current cover image</label>
                </div>
              </div>
            </div>

            <div class="col-md-6" style="min-width:220px">
              <label class="form-label">Additional Images (Gallery)</label>
              <input type="file" name="gallery_images[]" id="modal-gallery-input" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" multiple style="display:block !important">
              <div class="form-text">You can select several photos at once. JPG, PNG, GIF or WEBP, max 10MB each, up to 10 images per upload.</div>
            </div>

            <div class="col-12" id="modal-gallery-existing-wrap" style="display:none">
              <label class="form-label mb-1">Current Gallery Images</label>
              <div class="form-text mb-2">Check an image to remove it when you save.</div>
              <div id="modal-gallery-existing" class="d-flex flex-wrap gap-2"></div>
            </div>

            <div class="col-12" id="modal-gallery-new-wrap" style="display:none">
              <label class="form-label mb-1">New Images to Upload</label>
              <div id="modal-gallery-new" class="d-flex flex-wrap gap-2"></div>
            </div>

            <!-- Content goes LAST: it's the field most likely to be resized/grown,
                so nothing below it can be overlapped no matter how tall it gets. -->
            <div class="col-12">
              <label class="form-label">Content <span style="color:#dc3545">*</span></label>
              <div id="modal-content-editor" style="background:#fff"></div>
              <input type="hidden" name="content" id="modal-content-input">
              <style>
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
            <i class="bi bi-check-lg"></i> <span id="modal-save-label">Create News</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php ob_start(); ?>
<script>
// NOTE: this runs via $extraScripts (echoed in footer-admin.php), AFTER
// quill.min.js and bootstrap.bundle.min.js have loaded — same reasoning
// as announcement.php.
const modalQuill = initQuill('modal-content-editor', 'modal-content-input', { placeholder: 'Write the news content here…' });

const newsModalEl = document.getElementById('newsModal');
const newsModal   = new bootstrap.Modal(newsModalEl);

const modalTitleInput   = document.getElementById('modal-title');
const modalAuthorInput  = document.getElementById('modal-author');
const modalCategory     = document.getElementById('modal-category');
const modalStatus       = document.getElementById('modal-status');
const modalActionInput  = document.getElementById('modal-action');
const modalIdInput      = document.getElementById('modal-news-id');
const modalLabel        = document.getElementById('newsModalLabel');
const modalSaveLabel    = document.getElementById('modal-save-label');
const modalImageInput   = document.getElementById('modal-image-input');
const modalImagePreview = document.getElementById('modal-image-preview');
const modalImagePreviewWrap = document.getElementById('modal-image-preview-wrap');
const modalRemoveWrap   = document.getElementById('modal-remove-image-wrap');
const modalRemoveCheck  = document.getElementById('modal-remove-image');

const modalGalleryInput      = document.getElementById('modal-gallery-input');
const modalGalleryNewWrap    = document.getElementById('modal-gallery-new-wrap');
const modalGalleryNew        = document.getElementById('modal-gallery-new');
const modalGalleryExistWrap  = document.getElementById('modal-gallery-existing-wrap');
const modalGalleryExist      = document.getElementById('modal-gallery-existing');

const MAX_GALLERY_IMAGES = 10;

function resetModalForm() {
    document.getElementById('news-form').reset();
    modalQuill.setContents([]);
    modalActionInput.value = 'create';
    modalIdInput.value = '';
    if (modalCategory) modalCategory.value = 'Others';
    modalLabel.textContent = 'Create News';
    modalSaveLabel.textContent = 'Create News';
    modalImagePreviewWrap.style.display = 'none';
    modalRemoveWrap.style.display = 'none';
    modalImagePreview.src = '';
    resetGalleryUI();
}

function resetGalleryUI() {
    modalGalleryInput.value = '';
    modalGalleryNew.innerHTML = '';
    modalGalleryNewWrap.style.display = 'none';
    modalGalleryExist.innerHTML = '';
    modalGalleryExistWrap.style.display = 'none';
    // Clear any stray remove_gallery_ids[] checkboxes left over from a previous edit
    document.querySelectorAll('input[name="remove_gallery_ids[]"]').forEach(el => el.remove());
}

function openCreateModal() {
    resetModalForm();
    newsModal.show();
}

// Renders the "new images selected in this session" preview strip from
// whatever is currently in the file input's FileList.
function renderNewGalleryPreview() {
    modalGalleryNew.innerHTML = '';
    const files = Array.from(modalGalleryInput.files || []);
    if (!files.length) {
        modalGalleryNewWrap.style.display = 'none';
        return;
    }
    modalGalleryNewWrap.style.display = 'block';
    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const wrap = document.createElement('div');
            wrap.className = 'gallery-thumb-wrap';
            wrap.innerHTML = '<img src="' + e.target.result + '" alt="">';
            modalGalleryNew.appendChild(wrap);
        };
        reader.readAsDataURL(file);
    });
}

// Renders the existing (already-saved) gallery images for an article being
// edited, each with a checkbox-style toggle that queues it for removal via
// a hidden remove_gallery_ids[] input added to the form on toggle.
function renderExistingGallery(images) {
    modalGalleryExist.innerHTML = '';
    if (!images || !images.length) {
        modalGalleryExistWrap.style.display = 'none';
        return;
    }
    modalGalleryExistWrap.style.display = 'block';
    images.forEach(img => {
        const wrap = document.createElement('div');
        wrap.className = 'gallery-thumb-wrap';
        wrap.dataset.imageId = img.image_id;

        const imgEl = document.createElement('img');
        imgEl.src = img.file_path;
        wrap.appendChild(imgEl);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'gallery-thumb-remove';
        btn.title = 'Remove this image';
        btn.textContent = '×';
        btn.addEventListener('click', () => toggleGalleryRemoval(wrap, img.image_id));
        wrap.appendChild(btn);

        modalGalleryExist.appendChild(wrap);
    });
}

function toggleGalleryRemoval(wrap, imageId) {
    const existingInput = document.querySelector('input[name="remove_gallery_ids[]"][value="' + imageId + '"]');
    if (existingInput) {
        existingInput.remove();
        wrap.classList.remove('marked-remove');
    } else {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'remove_gallery_ids[]';
        input.value = imageId;
        document.getElementById('news-form').appendChild(input);
        wrap.classList.add('marked-remove');
    }
}

function openEditModal(btn) {
    resetModalForm();
    modalActionInput.value = 'update';
    modalIdInput.value = btn.dataset.id;
    modalLabel.textContent = 'Edit News';
    modalSaveLabel.textContent = 'Save Changes';

    modalTitleInput.value = btn.dataset.title || '';
    modalAuthorInput.value = btn.dataset.author || '';
    if (modalCategory) modalCategory.value = btn.dataset.category || 'Others';
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

    if (btn.dataset.gallery) {
        try {
            renderExistingGallery(JSON.parse(btn.dataset.gallery));
        } catch (e) {
            renderExistingGallery([]);
        }
    }

    newsModal.show();
}

document.getElementById('btn-new-news')?.addEventListener('click', openCreateModal);

document.querySelectorAll('.btn-edit-news').forEach(btn => {
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

modalGalleryInput?.addEventListener('change', () => {
    if (modalGalleryInput.files.length > MAX_GALLERY_IMAGES) {
        showToast('You can upload up to ' + MAX_GALLERY_IMAGES + ' images at a time.', 'error');
        modalGalleryInput.value = '';
        modalGalleryNew.innerHTML = '';
        modalGalleryNewWrap.style.display = 'none';
        return;
    }
    renderNewGalleryPreview();
});

document.getElementById('news-form').addEventListener('submit', (e) => {
    document.getElementById('modal-content-input').value = modalQuill.root.innerHTML;
    if (!modalQuill.getText().trim()) {
        e.preventDefault();
        showToast('Please write some content for the news article.', 'error');
    }
});

initTableSearch('news-search', 'news-table');

// Auto-reopen the modal on load: validation error, or ?modal=create / ?modal=edit&id=N
<?php if ($modalMode === 'create'): ?>
openCreateModal();
<?php if (!empty($flashErrors)): ?>
modalTitleInput.value = <?= json_encode($flashOld['title'] ?? '') ?>;
modalQuill.root.innerHTML = <?= json_encode($flashOld['content'] ?? '') ?>;
modalAuthorInput.value = <?= json_encode($flashOld['author'] ?? '') ?>;
if (modalCategory) modalCategory.value = <?= json_encode($flashOld['category'] ?? 'Others') ?>;
if (modalStatus) modalStatus.value = <?= json_encode($flashOld['status'] ?? 'draft') ?>;
<?php endif; ?>
<?php elseif ($modalMode === 'edit' && !empty($modalEditing)): ?>
resetModalForm();
modalActionInput.value = 'update';
modalIdInput.value = <?= json_encode($modalEditing['news_id'] ?? ($_GET['id'] ?? '')) ?>;
modalLabel.textContent = 'Edit News';
modalSaveLabel.textContent = 'Save Changes';
modalTitleInput.value = <?= json_encode($modalEditing['title'] ?? '') ?>;
modalQuill.root.innerHTML = <?= json_encode($modalEditing['content'] ?? '') ?>;
modalAuthorInput.value = <?= json_encode($modalEditing['author'] ?? '') ?>;
if (modalCategory) modalCategory.value = <?= json_encode($modalEditing['category'] ?? 'Others') ?>;
if (modalStatus) modalStatus.value = <?= json_encode($modalEditing['status'] ?? 'draft') ?>;
<?php if (!empty($modalEditing['image_path'])): ?>
modalImagePreview.src = <?= json_encode($modalEditing['image_path']) ?>;
modalImagePreviewWrap.style.display = 'block';
modalRemoveWrap.style.display = 'block';
<?php endif; ?>
renderExistingGallery(<?= json_encode($modalGallery) ?>);
newsModal.show();
<?php endif; ?>
</script>
<?php $extraScripts = ob_get_clean(); ?>

<?php include __DIR__ . '/../components/footer-admin.php'; ?>