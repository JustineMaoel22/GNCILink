<?php
/**
 * GNC Admin Panel - Section Editor: Homepage Slideshow
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';

initSession();
requireLogin();

$currentUser = getCurrentUser();
$pageTitle   = 'Content Editor';
$slides      = getHeroSlides(false); // all slides, including drafts, for the admin list

// ── Page selector (Content Editor → Select Page) ──────────────
// Home keeps using the dedicated slideshow editor below; every other
// page is powered by the generic Page → Section → Content structure
// (see page-content-functions in admin-functions.php). Pages/sections
// are read from the database so new pages can be added later without
// touching this file.
$canManagePages = hasPermission('manage_page_content');
$editablePages  = getEditablePages();
if (empty($editablePages)) {
    // Fallback so the dropdown still works even before the
    // page-content-schema.sql migration has been run.
    $editablePages = [['slug' => 'home', 'title' => 'Home', 'page_type' => 'slideshow']];
}
$sectionPages = array_filter($editablePages, fn($p) => ($p['page_type'] ?? 'sections') === 'sections');

$requestedPage = $_GET['page'] ?? 'home';
$validSlugs    = array_column($editablePages, 'slug');
$activeSlug    = in_array($requestedPage, $validSlugs, true) ? $requestedPage : 'home';

include __DIR__ . '/../components/header-admin.php';
?>

<div class="page-header">
    <div>
        <h1>Content Editor</h1>
        <p>Manage and update the content of your website pages.</p>
    </div>
</div>

<div class="data-card mb-3" style="padding:1rem 1.25rem">
    <label class="form-label mb-1" for="page-select">Select Page</label>
    <select class="form-select" id="page-select" style="max-width:320px">
        <?php foreach ($editablePages as $p): ?>
            <option value="<?= htmlspecialchars($p['slug']) ?>" <?= $p['slug'] === $activeSlug ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['title']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- ══════════════════ HOME → Slideshow (existing editor, unchanged) ══════════════════ -->
<div class="page-panel" id="page-panel-home" style="<?= $activeSlug === 'home' ? '' : 'display:none' ?>">

<div class="page-header">
    <div>
        <h1>Slideshow</h1>
        <p>Add, remove, and reorder slides that appear in the homepage slideshow.</p>
    </div>
    <button type="button" class="btn-gnc-gold" id="btn-add-slide">
        <i class="bi bi-plus-lg"></i> Add New Slide
    </button>
</div>

<div class="row g-3 mb-4">
    <!-- ══════════════════ Slide Order ══════════════════ -->
    <div class="col-lg-5">
        <div class="data-card h-100">
            <div class="data-card-header">
                <span class="data-card-title"><i class="bi bi-images me-1"></i> Slide Order</span>
            </div>
            <div style="padding:.5rem .5rem 0">
                <p class="text-muted small px-2 mb-2">Drag and drop to reorder slides.</p>
                <div id="slide-list" class="slide-list">
                    <?php if (empty($slides)): ?>
                        <div class="empty-state"><i class="bi bi-images"></i><p>No slides yet. Click "Add New Slide" to create one.</p></div>
                    <?php else: foreach ($slides as $i => $s): ?>
                        <div class="slide-item" draggable="true" data-id="<?= (int)$s['slide_id'] ?>">
                            <span class="slide-drag-handle"><i class="bi bi-grip-vertical"></i></span>
                            <span class="slide-number"><?= $i + 1 ?></span>
                            <div class="slide-thumb">
                                <?php if ($s['media_type'] === 'video'): ?>
                                    <i class="bi bi-camera-reels-fill"></i>
                                <?php else: ?>
                                    <img src="<?= htmlspecialchars($s['media_path']) ?>" alt="">
                                <?php endif; ?>
                            </div>
                            <div class="slide-item-info">
                                <div class="slide-item-title"><?= htmlspecialchars($s['title'] ? str_replace('|', ' ', $s['title']) : '(No title)') ?></div>
                                <div class="slide-item-sub"><?= htmlspecialchars($s['subtitle'] ?? '') ?></div>
                                <span class="badge-status <?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span>
                            </div>
                            <button type="button" class="slide-item-menu" title="Options"><i class="bi bi-three-dots-vertical"></i></button>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <div class="alert alert-info d-flex align-items-start gap-2 mt-2 mb-2" style="font-size:.8rem">
                    <i class="bi bi-info-circle-fill mt-1"></i>
                    <span>The first slide in the list will be displayed first on the website.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════ Slide Preview / Editor ══════════════════ -->
    <div class="col-lg-7">
        <div class="data-card h-100">
            <div class="data-card-header">
                <span class="data-card-title"><i class="bi bi-eye me-1"></i> Slide Preview</span>
            </div>

            <div id="editor-empty" class="empty-state">
                <p>Select a slide from the list, or add a new one, to start editing.</p>
            </div>

            <form id="slide-form" enctype="multipart/form-data" style="padding:0 1.25rem 1.25rem; display:none">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="slide_id" id="f-slide-id" value="0">

                <!-- Preview mode switch -->
                <div class="preview-tabs" role="tablist" aria-label="Preview device">
                    <button type="button" class="preview-tab active" id="tab-desktop" data-preview="desktop" role="tab" aria-selected="true">
                        <i class="bi bi-display"></i> Desktop Preview
                    </button>
                    <button type="button" class="preview-tab" id="tab-mobile" data-preview="mobile" role="tab" aria-selected="false">
                        <i class="bi bi-phone"></i> Mobile Preview
                    </button>
                </div>
                <p class="small text-muted mb-2" style="margin-top:-.3rem">
                    Mobile Preview is an approximation of the live site's layout, not a pixel-exact render.
                </p>

                <!-- Live preview -->
                <div class="mini-hero-wrap" id="mini-hero-wrap">
                    <div class="mini-hero" id="mini-hero">
                        <div class="mini-hero-bg" id="mini-hero-bg"></div>
                        <video id="mini-hero-video" muted loop playsinline style="display:none"></video>
                        <div class="mini-hero-overlay"></div>
                        <div class="mini-hero-content">
                            <div class="mini-title" id="mini-title"></div>
                            <div class="mini-sub" id="mini-sub"></div>
                            <div class="mini-ctas">
                                <span class="mini-btn mini-btn-primary" id="mini-btn1"></span>
                                <span class="mini-btn mini-btn-secondary" id="mini-btn2"></span>
                            </div>
                        </div>
                        <div class="mini-arrow mini-arrow-prev"><i class="bi bi-chevron-left"></i></div>
                        <div class="mini-arrow mini-arrow-next"><i class="bi bi-chevron-right"></i></div>
                        <div class="mini-dots"><span class="active"></span><span></span><span></span></div>
                    </div>
                </div>
                <div class="small text-muted mt-1 d-none" id="mobile-fallback-note">
                    <i class="bi bi-info-circle"></i> No mobile image set for this slide — showing the desktop image as a fallback.
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label class="form-label d-flex justify-content-between">
                            <span>Title</span><span class="text-muted small counter" data-max="50">0 / 50</span>
                        </label>
                        <input type="text" class="form-control" id="f-title" name="title" maxlength="50" placeholder="Leave blank to hide title">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label d-flex justify-content-between">
                            <span>Subtitle</span><span class="text-muted small counter" data-max="150">0 / 150</span>
                        </label>
                        <input type="text" class="form-control" id="f-subtitle" name="subtitle" maxlength="150" placeholder="Leave blank to hide subtitle">
                    </div>

                    <!-- Button 1 -->
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label mb-0">Button 1</label>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="f-btn1-toggle">
                                <label class="form-check-label small text-muted" for="f-btn1-toggle">Show</label>
                            </div>
                        </div>
                        <div id="btn1-fields" style="display:none">
                            <input type="text" class="form-control mb-2" id="f-btn1-text" name="btn1_text" maxlength="30" placeholder="Button Text (e.g. ENROLL NOW)">
                            <input type="text" class="form-control" id="f-btn1-link" name="btn1_link" placeholder="Button Link (e.g. /auth/login.php)">
                        </div>
                    </div>

                    <!-- Button 2 -->
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label mb-0">Button 2</label>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="f-btn2-toggle">
                                <label class="form-check-label small text-muted" for="f-btn2-toggle">Show</label>
                            </div>
                        </div>
                        <div id="btn2-fields" style="display:none">
                            <input type="text" class="form-control mb-2" id="f-btn2-text" name="btn2_text" maxlength="30" placeholder="Button Text (e.g. EXPLORE GNC)">
                            <input type="text" class="form-control" id="f-btn2-link" name="btn2_link" placeholder="Button Link (e.g. #about)">
                        </div>
                    </div>

                    <!-- Background Media: Desktop + Mobile are independent -->
                    <div class="col-12">
                        <label class="form-label mb-2">Background Media</label>
                        <div class="row g-3">
                            <!-- Desktop background -->
                            <div class="col-md-6">
                                <div class="bg-media-field">
                                    <div class="bg-media-field-label">
                                        <i class="bi bi-display"></i> Desktop <span class="text-muted fw-normal">(image or video)</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="media-swatch" id="media-swatch"><i class="bi bi-image"></i></div>
                                        <div class="flex-grow-1">
                                            <div class="small text-truncate" id="media-filename">No file selected</div>
                                            <div class="small text-muted" id="media-meta">Image or video</div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-upload">Upload</button>
                                        <input type="file" id="f-media" name="media" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime" hidden>
                                    </div>
                                </div>
                            </div>

                            <!-- Mobile background -->
                            <div class="col-md-6">
                                <div class="bg-media-field">
                                    <div class="bg-media-field-label">
                                        <i class="bi bi-phone"></i> Mobile <span class="text-muted fw-normal">(image only, optional)</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="media-swatch" id="media-mobile-swatch"><i class="bi bi-image"></i></div>
                                        <div class="flex-grow-1">
                                            <div class="small text-truncate" id="media-mobile-filename">Using desktop image</div>
                                            <div class="small text-muted" id="media-mobile-meta">No mobile-specific image set</div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-upload-mobile">Upload</button>
                                        <input type="file" id="f-media-mobile" name="media_mobile" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                                    </div>
                                    <button type="button" class="btn btn-link btn-sm p-0 mt-1 d-none" id="btn-clear-mobile">
                                        <i class="bi bi-x-circle"></i> Remove mobile image (fall back to desktop)
                                    </button>
                                    <input type="hidden" name="remove_mobile_media" id="f-remove-mobile-media" value="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status + Mobile + Gradient -->
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="f-status" name="status">
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label d-block">Display on Mobile</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="f-mobile" checked>
                            <label class="form-check-label small" id="f-mobile-label">Show</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label d-block">Gradient Overlay</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="f-gradient" checked>
                            <label class="form-check-label small" id="f-gradient-label">On</label>
                        </div>
                    </div>
                </div>

                <div id="form-alert" class="alert d-none mt-3 mb-0"></div>

                <div class="d-flex justify-content-between align-items-center mt-4 pt-3" style="border-top:1px solid #f0f0f0">
                    <button type="button" class="btn btn-outline-danger" id="btn-remove-slide">
                        <i class="bi bi-trash"></i> Remove Slide
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="btn-cancel">Cancel</button>
                        <button type="submit" class="btn-gnc-primary" id="btn-save">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (hasPermission('manage_page_content')): ?>
    <hr style="margin: 2rem 0; border-top: 1px solid #e5e5e5;">
    <div style="margin-top: 2rem;">
        <?php include __DIR__ . '/../components/homepage-president-manager.php'; ?>
    </div>
<?php endif; ?>

</div><!-- /#page-panel-home -->

<!-- ══════════════════ Generic section-based pages (Vision & Mission, About Us, etc.) ══════════════════ -->
<?php foreach ($sectionPages as $sp):
    $sections = getPageSections($sp['slug']);
    // College Departments / Programs have no free-text sections to edit:
    // department logos + Head of Office live in `departments`, and each
    // program's curriculum PDF lives in `programs`.
    $isDeptPage      = ($sp['slug'] === 'college-departments');
    $isProgramsPage  = ($sp['slug'] === 'programs');
    $hasCustomPanel  = $isDeptPage || $isProgramsPage;
?>
<div class="page-panel" id="page-panel-<?= htmlspecialchars($sp['slug']) ?>" style="<?= $activeSlug === $sp['slug'] ? '' : 'display:none' ?>">
    <div class="page-header">
        <div>
            <h1><?= htmlspecialchars($sp['title']) ?></h1>
            <p><?= $isDeptPage
                ? 'Manage each department\'s logo and Head of Office. Changes go live everywhere the department appears.'
                : ($isProgramsPage
                    ? 'Upload the curriculum PDF for each program. It appears in the Program Curriculum section of that program\'s page.'
                    : 'Edit the content sections for this page, then save, preview, and publish your changes.') ?></p>
        </div>
    </div>

    <?php if ($canManagePages && $isDeptPage): ?>
        <?php include __DIR__ . '/../components/department-media-manager.php'; ?>
    <?php elseif ($canManagePages && $isProgramsPage): ?>
        <?php include __DIR__ . '/../components/program-pdf-manager.php'; ?>
    <?php endif; ?>

    <?php if (!$canManagePages): ?>
        <div class="data-card">
            <div class="empty-state">
                <i class="bi bi-lock-fill"></i>
                <p>You don't have permission to edit this page's content.</p>
            </div>
        </div>
    <?php elseif (empty($sections) && $hasCustomPanel): ?>
        <?php /* nothing else to edit on this page */ ?>
    <?php elseif (empty($sections)): ?>
        <div class="data-card">
            <div class="empty-state">
                <i class="bi bi-file-earmark-text"></i>
                <p>No editable sections yet for this page.</p>
            </div>
        </div>
    <?php else: foreach ($sections as $sec):
        $hasDraft   = $sec['draft_content'] !== null;
        $textValue  = $hasDraft ? $sec['draft_content'] : $sec['content'];
    ?>
        <div class="data-card mb-3 page-section-card"
             data-page="<?= htmlspecialchars($sp['slug']) ?>"
             data-section="<?= htmlspecialchars($sec['section_key']) ?>">
            <div class="data-card-header">
                <span class="data-card-title"><?= htmlspecialchars($sec['title']) ?></span>
                <span class="badge-status <?= $hasDraft ? 'draft' : 'published' ?> ms-auto section-status-badge">
                    <?= $hasDraft ? 'Unpublished changes' : 'Published' ?>
                </span>
            </div>
            <div style="padding:0 1.25rem 1.25rem">
                <textarea class="form-control section-textarea" rows="4"
                          aria-label="<?= htmlspecialchars($sec['title']) ?> text"><?= htmlspecialchars($textValue ?? '') ?></textarea>
                <div class="section-alert alert d-none mt-2 mb-0"></div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm btn-discard-draft" <?= $hasDraft ? '' : 'disabled' ?>>
                        Discard Draft
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm btn-preview-section">
                        Preview
                    </button>
                    <button type="button" class="btn-gnc-primary btn-sm btn-save-section">
                        Save Changes
                    </button>
                    <button type="button" class="btn-gnc-gold btn-sm btn-publish-section">
                        Publish
                    </button>
                </div>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php endforeach; ?>

