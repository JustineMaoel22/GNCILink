<?php
/**
 * GNC Admin Panel - News
 * /admin/news.php
 *
 * Renders the list + the create/edit modal. All writes (create, update,
 * delete, pin, unpin) are handled by /admin/action/create-news.php, which
 * the modal form and the per-row forms POST to directly.
 *
 * Mirrors /admin/announcement.php's look and behaviour so News feels
 * identical to Announcements from an admin's point of view, with two
 * differences: a fixed News-specific category list (not the shared
 * `categories` table) and a multi-image gallery on top of the cover image.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/admin-functions.php';
require_once __DIR__ . '/news-view.php';

initSession();
requireLogin();

/* Dates are stored in UTC. Show them in local (Philippine) time. */
if (!function_exists('newsLocalTime')) {
    function newsLocalTime($raw, $format) {
        try {
            $d = new DateTime((string)$raw, new DateTimeZone('UTC'));
            $d->setTimezone(new DateTimeZone('Asia/Manila'));
            return $d->format($format);
        } catch (Exception $e) { return ''; }
    }
}


// Flash from a failed submit (set by the action handler)
$flashErrors  = $_SESSION['flash_errors'] ?? [];
$flashOld     = $_SESSION['flash_old'] ?? [];
require_once __DIR__.'/../includes/content-submissions.php';
if (isset($_GET['revise'])) $flashOld=rbacRevisionFormData('/admin/action/create-news.php');
$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_errors'], $_SESSION['flash_old'], $_SESSION['flash_success']);

// ============================================================
// LIST DATA
// ============================================================
$listFilters=newsFilters($_GET);$listError='';$listTotal=0;$listPages=1;$listGalleries=[];$newsList=[];
$statusFilter=$listFilters['status'];$categoryFilter=$listFilters['category'];$search=$listFilters['search'];
try{$result=newsListResults(getDB(),$listFilters,rbacRole(),(int)$_SESSION['user_id']);$newsList=$result['rows'];$listTotal=$result['total'];$listPages=$result['pages'];$listGalleries=$result['galleries'];}
catch(Throwable $e){$listError='News articles could not be loaded. Please try again.';error_log('News management list unavailable.');}
$newsCategories = getNewsCategories();
$newsCreateCategories = array_diff_key($newsCategories, array_flip(['Updates', 'Job Opportunities', 'Scholarship']));
$pinnedCount    = countPinnedItems();

// Which modal (if any) should auto-open on load
$modalMode=is_string($_GET['modal']??null)&&in_array($_GET['modal'],['create','edit'],true)?$_GET['modal']:'';
$modalEditing = null;
$modalGallery = [];
if ($modalMode === 'edit') {
    $editId=is_string($_GET['id']??null)?(int)$_GET['id']:0;
    $modalEditing = getNewsById($editId);
    if ($modalEditing) {
        $modalGallery = getNewsImages($editId);
    }
}
if (!empty($flashOld)) {
    $modalEditing = array_merge($modalEditing ?? [], $flashOld);
    if(isset($_GET['revise'])){
        $modalMode=($flashOld['action']??'')==='update'?'edit':'create';
        if(!empty($flashOld['news_id'])){$savedNews=getNewsById((int)$flashOld['news_id']);if($savedNews)$modalEditing=array_merge($savedNews,$flashOld);$modalGallery=getNewsImages((int)$flashOld['news_id']);}
    }
}
$revisionFeedback='';
if(isset($_GET['revise'])&&!empty($flashOld)){$revision=rbacGetSubmission((int)$_GET['revise']);$revisionFeedback=$revision['reason']??'';}

