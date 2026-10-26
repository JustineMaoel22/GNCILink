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
require_once __DIR__ . '/../includes/facebook-post-url.php';
require_once __DIR__ . '/admin-functions.php';
require_once __DIR__ . '/../includes/facebook-publishing.php';

initSession();
requireLogin();

$facebookPostsPage = ($facebookPostsPage ?? false) === true;
// Keep bookmarks to the former Facebook filter working.
if (!$facebookPostsPage && ($_GET['source'] ?? '') === 'facebook') {
    $query = [];
    if (is_string($_GET['search'] ?? null)) $query['search'] = $_GET['search'];
    if (($_GET['embed'] ?? '') === '1') $query['embed'] = '1';
    header('Location: /admin/facebook-posts.php' . ($query ? '?' . http_build_query($query) : ''));
    exit;
}

// Flash from a failed submit (set by the action handler)
$flashErrors = $_SESSION['flash_errors'] ?? [];
$flashOld    = $_SESSION['flash_old'] ?? [];
require_once __DIR__.'/../includes/content-submissions.php';
if (isset($_GET['revise'])) $flashOld=rbacRevisionFormData('/admin/action/create-announcement.php');
$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_errors'], $_SESSION['flash_old'], $_SESSION['flash_success']);

// ============================================================
// LIST DATA
// ============================================================
$statusFilter  = $facebookPostsPage ? '' : ($_GET['status'] ?? '');
$programFilter = $facebookPostsPage ? '' : ($_GET['program'] ?? '');
$search        = trim($_GET['search'] ?? '');
$sourceFilter = $facebookPostsPage ? 'facebook' : 'website';
$filters       = [];
if ($statusFilter !== '') $filters['status'] = $statusFilter;
if ($programFilter !== '') $filters['program'] = $programFilter;
if ($search !== '') $filters['search'] = $search;

// Source filter: "facebook" hides website announcements entirely.
$announcements = ($sourceFilter === 'facebook') ? [] : getAnnouncements($filters, 50, 0);
$pinnedCount   = countPinnedItems();

// Facebook posts have their own sidebar page; both lists retain search.
$fbPosts = [];
if ($facebookPostsPage) {
    $fbPosts = getFacebookPostsAdminList(50, $search);
}

