<?php
require_once __DIR__.'/../admin-functions.php';
require_once __DIR__.'/../../components/alumni-page-content.php';
initSession();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
if (!isLoggedIn() || !hasPermission('manage_page_content')) { http_response_code(403); echo json_encode(['error'=>'You cannot publish alumni voices.']); exit; }
if (($_SERVER['REQUEST_METHOD']??'')!=='POST' || !verifyCSRFToken((string)($_POST['csrf_token']??''))) { http_response_code(403); echo json_encode(['error'=>'Refresh the editor and try again.']); exit; }
$db=getDB(); $uploads=[];
try {
    $raw=$_POST['items']??null; $revision=$_POST['revision']??null;
    if (!is_string($raw) || strlen($raw)>1000000 || !is_string($revision)) throw new InvalidArgumentException('Invalid alumni voices content.');
    $input=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new InvalidArgumentException('Invalid alumni voices.');
    $items=alumniVoicesValidate($input);
    $db->beginTransaction(); $current=alumniVoicesRead(true);
    if (!$current['ready']) throw new InvalidArgumentException('Run database/migrate-alumni-page.php to install alumni voices.');
    if (!hash_equals($current['revision'],$revision)) throw new InvalidArgumentException('Alumni voices changed in another editor. Save your draft and reload before publishing.');
    $allowed=[];
    foreach ($items as &$item) {
        $key='voice_'.$item['id']; $allowed[]=$key;
        if (isset($_FILES[$key])) {
            $stored=storeDepartmentUpload($_FILES[$key],'homepage_image'); $uploads[]=$stored;
            $query=$db->prepare('SELECT file_path FROM media_library WHERE media_id=?'); $query->execute([$stored['media_id']]);
            $item['image']=(string)$query->fetchColumn();
        }
        if ($item['image']==='') throw new InvalidArgumentException('Choose a photo for each alumni voice.');
    }
    unset($item);
    if (array_diff(array_keys($_FILES),$allowed)) throw new InvalidArgumentException('Unexpected alumni photo upload.');
    $items=alumniVoicesValidate($items);
    $json=json_encode($items,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $query=$db->prepare("UPDATE page_sections ps JOIN pages p ON p.page_id=ps.page_id SET ps.content=?,ps.draft_content=NULL,ps.status='published',ps.updated_by=?,ps.updated_at=NOW() WHERE p.slug='alumni' AND ps.section_key='voices_data'");
    $query->execute([$json,(int)$_SESSION['user_id']]);
    logActivity((int)$_SESSION['user_id'],'UPDATE','page_sections',null,'Published alumni voices');
    $db->commit(); $uploads=[];
    echo json_encode(['success'=>true,'items'=>$items,'revision'=>hash('sha256',$json)],JSON_HEX_TAG|JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    foreach ($uploads as $upload) discardStoredDepartmentUpload($upload);
    $expected=$error instanceof InvalidArgumentException || $error instanceof JsonException;
    if (!$expected) error_log('Alumni voices publish: '.$error->getMessage());
    http_response_code($expected?422:500);
    echo json_encode(['error'=>$expected?$error->getMessage():'Could not publish alumni voices. Your edits are available to retry.']);
}
