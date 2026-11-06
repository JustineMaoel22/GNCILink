<?php
// CLI-only, local test login. Never exposed as an HTTP endpoint.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
if(!in_array(DB_HOST,['db','localhost','127.0.0.1'],true))throw new RuntimeException('Local tests only.');
if(($argv[1]??'')==='destroy'){
    session_id($argv[2]);session_start();
    getDB()->prepare('UPDATE content_editor_versions SET lease_owner=NULL,lease_until=0 WHERE lease_owner IN (SELECT peer_key FROM content_editor_presence WHERE session_key=?)')->execute([hash('sha256',session_id())]);
    getDB()->prepare('DELETE FROM content_editor_presence WHERE session_key=?')->execute([hash('sha256',session_id())]);
    $_SESSION=[];session_destroy();exit;
}
$q=getDB()->prepare("SELECT user_id,role,credential_version FROM users WHERE role=? AND status='active' AND must_change_password=0 LIMIT 1");
$q->execute([$argv[1]??'Super Admin']);$user=$q->fetch();
if(!$user)throw new RuntimeException('No local test account for this role.');
session_start();$publish=bin2hex(random_bytes(32));
$_SESSION=['user_id'=>(int)$user['user_id'],'user_role'=>$user['role'],'credential_version'=>(int)$user['credential_version'],'_initiated'=>true,'last_activity'=>time(),
    'publish_confirmations'=>[hash('sha256',$publish)=>['expires'=>time()+600,'version'=>(int)$user['credential_version'],'user'=>(int)$user['user_id']]]];
echo json_encode(['name'=>session_name(),'value'=>session_id(),'publish_token'=>$publish,'user_id'=>(int)$user['user_id']]);session_write_close();