// ---- Friendly wording + helpers for non-technical admins ----
$statusLabels = [
    'draft'     => 'Draft',
    'pending'   => 'Waiting for approval',
    'published' => 'Published',
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
$baseQuery=$listFilters;
$filtersActive=(bool)array_filter(array_intersect_key($listFilters,array_flip(['search','status','category','from','to','pinned'])),static fn($v)=>$v!=='');
$ne=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$newsJson=static fn($v,$flags=0)=>json_encode($v,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE);
$returnQuery=http_build_query($listFilters);$newsPreviewData=[];
$canPublish    = hasPermission('publish_news');
// Super admins are the approvers, so they never need "Send for approval".
$isSuperAdmin = (($_SESSION['user_role'] ?? '') === 'Super Admin');

$pageTitle = 'News';
include __DIR__ . '/../components/header-admin.php';
?>
<div class="announcement-management news-management">
<div class="page-header">
    <div>
        <h1>News</h1>
        <p>Share stories and updates with students and parents. Write a new article, or manage the ones you already posted.</p>
    </div>
    <?php if (hasPermission('create_news')): ?>
    <button type="button" class="btn btn-gnc-gold" id="btn-new-news">
        <i class="bi bi-plus-lg"></i> New Article
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
<section class="ann-pin-banner" aria-labelledby="ann-pin-heading">
    <div class="ann-pin-heading">
        <div><h2 id="ann-pin-heading"><i class="bi bi-pin-angle" aria-hidden="true"></i> Pinned news and announcement</h2><p>Five slots shared by Announcements, News, and Facebook Posts.</p></div>
        <strong><?= (int)$pinnedCount ?> / <?= MAX_PINNED_ITEMS ?> used</strong>
    </div>
    <div class="ann-pin-meter" role="progressbar" aria-label="Occupied pinned content slots" aria-valuemin="0" aria-valuemax="<?= MAX_PINNED_ITEMS ?>" aria-valuenow="<?= (int)$pinnedCount ?>">
        <?php for ($i = 1; $i <= MAX_PINNED_ITEMS; $i++): ?><span class="<?= $i <= $pinnedCount ? 'on' : '' ?>"></span><?php endfor; ?>
    </div>
    <p class="ann-pin-help"><?= $pinnedCount >= MAX_PINNED_ITEMS ? 'All slots are used. Unpin an item below to make room for another post.' : (MAX_PINNED_ITEMS-$pinnedCount).' slots available. Pin a published article to feature it on the homepage.' ?></p>
    <details class="ann-slideshow-manage" id="pinned-content"><summary>Manage pinned news and announcement <span>(<?= (int)$pinnedCount ?> items)</span></summary><ul>
    <?php foreach(announcementPinnedItems(getDB()) as $item): ?>
    <li><span><?= $ne(ucfirst($item['source']).' — '.($item['title']?:'Pinned item')) ?></span><div class="ann-pinned-actions"><form method="post" action="/admin/action/unpin-content.php" class="ann-inline-form"><input type="hidden" name="csrf_token" value="<?= $ne($csrfToken) ?>"><input type="hidden" name="action" value="unpin"><input type="hidden" name="source" value="<?= $ne($item['source']) ?>"><input type="hidden" name="item_id" value="<?= $ne($item['item_id']) ?>"><input type="hidden" name="return_to" value="news"><input type="hidden" name="return_query" value="<?= $ne($returnQuery) ?>"><button type="submit" class="btn btn-outline-secondary"><i class="bi bi-pin-angle" aria-hidden="true"></i> Unpin</button></form></div></li>
    <?php endforeach; ?>
    <?php if(!$pinnedCount): ?><li>No items are pinned.</li><?php endif; ?>
    </ul></details>
</section>
<?php endif; ?>

<?php include __DIR__.'/partials/news-filters.php'; ?>
<?php $bulkDeleteKind='news';$bulkDeleteTable='news-table';$bulkDeleteCsrf=$csrfToken;include __DIR__.'/../components/content-bulk-actions.php'; ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0 ann-table" id="news-table">
            <thead>
                <tr>
                    <th scope="col"><span class="visually-hidden">Picture</span></th>
                    <th scope="col">Article</th>
                    <th scope="col">Category</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($newsList)): ?>
                <tr class="ann-empty-row"><td colspan="5">
                    <div class="ann-empty">
                        <i class="bi bi-newspaper"></i>
                        <?php if($listError): ?><p>Article data is unavailable. Reload to try again.</p>
                        <?php elseif ($filtersActive): ?>
                            <p class="mb-2">No news articles match your search or filters.</p>
                            <a href="?" class="btn btn-sm btn-outline-secondary">Clear filters</a>
                        <?php else: ?>
                            <p class="mb-2">No news articles yet.</p>
                            <?php if (hasPermission('create_news')): ?>
                            <button type="button" class="btn btn-sm btn-gnc-gold js-new-news"><i class="bi bi-plus-lg"></i> Write your first article</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </td></tr>
                <?php else: foreach ($newsList as $n):
                    $isOwner    = $n['user_id'] == ($_SESSION['user_id'] ?? null);
                    $postedBy   = trim(($n['first_name'] ?? '') . ' ' . ($n['last_name'] ?? ''));
                    $byline     = trim($n['author'] ?? '');
                    $byName     = $byline !== '' ? $byline : $postedBy;
                    $catCode    = $n['category'] ?? 'Others';
                    $catLabel   = $newsCategories[$catCode] ?? $catCode;
                    $canEdit=hasPermission('edit_news')||(hasPermission('edit_own_news')&&$isOwner);
                    $newsPreviewData[]=['id'=>(int)$n['news_id'],'title'=>$n['title'],'html'=>renderSectionContent($n['content']),'author'=>$byName?:'School editor','created'=>newsLocalTime($n['created_at'],'M j, Y · g:i A'),'published'=>!empty($n['published_at'])?newsLocalTime($n['published_at'],'M j, Y · g:i A'):'','category'=>$catLabel,'status'=>$statusLabels[$n['status']]??$n['status'],'canEdit'=>$canEdit,'images'=>array_values(array_filter(array_merge([$n['image_path']??null],array_column($listGalleries[$n['news_id']]??[],'file_path'))))];
                ?>
                <tr class="ann-row">
                    <td class="ann-img-cell">
                        <div class="bulk-select-media">
                        <?php if (hasPermission('delete_news') || (hasPermission('delete_own_news') && $isOwner)): ?><input type="checkbox" class="form-check-input m-0" data-bulk-item value="<?= (int)$n['news_id'] ?>" aria-label="Select article <?= htmlspecialchars($n['title'],ENT_QUOTES) ?>"><?php endif; ?>
                        <button type="button" class="news-thumb-button" data-news-preview="<?= (int)$n['news_id'] ?>" aria-label="<?= $ne('Preview: '.$n['title']) ?>">
                        <?php if (!empty($n['image_path'])): ?>
                        <img src="<?= htmlspecialchars($n['image_path']) ?>" alt="" class="ann-thumb" loading="lazy">
                        <?php else: ?>
                        <div class="ann-thumb ann-thumb-empty"><i class="bi bi-image"></i></div>
                        <?php endif; ?>
                        </button>
                        </div>
                    </td>
                    <td class="ann-title-cell">
                        <button type="button" class="ann-title ann-preview-open" data-news-preview="<?= (int)$n['news_id'] ?>"><?= htmlspecialchars($n['title']) ?></button>
                        <div class="ann-sub">
                            <?= $byName !== '' ? 'By ' . htmlspecialchars($byName) . ' &middot; ' : '' ?><?= newsLocalTime($n['created_at'], 'M j, Y') ?>
                        </div>
                        <?php if (!empty($n['is_pinned'])): ?>
                        <span class="ann-pinned"><i class="bi bi-pin-angle-fill"></i> On homepage slideshow</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Category"><span class="program-badge"><?= htmlspecialchars($catLabel) ?></span></td>
                    <td data-label="Status"><span class="status-badge <?= htmlspecialchars($n['status']) ?>"><?= htmlspecialchars($statusLabels[$n['status']] ?? ucfirst($n['status'])) ?></span><div class="ann-sub"><?= $ne(['draft'=>'Continue editing when ready.','pending'=>'Awaiting Super Admin review.','published'=>'Visible on the website.','archived'=>'Hidden; restore when ready.'][$n['status']]??'') ?></div></td>
                    <td class="ann-actions-cell">
                        <?php if ($canPublish): ?>
                        <form method="POST" action="/admin/action/create-news.php" class="ann-inline-form">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="<?= !empty($n['is_pinned']) ? 'unpin' : 'pin' ?>">
                            <input type="hidden" name="news_id" value="<?= $n['news_id'] ?>">
                            <input type="hidden" name="return_query" value="<?= $ne($returnQuery) ?>">
                            <button type="submit"
                                    class="btn btn-sm btn-outline-secondary btn-pin-toggle<?= !empty($n['is_pinned']) ? ' is-pinned' : '' ?>"
                                    <?php if (empty($n['is_pinned']) && $pinnedCount >= MAX_PINNED_ITEMS): ?>
                                    disabled title="All <?= MAX_PINNED_ITEMS ?> slideshow spots are used. Unpin one first."
                                    <?php elseif (empty($n['is_pinned']) && $n['status'] !== 'published'): ?>
                                    disabled title="Publish this article before pinning it."
                                    <?php else: ?>
                                    title="<?= !empty($n['is_pinned']) ? 'Remove from the homepage slideshow' : 'Show in the homepage slideshow' ?>"
                                    <?php endif; ?>>
                                <i class="bi <?= !empty($n['is_pinned']) ? 'bi-pin-angle-fill' : 'bi-pin-angle' ?>"></i>
                                <span><?= !empty($n['is_pinned']) ? 'Unpin' : 'Pin' ?></span>
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php if (rbacRole() !== 'The Mirror' && ($canPublish || hasPermission('edit_news') || (hasPermission('edit_own_news') && $isOwner))): ?>
                        <form method="POST" action="/admin/action/create-news.php" class="ann-inline-form">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="<?= $n['status'] === 'archived' ? 'unarchive' : 'archive' ?>">
                            <input type="hidden" name="news_id" value="<?= $n['news_id'] ?>">
                            <input type="hidden" name="return_query" value="<?= $ne($returnQuery) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                    title="<?= $n['status'] === 'archived' ? 'Bring it back as a draft' : 'Hide it from the website but keep it on record' ?>">
                                <i class="bi <?= $n['status'] === 'archived' ? 'bi-arrow-counterclockwise' : 'bi-archive' ?>"></i>
                                <span><?= $n['status'] === 'archived' ? 'Restore' : 'Archive' ?></span>
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php if (hasPermission('edit_news') || (hasPermission('edit_own_news') && $isOwner)): ?>
                        <?php $rowGallery=$listGalleries[$n['news_id']]??[]; ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-news" title="Edit this article"
                                data-id="<?= $n['news_id'] ?>"
                                data-title="<?= htmlspecialchars($n['title'], ENT_QUOTES) ?>"
                                data-author="<?= htmlspecialchars($n['author'] ?? '', ENT_QUOTES) ?>"
                                data-category="<?= htmlspecialchars($n['category'] ?? 'Others', ENT_QUOTES) ?>"
                                data-status="<?= htmlspecialchars($n['status']) ?>"
                                data-image="<?= htmlspecialchars($n['image_path'] ?? '') ?>"
                                data-gallery="<?= htmlspecialchars(json_encode($rowGallery), ENT_QUOTES) ?>"
                                data-content-id="news-content-<?= $n['news_id'] ?>">
                            <i class="bi bi-pencil"></i><span>Edit</span>
                        </button>
                        <!-- Raw content stashed in a hidden template (not JSON) -->
                        <template id="news-content-<?= $n['news_id'] ?>"><?= sectionToEditorHtml($n['content']) ?></template>
                        <?php endif; ?>
                        <?php if (hasPermission('delete_news') || (hasPermission('delete_own_news') && $isOwner)): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-news" title="Delete this article"
                                data-id="<?= $n['news_id'] ?>"
                                data-title="<?= htmlspecialchars($n['title'], ENT_QUOTES) ?>">
                            <i class="bi bi-trash"></i><span>Delete</span>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__.'/partials/news-pagination.php'; ?>

