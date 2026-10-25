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
require_once __DIR__ . '/../../includes/facebook-publishing.php';

initSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/announcement.php');
    exit;
}

$postAction = $_POST['action'] ?? '';
$facebookAction = in_array($postAction, ['pin_facebook', 'unpin_facebook', 'set_fb_category'], true);

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_errors'] = ['Invalid or expired request token. Please try again.'];
    header('Location: ' . ($facebookAction ? '/admin/facebook-posts.php' : '/admin/announcement.php'));
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
// PIN / UNPIN A FACEBOOK POST
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

    header('Location: /admin/facebook-posts.php');
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

    header('Location: /admin/facebook-posts.php');
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
    $destination = $_POST['publish_destination'] ?? 'website';
    $sendFacebook = in_array($destination, ['facebook', 'both'], true);
    $facebookTransaction = false;
    $savedId = 0;
    if (!in_array($destination, ['website', 'facebook', 'both'], true)) $errors[] = 'Invalid publishing destination.';
    if ($sendFacebook) {
        if (!hasPermission('publish_announcement') || ($_POST['status'] ?? '') !== 'published') {
            $errors[] = 'Facebook publishing requires publishing permission and Publish now. Drafts and approval requests cannot be sent to Facebook.';
        }
        if (($_POST['facebook_confirmed'] ?? '') !== '1') $errors[] = 'Confirm the Facebook destination before publishing.';
        if (!facebookPublishingReady()) $errors[] = 'Run the Facebook publishing database migration first.';
        $requestKey = $_POST['facebook_request_key'] ?? '';
        if (!is_string($requestKey) || !preg_match('/^[a-f0-9]{64}$/D', $requestKey)) $errors[] = 'Invalid publishing request. Reload the form.';
        if (!$errors) {
            $connection = facebookPublishingConnection();
            if (!$connection || !$connection['enabled'] || ($_POST['facebook_page_id'] ?? '') !== $connection['page_id']) {
                $errors[] = 'The connected Facebook Page changed or was disconnected. Reload and confirm the destination.';
            }
            $receipt = getDB()->prepare('SELECT announcement_id FROM facebook_publishing_requests WHERE request_key=? AND user_id=?');
            $receipt->execute([$requestKey, $_SESSION['user_id']]);
            if ($receipt->fetchColumn()) {
                $_SESSION['flash_success'] = 'This request was already saved. Check its Facebook status; edit the saved announcement to retry if needed.';
                header('Location: /admin/announcement.php');
                exit;
            }
            try { facebookPublishingLink($_POST['facebook_link'] ?? ''); }
            catch (FacebookPublishingError $e) { $errors[] = $e->getMessage(); }
        }
    }

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
        if ($title === '' && !contentPublishAllowsIncomplete()) $errors[] = 'Title is required.';
        if (($content === '' || strip_tags($content) === '') && ($_POST['allow_incomplete'] ?? '') !== '1') $errors[] = 'Content is required.';
    }

    // Super admins approve things themselves, so "Send for approval" is not allowed for them.
    if (empty($errors) && $isSuperAdmin && ($_POST['status'] ?? '') === 'pending') {
        $errors[] = 'Super admins do not need approval. Please choose "Save as draft" or "Publish now".';
    }

    if (empty($errors)) {
        try {
            if ($sendFacebook) {
                getDB()->beginTransaction();
                $facebookTransaction = true;
                // Unique request key is reserved in the same transaction as the saved announcement.
                getDB()->prepare('INSERT INTO facebook_publishing_requests (request_key,user_id,announcement_id,created_at) VALUES (?,?,0,NOW())')->execute([$requestKey, $_SESSION['user_id']]);
            }
            $program = normalizeAnnouncementProgramCategory($_POST['program'] ?? null, $isUpdate ? ($existing['program'] ?? null) : null);
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
                'allow_incomplete' => ($_POST['allow_incomplete'] ?? '') === '1',
                'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
                'program'     => $program,
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
            $savedId = $isUpdate ? $id : (int)($result ?? 0);
            if ($sendFacebook && empty($errors) && $savedId) {
                if ($destination === 'facebook') {
                    // Keep a local editable record, but do not expose it on the public website.
                    if (!empty(($existing ?? [])['is_pinned'])) unpinAnnouncement($savedId);
                    getDB()->prepare("UPDATE announcements SET status='draft' WHERE announcement_id=?")->execute([$savedId]);
                }
                getDB()->prepare('UPDATE facebook_publishing_requests SET announcement_id=? WHERE request_key=?')->execute([$savedId, $requestKey]);
                getDB()->prepare("INSERT IGNORE INTO announcement_facebook_deliveries (announcement_id,page_id,state,attempted_by,updated_at) VALUES (?,?,'ready',?,NOW())")->execute([$savedId, $_POST['facebook_page_id'], $_SESSION['user_id']]);
                getDB()->commit();
                $facebookTransaction = false;
            } elseif ($facebookTransaction) {
                getDB()->rollBack();
                $facebookTransaction = false;
            }
        } catch (Exception $e) {
            if ($facebookTransaction && getDB()->inTransaction()) getDB()->rollBack();
            $errors[] = $sendFacebook ? 'The announcement could not be saved for Facebook publishing. Reload and check the announcement list before retrying.' : $e->getMessage();
        }
    }

    if (empty($errors) && $sendFacebook && $savedId) {
        $websiteMessage = $destination === 'both' ? 'Website published. ' : 'Website hidden; editable record saved. ';
        try {
            // Pin the confirmed Page across connection changes between save and send.
            $connection = facebookPublishingConnection();
            if (!$connection || $connection['page_id'] !== ($_POST['facebook_page_id'] ?? '')) throw new FacebookPublishingError('Facebook Page changed after confirmation. Nothing was sent.');
            $_SESSION['flash_success'] = $websiteMessage . facebookPublishAnnouncement($savedId, $_POST['facebook_link'] ?? '', $_POST['facebook_page_id'] ?? '');
        } catch (FacebookPublishingError $e) {
            getDB()->prepare("UPDATE announcement_facebook_deliveries SET state='failed',last_error=?,updated_at=NOW() WHERE announcement_id=? AND state IN ('ready','failed')")
                ->execute([$e->getMessage(), $savedId]);
            $_SESSION['flash_success'] = $websiteMessage;
            $_SESSION['flash_errors'] = ['The announcement is saved. ' . $e->getMessage() . ' Use Edit on the saved announcement to retry.'];
        } catch (Throwable $e) {
            $_SESSION['flash_success'] = $websiteMessage;
            $_SESSION['flash_errors'] = ['The announcement is saved, but Facebook delivery could not be confirmed. Check Facebook Integration before retrying.'];
        }
        header('Location: /admin/announcement.php');
        exit;
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
