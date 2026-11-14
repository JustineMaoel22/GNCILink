<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../admin-functions.php';
require_once __DIR__.'/../../includes/content-submissions.php';
require_once __DIR__.'/../../includes/editor-presence.php';
initSession();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
function epReply(array $data,int $status=200): void { http_response_code($status);echo json_encode($data,JSON_HEX_TAG|JSON_UNESCAPED_UNICODE);exit; }
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') epReply(['error'=>'Use POST.'],405);
if (!isLoggedIn()) epReply(['error'=>'Your session has ended. Sign in again.'],401);
if (!hasPermission('manage_page_content')&&!hasPermission('edit_page_content')) epReply(['error'=>'You cannot access this editor.'],403);
if (!verifyCSRFToken(is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:'')) epReply(['error'=>'Your session has expired. Reload the editor.'],403);
try {
    $db=getDB();
    if (!editorPresenceReady($db)) epReply(['error'=>'Active users need the editor-presence database migration.','unavailable'=>true],503);
    $action=$_POST['action']??'';
    if (!in_array($action,['join','ping','leave','release','check','acquire','takeover','resume_draft','reconcile_draft'],true)) throw new InvalidArgumentException('Unknown presence action.');
    $token=is_string($_POST['token']??null)?$_POST['token']:'';
    if ($action==='join') {
        if (!is_string($_POST['page']??null)||!is_string($_POST['route']??null)) throw new InvalidArgumentException('Invalid page.');
        // Cleanup runs at entry, using indexed expiry; heartbeat itself never scans old sessions.
        $db->prepare('DELETE FROM content_editor_presence WHERE seen_at<?')->execute([time()-300]);
        $q=$db->prepare('SELECT COUNT(*) FROM content_editor_presence WHERE session_key=? AND seen_at>?');$q->execute([editorPresenceSession(),time()-EDITOR_PRESENCE_TTL]);
        if ((int)$q->fetchColumn()>=20) epReply(['error'=>'Too many editor tabs. Close an unused tab and try again.'],429);
        epReply(['success'=>true]+editorPresenceJoin($db,$_POST['page'],$_POST['route']));
    }
    $peer=editorPresencePeer($db,$token);
    editorPresenceLock($db);
    try {
    if(in_array($action,['acquire','takeover'],true)) {
        if($action==='takeover'&&($_POST['confirmed']??'')!=='1')throw new InvalidArgumentException('Confirm takeover first.');
        editorPresenceAcquire($db,$peer,$action==='takeover');
    }
    $draftRevision=null;
    if(in_array($action,['resume_draft','reconcile_draft'],true)) {
        editorPresenceRequireReservation($db,$peer);
        require_once __DIR__.'/../../includes/content-editor-drafts.php';
        $identity=editorPresencePage((string)($_POST['page']??''),(string)($_POST['route']??''));
        if($identity['page_key']!==$peer['page_key'])throw new InvalidArgumentException('Wrong draft page.');
        $draft=editorDraftLoad($db,(int)$_SESSION['user_id'],$_POST['page'].':'.$_POST['route']);
        if(!$draft||(int)$draft['revision']!==(int)($_POST['revision']??-1))throw new RuntimeException('The draft changed in another tab. Reopen it.',409);
        if($action==='reconcile_draft') {
            if(($_POST['confirmed']??'')!=='1')throw new InvalidArgumentException('Review the differences and confirm reconciliation first.');
            $version=editorPresenceVersion($db,$peer['scope_key']);$external=editorPresenceVersion($db,'external');
            if((int)($_POST['current_revision']??-1)!==(int)$version['revision']||(int)($_POST['current_external']??-1)!==(int)$external['revision'])throw new RuntimeException('The published page changed during review. Reopen and review the latest differences.',409);
            $draft['snapshot']['reconciled_from']=$draft['snapshot']['content_version']??null;
            $draft['snapshot']['content_version']=['scope'=>$peer['scope_key'],'revision'=>(int)$version['revision'],'external'=>(int)$external['revision']];
            $draftRevision=editorDraftSave($db,(int)$_SESSION['user_id'],$_POST['page'].':'.$_POST['route'],(int)$draft['revision'],json_encode($draft['snapshot'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        }
        editorPresenceRestoreDraft($db,$token,$draft['snapshot']);$peer=editorPresencePeer($db,$token);
    }
    if ($action==='leave') {
        editorPresenceRelease($db,$peer);
        $db->prepare('DELETE FROM content_editor_presence WHERE peer_key=?')->execute([$peer['peer_key']]);
        epReply(['success'=>true]);
    }
    if ($action==='release') { editorPresenceRelease($db,$peer);epReply(['success'=>true]); }
    if ($action==='check') {
        editorPresenceLock($db);
        try { editorPresenceRequireReservation($db,$peer);editorPresenceCheck($db,$peer); } finally { editorPresenceUnlock($db); }
    }
    // A heartbeat may renew an unexpired reservation, but never resurrect a lost one.
    $db->prepare('UPDATE content_editor_versions SET lease_until=? WHERE lease_owner=? AND lease_until>?')->execute([time()+EDITOR_PRESENCE_TTL,$peer['peer_key'],time()]);
    $owner=editorPresenceReservation($db,$peer);
    $activity=$owner&&$owner['owned']?'editing':'viewing';
    $db->prepare('UPDATE content_editor_presence SET activity=?,seen_at=? WHERE peer_key=?')->execute([$activity,time(),$peer['peer_key']]);
    $snapshot=editorPresenceSnapshot($db,$peer);if($draftRevision!==null)$snapshot['draft_revision']=$draftRevision;
    } finally { editorPresenceUnlock($db); }
    epReply(['success'=>true]+$snapshot);
} catch (InvalidArgumentException $e) { epReply(['error'=>$e->getMessage()],400); }
catch (Throwable $e) {
    $status=in_array($e->getCode(),[403,409,503],true)?$e->getCode():503;
    if ($status===503) error_log('Editor presence unavailable: '.get_class($e));
    epReply(['error'=>$status===503?'Active users are temporarily unavailable. Your changes remain in the editor.':$e->getMessage(),'conflict'=>$status===409],$status);
}