<!-- ============================================================
     CREATE / EDIT MODAL — posts to /admin/action/create-news.php
============================================================ -->
<div class="modal fade" id="newsModal" tabindex="-1" aria-hidden="true" aria-labelledby="newsModalLabel" aria-describedby="newsModalIntro">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">
      <form data-content-publish data-custom-publish-label method="POST" action="/admin/action/create-news.php" enctype="multipart/form-data" id="news-form" novalidate>
        <div class="modal-header">
          <div class="evt-modal-heading">
            <span class="evt-modal-icon" aria-hidden="true"><i class="bi bi-newspaper"></i></span>
            <div><h5 class="modal-title" id="newsModalLabel">New Article</h5>
            <p id="newsModalIntro" class="evt-modal-intro">Write a story and choose when it goes live.</p></div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">

          <?php if (!empty($flashErrors)): ?>
          <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert" tabindex="-1">
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
          <input type="hidden" name="return_query" value="<?= $ne($returnQuery) ?>">
          <input type="hidden" name="news_id" id="modal-news-id" value="">
          <input type="hidden" name="_review_id" value="<?= (int)($_GET['revise'] ?? 0) ?>">
          <?php if($revisionFeedback): ?><div class="alert alert-warning" role="note"><strong>Revision feedback</strong><p class="mb-0"><?= nl2br($ne($revisionFeedback)) ?></p></div><?php endif; ?>
          <div id="announcement-form-alert" class="alert alert-danger d-none" role="alert" tabindex="-1"></div>
          <p class="evt-required-note">Fields marked <span class="text-danger">*</span> are required. Pictures and the author name are optional.</p>
          <section class="evt-form-section" aria-labelledby="news-content-heading">
          <h6 id="news-content-heading">Article content</h6>

          <div class="mb-3">
            <label class="form-label" for="modal-title">Headline <span class="text-danger">*</span></label>
            <input type="text" name="title" id="modal-title" class="form-control" required maxlength="500" placeholder="e.g. GNC students win regional science fair">
          </div>

          <div class="mb-3">
            <label class="form-label" id="ann-message-label">Story <span class="text-danger">*</span></label>
            <div id="modal-content-editor"></div>
            <input type="hidden" name="content" id="modal-content-input">
            <div class="form-text" id="ann-message-help">Start with the key news, then add the full story. Use the toolbar for formatting, lists, and links.</div>
            <div class="invalid-feedback" id="ann-message-error" aria-live="polite"></div>
          </div>

          </section>
          <section class="evt-form-section" aria-labelledby="news-pictures-heading" data-warning-optional="true">
          <h6 id="news-pictures-heading">Pictures <span class="ann-optional">Optional</span></h6>
          <label class="form-label" for="modal-image-input">Cover picture</label>
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
              <div class="form-text">Optional. Shown as the thumbnail on the news page and the homepage. JPG, PNG, GIF or WEBP, up to 5 MB. A wide (landscape) picture looks best.</div>
            </div>
          </div>

          <label class="form-label" for="modal-gallery-input">More pictures (gallery)</label>
          <div class="mb-3">
            <input type="file" name="gallery_images[]" id="modal-gallery-input" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" multiple data-max-files="10" data-max-files-message="Choose a maximum of 10 gallery pictures at a time.">
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

          </section>
          <section class="evt-form-section" aria-labelledby="news-details-heading">
          <h6 id="news-details-heading">Article details</h6>
          <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6">
              <label class="form-label" for="modal-category">News category</label>
              <select name="category" id="modal-category" class="form-select">
                <?php foreach ($newsCreateCategories as $code => $label): ?>
                <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label" for="modal-author">Author <span class="ann-optional">Optional</span></label>
              <input type="text" name="author" id="modal-author" class="form-control" maxlength="255" placeholder="e.g. GNC Press Office">
              <div class="form-text">Optional name shown under the headline on the website.</div>
            </div>
          </div>

          </section>
          <section class="evt-form-section" aria-labelledby="news-visibility-heading">
          <h6 id="news-visibility-heading">Visibility</h6>
          <?php if ($canPublish): ?>
          <label class="form-label" for="modal-status">What should happen when you save?</label>
          <select name="status" id="modal-status" class="form-select">
            <option value="draft">Save as draft — still working on it</option>
            <?php if (!$isSuperAdmin): ?>
            <option value="pending">Send for approval</option>
            <?php endif; ?>
            <option value="published">Publish now — show on website</option>
            <option value="archived">Archive — hide from website</option>
          </select>
          <div class="ann-status-help" id="modal-status-help" aria-live="polite"></div>
          <?php else: ?>
          <label class="form-label" for="modal-status">What should happen when you save?</label>
          <select name="status" id="modal-status" class="form-select mb-2"><option value="draft">Save as draft</option><option value="pending" selected>Submit for approval</option></select>
          <div class="alert alert-secondary mb-0 d-flex gap-2" style="font-size:.88rem">
            <i class="bi bi-info-circle mt-1"></i>
            <span>This will be sent for approval first. It appears on the public website once it is approved.</span>
          </div>
          <?php endif; ?>
          </section>
        </div>
        <div class="modal-footer">
          <span id="ann-save-intent" class="evt-save-intent" aria-live="polite"></span>
          <button type="button" class="btn btn-outline-secondary" id="news-draft-preview">Preview article</button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-gnc-gold" id="modal-save-btn">
            <i class="bi bi-check-lg"></i> <span id="modal-save-label">Create Article</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============================================================
     DELETE CONFIRMATION MODAL
