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
require_once __DIR__ . '/../news-view.php';

initSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: '.newsReturnUrl($_POST));
    exit;
}

$postAction = $_POST['action'] ?? '';

if (!verifyCSRFToken(is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:'')) {
    $_SESSION['flash_errors'] = ['Your session timed out for safety. Please try again.'];
    header('Location: '.newsReturnUrl($_POST));
    exit;
}
if($postAction==='bulk_delete'){
    require_once __DIR__.'/../../includes/content-bulk-delete.php';
    try{$count=deleteSelectedContent('news',$_POST['ids']??null);$_SESSION['flash_success']=$count.' selected news article(s) deleted.';}
    catch(Throwable $e){$_SESSION['flash_errors']=[$e instanceof PDOException?'The selected articles could not be deleted. Nothing was changed.':$e->getMessage()];}
    header('Location: '.newsReturnUrl($_POST));exit;
}
if(!is_string($postAction)||!in_array($postAction,['create','update','delete','pin','unpin','archive','unarchive'],true)){
    $_SESSION['flash_errors']=['Choose a valid article action.'];header('Location: '.newsReturnUrl($_POST));exit;
}
if($postAction!=='create'&&(!is_string($_POST['news_id']??null)||!preg_match('/^[1-9]\d{0,9}$/D',$_POST['news_id']))){
    $_SESSION['flash_errors']=['Choose a valid news article.'];header('Location: '.newsReturnUrl($_POST));exit;
}

// Super admins are the approvers, so they never send items for approval.
// Keep this check in sync with $isSuperAdmin in /admin/news.php.
$isSuperAdmin = (($_SESSION['user_role'] ?? '') === 'Super Admin');

// ============================================================
// DELETE
// ============================================================
if ($postAction === 'delete') {
    $delId    = (int)($_POST['news_id'] ?? 0);
    $existing = $delId ? getNewsById($delId) : null;

    if ($existing) {
        $isOwner = $existing['user_id'] == ($_SESSION['user_id'] ?? null);
        if (hasPermission('delete_news') || (hasPermission('delete_own_news') && $isOwner)) {
            if(deleteNews($delId))$_SESSION['flash_success'] = 'The news article “' . ($existing['title'] ?? '') . '” was deleted.';
            else $_SESSION['flash_errors']=['The article could not be deleted. Please try again.'];
        } else {
            $_SESSION['flash_errors'] = ['Your account is not allowed to delete this news article.'];
        }
    }

    header('Location: '.newsReturnUrl($_POST));
    exit;
}

// ============================================================
// PIN / UNPIN (five slots shared with Announcements and Facebook Posts)
// ============================================================
if ($postAction === 'pin' || $postAction === 'unpin') {
    $pinId = (int)($_POST['news_id'] ?? 0);

    if (!hasPermission('publish_news')) {
        $_SESSION['flash_errors'] = ['Your account is not allowed to pin news articles to the homepage.'];
    } elseif ($pinId) {
        try {
            if ($postAction === 'pin') {
                $pinArticle=getNewsById($pinId);
                if(!$pinArticle||$pinArticle['status']!=='published')$_SESSION['flash_errors']=['Publish this article before pinning it.'];
                elseif(empty($pinArticle['is_pinned'])&&countPinnedItems()>=MAX_PINNED_ITEMS)$_SESSION['flash_errors']=['All five pinned content slots are used. Unpin an item first.'];
                elseif(pinNews($pinId))$_SESSION['flash_success']='Article added to pinned news and announcement.';
                else $_SESSION['flash_errors']=['The article could not be pinned. Please try again.'];
            } else {
                if(unpinNews($pinId))$_SESSION['flash_success']='Article removed from pinned news and announcement.';
                else $_SESSION['flash_errors']=['The article could not be unpinned. Please try again.'];
            }
        } catch (Exception $e) {
            $_SESSION['flash_errors'] = ['The pin change could not be saved. Please reload and try again.'];
        }
    }

    header('Location: '.newsReturnUrl($_POST));
    exit;
}

