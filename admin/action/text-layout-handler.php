<?php
require_once __DIR__ . '/../admin-functions.php';
require_once __DIR__ . '/../../includes/text-layout.php';
initSession();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') { http_response_code(405);echo json_encode(['error'=>'Use POST.']);exit; }
if (!isLoggedIn() || !hasPermission('manage_page_content') || !verifyCSRFToken($_POST['csrf_token']??'')) { http_response_code(403);echo json_encode(['error'=>'You cannot publish text placement. Refresh the editor and sign in.']);exit; }
try {
    $raw=$_POST['changes']??'';
    if (!is_string($raw) || strlen($raw)>200000) throw new InvalidArgumentException('Invalid layout request.');
    $changes=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
    if (!is_array($changes)) throw new InvalidArgumentException('Invalid layout settings.');
    textLayoutSave(getDB(),(string)($_POST['route']??''),$changes,(int)$_SESSION['user_id']);
    logActivity((int)$_SESSION['user_id'],'UPDATE','text_layout_settings',null,'Updated public text placement');
    echo json_encode(['success'=>true]);
} catch (Throwable $e) {
    $expected=$e instanceof InvalidArgumentException || $e instanceof JsonException;
    if (!$expected) error_log('Text layout: '.$e->getMessage());
    http_response_code(422);echo json_encode(['error'=>$expected?$e->getMessage():'Could not save text placement. Check that database/migrate-text-layout.php has been installed.']);
}
