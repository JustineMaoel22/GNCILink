<?php
/**
 * GNC Admin Panel - Announcement Actions
 * /admin/action/create-announcement.php
 *
 * Handles the create / update / delete form submissions from the modal
 * Updated to use featured_image column from existing schema
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';

initSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/announcement.php');
    exit;
}

$postAction = $_POST['action'] ?? '';

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_errors'] = ['Invalid or expired request token. Please try again.'];
    header('Location: /admin/announcement.php');
    exit;
}

// Super admins are the approvers, so they never send items for approval.
$isSuperAdmin = (($_SESSION['user_role'] ?? '') === 'Super Admin');

// ============================================================
// DELETE
// ============================================================
if ($postAction === 'delete') {
    $delId    = (int)($_POST['announcement_id'] ?? 0);
    $existing = $delId ? getAnnouncementById($delId) : null;

    if ($existing) {
        $isOwner = $existing['user_id'] == ($_SESSION['user_id'] ?? null);
        if (hasPermission('delete_announcement') || (hasPermission('delete_own_announcement') && $isOwner)) {
            if (deleteAnnouncement($delId)) {
                $_SESSION['flash_success'] = 'Announcement deleted.';
            } else {
                $_SESSION['flash_errors'] = ['Could not delete the announcement. Please try again.'];
            }
        } else {
            $_SESSION['flash_errors'] = ['You do not have permission to delete this announcement.'];
        }
    }

    header('Location: /admin/announcement.php');
    exit;
}

// ============================================================
// PIN / UNPIN A FACEBOOK POST (listed in the Announcements table)
// ============================================================
if ($postAction === 'pin_facebook' || $postAction === 'unpin_facebook') {
    $fbPostId = trim($_POST['fb_post_id'] ?? '');

    if (!hasPermission('publish_announcement')) {
        $_SESSION['flash_errors'] = ['You do not have permission to pin Facebook posts.'];
    } elseif ($fbPostId !== '') {
        try {
            if ($postAction === 'pin_facebook') {
                if (!pinFacebookPost($fbPostId)) {
                    $_SESSION['flash_errors'] = ['Facebook post not found.'];
                } else {
                    $_SESSION['flash_success'] = 'Pinned to the homepage slideshow.';
                }
            } else {
                unpinFacebookPost($fbPostId);
                $_SESSION['flash_success'] = 'Removed from the homepage slideshow.';
            }
        } catch (Exception $e) {
            $_SESSION['flash_errors'] = [$e->getMessage()];
        }
    }

    header('Location: /admin/announcement.php');
    exit;
}

// ============================================================
// CHANGE THE CATEGORY OF A FACEBOOK POST (manual override of auto-detect)
// ============================================================
if ($postAction === 'set_fb_category') {
    $fbPostId = trim($_POST['fb_post_id'] ?? '');
    $choice   = trim($_POST['category_choice'] ?? '');

    if (!hasPermission('publish_announcement')) {
        $_SESSION['flash_errors'] = ['You do not have permission to change Facebook post categories.'];
    } elseif ($fbPostId === '' || $choice === '') {
        $_SESSION['flash_errors'] = ['Invalid request.'];
    } elseif (!setFacebookPostCategory($fbPostId, $choice)) {
        $_SESSION['flash_errors'] = ['Could not update the Facebook post category.'];
    } else {
        $_SESSION['flash_success'] = 'Category updated.';
    }

    header('Location: /admin/announcement.php');
    exit;
}

// ============================================================
// PIN / UNPIN (homepage slideshow — max 3 items, shared with News)
// ============================================================
if ($postAction === 'pin' || $postAction === 'unpin') {
    $pinId = (int)($_POST['announcement_id'] ?? 0);

    if (!hasPermission('publish_announcement')) {
        $_SESSION['flash_errors'] = ['You do not have permission to pin announcements.'];
    } elseif ($pinId) {
        try {
            if ($postAction === 'pin') {
                pinAnnouncement($pinId);
                $_SESSION['flash_success'] = 'Pinned to the homepage slideshow.';
            } else {
                unpinAnnouncement($pinId);
                $_SESSION['flash_success'] = 'Removed from the homepage slideshow.';
            }
        } catch (Exception $e) {
            $_SESSION['flash_errors'] = [$e->getMessage()];
        }
    }

    header('Location: /admin/announcement.php');
    exit;
}

// ============================================================
// ARCHIVE / RESTORE (hides it from the website but keeps it on record)
// ============================================================
if ($postAction === 'archive' || $postAction === 'unarchive') {
    $arcId    = (int)($_POST['announcement_id'] ?? 0);
    $existing = $arcId ? getAnnouncementById($arcId) : null;

    if (!$existing) {
        $_SESSION['flash_errors'] = ['We could not find that announcement. It may have been deleted.'];
    } else {
        $isOwner = $existing['user_id'] == ($_SESSION['user_id'] ?? null);
        if (!hasPermission('publish_announcement') && !hasPermission('edit_announcement') && !(hasPermission('edit_own_announcement') && $isOwner)) {
            $_SESSION['flash_errors'] = ['Your account is not allowed to archive this announcement.'];
        } else {
            try {
                if ($postAction === 'archive' && !empty($existing['is_pinned'])) {
                    unpinAnnouncement($arcId); // hidden items should not stay in the homepage slideshow
                }
                $ok = updateAnnouncement($arcId, [
                    'title'   => $existing['title'],
                    'content' => $existing['content'],
                    'category_id' => $existing['category_id'] ?? null,
                    'status'  => $postAction === 'archive' ? 'archived' : 'draft',
                ]);
                if ($ok) {
                    $_SESSION['flash_success'] = $postAction === 'archive'
                        ? 'The announcement was archived. It is hidden from the website but kept on record.'
                        : 'The announcement was restored as a draft. Publish it when you are ready.';
                } else {
                    $_SESSION['flash_errors'] = ['We could not update this announcement. Please try again.'];
                }
            } catch (Exception $e) {
                $_SESSION['flash_errors'] = [$e->getMessage()];
            }
        }
    }

    header('Location: /admin/announcement.php');
    exit;
}

// ============================================================
// CREATE / UPDATE
// ============================================================
if ($postAction === 'create' || $postAction === 'update') {
    $isUpdate = $postAction === 'update';
    $id       = (int)($_POST['announcement_id'] ?? 0);
    $errors   = [];

    if ($isUpdate) {
        $existing = $id ? getAnnouncementById($id) : null;
        if (!$existing) {
            $errors[] = 'Announcement not found.';
        } else {
            $isOwner = $existing['user_id'] == ($_SESSION['user_id'] ?? null);
            if (!hasPermission('edit_announcement') && !(hasPermission('edit_own_announcement') && $isOwner)) {
                $errors[] = 'You do not have permission to edit this announcement.';
            }
        }
    } else {
        if (!hasPermission('create_announcement')) {
            $errors[] = 'You do not have permission to create announcements.';
        }
    }

    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (empty($errors)) {
        if ($title === '') $errors[] = 'Title is required.';
        if ($content === '' || strip_tags($content) === '') $errors[] = 'Content is required.';
    }

    // Super admins approve things themselves, so "Send for approval" is not allowed for them.
    if (empty($errors) && $isSuperAdmin && ($_POST['status'] ?? '') === 'pending') {
        $errors[] = 'Super admins do not need approval. Please choose "Save as draft" or "Publish now".';
    }

    if (empty($errors)) {
        try {
            $mediaId = null;
            if (!empty($_FILES['image']['name'] ?? '')) {
                $mediaId = uploadAnnouncementImage($_FILES['image']);
            }

            // Gallery: validate the count up front so a bad batch never partially uploads.
            $galleryFiles = $_FILES['gallery_images'] ?? null;
            $galleryCount = 0;
            if (!empty($galleryFiles['name']) && is_array($galleryFiles['error'])) {
                foreach ($galleryFiles['error'] as $err) {
                    if ($err !== UPLOAD_ERR_NO_FILE) $galleryCount++;
                }
            }
            if ($galleryCount > 10) {
                throw new Exception('You can add up to 10 extra photos at a time. Please choose fewer photos.');
            }

            $data = [
                'title'       => $title,
                'content'     => $content,
                'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'program'     => normalizeProgramCategory($_POST['program'] ?? null),
                'dept_id'     => $_POST['dept_id'] ?? null, // validated in create/updateAnnouncement
            ];

            if ($isUpdate) {
                if ($mediaId !== null) {
                    $data['featured_image'] = $mediaId;
                } elseif (!empty($_POST['remove_image'])) {
                    $data['featured_image'] = null;
                }
                if (hasPermission('publish_announcement') && !empty($_POST['status'])) {
                    $data['status'] = $_POST['status'];
                }
                if (!updateAnnouncement($id, $data)) {
                    $errors[] = 'Failed to update the announcement.';
                } else {
                    // Remove any gallery photos the admin marked
                    if (!empty($_POST['remove_gallery_ids']) && is_array($_POST['remove_gallery_ids'])) {
                        foreach ($_POST['remove_gallery_ids'] as $imgId) {
                            removeAnnouncementImage((int)$imgId, $id);
                        }
                    }
                    // Attach any newly uploaded gallery photos
                    if ($galleryCount > 0) {
                        $newMediaIds = uploadAnnouncementImages($galleryFiles);
                        if (!addAnnouncementImages($id, $newMediaIds)) {
                            $errors[] = 'The announcement was saved, but the extra photos could not be added. Please make sure the announcement_images table exists.';
                        }
                    }
                }
            } else {
                $data['featured_image'] = $mediaId;
                $data['status']     = $_POST['status'] ?? 'draft';
                $result = createAnnouncement($data);
                if (!$result) {
                    $errors[] = 'Failed to create the announcement.';
                } elseif ($galleryCount > 0) {
                    $newMediaIds = uploadAnnouncementImages($galleryFiles);
                    if (!addAnnouncementImages($result, $newMediaIds)) {
                        $errors[] = 'The announcement was saved, but the extra photos could not be added. Please make sure the announcement_images table exists.';
                    }
                }
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        $finalStatus = $isUpdate ? ($data['status'] ?? null) : ($data['status'] ?? 'draft');
        if ($isUpdate) {
            $_SESSION['flash_success'] = 'Your changes were saved.';
        } elseif ($finalStatus === 'published') {
            $_SESSION['flash_success'] = 'Announcement published. It is now live on the website.';
        } elseif ($finalStatus === 'pending') {
            $_SESSION['flash_success'] = 'Announcement sent for approval.';
        } else {
            $_SESSION['flash_success'] = 'Announcement saved as a draft.';
        }
        header('Location: /admin/announcement.php');
        exit;
    }

    // Validation/permission failure — send the user back with the modal
    // flagged to reopen, and their submitted values preserved.
    $_SESSION['flash_errors'] = $errors;
    $_SESSION['flash_old']    = $_POST;
    header('Location: /admin/announcement.php?modal=' . ($isUpdate ? 'edit&id=' . $id : 'create'));
    exit;
}

// Unknown action
header('Location: /admin/announcement.php');
exit;