try {
    $categories = getDB()->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

$programCategories = getProgramCategories();
$announcementProgramChoices = getAnnouncementProgramCategories();

try {
    $departments = getDB()->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_id ASC")->fetchAll();
} catch (Exception $e) {
    $departments = [];
}

// Which modal (if any) should auto-open on load
$modalMode    = $facebookPostsPage ? '' : ($_GET['modal'] ?? '');   // '', 'create', or 'edit'
$modalEditing = null;
if ($modalMode === 'edit') {
    $modalEditing = getAnnouncementById((int)($_GET['id'] ?? 0));
}
if (!empty($flashOld)) {
    $modalEditing = array_merge($modalEditing ?? [], $flashOld);
}


// ---- Friendly wording + helpers for non-technical admins ----
$statusLabels = [
    'draft'     => 'Draft',
    'pending'   => 'Waiting for approval',
    'published' => 'Live on website',
    'archived'  => 'Archived',
];
$statusHelp = [
    'draft'     => 'Only admins can see it. Use this while you are still working on it.',
    'pending'   => 'Sent to an approver. It goes live once approved.',
    'published' => 'Visible to everyone on the public website right away.',
    'archived'  => 'Kept on record but hidden from the public website. You can restore it later.',
];
if (!function_exists('ann_url')) {
    function ann_url(array $base, array $over): string {
        $q = array_filter(array_merge($base, $over), function ($v) { return $v !== '' && $v !== null; });
        return '?' . http_build_query($q);
    }
}
$baseQuery = ['search' => $search, 'source' => $sourceFilter, 'status' => $statusFilter, 'program' => $programFilter];
$filtersActive = ($search !== '' || $statusFilter !== '' || $programFilter !== '');
$canPublish    = hasPermission('publish_announcement');
$facebookPublishingReady = facebookPublishingReady();
$facebookConnection = facebookPublishingConnection();
$facebookDeliveries = [];
if ($facebookPublishingReady && $announcements) {
    $deliveryIds = array_column($announcements, 'announcement_id');
    $stmt = getDB()->prepare('SELECT * FROM announcement_facebook_deliveries WHERE announcement_id IN (' . implode(',', array_fill(0, count($deliveryIds), '?')) . ')');
    $stmt->execute($deliveryIds);
    foreach ($stmt->fetchAll() as $delivery) $facebookDeliveries[$delivery['announcement_id']] = $delivery;
}
// Super admins are the approvers, so they never need "Send for approval".
$isSuperAdmin  = (($_SESSION['user_role'] ?? '') === 'Super Admin');

$pageTitle = $facebookPostsPage ? 'Facebook Posts' : 'Announcements';
include __DIR__ . '/../components/header-admin.php';
?>

<div class="page-header">
    <div>
        <h1><?= $pageTitle ?></h1>
        <p><?= rbacRole() === 'The Mirror' ? ($facebookPostsPage ? 'Browse posts retrieved from the school Facebook Page. Open a post to read it on Facebook.' : 'Browse announcements from all users. Create your own announcements and submit changes for approval.') : ($facebookPostsPage ? 'Manage posts retrieved from the school Facebook Page. Update their categories or pin them to the homepage slideshow.' : 'Share news with students and parents. Write a new announcement, or manage the ones you already posted.') ?></p>
    </div>
    <?php if (!$facebookPostsPage && hasPermission('create_announcement')): ?>
    <button type="button" class="btn btn-gnc-gold" id="btn-new-announcement">
        <i class="bi bi-plus-lg"></i> New Announcement
    </button>
    <?php endif; ?>
</div>

<?php if (!empty($flashErrors) && !in_array($modalMode, ['create', 'edit'], true)): ?>
<div class="alert alert-danger d-flex gap-2 align-items-start">
    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
    <div>
        <?php foreach ($flashErrors as $err): ?>
            <div><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($canPublish): ?>
<div class="ann-pin-banner">
    <div class="ann-pin-banner-icon"><i class="bi bi-pin-angle-fill"></i></div>
    <div class="flex-grow-1">
        <div class="fw-semibold">Homepage slideshow: <?= (int)$pinnedCount ?> of <?= MAX_PINNED_ITEMS ?> spots used</div>
        <div class="ann-sub">Pin an announcement, news item, or Facebook post to show it in the big slideshow on the homepage.<?= $pinnedCount >= MAX_PINNED_ITEMS ? ' All spots are taken — unpin one to pin another.' : '' ?></div>
    </div>
    <div class="ann-pin-dots" aria-hidden="true">
        <?php for ($i = 1; $i <= MAX_PINNED_ITEMS; $i++): ?><span class="<?= $i <= $pinnedCount ? 'on' : '' ?>"></span><?php endfor; ?>
    </div>
</div>
<?php endif; ?>

<div class="data-card">
    <div class="data-card-header flex-wrap gap-2">
        <span class="data-card-title"><i class="bi bi-<?= $facebookPostsPage ? 'facebook' : 'megaphone-fill' ?> me-1"></i> <?= $facebookPostsPage ? 'Facebook Posts' : 'All Announcements' ?></span>
        <form method="GET" class="ann-search ms-auto">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <input type="hidden" name="source" value="<?= htmlspecialchars($sourceFilter) ?>">
            <div class="ann-search-box">
                <i class="bi bi-search"></i>
                <input type="search" name="search" id="announcement-search" class="form-control form-control-sm"
                       placeholder="Search by title or content…" value="<?= htmlspecialchars($search) ?>" autocomplete="off">
            </div>
            <?php if (!$facebookPostsPage): ?>
            <select name="program" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Filter by program">
                <option value="">All programs</option>
                <?php foreach ($programCategories as $code => $label): ?>
                <option value="<?= htmlspecialchars($code) ?>" <?= $programFilter === $code ? 'selected' : '' ?>><?= htmlspecialchars($code === 'ALL' ? 'For everyone' : $code) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!$facebookPostsPage || $filtersActive): ?>
    <div class="ann-filters">
        <?php if (!$facebookPostsPage): ?>
        <div class="ann-chip-row">
            <a class="ann-chip<?= $statusFilter === '' ? ' active' : '' ?>" href="<?= ann_url($baseQuery, ['status' => '']) ?>">All</a>
            <?php foreach ($statusLabels as $key => $label): ?>
            <a class="ann-chip<?= $statusFilter === $key ? ' active' : '' ?>" href="<?= ann_url($baseQuery, ['status' => $key]) ?>"><?= $key === 'pending' ? 'Pending' : ($key === 'published' ? 'Live' : ($key === 'draft' ? 'Drafts' : $label)) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="ann-chip-row">
            <?php if ($filtersActive): ?>
            <a class="ann-clear" href="?"><i class="bi bi-x-circle"></i> Clear filters</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover mb-0 ann-table" id="announcements-table">
            <thead>
                <tr>
                    <th style="width:72px"></th>
                    <th><?= $facebookPostsPage ? 'Facebook post' : 'Announcement' ?></th>
                    <th>Category</th>
                    <th>For</th>
                    <th>Status</th>
                    <th style="width:1%;white-space:nowrap">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($announcements) && empty($fbPosts)): ?>
                <tr class="ann-empty-row"><td colspan="6">
                    <div class="ann-empty">
                        <i class="bi bi-megaphone"></i>
                        <?php if ($filtersActive): ?>
                            <p class="mb-2"><?= $facebookPostsPage ? 'No Facebook posts match your search.' : 'No announcements match your search or filters.' ?></p>
                            <a href="?" class="btn btn-sm btn-outline-secondary">Clear filters</a>
                        <?php else: ?>
                            <p class="mb-2"><?= $facebookPostsPage ? 'No Facebook posts have been retrieved yet.' : 'No announcements yet.' ?></p>
                            <?php if (!$facebookPostsPage && hasPermission('create_announcement')): ?>
                            <button type="button" class="btn btn-sm btn-gnc-gold js-new-announcement"><i class="bi bi-plus-lg"></i> Write your first announcement</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </td></tr>
                <?php else: foreach ($announcements as $a):
                    $isOwner = $a['user_id'] == ($_SESSION['user_id'] ?? null);
                    $authorName = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
                    $progCode = $a['program'] ?? 'ALL';
                ?>
                <tr class="ann-row">
                    <td class="ann-img-cell">
                        <?php if (!empty($a['image_path'])): ?>
                        <img src="<?= htmlspecialchars($a['image_path']) ?>" alt="" class="ann-thumb">
                        <?php else: ?>
                        <div class="ann-thumb ann-thumb-empty"><i class="bi bi-image"></i></div>
                        <?php endif; ?>
                    </td>
                    <td class="ann-title-cell">
                        <div class="ann-title"><?= htmlspecialchars($a['title']) ?></div>
                        <div class="ann-sub">
                            <?= $authorName !== '' ? 'By ' . htmlspecialchars($authorName) . ' &middot; ' : '' ?><?= date('M j, Y', strtotime($a['created_at'])) ?>
                        </div>
                        <?php if (!empty($a['is_pinned'])): ?>
                        <span class="ann-pinned"><i class="bi bi-pin-angle-fill"></i> On homepage slideshow</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Category"><?= htmlspecialchars($a['category_name'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
                    <td data-label="For">
                        <div class="ann-for">
                            <span><?= htmlspecialchars($a['dept_name'] ?? 'All departments') ?></span>
                            <span class="program-badge<?= $progCode === 'ALL' ? ' program-badge-all' : '' ?>"><?= $progCode === 'ALL' ? 'All programs' : htmlspecialchars($progCode) ?></span>
                        </div>
                    </td>
                    <td data-label="Status"><span class="status-badge <?= htmlspecialchars($a['status']) ?>"><?= htmlspecialchars($statusLabels[$a['status']] ?? ucfirst($a['status'])) ?></span>
                        <?php if (isset($facebookDeliveries[$a['announcement_id']])): $delivery = $facebookDeliveries[$a['announcement_id']]; ?>
                        <div class="small mt-1">Facebook: <?= htmlspecialchars($delivery['state']) ?> · Page <?= htmlspecialchars($delivery['page_id']) ?></div>
                        <?php if ($delivery['post_url']): ?><a class="small" target="_blank" rel="noopener noreferrer" href="<?= htmlspecialchars(facebookPublishingViewPostUrl($delivery['post_url'], $delivery['post_id'] ?? '')) ?>">View Facebook post</a><?php endif; ?>
                        <?php if ($delivery['last_error']): ?><div class="small text-danger"><?= htmlspecialchars($delivery['last_error']) ?></div><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="ann-actions-cell">
                        <?php if (rbacRole() === 'The Mirror' && !$isOwner): ?><span class="text-muted small">Read only</span><?php endif; ?>
                        <?php if ($canPublish): ?>
                        <form method="POST" action="/admin/action/create-announcement.php" class="ann-inline-form">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="<?= !empty($a['is_pinned']) ? 'unpin' : 'pin' ?>">
                            <input type="hidden" name="announcement_id" value="<?= $a['announcement_id'] ?>">
                            <button type="submit"
                                    class="btn btn-sm btn-outline-secondary btn-pin-toggle<?= !empty($a['is_pinned']) ? ' is-pinned' : '' ?>"
                                    <?php if (empty($a['is_pinned']) && $pinnedCount >= MAX_PINNED_ITEMS): ?>
                                    disabled title="All <?= MAX_PINNED_ITEMS ?> slideshow spots are used. Unpin one first."
                                    <?php elseif ($a['status'] === 'archived'): ?>
                                    disabled title="Restore it first to show it in the slideshow."
                                    <?php else: ?>
                                    title="<?= !empty($a['is_pinned']) ? 'Remove from the homepage slideshow' : 'Show in the homepage slideshow' ?>"
                                    <?php endif; ?>>
                                <i class="bi <?= !empty($a['is_pinned']) ? 'bi-pin-angle-fill' : 'bi-pin-angle' ?>"></i>
                                <span class="visually-hidden"><?= !empty($a['is_pinned']) ? 'Unpin' : 'Pin' ?></span>
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php if (rbacRole() !== 'The Mirror' && ($canPublish || hasPermission('edit_announcement') || (hasPermission('edit_own_announcement') && $isOwner))): ?>
                        <form method="POST" action="/admin/action/create-announcement.php" class="ann-inline-form">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="<?= $a['status'] === 'archived' ? 'unarchive' : 'archive' ?>">
                            <input type="hidden" name="announcement_id" value="<?= $a['announcement_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                    title="<?= $a['status'] === 'archived' ? 'Bring it back as a draft' : 'Hide it from the website but keep it on record' ?>">
                                <i class="bi <?= $a['status'] === 'archived' ? 'bi-arrow-counterclockwise' : 'bi-archive' ?>"></i>
                                <span class="visually-hidden"><?= $a['status'] === 'archived' ? 'Restore' : 'Archive' ?></span>
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php if (hasPermission('edit_announcement') || (hasPermission('edit_own_announcement') && $isOwner)): ?>
                        <?php $rowGallery = getAnnouncementImages((int)$a['announcement_id']); ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-announcement" title="Edit this announcement"
                                data-id="<?= $a['announcement_id'] ?>"
                                data-title="<?= htmlspecialchars($a['title'], ENT_QUOTES) ?>"
                                data-category="<?= (int)($a['category_id'] ?? 0) ?>"
                                data-program="<?= htmlspecialchars($a['program'] ?? 'ALL') ?>"
                                data-dept="<?= (int)($a['dept_id'] ?? 0) ?>"
                                data-status="<?= htmlspecialchars($a['status']) ?>"
                                data-image="<?= htmlspecialchars($a['image_path'] ?? '') ?>"
                                data-gallery="<?= htmlspecialchars(json_encode($rowGallery), ENT_QUOTES) ?>"
                                data-content-id="ann-content-<?= $a['announcement_id'] ?>">
                            <i class="bi bi-pencil"></i><span class="visually-hidden">Edit</span>
                        </button>
                        <!-- Raw content stashed in a hidden template (not JSON) -->
                        <template id="ann-content-<?= $a['announcement_id'] ?>"><?= $a['content'] ?></template>
                        <?php endif; ?>
                        <?php if (hasPermission('delete_announcement') || (hasPermission('delete_own_announcement') && $isOwner)): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-announcement" title="Delete this announcement"
                                data-id="<?= $a['announcement_id'] ?>"
                                data-title="<?= htmlspecialchars($a['title'], ENT_QUOTES) ?>">
                            <i class="bi bi-trash"></i><span class="visually-hidden">Delete</span>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach;
                foreach ($fbPosts as $fb):
                    $fbLines = preg_split('/\R+/u', trim($fb['message']));
                    $fbTitle = trim($fbLines[0] ?? '');
                    $fbTitle = mb_strlen($fbTitle) > 90 ? mb_substr($fbTitle, 0, 90) . '…' : $fbTitle;
                ?>
                <tr class="ann-row">
                    <td class="ann-img-cell">
                        <?php if (!empty($fb['image_path'])): ?>
                        <img src="<?= htmlspecialchars($fb['image_path']) ?>" alt="" class="ann-thumb">
                        <?php else: ?>
                        <div class="ann-thumb ann-thumb-empty"><i class="bi bi-facebook"></i></div>
                        <?php endif; ?>
                    </td>
                    <td class="ann-title-cell">
                        <div class="ann-title"><?= htmlspecialchars($fbTitle) ?></div>
                        <div class="ann-sub">From the Facebook Page &middot; <?= date('M j, Y', strtotime($fb['created_time'])) ?></div>
                        <?php if (!empty($fb['is_pinned'])): ?>
                        <span class="ann-pinned"><i class="bi bi-pin-angle-fill"></i> On homepage slideshow</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Category">
                        <?= htmlspecialchars($fb['category_name'] ?? '') ?: '<span class="text-muted">—</span>' ?>
                        <?php if (!empty($fb['category_locked'])): ?>
                        <i class="bi bi-pencil-fill" style="font-size:.65rem;opacity:.6" title="Set manually — the automatic detection will not change it"></i>
                        <?php endif; ?>
                    </td>
                    <td data-label="For"><span class="text-muted">Everyone</span></td>
                    <td data-label="Status"><span class="status-badge ann-fb-badge"><i class="bi bi-facebook"></i> From Facebook</span></td>
                    <td class="ann-actions-cell">
                        <?php if ($canPublish): ?>
                        <form method="POST" action="/admin/action/create-announcement.php" class="ann-inline-form">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="<?= !empty($fb['is_pinned']) ? 'unpin_facebook' : 'pin_facebook' ?>">
                            <input type="hidden" name="fb_post_id" value="<?= htmlspecialchars($fb['fb_post_id']) ?>">
                            <button type="submit"
                                    class="btn btn-sm btn-outline-secondary btn-pin-toggle<?= !empty($fb['is_pinned']) ? ' is-pinned' : '' ?>"
                                    <?php if (empty($fb['is_pinned']) && $pinnedCount >= MAX_PINNED_ITEMS): ?>
                                    disabled title="All <?= MAX_PINNED_ITEMS ?> slideshow spots are used. Unpin one first."
                                    <?php else: ?>
                                    title="<?= !empty($fb['is_pinned']) ? 'Remove from the homepage slideshow' : 'Show in the homepage slideshow' ?>"
                                    <?php endif; ?>>
                                <i class="bi <?= !empty($fb['is_pinned']) ? 'bi-pin-angle-fill' : 'bi-pin-angle' ?>"></i>
                                <span class="visually-hidden"><?= !empty($fb['is_pinned']) ? 'Unpin' : 'Pin' ?></span>
                            </button>
                        </form>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-fb" title="Change the category"
                                data-fb-id="<?= htmlspecialchars($fb['fb_post_id'], ENT_QUOTES) ?>"
                                data-title="<?= htmlspecialchars($fbTitle, ENT_QUOTES) ?>"
                                data-category="<?= (int)($fb['category_id'] ?? 0) ?: 'none' ?>"
                                data-locked="<?= !empty($fb['category_locked']) ? '1' : '0' ?>">
                            <i class="bi bi-tag"></i><span class="visually-hidden">Category</span>
                        </button>
                        <?php endif; ?>
                        <?php if (!empty($fb['permalink_url'])): ?>
                        <a href="<?= htmlspecialchars(gncFacebookPostUrl($fb['permalink_url'], $fb['fb_post_id'], FB_PAGE_ID), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Open the post on Facebook">
                            <i class="bi bi-box-arrow-up-right"></i><span class="visually-hidden">Open on Facebook</span>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($announcements) >= 50 || count($fbPosts) >= 50): ?>
    <div class="ann-sub p-3" style="border-top:1px solid rgba(128,128,128,.2)">Showing the 50 most recent <?= $facebookPostsPage ? 'Facebook posts' : 'announcements' ?>. Use the search box to find older ones.</div>
    <?php endif; ?>