============================================================ -->
<div class="modal fade" id="deleteNewsModal" data-delete-password-modal tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
    <div class="modal-content">
      <form method="POST" action="/admin/action/create-news.php" id="delete-news-form">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="news_id" id="delete-news-id" value="">
        <div class="modal-body text-center p-4">
          <div class="ann-del-icon"><i class="bi bi-trash3"></i></div>
          <h5 class="fw-bold mb-2">Delete this article?</h5>
          <p class="mb-1"><strong id="delete-news-title"></strong></p>
          <p class="text-muted mb-3" style="font-size:.85rem">It will be removed from the website. This cannot be undone.</p>
          <div class="text-start mb-3"><label class="form-label" for="delete-news-password">Your current password</label><input type="password" class="form-control" name="current_password" id="delete-news-password" autocomplete="current-password" maxlength="4096" required></div>
          <div class="d-flex gap-2 justify-content-center">
            <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger px-4" id="delete-news-btn"><i class="bi bi-trash3 me-1"></i> Delete</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="/assets/js/content-delete-password.js?v=<?= filemtime(__DIR__.'/../assets/js/content-delete-password.js') ?>"></script>
<link rel="stylesheet" href="/assets/css/content-row-actions.css?v=<?= filemtime(__DIR__.'/../assets/css/content-row-actions.css') ?>">
<script src="/assets/js/content-row-actions.js?v=<?= filemtime(__DIR__.'/../assets/js/content-row-actions.js') ?>"></script>
<style>
/* ---------- Shared look (same as Announcements) ---------- */
.ann-sub { font-size: .78rem; color: #8a8f8c; }
.page-header { flex-wrap: wrap; gap: .75rem; }
.data-card-header { flex-wrap: wrap; }

.program-badge {
    display: inline-block; padding: 2px 10px; border-radius: 12px;
    font-size: .72rem; font-weight: 600; letter-spacing: .02em; white-space: nowrap;
    background: rgba(201,162,39,.16); color: #C9A227; border: 1px solid rgba(201,162,39,.4);
}


.btn-pin-toggle.is-pinned { background: #EABA3B; border-color: #EABA3B; color: #1F5E2C; }

/* Pin banner */
.ann-pin-banner {
    display: flex; align-items: center; gap: .85rem; padding: .85rem 1rem; margin-bottom: 1rem;
    border: 1px solid rgba(234,186,59,.45); background: rgba(234,186,59,.1); border-radius: 12px; font-size: .9rem;
}
.ann-pin-banner-icon { color: #EABA3B; font-size: 1.4rem; }
.ann-pin-dots { display: flex; gap: 6px; }
.ann-pin-dots span { width: 12px; height: 12px; border-radius: 50%; border: 2px solid #EABA3B; }
.ann-pin-dots span.on { background: #EABA3B; }

/* Search + filters */
.ann-search { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
.ann-search-box { position: relative; }
.ann-search-box i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: .8rem; opacity: .6; }
.ann-search-box input { padding-left: 30px; width: 260px; }
.ann-search select { width: 170px; }
.ann-filters { padding: .75rem 1.25rem; display: flex; flex-direction: column; gap: .6rem; }
.ann-chip-row { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
.ann-chip {
    border: 1px solid rgba(128,128,128,.35); color: inherit; text-decoration: none;
    border-radius: 999px; padding: .3rem .9rem; font-size: .82rem; transition: background var(--transition), border-color var(--transition), color var(--transition); white-space: nowrap;
}
.ann-chip:hover { background: rgba(128,128,128,.15); color: inherit; }
.ann-chip.active { background: #EABA3B; border-color: #EABA3B; color: #094024; font-weight: 600; }
.ann-clear { font-size: .8rem; color: #dc3545; text-decoration: none; margin-left: .25rem; }

/* Table */
.ann-thumb { width: 56px; height: 56px; object-fit: cover; border-radius: 8px; display: block; }
.ann-thumb-empty { background: rgba(128,128,128,.18); display: flex; align-items: center; justify-content: center; color: #8a8f8c; font-size: 1.2rem; }
.ann-title-cell { max-width: 340px; }
.ann-title { font-weight: 600; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.ann-pinned { display: inline-block; margin-top: 4px; font-size: .72rem; padding: 1px 8px; border-radius: 999px; background: rgba(234,186,59,.22); color: #EABA3B; font-weight: 600; }
.ann-actions-cell { display: table-cell; white-space: nowrap; }
/* Icon-only action buttons: equal squares; the words are visually hidden but still read by screen readers */
.ann-actions-cell .btn { width: 36px; height: 36px; padding: 0; margin: 0 2px 0 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; vertical-align: middle; }
.ann-actions-cell .btn .bi { font-size: 1rem; line-height: 1; }
.ann-inline-form { display: inline; }
.ann-table td { vertical-align: middle; }

.ann-empty { text-align: center; padding: 2.5rem 1rem; color: #8a8f8c; }
.ann-empty i { font-size: 2.2rem; display: block; margin-bottom: .5rem; opacity: .7; }

/* Modal */
#news-form { display: flex; flex-direction: column; min-height: 0; max-height: 100%; }
#news-form .modal-body { overflow-y: auto; }
.ann-section-label {
    font-size: .72rem; text-transform: uppercase; letter-spacing: .6px; font-weight: 700; color: #8a8f8c;
    margin: 1.1rem 0 .55rem; padding-bottom: .3rem; border-bottom: 1px solid rgba(128,128,128,.2);
}
#modal-content-editor { background: #fff; color: #222; }
#modal-content-editor .ql-editor { min-height: 180px; max-height: 260px; overflow-y: auto; }
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

/* ---------- Responsive ---------- */
@media (max-width: 991.98px) {
    .ann-search-box input { width: 200px; }
    .ann-actions-cell { width: auto !important; }
}

/* Phones & small tablets: table turns into cards */
@media (max-width: 767.98px) {
    .ann-search { width: 100%; margin-left: 0 !important; }
    .ann-search-box { flex: 1 1 100%; }
    .ann-search-box input { width: 100%; }
    .ann-search select { flex: 1 1 0; width: auto; }
    .ann-filters { padding: .6rem .75rem; }
    .ann-chip-row { flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; }
    .ann-chip-row::-webkit-scrollbar { display: none; }
    .ann-chip { padding: .4rem .9rem; }

    .ann-table thead { display: none; }
    .ann-table, .ann-table tbody { display: block; width: 100%; }
    .ann-table tr.ann-row {
        display: grid; grid-template-columns: 64px 1fr; column-gap: .75rem;
        padding: .85rem 1rem; border-bottom: 1px solid rgba(128,128,128,.25);
    }
    .ann-table tr.ann-row > td { border: 0; padding: .25rem 0; text-align: right; max-width: none; }
    .ann-table tr.ann-row > td.ann-img-cell { grid-column: 1; grid-row: 1; text-align: left; padding: 0; }
    .ann-table tr.ann-row > td.ann-title-cell { grid-column: 2; grid-row: 1; text-align: left; padding: 0; }
    .ann-table tr.ann-row > td[data-label] {
        grid-column: 1 / -1; display: flex; justify-content: space-between; align-items: center; gap: 1rem;
    }
    .ann-table tr.ann-row > td[data-label]::before {
        content: attr(data-label); font-size: .7rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .5px; color: #8a8f8c; text-align: left; flex: 0 0 auto;
    }
    .ann-table tr.ann-row > td.ann-actions-cell {
        grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: .5rem; padding-top: .6rem;
    }
    .ann-actions-cell .ann-inline-form { flex: 0 0 auto; display: flex; margin: 0; }
    .ann-actions-cell .btn { flex: 0 0 auto; width: 44px; height: 44px; margin: 0; }
    .ann-table tr.ann-empty-row, .ann-table tr.ann-empty-row > td { display: block; }
}

@media (max-width: 575.98px) {
    .page-header > div { flex: 1 1 100%; }
    .page-header h1 { font-size: 1.4rem; }
    #btn-new-news { width: 100%; padding: .65rem 1rem; font-size: 1rem; }
    .ann-pin-banner { flex-wrap: wrap; font-size: .85rem; }
    .ann-pin-dots { margin-left: auto; }
    .data-card-header { padding-left: .75rem; padding-right: .75rem; }

    #newsModal .modal-footer { position: sticky; bottom: 0; background: var(--bs-modal-bg, inherit); z-index: 2; flex-wrap: nowrap; }
    #newsModal .modal-footer .btn { flex: 1 1 0; padding: .65rem; }
    .modal-body .form-control, .modal-body .form-select { font-size: 16px; } /* stops iOS zoom on focus */
    #modal-image-preview { width: 100%; height: 160px; }
    .ann-image-box > * { flex: 1 1 100%; }
    #deleteNewsModal .modal-dialog { margin: 1rem; }
    #deleteNewsModal .btn { flex: 1 1 0; }
    .ann-toast { left: 12px; right: 12px; bottom: 16px; max-width: none; }
}
@media (hover: none) {
    .btn, .ann-chip { min-height: 40px; }
    .ann-actions-cell .btn { width: 44px; height: 44px; }
}
</style>
<link rel="stylesheet" href="/assets/css/admin-announcements.css?v=<?= filemtime(__DIR__.'/../assets/css/admin-announcements.css') ?>">

<link rel="stylesheet" href="/assets/css/event-form.css?v=<?= filemtime(__DIR__ . '/../assets/css/event-form.css') ?>">
<link rel="stylesheet" href="/assets/css/announcement-form.css?v=<?= filemtime(__DIR__ . '/../assets/css/announcement-form.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-news.css?v=<?= filemtime(__DIR__.'/../assets/css/admin-news.css') ?>">
<script src="/assets/js/announcement-form.js?v=<?= filemtime(__DIR__ . '/../assets/js/announcement-form.js') ?>"></script>
</div>

<?php ob_start(); ?>
<script>
// NOTE: this runs via $extraScripts (echoed in footer-admin.php), AFTER
// quill.min.js and bootstrap.bundle.min.js have loaded — same reasoning
// as announcement.php.
const modalQuill = initQuill('modal-content-editor', 'modal-content-input', { placeholder: 'Write the article here…' });

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
annToast(<?= $newsJson($flashSuccess) ?>);
<?php endif; ?>

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
const statusHelpText = <?= $newsJson($statusHelp) ?>;
const modalStatusHelp = document.getElementById('modal-status-help');
function updateStatusHelp() {
    // If the saved status has no matching option (e.g. an old "pending" article
    // opened by a super admin), fall back to a draft.
    if (modalStatus && modalStatus.value === '') modalStatus.value = 'draft';
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

// Preserve a removed type only when editing an article already using it.
const savedNewsCategoryLabels = <?= $newsJson($newsCategories, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
function selectNewsCategory(code = 'Others', preserveExisting = false) {
    if (!modalCategory) return;
    modalCategory.querySelectorAll('[data-existing-category]').forEach(option => option.remove());
    if (preserveExisting && savedNewsCategoryLabels[code] && !Array.from(modalCategory.options).some(option => option.value === code)) {
        const option = new Option(savedNewsCategoryLabels[code] + ' (existing selection)', code);
        option.dataset.existingCategory = '1';
        modalCategory.add(option);
    }
    modalCategory.value = code;
    if (!modalCategory.value) modalCategory.value = 'Others';
}

function resetModalForm() {
    document.getElementById('news-form').reset();
    document.querySelector('#news-form [name="allow_incomplete"]')?.remove();
    modalQuill.setContents([]);
    modalActionInput.value = 'create';
    modalIdInput.value = '';
    selectNewsCategory(<?= $newsJson(isset($newsCreateCategories[$categoryFilter])?$categoryFilter:'Others') ?>);
    modalLabel.textContent = 'New Article';
    modalSaveLabel.textContent = 'Create Article';
    modalSaveBtn.disabled = false;
    modalImagePreviewWrap.style.display = 'none';
    modalRemoveWrap.style.display = 'none';
    modalImagePreview.src = '';
    resetGalleryUI();
    updateStatusHelp();
}

function openCreateModal() {
    resetModalForm();
    newsModal.show();
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
        document.getElementById('news-form').appendChild(input);
        wrap.classList.add('marked-remove');
    }
}

function openEditModal(btn) {
    resetModalForm();
    modalActionInput.value = 'update';
    modalIdInput.value = btn.dataset.id;
    modalLabel.textContent = 'Edit Article';
    modalSaveLabel.textContent = 'Save Changes';

    modalTitleInput.value = btn.dataset.title || '';
    modalAuthorInput.value = btn.dataset.author || '';
    selectNewsCategory(btn.dataset.category || 'Others', true);
    if (modalStatus) modalStatus.value = <?= $canPublish?'true':'false' ?> ? (btn.dataset.status || 'draft') : 'pending';
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

    newsModal.show();
}

document.getElementById('btn-new-news')?.addEventListener('click', openCreateModal);
document.querySelectorAll('.js-new-news').forEach(b => b.addEventListener('click', openCreateModal));

newsModalEl.addEventListener('shown.bs.modal', () => modalTitleInput.focus());

document.querySelectorAll('.btn-edit-news').forEach(btn => {
    btn.addEventListener('click', () => openEditModal(btn));
});

// Delete confirmation modal (replaces the browser's plain confirm() box)
const deleteModalEl = document.getElementById('deleteNewsModal');
const deleteModal   = deleteModalEl ? new bootstrap.Modal(deleteModalEl) : null;
document.querySelectorAll('.btn-delete-news').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('delete-news-id').value = btn.dataset.id;
        document.getElementById('delete-news-title').textContent = btn.dataset.title || '';
        document.getElementById('delete-news-btn').disabled = false;
        deleteModal?.show();
    });
});
document.getElementById('delete-news-form')?.addEventListener('submit', () => {
    const b = document.getElementById('delete-news-btn');
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

document.getElementById('news-form').addEventListener('submit', (e) => {
    document.getElementById('modal-content-input').value = modalQuill.root.innerHTML;
    if (!modalQuill.getText().trim() && !e.target.querySelector('[name="allow_incomplete"][value="1"]')) {
        e.preventDefault();
        annToast('Please write the story for the article.', 'error');
        return;
    }
    // Show progress so people don't click twice while pictures upload
    e.target.dispatchEvent(new Event('news-form:submitting'));
    setTimeout(() => {
        modalSaveBtn.disabled = true;
        modalSaveLabel.textContent = !modalStatus || modalStatus.value === 'pending' ? 'Submitting…' : modalStatus.value === 'published' ? 'Publishing…' : 'Saving…';
    }, 0);
});

window.GncAnnouncementForm.init({quill: modalQuill, kind: 'news'});

// Auto-reopen the modal on load: validation error, or ?modal=create / ?modal=edit&id=N
<?php if ($modalMode === 'create'): ?>
openCreateModal();
<?php if (!empty($flashErrors)): ?>
modalTitleInput.value = <?= $newsJson($flashOld['title'] ?? '') ?>;
modalQuill.root.innerHTML = <?= $newsJson(sectionToEditorHtml($flashOld['content'] ?? '')) ?>;
modalAuthorInput.value = <?= $newsJson($flashOld['author'] ?? '') ?>;
selectNewsCategory(<?= $newsJson($flashOld['category'] ?? 'Others') ?>);
if (modalStatus) modalStatus.value = <?= $newsJson($flashOld['status'] ?? 'draft') ?>;
updateStatusHelp();
<?php endif; ?>
<?php elseif ($modalMode === 'edit' && !empty($modalEditing)): ?>
resetModalForm();
modalActionInput.value = 'update';
modalIdInput.value = <?= $newsJson($modalEditing['news_id'] ?? ($_GET['id'] ?? '')) ?>;
modalLabel.textContent = 'Edit Article';
modalSaveLabel.textContent = 'Save Changes';
modalTitleInput.value = <?= $newsJson($modalEditing['title'] ?? '') ?>;
modalQuill.root.innerHTML = <?= $newsJson(sectionToEditorHtml($modalEditing['content'] ?? '')) ?>;
modalAuthorInput.value = <?= $newsJson($modalEditing['author'] ?? '') ?>;
selectNewsCategory(<?= $newsJson($modalEditing['category'] ?? 'Others') ?>, true);
if (modalStatus) modalStatus.value = <?= $newsJson($modalEditing['status'] ?? 'draft') ?>;
updateStatusHelp();
<?php if (!empty($modalEditing['image_path'])): ?>
modalImagePreview.src = <?= $newsJson($modalEditing['image_path']) ?>;
modalImagePreviewWrap.style.display = 'block';
modalRemoveWrap.style.display = 'block';
<?php endif; ?>
renderExistingGallery(<?= $newsJson($modalGallery) ?>);
newsModal.show();
<?php endif; ?>
</script>
<script src="/assets/js/admin-news.js?v=<?= filemtime(__DIR__.'/../assets/js/admin-news.js') ?>"></script>
<?php $extraScripts = ob_get_clean(); ?>

<?php
// IMPORTANT: the footer must be included last. It loads Bootstrap + Quill and
// then echoes $extraScripts (the page script above).
include __DIR__ . '/../components/footer-admin.php';
?>
