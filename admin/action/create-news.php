<?php
/**
 * GNC Admin Panel - News Actions
 * /admin/action/create-news.php
 *
 * Handles the create / update / delete form submissions from the modal
 * on /admin/news.php. Mirrors /admin/action/create-announcement.php.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';

initSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/news.php');
    exit;
}

$postAction = $_POST['action'] ?? '';

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_errors'] = ['Invalid or expired request token. Please try again.'];
    header('Location: /admin/news.php');
    exit;
}

// ============================================================
// DELETE
// ============================================================
if ($postAction === 'delete') {
    $delId    = (int)($_POST['news_id'] ?? 0);
    $existing = $delId ? getNewsById($delId) : null;

    if ($existing) {
        $isOwner = $existing['user_id'] == ($_SESSION['user_id'] ?? null);
        if (hasPermission('delete_news') || (hasPermission('delete_own_news') && $isOwner)) {
            deleteNews($delId);
        } else {
            $_SESSION['flash_errors'] = ['You do not have permission to delete this news article.'];
        }
    }

    header('Location: /admin/news.php');
    exit;
}

// ============================================================
// CREATE / UPDATE
// ============================================================
if ($postAction === 'create' || $postAction === 'update') {
    $isUpdate = $postAction === 'update';
    $id       = (int)($_POST['news_id'] ?? 0);
    $errors   = [];

    if ($isUpdate) {
        $existing = $id ? getNewsById($id) : null;
        if (!$existing) {
            $errors[] = 'News article not found.';
        } else {
            $isOwner = $existing['user_id'] == ($_SESSION['user_id'] ?? null);
            if (!hasPermission('edit_news') && !(hasPermission('edit_own_news') && $isOwner)) {
                $errors[] = 'You do not have permission to edit this news article.';
            }
        }
    } else {
        if (!hasPermission('create_news')) {
            $errors[] = 'You do not have permission to create news articles.';
        }
    }

    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $author  = trim($_POST['author'] ?? '');

    if (empty($errors)) {
        if ($title === '') $errors[] = 'Title is required.';
        if ($content === '' || strip_tags($content) === '') $errors[] = 'Content is required.';
    }

    if (empty($errors)) {
        try {
            $mediaId = null;
            if (!empty($_FILES['image']['name'] ?? '')) {
                $mediaId = uploadNewsImage($_FILES['image']);
            }

            // Gallery: validate count up front so a bad batch never partially uploads.
            $galleryFiles = $_FILES['gallery_images'] ?? null;
            $galleryCount = 0;
            if (!empty($galleryFiles['name'])) {
                foreach ($galleryFiles['error'] as $err) {
                    if ($err !== UPLOAD_ERR_NO_FILE) $galleryCount++;
                }
            }
            if ($galleryCount > 10) {
                throw new Exception('You can upload up to 10 gallery images at a time.');
            }

            $data = [
                'title'    => $title,
                'content'  => $content,
                'author'   => $author,
                'category' => $_POST['category'] ?? null,
            ];

            if ($isUpdate) {
                if ($mediaId !== null) {
                    $data['featured_image'] = $mediaId;
                } elseif (!empty($_POST['remove_image'])) {
                    $data['featured_image'] = null;
                }
                if (hasPermission('publish_news') && !empty($_POST['status'])) {
                    $data['status'] = $_POST['status'];
                }
                if (!updateNews($id, $data)) {
                    $errors[] = 'Failed to update the news article.';
                } else {
                    // Remove any gallery images the admin checked off
                    if (!empty($_POST['remove_gallery_ids']) && is_array($_POST['remove_gallery_ids'])) {
                        foreach ($_POST['remove_gallery_ids'] as $imgId) {
                            removeNewsImage((int)$imgId, $id);
                        }
                    }
                    // Attach any newly uploaded gallery images
                    if ($galleryCount > 0) {
                        $newMediaIds = uploadNewsImages($galleryFiles);
                        addNewsImages($id, $newMediaIds);
                    }
                }
            } else {
                $data['featured_image'] = $mediaId;
                $data['status']         = $_POST['status'] ?? 'draft';
                $result = createNews($data);
                if (!$result) {
                    $errors[] = 'Failed to create the news article.';
                } elseif ($galleryCount > 0) {
                    $newMediaIds = uploadNewsImages($galleryFiles);
                    addNewsImages($result, $newMediaIds);
                }
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        header('Location: /admin/news.php');
        exit;
    }

    // Validation/permission failure — send the user back with the modal
    // flagged to reopen, and their submitted values preserved.
    $_SESSION['flash_errors'] = $errors;
    $_SESSION['flash_old']    = $_POST;
    header('Location: /admin/news.php?modal=' . ($isUpdate ? 'edit&id=' . $id : 'create'));
    exit;
}

// Unknown action
header('Location: /admin/news.php');
exit;