</div>

<!-- ============================================================
     CREATE / EDIT MODAL — posts to /admin/action/create-announcement.php
============================================================ -->
<div class="modal fade" id="announcementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content">
      <form data-content-publish method="POST" action="/admin/action/create-announcement.php" enctype="multipart/form-data" id="announcement-form">
        <div class="modal-header">
          <h5 class="modal-title" id="announcementModalLabel">New Announcement</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">

          <?php if (!empty($flashErrors)): ?>
          <div class="alert alert-danger d-flex gap-2 align-items-start">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>
              <?php foreach ($flashErrors as $err): ?>
              <div><?= htmlspecialchars($err) ?></div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
          <input type="hidden" name="action" id="modal-action" value="create">
          <input type="hidden" name="announcement_id" id="modal-announcement-id" value="">
          <input type="hidden" name="_review_id" value="<?= (int)($_GET['revise'] ?? 0) ?>">

          <div class="mb-3">
            <label class="form-label" for="modal-title">Headline <span class="text-danger">*</span></label>
            <input type="text" name="title" id="modal-title" class="form-control" required maxlength="500" placeholder="e.g. Enrollment for Second Semester is now open">
          </div>

          <div class="mb-3">
            <label class="form-label">Message <span class="text-danger">*</span></label>
            <div id="modal-content-editor"></div>
            <input type="hidden" name="content" id="modal-content-input">
            <div class="form-text">Write what you want everyone to know. You can use the toolbar to make text bold or add lists and links.</div>
          </div>

          <div class="ann-section-label">Picture</div>
          <div class="ann-image-box mb-3">
            <div id="modal-image-preview-wrap" style="display:none">
              <img id="modal-image-preview" src="" alt="Selected picture preview">
              <div class="form-check mt-2" id="modal-remove-image-wrap" style="display:none">
                <input type="checkbox" class="form-check-input" id="modal-remove-image" name="remove_image" value="1">
                <label class="form-check-label" for="modal-remove-image">Remove the current picture</label>
              </div>
            </div>
            <div class="flex-grow-1">
              <input type="file" name="image" id="modal-image-input" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
              <div class="form-text">Optional. JPG, PNG, GIF or WEBP, up to 5 MB. A wide (landscape) picture looks best.</div>
            </div>
          </div>

          <div class="ann-section-label">More pictures (gallery)</div>
          <div class="mb-3">
            <input type="file" name="gallery_images[]" id="modal-gallery-input" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
            <div class="form-text">Optional. You can pick several photos at once — up to 10 at a time, 5 MB each.</div>

            <div id="modal-gallery-existing-wrap" class="mt-3" style="display:none">
              <div class="fw-semibold" style="font-size:.85rem">Pictures already in the gallery</div>
              <div class="form-text mb-2">Tap the × on a picture to remove it when you save. Tap again to undo.</div>
              <div id="modal-gallery-existing" class="d-flex flex-wrap gap-2"></div>
            </div>

            <div id="modal-gallery-new-wrap" class="mt-3" style="display:none">
              <div class="fw-semibold mb-2" style="font-size:.85rem">New pictures to upload</div>
              <div id="modal-gallery-new" class="d-flex flex-wrap gap-2"></div>
            </div>
          </div>

          <div class="ann-section-label">Who is this for?</div>
          <div class="row g-3 mb-3">
            <div class="col-12 col-sm-4">
              <label class="form-label" for="modal-category">Type</label>
              <select name="category_id" id="modal-category" class="form-select">
                <option value="">Not set</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-sm-4">
              <label class="form-label" for="modal-dept">Department</label>
              <select name="dept_id" id="modal-dept" class="form-select">
                <option value="">Everyone (all departments)</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= (int)$d['dept_id'] ?>"><?= htmlspecialchars($d['dept_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-sm-4">
              <label class="form-label" for="modal-program">Program <span class="text-muted fw-normal">(optional)</span></label>
              <select name="program" id="modal-program" class="form-select" aria-describedby="modal-audience-help">
                <?php foreach ($announcementProgramChoices as $code => $label): ?>
                <option value="<?= htmlspecialchars($code) ?>"><?= $code === 'ALL' ? 'Everyone (all programs)' : htmlspecialchars($code . ' — ' . $label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12"><div class="form-text mt-0" id="modal-audience-help">Program is optional. Select a department to include all its programs, or leave both on "Everyone" for a school-wide announcement.</div></div>
          </div>

          <div class="ann-section-label">Visibility</div>
          <?php if ($canPublish): ?>
          <div class="mb-3">
            <label class="form-label" for="publish-destination">Publish destination</label>
            <select name="publish_destination" id="publish-destination" class="form-select">
              <option value="website">Publish to Website Only</option>
              <option value="facebook" <?= !$facebookConnection || !$facebookConnection['enabled'] ? 'disabled' : '' ?>>Publish to Facebook Only</option>
              <option value="both" <?= !$facebookConnection || !$facebookConnection['enabled'] ? 'disabled' : '' ?>>Publish to Both Website and Facebook</option>
            </select>
            <div class="form-text">Facebook is sent only with Publish now and your confirmation. Facebook Only hides this record from the website. Editing a delivered announcement does not update or duplicate its Facebook post.</div>
            <div class="form-text">Facebook destination: <?= htmlspecialchars($facebookConnection && $facebookConnection['enabled'] ? $facebookConnection['page_name'] . ' · ' . $facebookConnection['page_id'] . ' (' . $facebookConnection['environment'] . ')' : 'No Page connected. Ask a Super Admin to open Settings → Facebook Integration.') ?></div>
          </div>
          <div class="mb-3">
            <label for="facebook-link" class="form-label">Facebook attachment link (optional)</label>
            <input type="url" class="form-control" id="facebook-link" name="facebook_link" maxlength="2000" placeholder="https://example.com/announcement">
            <div class="form-text">With photos, the link is added to the message. Otherwise Facebook receives a link attachment. Main picture and gallery photos are included, up to 10 total.</div>
          </div>
          <input type="hidden" name="facebook_request_key" id="facebook-request-key" value="<?= bin2hex(random_bytes(32)) ?>">
          <input type="hidden" name="facebook_confirmed" id="facebook-confirmed" value="0">
          <input type="hidden" name="facebook_page_id" value="<?= htmlspecialchars($facebookConnection['page_id'] ?? '') ?>">
          <select name="status" id="modal-status" class="form-select">
            <option value="draft">Save as draft — still working on it</option>
            <?php if (!$isSuperAdmin): ?>
            <option value="pending">Send for approval</option>
            <?php endif; ?>
            <option value="published">Publish now — use selected destination</option>
            <option value="archived">Archive — hide from website</option>
          </select>
          <div class="ann-status-help" id="modal-status-help"></div>
          <?php else: ?>
          <div class="alert alert-secondary mb-0 d-flex gap-2" style="font-size:.88rem">
            <i class="bi bi-info-circle mt-1"></i>
            <span>This will be sent for approval first. It appears on the public website once it is approved.</span>
          </div>
          <?php endif; ?>
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

<!-- ============================================================
     DELETE CONFIRMATION MODAL
============================================================ -->
<div class="modal fade" id="deleteAnnouncementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:min(420px, calc(100% - 1rem))">
    <div class="modal-content">
      <form method="POST" action="/admin/action/create-announcement.php" id="delete-announcement-form">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="announcement_id" id="delete-announcement-id" value="">
        <div class="modal-body text-center p-4">
          <div class="ann-del-icon"><i class="bi bi-trash3"></i></div>
          <h5 class="fw-bold mb-2">Delete this announcement?</h5>
          <p class="mb-1"><strong id="delete-announcement-title"></strong></p>
          <p class="text-muted mb-3" style="font-size:.85rem">It will be removed from the website. This cannot be undone.</p>
          <div class="d-flex gap-2 justify-content-center">
            <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger px-4" id="delete-announcement-btn"><i class="bi bi-trash3 me-1"></i> Delete</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============================================================
     EDIT FACEBOOK POST MODAL — posts to /admin/action/create-announcement.php
============================================================ -->
<div class="modal fade" id="fbEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" action="/admin/action/create-announcement.php">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-facebook" style="color:#1877F2"></i> Facebook Post</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
          <input type="hidden" name="action" value="set_fb_category">
          <input type="hidden" name="fb_post_id" id="fb-modal-id" value="">

          <div class="mb-3">
            <label class="form-label">Post</label>
            <input type="text" id="fb-modal-title" class="form-control" readonly disabled>
            <div class="form-text">The text comes from Facebook, so it can only be changed there.</div>
          </div>
          <div>
            <label class="form-label" for="fb-modal-category">Type</label>
            <select name="category_choice" id="fb-modal-category" class="form-select">
              <option value="none">Not set</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
              <?php endforeach; ?>
              <option value="auto" id="fb-modal-auto-opt">↻ Let the website choose again</option>
            </select>
            <div class="form-text">The website guesses the type from the post text. If it is wrong, pick the right one — your choice will be kept.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-gnc-gold"><i class="bi bi-check-lg"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
/* ---------- Shared look ---------- */
.ann-sub { font-size: .78rem; color: #8a8f8c; overflow-wrap: anywhere; }
.ann-pin-banner > .flex-grow-1 { min-width: 0; }
.page-header { display: flex; flex-wrap: wrap; gap: .75rem; justify-content: space-between; align-items: center; }
.data-card-header { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; }

.program-badge {
    display: inline-block; padding: 2px 10px; border-radius: 12px;
    font-size: .72rem; font-weight: 600; letter-spacing: .02em; white-space: nowrap;
    background: rgba(201,162,39,.16); color: #C9A227; border: 1px solid rgba(201,162,39,.4);
}
.program-badge-all { background: rgba(31,138,59,.14); color: #2FA84F; border-color: rgba(31,138,59,.4); }

.ann-table .ann-fb-badge { background: #1877F2; color: #fff; }

.btn-pin-toggle.is-pinned { background: #EABA3B; border-color: #EABA3B; color: #1F5E2C; }

/* Pin banner */
.ann-pin-banner {
    display: flex; align-items: center; gap: .85rem; padding: .85rem 1rem; margin-bottom: 1rem;
    border: 1px solid rgba(234,186,59,.45); background: rgba(234,186,59,.1); border-radius: 12px; font-size: .9rem;
}
.ann-pin-banner-icon { color: #EABA3B; font-size: 1.4rem; flex-shrink: 0; }
.ann-pin-dots { display: flex; gap: 6px; flex-shrink: 0; }
.ann-pin-dots span { width: 12px; height: 12px; border-radius: 50%; border: 2px solid #EABA3B; }
.ann-pin-dots span.on { background: #EABA3B; }

/* Fluid Search + filters */
.ann-search { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; flex-grow: 1; justify-content: flex-end; }
.ann-search-box { position: relative; flex: 1 1 200px; max-width: 350px; }
.ann-search-box i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: .8rem; opacity: .6; }
.ann-search-box input { padding-left: 30px; width: 100%; }
.ann-search select { flex: 0 1 auto; width: auto; max-width: 100%; }
.ann-filters { padding: .75rem 1.25rem; display: flex; flex-direction: column; gap: .6rem; overflow-x: auto; scrollbar-width: none; }
.ann-filters::-webkit-scrollbar { display: none; }
.ann-chip-row { display: flex; flex-wrap: nowrap; gap: .5rem; align-items: center; width: max-content; }
.ann-chip-label { font-size: .8rem; color: #8a8f8c; }
.ann-chip {
    border: 1px solid rgba(128,128,128,.35); color: inherit; text-decoration: none;
    border-radius: 999px; padding: .3rem .9rem; font-size: .82rem; transition: background var(--transition), border-color var(--transition), color var(--transition); white-space: nowrap;
}
.ann-chip-sm { padding: .2rem .7rem; font-size: .78rem; }
.ann-chip:hover { background: rgba(128,128,128,.15); color: inherit; }
.ann-chip.active { background: #EABA3B; border-color: #EABA3B; color: #094024; font-weight: 600; }
.ann-clear { font-size: .8rem; color: #dc3545; text-decoration: none; margin-left: .25rem; white-space: nowrap; }

/* Table */
.ann-table { min-width: 850px; } /* Forces native horizontal scroll on narrow tablets instead of crushing the layout */
.ann-thumb { width: 56px; height: 56px; object-fit: cover; border-radius: 8px; display: block; flex-shrink: 0; }
.ann-thumb-empty { background: rgba(128,128,128,.18); display: flex; align-items: center; justify-content: center; color: #8a8f8c; font-size: 1.2rem; }
.ann-title-cell { min-width: 260px; max-width: 340px; } /* Prevents the text from squishing into 1 word per line */
.ann-title { font-weight: 600; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; word-break: break-word; }
.ann-pinned { display: inline-block; margin-top: 4px; font-size: .72rem; padding: 1px 8px; border-radius: 999px; background: rgba(234,186,59,.22); color: #EABA3B; font-weight: 600; }
.ann-for { display: flex; flex-direction: column; gap: 4px; align-items: flex-start; font-size: .85rem; }
.ann-actions-cell { display: table-cell; white-space: nowrap; }
/* Icon-only action buttons: equal squares, words are visually hidden but still read by screen readers */
.ann-actions-cell .btn { width: 36px; height: 36px; padding: 0; margin: 0 2px 0 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; vertical-align: middle; }
.ann-actions-cell .btn .bi { font-size: 1rem; line-height: 1; }
.ann-inline-form { display: inline; }
.ann-table td { vertical-align: middle; }

.ann-empty { text-align: center; padding: 2.5rem 1rem; color: #8a8f8c; }
.ann-empty i { font-size: 2.2rem; display: block; margin-bottom: .5rem; opacity: .7; }

/* Modal */
#announcement-form { display: flex; flex-direction: column; min-height: 0; max-height: 100%; }
#announcement-form .modal-body { overflow-y: auto; }
.ann-section-label {
    font-size: .72rem; text-transform: uppercase; letter-spacing: .6px; font-weight: 700; color: #8a8f8c;
    margin: 1.1rem 0 .55rem; padding-bottom: .3rem; border-bottom: 1px solid rgba(128,128,128,.2);
}
#modal-content-editor { background: #fff; color: #222; }
#modal-content-editor .ql-editor { min-height: 180px; max-height: min(260px, 40vh); overflow-y: auto; }
#modal-content-editor .ql-toolbar { flex-wrap: wrap; }
.ann-image-box { display: flex; gap: 1rem; align-items: flex-start; flex-wrap: wrap; }
#modal-image-preview { width: 140px; height: 100px; object-fit: cover; border-radius: 8px; display: block; }
.ann-status-help { font-size: .8rem; color: #8a8f8c; margin-top: .4rem; }
.ann-del-icon {
    width: 60px; height: 60px; margin: 0 auto 14px; border-radius: 50%;
    background: rgba(220,53,69,.15); color: #dc3545;
    display: flex; align-items: center; justify-content: center; font-size: 1.6rem;
}

/* Gallery thumbnails */
.gallery-thumb-wrap {
    position: relative; width: 78px; height: 78px; border-radius: 8px; overflow: hidden;
    background: rgba(128,128,128,.18);
}
.gallery-thumb-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; }
.gallery-thumb-remove {
    position: absolute; top: 3px; right: 3px; width: 22px; height: 22px; border-radius: 50%;
    background: rgba(0,0,0,.65); color: #fff; border: none; font-size: .85rem; line-height: 22px;
    text-align: center; padding: 0; cursor: pointer;
}
.gallery-thumb-wrap.marked-remove img { opacity: .3; }
.gallery-thumb-wrap.marked-remove .gallery-thumb-remove { background: #dc3545; }

/* Toast */
.ann-toast {
    position: fixed; right: 20px; bottom: 24px; z-index: 2000; width: min(380px, calc(100vw - 2rem));
    transform: translateY(20px); opacity: 0;
    transition: transform var(--transition), opacity var(--transition);
}
.ann-toast.show { transform: none; opacity: 1; }

/* ---------- Responsive Breakpoints ---------- */

/* Laptops & Tablets (Including 1024px iPad Pro) */
@media (max-width: 1199.98px) {
    .ann-search-box { max-width: 100%; }
    .ann-actions-cell { white-space: normal; width: 1%; }
    .ann-actions-cell .ann-inline-form { display: inline-block; }
    .ann-actions-cell .btn { margin: 0 2px 4px 0; }
}

/* Phones & small tablets: table turns into cards */
@media (max-width: 767.98px) {
    .ann-table { min-width: 0; } /* Reset min-width for mobile grid layout */
    
    .ann-search { width: 100%; margin-left: 0 !important; }
    .ann-search-box { flex: 1 1 100%; }
    .ann-search select { flex: 1 1 100%; }
    
    .ann-table thead { display: none; }
    .ann-table, .ann-table tbody { display: block; width: 100%; }
    .ann-table tr.ann-row {
        display: grid; grid-template-columns: 56px minmax(0, 1fr); column-gap: 1rem; row-gap: .5rem;
        padding: 1rem; border-bottom: 1px solid rgba(128,128,128,.25);
    }
    .ann-table tr.ann-row > td { border: 0; padding: 0; text-align: right; max-width: none; min-width: 0; width: auto; }
    .ann-table tr.ann-row > td.ann-img-cell { grid-column: 1; grid-row: 1; }
    .ann-table tr.ann-row > td.ann-title-cell { grid-column: 2; grid-row: 1; text-align: left; }
    .ann-table tr.ann-row > td[data-label] {
        grid-column: 1 / -1; display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-top: .25rem;
    }
    .ann-table tr.ann-row > td[data-label]::before {
        content: attr(data-label); font-size: .7rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .5px; color: #8a8f8c; text-align: left; flex: 0 0 auto;
    }
    .ann-for { align-items: flex-end; text-align: right; }
    
    .ann-table tr.ann-row > td.ann-actions-cell {
        grid-column: 1 / -1; display: flex; flex-direction: row; flex-wrap: wrap; gap: .5rem; padding-top: .75rem; margin-top: .5rem; border-top: 1px dashed rgba(128,128,128,.2);
    }
    .ann-actions-cell .ann-inline-form { flex: 0 0 auto; display: flex; margin: 0; }
    .ann-actions-cell .btn { flex: 0 0 auto; width: 44px; height: 44px; margin: 0; }
    
    .ann-table tr.ann-empty-row, .ann-table tr.ann-empty-row > td { display: block; }
}

/* Extra Small Phones */
@media (max-width: 575.98px) {
    .page-header { flex-direction: column; align-items: flex-start; }
    .page-header > div { width: 100%; }
    .page-header h1 { font-size: 1.4rem; }
    #btn-new-announcement { width: 100%; padding: .75rem 1rem; font-size: 1rem; justify-content: center; }
    
    .ann-pin-banner { flex-direction: column; align-items: flex-start; font-size: .85rem; }
    .ann-pin-dots { margin-left: 0; margin-top: .5rem; }
    
    /* Icon buttons stay in one tidy row on small phones */
    .ann-table tr.ann-row > td.ann-actions-cell { justify-content: flex-start; gap: .6rem; }

    /* Modal iOS Safe Area Spacing */
    #announcementModal .modal-footer { 
        position: sticky; bottom: 0; background: var(--bs-modal-bg, #fff); z-index: 2; flex-wrap: nowrap;
        padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));
    }
    #announcementModal .modal-footer .btn { flex: 1 1 0; padding: .75rem; }
    
    /* Prevent iOS zoom on form focus */
    .modal-body .form-control, .modal-body .form-select { font-size: 16px; } 
    #modal-image-preview { width: 100%; height: 180px; }
    .ann-image-box > * { flex: 1 1 100%; }
    
    .ann-toast { left: 16px; right: 16px; bottom: max(16px, env(safe-area-inset-bottom)); width: auto; }
}

/* Touch targets for mobile */
@media (hover: none) and (pointer: coarse) {
    .btn, .ann-chip { min-height: 44px; display: inline-flex; align-items: center; justify-content: center; }
    .ann-chip-sm { min-height: 40px; }
    .ann-actions-cell .btn { width: 44px; height: 44px; }
    /* 16px stops iOS Safari zooming the page when a field is focused */
    .ann-search .form-control, .ann-search .form-select,
    .modal-body .form-control, .modal-body .form-select { font-size: 16px; }
    .ann-search-box input { min-height: 44px; }
}

/* Landscape phones / short windows: give the modal editors room */
@media (max-height: 500px) {
    #modal-content-editor .ql-editor { min-height: 110px; max-height: 30vh; }
    #modal-image-preview { height: 80px; width: 120px; }
    .modal-body { padding-top: .75rem; padding-bottom: .75rem; }
}

/* Notched phones in landscape */
@supports (padding: max(0px)) {
    .ann-toast { right: max(20px, env(safe-area-inset-right)); }
}
</style>

<?php ob_start(); ?>
<script>
// NOTE: this runs via $extraScripts (echoed in footer-admin.php), AFTER
// quill.min.js and bootstrap.bundle.min.js have loaded. Putting it here
// directly (before footer-admin.php is included) would throw a
// "Quill is not defined" error and silently kill the rest of the script,
// which is why the New Announcement button would do nothing.
const modalQuill = initQuill('modal-content-editor', 'modal-content-input', { placeholder: 'Write the announcement here…' });

// ---- Friendly toast message ----
function annToast(msg, type) {
    if (window.ContentEditorNotice && window.ContentEditorNotice(msg, type === 'error')) return;
    const t = document.createElement('div');
    t.className = 'ann-toast' + (type === 'error' ? ' error' : '');
    t.innerHTML = '<i class="bi bi-' + (type === 'error' ? 'exclamation-circle' : 'check-circle') + '"></i><span></span>';
    t.querySelector('span').textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(() => t.classList.add('show'));
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 400); }, 4500);
}
<?php if (!empty($flashSuccess)): ?>
annToast(<?= json_encode($flashSuccess) ?>);
<?php endif; ?>

const announcementModalEl = document.getElementById('announcementModal');
const announcementModal   = new bootstrap.Modal(announcementModalEl);

const modalTitleInput   = document.getElementById('modal-title');
const modalCategory     = document.getElementById('modal-category');
const modalProgram      = document.getElementById('modal-program');
const modalDept         = document.getElementById('modal-dept');
// Only an existing announcement may retain its previously saved Basic Education tag.
const savedProgramLabels = <?= json_encode($programCategories, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
function selectAnnouncementProgram(code = 'ALL', preserveExisting = false) {
    if (!modalProgram) return;
    modalProgram.querySelectorAll('[data-legacy-program]').forEach(option => option.remove());
    if (preserveExisting && savedProgramLabels[code] && !Array.from(modalProgram.options).some(option => option.value === code)) {
        const option = new Option(code + ' \u2014 ' + savedProgramLabels[code] + ' (existing selection)', code);
        option.dataset.legacyProgram = '1';
        modalProgram.add(option);
    }
    modalProgram.value = code;
    if (!modalProgram.value) modalProgram.value = 'ALL';
}

function updateAudienceHelp() {
    const hasDepartment = Boolean(modalDept?.value);
    const allPrograms = modalProgram?.querySelector('option[value="ALL"]');
    if (allPrograms) allPrograms.textContent = hasDepartment ? 'All programs in this department' : 'Everyone (all programs)';
    const help = document.getElementById('modal-audience-help');
    if (help) help.textContent = hasDepartment
        ? (modalProgram?.value && modalProgram.value !== 'ALL'
            ? 'This announcement is limited to the selected department and program. Choose "All programs in this department" to include the whole department.'
            : 'This announcement applies to all programs in the selected department. Choosing a specific program is optional.')
        : 'Program is optional. Select a department to include all its programs, or leave both on "Everyone" for a school-wide announcement.';
}
modalDept?.addEventListener('change', () => {
    selectAnnouncementProgram();
    updateAudienceHelp();
});
modalProgram?.addEventListener('change', updateAudienceHelp);
const modalStatus       = document.getElementById('modal-status');
const modalActionInput  = document.getElementById('modal-action');
const modalIdInput      = document.getElementById('modal-announcement-id');
const modalLabel        = document.getElementById('announcementModalLabel');
const modalSaveLabel    = document.getElementById('modal-save-label');
const modalSaveBtn      = document.getElementById('modal-save-btn');
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

// Plain-language hint under the Visibility dropdown
const statusHelpText = <?= json_encode($statusHelp) ?>;
const modalStatusHelp = document.getElementById('modal-status-help');
function updateStatusHelp() {
    // If the saved status has no matching option (e.g. an old "pending" item
    // opened by a super admin), fall back to Publish now.
    if (modalStatus && modalStatus.value === '') modalStatus.value = 'published';
    if (modalStatus && modalStatusHelp) modalStatusHelp.textContent = statusHelpText[modalStatus.value] || '';
}
modalStatus?.addEventListener('change', updateStatusHelp);

function resetGalleryUI() {
    modalGalleryInput.value = '';
    modalGalleryNew.innerHTML = '';
    modalGalleryNewWrap.style.display = 'none';
    modalGalleryExist.innerHTML = '';
    modalGalleryExistWrap.style.display = 'none';
    // Clear any stray remove_gallery_ids[] inputs left over from a previous edit
    document.querySelectorAll('input[name="remove_gallery_ids[]"]').forEach(el => el.remove());
}

function resetModalForm() {
    document.getElementById('announcement-form').reset();
    const facebookConfirmed = document.getElementById('facebook-confirmed');
    if (facebookConfirmed) facebookConfirmed.value = '0';
    const facebookRequestKey = document.getElementById('facebook-request-key');
    if (facebookRequestKey && window.crypto?.getRandomValues) {
        facebookRequestKey.value = Array.from(crypto.getRandomValues(new Uint8Array(32)), byte => byte.toString(16).padStart(2, '0')).join('');
    }
    modalQuill.setContents([]);
    modalActionInput.value = 'create';
    modalIdInput.value = '';
    selectAnnouncementProgram();
    if (modalDept) modalDept.value = '';
    updateAudienceHelp();
    modalLabel.textContent = 'New Announcement';
    modalSaveLabel.textContent = 'Create Announcement';
    modalSaveBtn.disabled = false;
    modalImagePreviewWrap.style.display = 'none';
    modalRemoveWrap.style.display = 'none';
    modalImagePreview.src = '';
    resetGalleryUI();
    updateStatusHelp();
}

// Preview of the new pictures picked in this session
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
            const img = document.createElement('img');
            img.src = e.target.result;
            img.alt = '';
            wrap.appendChild(img);
            modalGalleryNew.appendChild(wrap);
        };
        reader.readAsDataURL(file);
    });
}

// Already-saved gallery pictures, each with an × that queues it for removal
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
        imgEl.alt = '';
        wrap.appendChild(imgEl);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'gallery-thumb-remove';
        btn.title = 'Remove this picture';
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
        document.getElementById('announcement-form').appendChild(input);
        wrap.classList.add('marked-remove');
    }
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
    selectAnnouncementProgram(btn.dataset.program || 'ALL', true);
    if (modalDept) modalDept.value = (btn.dataset.dept && btn.dataset.dept !== '0') ? btn.dataset.dept : '';
    updateAudienceHelp();
    if (modalStatus) modalStatus.value = btn.dataset.status || 'draft';
    updateStatusHelp();

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

    announcementModal.show();
}

document.getElementById('btn-new-announcement')?.addEventListener('click', openCreateModal);
document.querySelectorAll('.js-new-announcement').forEach(b => b.addEventListener('click', openCreateModal));

announcementModalEl.addEventListener('shown.bs.modal', () => modalTitleInput.focus());

// Facebook post edit modal
const fbEditModalEl = document.getElementById('fbEditModal');
const fbEditModal   = fbEditModalEl ? new bootstrap.Modal(fbEditModalEl) : null;
document.querySelectorAll('.btn-edit-fb').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('fb-modal-id').value    = btn.dataset.fbId;
        document.getElementById('fb-modal-title').value = btn.dataset.title || '';
        document.getElementById('fb-modal-category').value = btn.dataset.category || 'none';
        // "Auto-detect again" only makes sense for manually-set categories
        document.getElementById('fb-modal-auto-opt').hidden = btn.dataset.locked !== '1';
        fbEditModal?.show();
    });
});

