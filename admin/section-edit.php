<?php
/**
 * GNC Admin Panel - Section Editor: Homepage Slideshow
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';

initSession();
requireLogin();

$currentUser = getCurrentUser();
$pageTitle   = 'Section Editor';
$slides      = getHeroSlides(false); // all slides, including drafts, for the admin list

include __DIR__ . '/../components/header-admin.php';
?>

<div class="page-header">
    <div>
        <h1>Slideshow</h1>
        <p>Add, remove, and reorder slides that appear in the homepage slideshow.</p>
    </div>
    <button type="button" class="btn-gnc-gold" id="btn-add-slide">
        <i class="bi bi-plus-lg"></i> Add New Slide
    </button>
</div>

<div class="row g-3">
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

                <!-- Live preview -->
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

                    <!-- Background Media -->
                    <div class="col-md-6">
                        <label class="form-label">Background Media</label>
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

                    <!-- Status + Mobile -->
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

<style>
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

.mini-hero {
    position: relative;
    width: 100%;
    aspect-ratio: 16/8;
    border-radius: 12px;
    overflow: hidden;
    background: var(--gnc-green-dark);
    color: #fff;
    display: flex;
    align-items: center;
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
}

.mini-hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, rgba(9, 64, 36, .85) 10%, rgba(9, 64, 36, .35) 60%, rgba(9, 64, 36, .15) 100%);
}

.mini-hero-content {
    position: relative;
    z-index: 2;
    padding: 0 8%;
    max-width: 70%;
}

.mini-title {
    font-family: 'Noto Serif', serif;
    font-weight: bold;
    font-size: clamp(1rem, 2.6vw, 1.7rem);
    line-height: 1.15;
    color: var(--gnc-gold);
    text-transform: uppercase;
    white-space: pre-line;
}

.mini-sub {
    font-family: 'Inter', sans-serif;
    font-size: .8rem;
    color: #f1f1f1;
    margin-top: .4rem;
    max-width: 420px;
}

.mini-ctas {
    display: flex;
    gap: .5rem;
    margin-top: .9rem;
    flex-wrap: wrap;
}

.mini-btn {
    font-size: .7rem;
    font-weight: 700;
    padding: .4rem .9rem;
    border-radius: 30px;
    letter-spacing: .03em;
}

.mini-btn:empty {
    display: none;
}

.mini-btn-primary {
    background: var(--gnc-gold);
    color: var(--gnc-green);
}

.mini-btn-secondary {
    border: 1.5px solid #fff;
    color: #fff;
}

.mini-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 3;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: rgba(255, 255, 255, .2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}

.mini-arrow-prev {
    left: 12px;
}

.mini-arrow-next {
    right: 12px;
}

.mini-dots {
    position: absolute;
    bottom: 10px;
    left: 0;
    right: 0;
    display: flex;
    justify-content: center;
    gap: 5px;
    z-index: 3;
}

.mini-dots span {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: rgba(255, 255, 255, .5);
}

.mini-dots span.active {
    background: var(--gnc-gold);
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
</style>

<script>
    window.SLIDE_EDITOR_CONFIG = {
        csrfToken: <?= json_encode($csrfToken) ?>,
        ajaxUrl: '/admin/action/hero-slides-handler.php'
    };
</script>
<script src="/assets/js/section-edit.js"></script>

<?php include __DIR__ . '/../components/footer-admin.php'; ?>