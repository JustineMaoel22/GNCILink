<?php
require_once __DIR__.'/../admin-functions.php';
require_once __DIR__.'/../../includes/community-activities.php';
initSession();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
if(!isLoggedIn()||!hasPermission('manage_page_content')){http_response_code(403);echo json_encode(['error'=>'You cannot manage community activities.']);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!verifyCSRFToken($_POST['csrf_token']??'')){http_response_code(403);echo json_encode(['error'=>'Refresh the editor and try again.']);exit;}
try{
    $raw=$_POST['content']??'';
    if(!is_string($raw)||strlen($raw)>2000000)throw new InvalidArgumentException('The page content is too large.');
    $input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if(!is_array($input))throw new InvalidArgumentException('Invalid page content.');
    $saved=communityActivitySave(getDB(),$input,$_FILES,(string)($_POST['revision']??''));
    logActivity((int)$_SESSION['user_id'],'UPDATE','community_activity_page',1,'Published community activities');
    echo json_encode(['success'=>true,'revision'=>communityActivityRevision($saved)]);
}catch(InvalidArgumentException|JsonException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);}
catch(Throwable $e){error_log('Community activities publish: '.$e->getMessage());http_response_code(500);echo json_encode(['error'=>'Could not publish the page. Your edits are available to retry. Check that community activity storage is installed.']);}