document.querySelectorAll('.btn-edit-announcement').forEach(btn => {
    btn.addEventListener('click', () => openEditModal(btn));
});

// Delete confirmation modal (replaces the browser's plain confirm() box)
const deleteModalEl = document.getElementById('deleteAnnouncementModal');
const deleteModal   = deleteModalEl ? new bootstrap.Modal(deleteModalEl) : null;
document.querySelectorAll('.btn-delete-announcement').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('delete-announcement-id').value = btn.dataset.id;
        document.getElementById('delete-announcement-title').textContent = btn.dataset.title || '';
        document.getElementById('delete-announcement-btn').disabled = false;
        deleteModal?.show();
    });
});
document.getElementById('delete-announcement-form')?.addEventListener('submit', () => {
    const b = document.getElementById('delete-announcement-btn');
    // disable on the next tick so the form still submits normally
    setTimeout(() => { b.disabled = true; b.textContent = 'Deleting…'; }, 0);
});

modalImageInput?.addEventListener('change', () => {
    const file = modalImageInput.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
        annToast('That picture is larger than 5 MB. Please choose a smaller one.', 'error');
        modalImageInput.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = e => {
        modalImagePreview.src = e.target.result;
        modalImagePreviewWrap.style.display = 'block';
        if (modalRemoveCheck) modalRemoveCheck.checked = false;
    };
    reader.readAsDataURL(file);
});

