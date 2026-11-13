<?php
// Connection-local shadow tables: no persistent personnel, drafts or media change.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/content-editor-drafts.php';
require_once __DIR__.'/../includes/content-submissions.php';
require_once __DIR__.'/../includes/publish-confirmation.php';
$db=getDB();$checks=0;
function admCheck(bool $ok,string $message):void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
$db->exec('CREATE TEMPORARY TABLE content_editor_drafts(draft_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED,page_key CHAR(64),revision INT UNSIGNED,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY owner_page(user_id,page_key)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE content_editor_draft_chunks(draft_id BIGINT UNSIGNED,chunk_no INT UNSIGNED,payload MEDIUMBLOB,PRIMARY KEY(draft_id,chunk_no)) ENGINE=InnoDB');
$counts=[];foreach(['admin_people','admin_offices','media_library'] as $table)$counts[$table]=(int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
$route='/pages/about/administration.php';$key='administration:'.$route;
$photo=base64_encode(file_get_contents(__DIR__.'/../assets/images/test-image.png'));
$sections=['board-of-trustees','corporate-officers','board-of-administration','heads-of-student-services'];
$snapshot=['version'=>1,'state'=>['edits'=>['sec'=>[],'person'=>[],'office'=>[],'order'=>[]],'additions'=>[],'nextId'=>-5],'preview'=>[],'files'=>[['name'=>'portrait.png','type'=>'image/png','data'=>$photo]],'urls'=>[]];
foreach($sections as $index=>$section){$id=-1-$index;$kind=$index===3?'office':'person';$snapshot['state']['additions'][$id]=['kind'=>$kind,'section'=>$section,'item'=>['id'=>$id,'name'=>'Draft Person '.$index,'role'=>'Head','image'=>'']];$snapshot['state']['edits'][$kind][$id]=['file'=>['$draftFile'=>0]];}
foreach([1=>'Super Admin',2=>'Content Editor'] as $owner=>$role){
    $_SESSION=['user_id'=>$owner,'user_role'=>$role,'credential_version'=>1];
    admCheck(!publishNeedsConfirmation('/admin/action/editor-draft-handler.php',['action'=>'save']),'Private save asks for publication password.');
    $revision=editorDraftSave($db,$owner,$key,0,json_encode($snapshot,JSON_THROW_ON_ERROR));
    $loaded=editorDraftLoad($db,$owner,$key);
    admCheck($revision===1&&count($loaded['snapshot']['state']['additions'])===4,'Database restore lost personnel or sections.');
    admCheck(base64_decode($loaded['snapshot']['files'][0]['data'],true)===base64_decode($photo,true),'Database restore lost photo bytes.');
    admCheck(editorDraftLoad($db,$owner+100,$key)===null,'A different user can read the draft.');
    try{editorDraftSave($db,$owner,$key,0,json_encode($snapshot));throw new LogicException('Stale draft save accepted.');}catch(RuntimeException $e){admCheck($e->getCode()===409,'Stale revision did not return a conflict.');}
    foreach(['admin_save_person','admin_save_office'] as $action){admCheck(publishNeedsConfirmation('/admin/action/page-content-handler.php',['action'=>$action]),'Live save bypasses password verification.');admCheck(rbacShouldQueue('/admin/action/page-content-handler.php',['action'=>$action]),'Content Editor live save bypasses approval.');}
    editorDraftDelete($db,$owner,$key,0);admCheck(editorDraftLoad($db,$owner,$key)!==null,'Stale deletion removed a newer draft.');
}
foreach($counts as $table=>$count)admCheck((int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn()===$count,'Draft save changed live '.$table.'.');
echo "Administration drafts: $checks checks passed. Personnel and photo bytes restored from database; no persistent content changed.\n";
