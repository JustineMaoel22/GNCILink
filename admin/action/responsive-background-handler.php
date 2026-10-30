<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../admin-functions.php';
require_once __DIR__.'/../../includes/responsive-backgrounds.php';
initSession();requireLogin();header('Content-Type: application/json; charset=utf-8');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['error'=>'Use POST.']);exit;}
if(!hasPermission('manage_page_content')){http_response_code(403);echo json_encode(['error'=>'You cannot edit page backgrounds.']);exit;}
if(!verifyCSRFToken((string)($_POST['csrf_token']??''))){http_response_code(403);echo json_encode(['error'=>'Refresh the editor and try again.']);exit;}
$uploads=[];$retired=[];$db=getDB();
try {
    try{$db->query('SELECT page_key FROM responsive_background_settings LIMIT 0');}
    catch(PDOException $e){if($e->getCode()==='42S02')throw new InvalidArgumentException('Device backgrounds need database/migrate-responsive-backgrounds.php to be run on this server.');throw $e;}
    $route=responsiveBackgroundRoute((string)($_POST['route']??''));$raw=(string)($_POST['changes']??'');
    if(strlen($raw)>512000)throw new InvalidArgumentException('Too many background settings.');
    $changes=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
    if(!is_array($changes)||!$changes||count($changes)>200)throw new InvalidArgumentException('Invalid background changes.');
    $normalized=[];
    foreach($changes as $selector=>$devices){
        responsiveBackgroundSelector($selector);if(!is_array($devices)||!$devices)throw new InvalidArgumentException('Invalid device settings.');
        foreach($devices as $device=>$value){
            if(!in_array($device,['desktop','tablet','mobile'],true))throw new InvalidArgumentException('Invalid background device.');
            if($value===null){$normalized[$selector][$device]=null;continue;}
            if(!is_array($value))throw new InvalidArgumentException('Invalid background settings.');
            $setting=responsiveBackgroundNormalize($value);$media=null;
            if(isset($value['upload'])){
                if(!is_string($value['upload'])||!preg_match('/^background_[0-9]{1,4}$/D',$value['upload'])||!isset($_FILES[$value['upload']]))throw new InvalidArgumentException('The background upload is missing.');
                $stored=storeDepartmentUpload($_FILES[$value['upload']],'homepage_image');$uploads[]=$stored;$setting['path']=$stored['path']??$stored['file_path']??'';$media=(int)$stored['media_id'];
                if(!$setting['path']){$q=$db->prepare('SELECT file_path FROM media_library WHERE media_id=?');$q->execute([$media]);$setting['path']=$q->fetchColumn();}
                $setting=responsiveBackgroundNormalize($setting);
            } elseif($setting['path']!==''){$q=$db->prepare('SELECT media_id FROM media_library WHERE file_path=? LIMIT 1');$q->execute([$setting['path']]);$media=$q->fetchColumn()?:null;}
            if($setting['path']==='')throw new InvalidArgumentException('Choose a background image.');
            $normalized[$selector][$device]=[$setting,$media];
        }
    }
    $db->beginTransaction();$pageKey=hash('sha256',$route);
    foreach($normalized as $selector=>$devices)foreach($devices as $device=>$entry){
        $key=hash('sha256',$selector);$q=$db->prepare('SELECT media_id FROM responsive_background_settings WHERE page_key=? AND element_key=? AND device=? FOR UPDATE');$q->execute([$pageKey,$key,$device]);$old=$q->fetchColumn();if($old)$retired[]=(int)$old;
        if($entry===null){$db->prepare('DELETE FROM responsive_background_settings WHERE page_key=? AND element_key=? AND device=?')->execute([$pageKey,$key,$device]);continue;}
        [$setting,$media]=$entry;
        $db->prepare('INSERT INTO responsive_background_settings(page_key,element_key,device,selector,settings,media_id,updated_by) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE settings=VALUES(settings),media_id=VALUES(media_id),updated_by=VALUES(updated_by)')->execute([$pageKey,$key,$device,$selector,json_encode($setting,JSON_THROW_ON_ERROR),$media,(int)$_SESSION['user_id']]);
    }
    $response=json_encode(['success'=>true,'settings'=>responsiveBackgroundLoad($db,$route)],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
    logActivity((int)$_SESSION['user_id'],'UPDATE','responsive_background_settings',null,'Published device background changes for '.$route);$db->commit();
    $uploads=[]; // Committed media must never be discarded by cleanup failures.
    foreach(array_unique($retired) as $media){try{deleteMediaIfUnreferenced($media);}catch(Throwable $cleanupError){error_log('Responsive background media cleanup failed.');}}
    echo $response;
} catch(Throwable $e){
    if($db->inTransaction())$db->rollBack();foreach($uploads as $upload)discardStoredDepartmentUpload($upload);
    $expected=$e instanceof InvalidArgumentException||$e instanceof JsonException;if(!$expected)error_log('Responsive backgrounds: '.$e->getMessage());
    http_response_code(422);echo json_encode(['error'=>$expected?$e->getMessage():'Could not save device background settings. Please try again.']);
}
