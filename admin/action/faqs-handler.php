<?php
require_once __DIR__.'/../admin-functions.php';
require_once __DIR__.'/../../includes/faqs.php';
initSession();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
if(!isLoggedIn()||!hasPermission('manage_page_content')){http_response_code(403);echo json_encode(['error'=>'You cannot publish FAQs.']);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!is_string($_POST['csrf_token']??null)||!verifyCSRFToken($_POST['csrf_token'])){http_response_code(403);echo json_encode(['error'=>'Refresh the editor and try again.']);exit;}
try{
    if($_FILES)throw new InvalidArgumentException('FAQs support questions and answers only.');
    $raw=$_POST['content']??null;$revision=$_POST['revision']??null;
    if(!is_string($raw)||strlen($raw)>6000000||!is_string($revision))throw new InvalidArgumentException('The FAQ content is invalid or too large.');
    $input=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
    if(!is_array($input))throw new InvalidArgumentException('Invalid FAQ content.');
    $saved=faqPublish(getDB(),$input,$revision,(int)$_SESSION['user_id']);
    logActivity((int)$_SESSION['user_id'],'UPDATE','faq_page',1,'Published FAQs');
    echo json_encode(['success'=>true,'revision'=>faqPageRevision($saved)]);
}catch(InvalidArgumentException|JsonException $error){http_response_code(422);echo json_encode(['error'=>$error->getMessage()]);}
catch(Throwable $error){error_log('FAQ publish: '.$error->getMessage());http_response_code(500);echo json_encode(['error'=>'Could not publish the FAQs. Your edits are available to retry.']);}