<!-- Preview modal for section text. Vision & Mission sections render as a
     scaled-down copy of their actual public-site card (see .cms-preview-*
     below); any other page/section falls back to plain text. -->
<div class="modal fade" id="sectionPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="sectionPreviewBody" style="background:var(--gnc-cream, #f7f3ea); padding:2rem"></div>
        </div>
    </div>
</div>

<style>
/* ── Section preview card — mirrors /assets/css/vision-mission-style.css
     (.vm-card etc.) at a smaller scale, so "Preview" shows admins roughly
     what the public page will actually look like. Falls back to the
     admin panel's own --gnc-* variables where defined, with hardcoded
     fallbacks matching the public site in case those aren't in scope here. */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Noto+Serif:wght@700&display=swap');

.cms-preview-card {
    display: flex;
    background-color: #ffffff;
    border-radius: 12px;
    overflow: hidden;
    position: relative;
    min-height: 260px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

.cms-preview-sidebar {
    width: 110px;
    flex-shrink: 0;
    position: relative;
    overflow: hidden;
    z-index: 2;
}

.cms-preview-sidebar.cms-green { background-color: var(--gnc-green-light, #145c3a); }
.cms-preview-sidebar.cms-gold  { background-color: var(--gnc-gold, #d3a63a); }

.cms-preview-watermark-seal {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 220px;
    height: 220px;
    opacity: .15;
    background-image: url('/assets/images/logos/gnc-logo-v1.svg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
}

.cms-preview-watermark-building {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 100%;
    height: 120px;
    background-image: url('/assets/images/svg/gnc-illustration.svg');
    background-size: cover;
    background-position: bottom center;
    background-repeat: no-repeat;
    z-index: 1;
    pointer-events: none;
    filter: invert(1);
    mix-blend-mode: multiply;
    opacity: .2;
}

.cms-preview-icon-circle {
    position: absolute;
    top: 28px;
    left: 50%;
    transform: translateX(-50%);
    width: 72px;
    height: 72px;
    background-color: #ffffff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 3;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.cms-preview-icon-circle img {
    width: 34px;
}

.cms-preview-content {
    padding: 38px 32px 30px 34px;
    flex-grow: 1;
    position: relative;
    z-index: 2;
}

.cms-preview-title {
    font-family: 'Noto Serif', serif;
    font-weight: 700;
    font-size: 26px;
    margin-bottom: 18px;
    position: relative;
    display: inline-block;
}

.cms-preview-title::after {
    content: '';
    position: absolute;
    bottom: -6px;
    left: 0;
    width: 100%;
    height: 2px;
    background-color: currentColor;
}

.cms-preview-title.cms-green { color: var(--gnc-green-light, #145c3a); }
.cms-preview-title.cms-gold  { color: var(--gnc-gold, #d3a63a); }

.cms-preview-text {
    font-family: 'Inter', sans-serif;
    font-weight: 500;
    color: var(--gnc-text-dark, #333333);
    font-size: 16px;
    line-height: 1.5;
    margin: 0;
    white-space: pre-line;
}

.slide-list {
    display: flex;
    flex-direction: column;
    gap: .5rem;
    max-height: 640px;
    overflow-y: auto;
    padding-bottom: .5rem;
}

.slide-item {
    display: flex;
    align-items: center;
    gap: .6rem;
    border: 1px solid #eee;
    border-radius: 10px;
    padding: .6rem .7rem;
    background: #fff;
    cursor: pointer;
    transition: border-color var(--transition), background var(--transition);
}

.slide-item:hover {
    border-color: rgba(9, 64, 36, 0.25);
    background: var(--gnc-cream);
}

.slide-item.selected {
    border-color: var(--gnc-green);
    background: rgba(9, 64, 36, 0.06);
}

.slide-item.dragging {
    opacity: .4;
}

.slide-drag-handle {
    color: #bbb;
    cursor: grab;
    font-size: 1rem;
}

.slide-number {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--gnc-green);
    color: #fff;
    font-size: .72rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.slide-thumb {
    width: 56px;
    height: 40px;
    border-radius: 6px;
    overflow: hidden;
    flex-shrink: 0;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
}

.slide-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.slide-item-info {
    flex: 1;
    min-width: 0;
}

.slide-item-title {
    font-weight: 600;
    font-size: .85rem;
    color: #222;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.slide-item-sub {
    font-size: .72rem;
    color: #888;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Reuses the .status-badge naming convention from header-admin-style.css */
.badge-status {
    font-size: .65rem;
    font-weight: 700;
    padding: .15rem .5rem;
    border-radius: 20px;
    display: inline-block;
    margin-top: 2px;
}

.badge-status.published {
    background: #d1e7dd;
    color: #0a6932;
}

.badge-status.draft {
    background: #f0f0f0;
    color: #666;
}

.slide-item-menu {
    border: none;
    background: none;
    color: #aaa;
    padding: .2rem .4rem;
}

.preview-tabs {
    display: flex;
    gap: .4rem;
    margin-bottom: .6rem;
}

.preview-tab {
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 20px;
    padding: .35rem 1rem;
    font-size: .78rem;
    font-weight: 600;
    color: #666;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    cursor: pointer;
    transition: background var(--transition), border-color var(--transition), color var(--transition);
}

.preview-tab:hover {
    border-color: rgba(9, 64, 36, 0.3);
}

.preview-tab.active {
    background: var(--gnc-green);
    border-color: var(--gnc-green);
    color: #fff;
}

/* ─────────────────────────────────────────────────────────────────────
   Slide preview — mirrors the real homepage hero (assets/css/index-style.css:
   .hero-slide / .hero-content / .hero-title-line / .btn-enroll / .btn-explore /
   .slide-arrow / .hero-dots). Every size is written in cqw (1% of the preview's
   width) and is the real site's px value divided by a 1440px desktop viewport
   (390px for the mobile preview), so the preview is a true scaled-down copy.
   If the site's hero CSS changes, update the numbers here to match.
   ───────────────────────────────────────────────────────────────────── */
.mini-hero-wrap {
    display: flex;
    justify-content: center;
    transition: all var(--transition);
}

.mini-hero {
    position: relative;
    width: 100%;
    aspect-ratio: 1440 / 720;
    border-radius: 12px;
    overflow: hidden;
    background: var(--gnc-green-dark);
    color: #fff;
    display: flex;
    align-items: center;
    container-type: inline-size;
    transition: aspect-ratio var(--transition), max-width var(--transition);
}

.mini-hero-bg {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
}

#mini-hero-video {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}

/* same gradient as .slide-overlay */
.mini-hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(105deg, rgba(9, 64, 36, .72) 0%, rgba(0, 0, 0, .45) 55%, rgba(0, 0, 0, .15) 100%);
    z-index: 1;
}

/* .container (left edge ≈ 72px @1440) + .hero-content (max-width 600px) */
.mini-hero-content {
    position: relative;
    z-index: 2;
    box-sizing: border-box;
    padding-left: 5cqw;
    max-width: 46.67cqw;
    text-align: left;
}

.mini-title {
    font-family: 'Noto Serif', serif;
    font-weight: 800;
    font-size: 3.333cqw;      /* 3rem */
    line-height: 1.1;
    letter-spacing: .01em;
    color: var(--gnc-gold);
    text-transform: uppercase;
    margin: 0 0 1.111cqw;
}

.mini-sub {
    font-family: 'Inter', sans-serif;
    font-size: 1.056cqw;      /* .95rem */
    line-height: 1.6;
    color: rgba(255, 255, 255, .88);
    margin: 0 0 2cqw;
    max-width: 29.17cqw;      /* 420px */
}

.mini-title:empty,
.mini-sub:empty {
    display: none;
}

.mini-ctas {
    display: flex;
    gap: 1.111cqw;
    flex-wrap: wrap;
}

.mini-btn {
    display: inline-flex;
    align-items: center;
    gap: .417cqw;
    font-family: 'Inter', sans-serif;
    font-size: .911cqw;       /* .82rem */
    font-weight: 700;
    line-height: 1.5;
    padding: .722cqw 1.667cqw;
    border: .14cqw solid transparent;
    border-radius: .417cqw;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.mini-btn:empty {
    display: none;
}

.mini-btn-primary {
    background: var(--gnc-gold);
    border-color: var(--gnc-gold);
    color: var(--gnc-green);
}

/* the arrow the real "Enroll Now" button has */
.mini-btn-primary::after {
    content: '';
    width: .903cqw;
    height: .903cqw;
    background: currentColor;
    -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z'/%3E%3C/svg%3E") center / contain no-repeat;
            mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z'/%3E%3C/svg%3E") center / contain no-repeat;
}

.mini-btn-secondary {
    border-color: rgba(255, 255, 255, .6);
    color: #fff;
}

.mini-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 3;
    width: 3.056cqw;          /* 44px */
    height: 3.056cqw;
    border-radius: 50%;
    background: rgba(255, 255, 255, .12);
    border: 1px solid rgba(255, 255, 255, .25);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2cqw;
}

.mini-arrow-prev { left: 1.389cqw; }
.mini-arrow-next { right: 1.389cqw; }

.mini-dots {
    position: absolute;
    bottom: 5.556cqw;         /* 80px */
    left: 0;
    right: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: .556cqw;
    z-index: 3;
}

.mini-dots span {
    width: .556cqw;
    height: .556cqw;
    border-radius: 50%;
    background: rgba(255, 255, 255, .4);
}

.mini-dots span.active {
    width: 1.944cqw;
    border-radius: .278cqw;
    background: var(--gnc-gold);
}

/* ── Mobile preview: same rules as the site's @media (max-width:768px),
   measured against a 390px-wide phone. Content is centered, buttons stack
   full-width, title stays at clamp()'s 2rem floor. */
.mini-hero-wrap.preview-mobile {
    background: #f4f4f4;
    border-radius: 12px;
    padding: 1.25rem 0;
}

.mini-hero-wrap.preview-mobile .mini-hero {
    aspect-ratio: 390 / 736;
    max-width: 300px;
    border-radius: 0;
    box-shadow: 0 0 0 1px #ddd;
}

.mini-hero-wrap.preview-mobile .mini-hero-content {
    width: 100%;
    max-width: 100%;
    padding: 0 3.08cqw;       /* container gutter 12px */
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.mini-hero-wrap.preview-mobile .mini-title {
    font-size: 8.205cqw;      /* 2rem */
    margin-bottom: 4.1cqw;
}

.mini-hero-wrap.preview-mobile .mini-sub {
    font-size: 3.897cqw;
    margin-bottom: 7.38cqw;
    max-width: 100%;
}

.mini-hero-wrap.preview-mobile .mini-ctas {
    flex-direction: column;
    align-items: center;
    width: 100%;
    gap: 4.1cqw;
}

.mini-hero-wrap.preview-mobile .mini-btn {
    width: 100%;
    justify-content: center;
    box-sizing: border-box;
    font-size: 3.364cqw;
    padding: 2.667cqw 6.154cqw;
    border-width: .513cqw;
    border-radius: 1.538cqw;
    gap: 1.538cqw;
}

.mini-hero-wrap.preview-mobile .mini-btn-primary::after {
    width: 3.333cqw;
    height: 3.333cqw;
}

.mini-hero-wrap.preview-mobile .mini-arrow {
    width: 9.23cqw;           /* 36px */
    height: 9.23cqw;
    font-size: 3.6cqw;
}

.mini-hero-wrap.preview-mobile .mini-arrow-prev { left: 2.46cqw; }
.mini-hero-wrap.preview-mobile .mini-arrow-next { right: 2.46cqw; }

.mini-hero-wrap.preview-mobile .mini-dots {
    bottom: 20.5cqw;
    gap: 2.05cqw;
}

.mini-hero-wrap.preview-mobile .mini-dots span {
    width: 2.05cqw;
    height: 2.05cqw;
}

.mini-hero-wrap.preview-mobile .mini-dots span.active {
    width: 7.18cqw;
    border-radius: 1.026cqw;
}

.bg-media-field-label {
    font-size: .78rem;
    font-weight: 600;
    color: #555;
    margin-bottom: .4rem;
    display: flex;
    align-items: center;
    gap: .35rem;
}

.media-swatch {
    width: 52px;
    height: 40px;
    border-radius: 8px;
    background: #f1f1f1;
    color: #999;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
}

.media-swatch img,
.media-swatch video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.counter.over-limit {
    color: #dc3545;
    font-weight: 700;
}

/* .btn-gnc-gold / .btn-gnc-primary render as inline-block by default via Bootstrap's
   button reset; this keeps them lined up with the page-header row and form footer. */
.page-header .btn-gnc-gold {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
}

.page-panel {
    animation: page-panel-fade .15s ease;
}

@keyframes page-panel-fade {
    from { opacity: 0; }
    to   { opacity: 1; }
}

.section-status-badge.published {
    background: #d1e7dd;
    color: #0a6932;
}

.section-status-badge.draft {
    background: #fff3cd;
    color: #8a6100;
}
</style>

<script>
    window.SLIDE_EDITOR_CONFIG = {
        csrfToken: <?= json_encode($csrfToken) ?>,
        ajaxUrl: '/admin/action/page-content-handler.php'
    };
</script>
<script src="/assets/js/content-edit.js"></script>

<script>
// ══════════════════ Content Editor: page selector + generic section editor ══════════════════
// Handles switching between "Select Page" options and the Save / Preview / Publish /
// Discard Draft actions for section-based pages (Vision & Mission, About Us, etc.).
// The homepage Slideshow editor above is untouched and keeps using /assets/js/content-edit.js.
(function () {
    var PAGE_CONTENT_AJAX_URL = '/admin/action/page-content-handler.php';
    var csrfToken = <?= json_encode($csrfToken) ?>;

    // ── Page selector ──
    var pageSelect = document.getElementById('page-select');
    if (pageSelect) {
        pageSelect.addEventListener('change', function () {
            var slug = this.value;
            document.querySelectorAll('.page-panel').forEach(function (panel) {
                panel.style.display = (panel.id === 'page-panel-' + slug) ? '' : 'none';
            });
            // Keep the URL shareable/bookmarkable without a full page reload.
            if (window.history && window.history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.set('page', slug);
                window.history.replaceState({}, '', url);
            }
        });
    }

    function showSectionAlert(card, message, isError) {
        var alertEl = card.querySelector('.section-alert');
        if (!alertEl) return;
        alertEl.className = 'section-alert alert mt-2 mb-0 ' + (isError ? 'alert-danger' : 'alert-success');
        alertEl.textContent = message;
        alertEl.classList.remove('d-none');
        setTimeout(function () { alertEl.classList.add('d-none'); }, 4000);
    }

    function setStatusBadge(card, hasDraft) {
        var badge = card.querySelector('.section-status-badge');
        if (!badge) return;
        badge.classList.toggle('draft', hasDraft);
        badge.classList.toggle('published', !hasDraft);
        badge.textContent = hasDraft ? 'Unpublished changes' : 'Published';
        var discardBtn = card.querySelector('.btn-discard-draft');
        if (discardBtn) discardBtn.disabled = !hasDraft;
    }

    function postAction(action, payload) {
        var body = new URLSearchParams(Object.assign({ action: action, csrf_token: csrfToken }, payload));
        return fetch(PAGE_CONTENT_AJAX_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (res) { return res.json(); });
    }

    document.querySelectorAll('.page-section-card').forEach(function (card) {
        var pageSlug    = card.dataset.page;
        var sectionKey  = card.dataset.section;
        var textarea    = card.querySelector('.section-textarea');

        var saveBtn    = card.querySelector('.btn-save-section');
        var previewBtn = card.querySelector('.btn-preview-section');
        var publishBtn = card.querySelector('.btn-publish-section');
        var discardBtn = card.querySelector('.btn-discard-draft');

        if (saveBtn) saveBtn.addEventListener('click', function () {
            saveBtn.disabled = true;
            postAction('save_draft', { page: pageSlug, section_key: sectionKey, content: textarea.value })
                .then(function (data) {
                    if (data.success) {
                        setStatusBadge(card, true);
                        showSectionAlert(card, 'Draft saved. Publish to make it live.', false);
                    } else {
                        showSectionAlert(card, data.error || 'Could not save.', true);
                    }
                })
                .catch(function () { showSectionAlert(card, 'Network error. Please try again.', true); })
                .finally(function () { saveBtn.disabled = false; });
        });

        // Vision & Mission sections get a mini replica of their actual public
        // card (see .cms-preview-* CSS above); anything else falls back to
        // plain text so new pages/sections still get a usable preview.
        var VM_CARD_CONFIG = {
            vision:  { colorClass: 'cms-green', icon: '/assets/images/svg/vision icon.svg',  label: 'Our Vision'  },
            mission: { colorClass: 'cms-gold',  icon: '/assets/images/svg/mission icon.svg', label: 'Our Mission' }
        };

        if (previewBtn) previewBtn.addEventListener('click', function () {
            var modalBody = document.getElementById('sectionPreviewBody');
            var cardCfg = (pageSlug === 'vision-mission') ? VM_CARD_CONFIG[sectionKey] : null;

            if (cardCfg) {
                modalBody.innerHTML =
                    '<div class="cms-preview-card">' +
                        '<div class="cms-preview-sidebar ' + cardCfg.colorClass + '">' +
                            '<div class="cms-preview-watermark-seal"></div>' +
                            '<div class="cms-preview-icon-circle"><img src="' + cardCfg.icon + '" alt=""></div>' +
                        '</div>' +
                        '<div class="cms-preview-watermark-building"></div>' +
                        '<div class="cms-preview-content">' +
                            '<h2 class="cms-preview-title ' + cardCfg.colorClass + '">' + cardCfg.label + '</h2>' +
                            '<p class="cms-preview-text"></p>' +
                        '</div>' +
                    '</div>';
                // Set via textContent (not innerHTML) so the admin's typed text is
                // never parsed as markup, even though it's their own content.
                modalBody.querySelector('.cms-preview-text').textContent = textarea.value;
            } else {
                modalBody.innerHTML = '<p style="white-space:pre-line; margin:0"></p>';
                modalBody.querySelector('p').textContent = textarea.value;
            }

            var modalEl = document.getElementById('sectionPreviewModal');
            if (window.bootstrap && modalEl) {
                new bootstrap.Modal(modalEl).show();
            }
        });

        if (publishBtn) publishBtn.addEventListener('click', function () {
            publishBtn.disabled = true;
            // Publish always saves the current textarea first, then publishes it,
            // so clicking Publish directly (without a prior Save) still works.
            postAction('save_draft', { page: pageSlug, section_key: sectionKey, content: textarea.value })
                .then(function () {
                    return postAction('publish', { page: pageSlug, section_key: sectionKey });
                })
                .then(function (data) {
                    if (data.success) {
                        setStatusBadge(card, false);
                        showSectionAlert(card, 'Published to the live site.', false);
                    } else {
                        showSectionAlert(card, data.error || 'Could not publish.', true);
                    }
                })
                .catch(function () { showSectionAlert(card, 'Network error. Please try again.', true); })
                .finally(function () { publishBtn.disabled = false; });
        });

        if (discardBtn) discardBtn.addEventListener('click', function () {
            discardBtn.disabled = true;
            postAction('discard_draft', { page: pageSlug, section_key: sectionKey })
                .then(function (data) {
                    if (data.success) {
                        // Revert the textarea to the published value returned by the server
                        // by simply re-fetching this page's sections is overkill here —
                        // reload just this panel's content from the page instead.
                        window.location.reload();
                    } else {
                        showSectionAlert(card, 'Could not discard draft.', true);
                    }
                })
                .catch(function () { showSectionAlert(card, 'Network error. Please try again.', true); });
        });
    });
})();
</script>

<?php include __DIR__ . '/../components/footer-admin.php'; ?>