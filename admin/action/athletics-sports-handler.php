<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../admin-functions.php';
require_once __DIR__.'/../../components/athletics-sports-manager.php';
initSession();requireLogin();requirePermission('manage_page_content');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD']!=='POST'||!verifyCSRFToken((string)($_POST['csrf_token']??''))) {
    http_response_code(403);echo json_encode(['success'=>false,'error'=>'Refresh the editor and try again.']);exit;
}
try {
    $input=json_decode((string)($_POST['content']??''),true);
    if(!is_array($input))throw new InvalidArgumentException('Invalid sports content.');
    $content=saveAthleticsSports($input,$_FILES,(string)($_POST['revision']??''));
    echo json_encode(['success'=>true,'content'=>$content,'revision'=>athleticsSportsRevision($content)]);
} catch (InvalidArgumentException $error) {
    http_response_code(422);echo json_encode(['success'=>false,'error'=>$error->getMessage()]);
} catch (Throwable $error) {
    error_log('Sports publish: '.$error->getMessage());http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Could not publish the sports programs. Your edits are still available to retry.']);
}
