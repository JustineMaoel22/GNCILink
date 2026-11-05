<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config/config.php';
if(($argv[1]??'')==='destroy'){
    session_id($argv[2]); session_start(); $_SESSION=[]; session_destroy(); exit;
}
$q=getDB()->prepare("SELECT user_id,role,credential_version FROM users WHERE role=? AND status='active' AND must_change_password=0 LIMIT 1");
$q->execute([$argv[1]??'Super Admin']);$user=$q->fetch();
if(!$user)throw new RuntimeException('No suitable local account for browser checks.');
session_start();
$_SESSION=['user_id'=>(int)$user['user_id'],'user_role'=>$user['role'],'credential_version'=>(int)$user['credential_version'],'_initiated'=>true,'last_activity'=>time()];
echo json_encode(['name'=>session_name(),'value'=>session_id()]);
session_write_close();
