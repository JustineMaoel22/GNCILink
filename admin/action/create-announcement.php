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

// ============================================================
// DELETE
// ============================================================
if ($postAction === 'delete') {
    $delId    = (int)($_POST['announcement_id'] ?? 0);
    $existing = $delId ? getAnnouncementById($delId) : null;

    if ($existing) {
        $isOwner = $existing['user_id'] == ($_SESSION['user_id'] ?? null);
        if (hasPermission('delete_announcement') || (hasPermission('delete_own_announcement') && $isOwner)) {
            deleteAnnouncement($delId);
        } else {
            $_SESSION['flash_errors'] = ['You do not have permission to delete this announcement.'];
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

    if (empty($errors)) {
        try {
            $mediaId = null;
            if (!empty($_FILES['image']['name'] ?? '')) {
                $mediaId = uploadAnnouncementImage($_FILES['image']);
            }

            $data = [
                'title'       => $title,
                'content'     => $content,
                'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
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
                }
            } else {
                $data['featured_image'] = $mediaId;
                $data['status']     = $_POST['status'] ?? 'draft';
                $result = createAnnouncement($data);
                if (!$result) {
                    $errors[] = 'Failed to create the announcement.';
                }
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
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