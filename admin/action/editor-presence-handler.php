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
    if (!in_array($action,['join','ping','leave','release','check'],true)) throw new InvalidArgumentException('Unknown presence action.');
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
    if ($action==='leave') {
        editorPresenceRelease($db,$peer);
        $db->prepare('DELETE FROM content_editor_presence WHERE peer_key=?')->execute([$peer['peer_key']]);
        epReply(['success'=>true]);
    }
    if ($action==='release') { editorPresenceRelease($db,$peer);epReply(['success'=>true]); }
    if ($action==='check') {
        editorPresenceLock($db);
        try { editorPresenceCheck($db,$peer); } finally { editorPresenceUnlock($db); }
    }
    $activity=($_POST['activity']??'')==='editing'?'editing':'viewing';
    $db->prepare('UPDATE content_editor_presence SET activity=?,seen_at=? WHERE peer_key=?')->execute([$activity,time(),$peer['peer_key']]);
    epReply(['success'=>true]+editorPresenceSnapshot($db,$peer));
} catch (InvalidArgumentException $e) { epReply(['error'=>$e->getMessage()],400); }
catch (Throwable $e) {
    $status=in_array($e->getCode(),[403,409,503],true)?$e->getCode():503;
    if ($status===503) error_log('Editor presence unavailable: '.get_class($e));
    epReply(['error'=>$status===503?'Active users are temporarily unavailable. Your changes remain in the editor.':$e->getMessage(),'conflict'=>$status===409],$status);
}