modalGalleryInput?.addEventListener('change', () => {
    const files = Array.from(modalGalleryInput.files || []);
    if (files.length > MAX_GALLERY_IMAGES) {
        annToast('You can upload up to ' + MAX_GALLERY_IMAGES + ' pictures at a time.', 'error');
        modalGalleryInput.value = '';
        renderNewGalleryPreview();
        return;
    }
    if (files.some(f => f.size > 5 * 1024 * 1024)) {
        annToast('One of the pictures is larger than 5 MB. Please choose smaller ones.', 'error');
        modalGalleryInput.value = '';
        renderNewGalleryPreview();
        return;
    }
    renderNewGalleryPreview();
});

document.getElementById('announcement-form').addEventListener('submit', async (e) => {
    document.getElementById('modal-content-input').value = modalQuill.root.innerHTML;
    if (!modalQuill.getText().trim() && !e.target.querySelector('[name="allow_incomplete"][value="1"]')) {
        e.preventDefault();
        annToast('Please write a message for the announcement.', 'error');
        return;
    }
    const destination = document.getElementById('publish-destination')?.value || 'website';
    const sendingFacebook = destination !== 'website';
    if (sendingFacebook && modalStatus?.value !== 'published') {
        e.preventDefault();
        annToast('Select Publish now to publish to Facebook. Choose Website Only to save a draft or send for approval.', 'error');
        return;
    }
    if (modalStatus?.value === 'published') {
        e.preventDefault();
        const page = <?= json_encode($facebookConnection ? $facebookConnection['page_name'] . ' · ' . $facebookConnection['page_id'] . ' (' . $facebookConnection['environment'] . ')' : 'No Page connected', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const message = destination === 'website' ? 'Publish this announcement to the website?'
            : 'Publish this announcement to ' + page + (destination === 'both' ? ' and the website?' : '? It will be hidden from the website.')
              + ' Any previous successful Facebook delivery will be kept without creating another post.';
        if (!await window.AdminDialogs.confirm(message, {title: 'Confirm publishing destination', confirmText: 'Publish'})) return;
        const confirmed = document.getElementById('facebook-confirmed');
        if (confirmed) confirmed.value = sendingFacebook ? '1' : '0';
        modalSaveBtn.disabled = true;
        modalSaveLabel.textContent = 'Publishing…';
        // The shared completeness/approval checks already ran before this listener.
        HTMLFormElement.prototype.submit.call(e.target);
        return;
    }
    // Show progress so people don't click twice while the picture uploads
    setTimeout(() => {
        modalSaveBtn.disabled = true;
        modalSaveLabel.textContent = 'Saving…';
    }, 0);
});

// ---- Live search: results update while typing, no button needed ----
(function () {
    const input = document.getElementById('announcement-search');
    if (!input) return;
    const form = input.closest('form');
    let timer = null;
    let lastValue = input.value;

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (input.value.trim() === lastValue.trim()) return;
            lastValue = input.value;
            sessionStorage.setItem('annSearchFocus', '1');
            form.submit();
        }, 400); // wait until the person pauses typing
    });

    // After the page reloads with results, put the cursor back at the end of the word
    if (sessionStorage.getItem('annSearchFocus') === '1') {
        sessionStorage.removeItem('annSearchFocus');
        input.focus();
        const end = input.value.length;
        input.setSelectionRange(end, end);
    }
})();

