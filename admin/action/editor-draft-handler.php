<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';
require_once __DIR__ . '/../../includes/content-editor-drafts.php';
require_once __DIR__ . '/../../includes/content-submissions.php';
initSession();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
function edReply(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') edReply(['error' => 'Invalid request.'], 405);
if (!isLoggedIn()) edReply(['error' => 'Please sign in again.'], 401);
if (!verifyCSRFToken((is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : ''))) edReply(['error' => 'Your session has expired. Reload the editor.'], 403);
$manager = hasPermission('manage_page_content');
if (!$manager && !hasPermission('edit_page_content')) edReply(['error' => 'You cannot edit page content.'], 403);
$page = $_POST['page'] ?? null;
$route = $_POST['route'] ?? null;
$action = $_POST['action'] ?? null;
if (!is_string($page) || !preg_match('/\A[a-z0-9-]{1,100}\z/', $page) || !is_string($route) || strlen($route) > 1000 || !preg_match('~\A/(?!/)[^\x00-\x20]*\z~', $route)) edReply(['error' => 'Invalid editor page.'], 400);
$pages = getEditablePages();
if (!rbacSafePreviewRoute($route)) edReply(['error'=>'This preview route is not allowed.'],403);
$allowed = array_column($pages, 'slug');
if ($manager) $allowed = array_merge($allowed, ['home','basic-education','graduate-school','programs','college-departments','administration','student-organizations','student-achievements','athletics-sports','community-activities','faqs','tuition-fees','online-payment-guide','scholarships']);
if (!in_array($page, $allowed, true)) edReply(['error' => 'This page is not available to you.'], 403);
if (!$manager && in_array($page, ['home','administration','college-departments','programs','graduate-school','basic-education','basic-education-departments','student-organizations','student-achievements','athletics-sports','community-activities','faqs','tuition-fees','online-payment-guide','scholarships'], true)) edReply(['error' => 'You cannot edit this page.'], 403);
$userId = (int)$_SESSION['user_id'];
$key = $page . ':' . $route;
$revision = filter_var($_POST['revision'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($revision === false) edReply(['error' => 'Invalid draft revision.'], 400);
try {
    $db = getDB();
    if($action==='record_publication'){
        if(!rbacIsSuper())edReply(['error'=>'Only Super Admin can record a publication.'],403);
        require_once __DIR__.'/../../includes/website-publications.php';
        recordWebsitePublication($db,['page'=>$page,'route'=>$route,'sub'=>$_POST['sub']??'','title'=>$_POST['title']??null]);
        edReply(['success'=>true]);
    }
    $reviewId=(int)($_POST['review_submission']??0);
    if($action==='complete_review'){
        if(!rbacIsSuper())edReply(['error'=>'Only Super Admin can approve changes.'],403);
        $q=$db->prepare("UPDATE rbac_submissions SET status='approved',reviewed_by=?,reviewed_at=NOW(),updated_at=NOW() WHERE submission_id=? AND kind='snapshot' AND status='pending'");$q->execute([$userId,$reviewId]);
        if($q->rowCount()!==1)edReply(['error'=>'This submission is no longer pending.'],409);
        logActivity($userId,'APPROVE','rbac_submissions',$reviewId,'Approved visual-editor changes.');
        logActivity($userId,'PUBLISH','rbac_submissions',$reviewId,'Published approved visual-editor changes.');
        edReply(['success'=>true]);
    }
    if($action==='load'&&$reviewId){
        $review=rbacGetSubmission($reviewId);
        if(!$review||$review['kind']!=='snapshot'||(!rbacIsSuper()&&(int)$review['user_id']!==$userId))edReply(['error'=>'Submission not found.'],403);
        $payload=rbacReadPayload($reviewId);
        if(($payload['page']??'')!==$page||($payload['route']??'')!==$route)edReply(['error'=>'Open the page route stored with this submission.'],409);
        if(!empty($_SERVER['HTTP_X_GNCI_EDITOR_PEER'])) {
            require_once __DIR__.'/../../includes/editor-presence.php';
            editorPresenceRestoreDraft($db,$_SERVER['HTTP_X_GNCI_EDITOR_PEER'],$payload['snapshot']);
        }
        edReply(['success'=>true,'draft'=>['revision'=>0,'snapshot'=>$payload['snapshot']]]);
    }
    if($action==='submit_review'){
        $submitted=editorDraftSubmitForReview($db,$userId,$page,$route,$revision,$reviewId);
        edReply(['success'=>true,'message'=>$submitted['already_submitted']?'This saved version has already been submitted. Check My Submissions for its review status.':'Submitted for Super Admin approval. Visitors still see the published page.']+$submitted);
    }
    if ($action === 'load') {
        $loaded=editorDraftLoad($db,$userId,$key);
        if($loaded && !empty($_SERVER['HTTP_X_GNCI_EDITOR_PEER'])) {
            require_once __DIR__.'/../../includes/editor-presence.php';
            $saved=$loaded['snapshot']['content_version']??['scope'=>'','revision'=>0,'external'=>0];
            $peer=editorPresencePeer($db,$_SERVER['HTTP_X_GNCI_EDITOR_PEER']);
            $latest=editorPresenceSnapshot($db,$peer);
            $current=['scope'=>$peer['scope_key'],'revision'=>$latest['revision'],'external'=>$latest['external']];
            $loaded['outdated']=$saved!==$current;
            $loaded['current_version']=$current;
        }
        edReply(['success'=>true,'draft'=>$loaded]);
    }
    if ($action === 'save' || $action === 'save_recovery') {
        if($action==='save_recovery'&&empty($_SERVER['HTTP_X_GNCI_EDITOR_PEER']))edReply(['error'=>'Open the Content Editor to save a recovery draft.'],403);
        try {$payload=editorDraftRequestPayload($_POST,$_FILES);}
        catch(LengthException $e){edReply(['error'=>$e->getMessage()],413);}
        catch(InvalidArgumentException $e){edReply(['error'=>$e->getMessage()],400);}
        if(!empty($_SERVER['HTTP_X_GNCI_EDITOR_PEER'])) {
            require_once __DIR__.'/../../includes/editor-presence.php';
            $decoded=json_decode($payload,true,64,JSON_THROW_ON_ERROR);
            $decoded['content_version']=editorPresenceDraftVersion($db,$_SERVER['HTTP_X_GNCI_EDITOR_PEER']);
            $payload=json_encode($decoded,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            unset($decoded);
        }
        $next = editorDraftSave($db, $userId, $key, $revision, $payload);
        edReply(['success' => true, 'revision' => $next]);
    }
    if ($action === 'delete') {
        editorDraftDelete($db, $userId, $key, $revision);
        edReply(['success' => true]);
    }
    edReply(['error' => 'Unknown draft action.'], 400);
} catch (JsonException | InvalidArgumentException | LengthException $e) {
    if($action==='record_publication')edReply(['error'=>'Invalid page publication summary.'],400);
    edReply(['error' => 'The draft is invalid or too large. Save fewer media changes at a time.'], 400);
} catch (PDOException $e) {
    if($action==='record_publication'){error_log('Page publication history unavailable.');edReply(['error'=>'The page publication summary could not be recorded.'],503);}
    error_log('Editor draft storage error: ' . $e->getCode());
    edReply(['error' => 'Draft storage is unavailable. Ask your developer to run database/migrate-content-editor-drafts.php.'], 503);
} catch (Throwable $e) {
    edReply(['error' => in_array($e->getCode(),[403,409],true) ? $e->getMessage() : 'Could not save the draft. Please try again.'], in_array($e->getCode(),[403,409],true) ? $e->getCode() : 500);
}