// ============================================================
// ARCHIVE / RESTORE (hides it from the website but keeps it on record)
// ============================================================
if ($postAction === 'archive' || $postAction === 'unarchive') {
    $arcId    = (int)($_POST['news_id'] ?? 0);
    $existing = $arcId ? getNewsById($arcId) : null;

    if (!$existing) {
        $_SESSION['flash_errors'] = ['We could not find that news article. It may have been deleted.'];
    } else {
        $isOwner = $existing['user_id'] == ($_SESSION['user_id'] ?? null);
        if (!hasPermission('publish_news') && !hasPermission('edit_news') && !(hasPermission('edit_own_news') && $isOwner)) {
            $_SESSION['flash_errors'] = ['Your account is not allowed to archive this news article.'];
        } else {
            try {
                if ($postAction === 'archive' && !empty($existing['is_pinned'])) {
                    unpinNews($arcId); // hidden items should not stay in the homepage slideshow
                }
                $ok = updateNews($arcId, [
                    'title'   => $existing['title'],
                    'content' => $existing['content'],
                    'status'  => $postAction === 'archive' ? 'archived' : 'draft',
                ]);
                if ($ok) {
                    $_SESSION['flash_success'] = $postAction === 'archive'
                        ? 'The article was archived. It is hidden from the website but kept on record.'
                        : 'The article was restored as a draft. Publish it when you are ready.';
                } else {
                    $_SESSION['flash_errors'] = ['We could not update this article. Please try again.'];
                }
            } catch (Exception $e) {
                $_SESSION['flash_errors'] = [$e->getMessage()];
            }
        }
    }

    header('Location: '.newsReturnUrl($_POST));
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
            $errors[] = 'We could not find that news article. It may have been deleted. Please refresh the page.';
        } else {
            $isOwner = $existing['user_id'] == ($_SESSION['user_id'] ?? null);
            if (!hasPermission('edit_news') && !(hasPermission('edit_own_news') && $isOwner)) {
                $errors[] = 'Your account is not allowed to edit this news article.';
            }
        }
    } else {
        if (!hasPermission('create_news')) {
            $errors[] = 'Your account is not allowed to create news articles.';
        }
    }

    foreach(['title','content','author','category','status'] as $field)if(isset($_POST[$field])&&!is_string($_POST[$field]))$errors[]='Check the article fields and try again.';
    $title=is_string($_POST['title']??null)?trim($_POST['title']):'';
    $content=is_string($_POST['content']??null)?trim($_POST['content']):'';
    $author=is_string($_POST['author']??null)?trim($_POST['author']):'';
    if(mb_strlen($title)>500)$errors[]='Keep the headline within 500 characters.';
    if(mb_strlen($author)>255)$errors[]='Keep the author name within 255 characters.';
    if(!in_array($_POST['status']??'draft',['draft','pending','published','archived'],true))$errors[]='Choose a valid publication status.';

    if (empty($errors)) {
        if ($title === '' && !contentPublishAllowsIncomplete()) $errors[] = 'Please type a headline for the article.';
        if (($content === '' || strip_tags($content) === '') && ($_POST['allow_incomplete'] ?? '') !== '1') $errors[] = 'Please write the story before saving.';
    }

    // Super admins approve things themselves, so "Send for approval" is not allowed for them.
    if (empty($errors) && $isSuperAdmin && ($_POST['status'] ?? '') === 'pending') {
        $errors[] = 'Super admins do not need approval. Please choose “Save as draft” or “Publish now”.';
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
                throw new Exception('You can add up to 10 extra photos at a time. Please choose fewer photos.');
            }

            $data = [
                'title'    => $title,
                'content'  => $content,
                'allow_incomplete' => ($_POST['allow_incomplete'] ?? '') === '1',
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
                    $errors[] = 'We could not save your changes. Please try again.';
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
                    $errors[] = 'We could not save the news article. Please try again.';
                } elseif ($galleryCount > 0) {
                    $newMediaIds = uploadNewsImages($galleryFiles);
                    addNewsImages($result, $newMediaIds);
                }
            }
        } catch (Exception $e) {
            $errors[] = $e instanceof PDOException ? 'The article could not be saved. Please try again.' : $e->getMessage();
        }
    }

    if (empty($errors)) {
        // Tell the admin exactly what happened (shown as a green message on the list page).
        $savedStatus = $data['status'] ?? ($existing['status'] ?? '');
        if ($isUpdate) {
            $_SESSION['flash_success'] = $savedStatus === 'published'
                ? 'Your changes were saved and are live on the website.'
                : 'Your changes were saved.';
        } elseif (!hasPermission('publish_news')) {
            $_SESSION['flash_success'] = 'Your news article was saved. It will appear on the website after it is approved.';
        } elseif ($savedStatus === 'published') {
            $_SESSION['flash_success'] = 'Done! Your news article is now live on the website.';
        } elseif ($savedStatus === 'pending') {
            $_SESSION['flash_success'] = 'Your news article was sent for approval. It will appear on the website once it is approved.';
        } else {
            $_SESSION['flash_success'] = 'Saved as a draft. Visitors cannot see it yet.';
        }
        header('Location: '.newsReturnUrl($_POST));
        exit;
    }

    // Validation/permission failure — send the user back with the modal
    // flagged to reopen, and their submitted values preserved.
    $_SESSION['flash_errors'] = $errors;
    $_SESSION['flash_old']    = $_POST;
    header('Location: '.newsReturnUrl($_POST,['modal'=>$isUpdate?'edit':'create','id'=>$isUpdate?$id:null]));
    exit;
}

// Unknown action
header('Location: '.newsReturnUrl($_POST));
exit;