// Auto-reopen the modal on load: validation error, or ?modal=create / ?modal=edit&id=N
<?php if ($modalMode === 'create'): ?>
openCreateModal();
<?php if (!empty($flashErrors)): ?>
modalTitleInput.value = <?= json_encode($flashOld['title'] ?? '') ?>;
modalQuill.root.innerHTML = <?= json_encode($flashOld['content'] ?? '') ?>;
if (modalCategory) modalCategory.value = <?= json_encode($flashOld['category_id'] ?? '') ?>;
selectAnnouncementProgram(<?= json_encode($flashOld['program'] ?? 'ALL') ?>);
if (modalDept) modalDept.value = <?= json_encode($flashOld['dept_id'] ?? '') ?>;
if (modalStatus) modalStatus.value = <?= json_encode($flashOld['status'] ?? 'draft') ?>;
updateStatusHelp();
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
selectAnnouncementProgram(<?= json_encode($modalEditing['program'] ?? 'ALL') ?>, true);
if (modalDept) modalDept.value = <?= json_encode($modalEditing['dept_id'] ?? '') ?>;
if (modalStatus) modalStatus.value = <?= json_encode($modalEditing['status'] ?? 'draft') ?>;
updateStatusHelp();
<?php if (!empty($modalEditing['image_path'])): ?>
modalImagePreview.src = <?= json_encode($modalEditing['image_path']) ?>;
modalImagePreviewWrap.style.display = 'block';
modalRemoveWrap.style.display = 'block';
<?php endif; ?>
announcementModal.show();
<?php endif; ?>
updateAudienceHelp();
</script>
<?php $extraScripts = ob_get_clean(); ?>

<?php
// IMPORTANT: the footer must be included last. It loads Bootstrap + Quill and
// then echoes $extraScripts (the page script above). Without this line none of
// the buttons that rely on JavaScript will work.
include __DIR__ . '/../components/footer-admin.php';
?>
