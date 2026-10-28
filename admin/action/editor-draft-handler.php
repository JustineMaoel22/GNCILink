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
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) edReply(['error' => 'Your session has expired. Reload the editor.'], 403);
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
        edReply(['success'=>true,'draft'=>['revision'=>0,'snapshot'=>$payload['snapshot']]]);
    }
    if($action==='submit_review'){
        $draft=editorDraftLoad($db,$userId,$key);
        if(!$draft||(int)$draft['revision']!==$revision)edReply(['error'=>'Save the draft before submitting it.'],409);
        $resubmit=0;
        if($reviewId){$old=rbacGetSubmission($reviewId);if($old&&(int)$old['user_id']===$userId&&in_array($old['status'],['draft','rejected','failed'],true))$resubmit=$reviewId;}
        $id=rbacSaveSubmission('snapshot','/admin/content-edit.php','Page changes: '.$page,['page'=>$page,'route'=>$route,'snapshot'=>$draft['snapshot']],0,$resubmit);
        edReply(['success'=>true,'submission_id'=>$id,'message'=>'Submitted for Super Admin approval. Visitors still see the published page.']);
    }
    if ($action === 'load') edReply(['success' => true, 'draft' => editorDraftLoad($db, $userId, $key)]);
    if ($action === 'save') {
        $payload = $_POST['snapshot'] ?? null;
        if (!is_string($payload)) edReply(['error' => 'The draft was not received. Check the hosting request-size limit.'], 400);
        $next = editorDraftSave($db, $userId, $key, $revision, $payload);
        edReply(['success' => true, 'revision' => $next]);
    }
    if ($action === 'delete') {
        editorDraftDelete($db, $userId, $key, $revision);
        edReply(['success' => true]);
    }
    edReply(['error' => 'Unknown draft action.'], 400);
} catch (JsonException | InvalidArgumentException | LengthException $e) {
    edReply(['error' => 'The draft is invalid or too large. Save fewer media changes at a time.'], 400);
} catch (PDOException $e) {
    error_log('Editor draft storage error: ' . $e->getCode());
    edReply(['error' => 'Draft storage is unavailable. Ask your developer to run database/migrate-content-editor-drafts.php.'], 503);
} catch (Throwable $e) {
    edReply(['error' => $e->getCode() === 409 ? $e->getMessage() : 'Could not save the draft. Please try again.'], $e->getCode() === 409 ? 409 : 500);
}
