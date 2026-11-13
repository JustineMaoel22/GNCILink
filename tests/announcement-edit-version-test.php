<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/announcement-edit-version.php';
$db=getDB();$checks=0;
function editCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
// All writes, including activity records and associations, stay connection-local.
foreach(['announcements','activity_logs','announcement_categories','announcement_images','announcement_departments','announcement_programs'] as $table){
    $ddl=$db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
    $ddl=preg_replace('/^CREATE TABLE/','CREATE TEMPORARY TABLE',$ddl);
    $ddl=preg_replace('/^\s*CONSTRAINT[^\n]*\n?/m','',$ddl);
    $db->exec(preg_replace('/,\s*\)/',"\n)",$ddl));
}
$_SESSION=['user_id'=>1,'user_role'=>'Super Admin'];
$db->exec("INSERT INTO announcements(user_id,title,slug,content,status,updated_at) VALUES(1,'Version test','version-test','<p>Original</p>','draft','2026-11-13 10:00:00')");
$id=(int)$db->lastInsertId();
function editSnapshot(PDO $db,int $id):string{
    $record=getAnnouncementById($id);
    return announcementEditVersion($record,getAnnouncementImages($id),getAnnouncementAudience($db,$id,$record['dept_id'],$record['program']));
}
$db->beginTransaction();$initial=editSnapshot($db,$id);$db->commit();
$db->beginTransaction();
announcementCheckEditVersion($db,$id,$initial);
editCheck(updateAnnouncement($id,['title'=>'Version test','content'=>'<p>First tab message</p>','category_ids'=>[],'status'=>'draft']),'Fresh edit failed.');
// Same-second writes must be protected even when the timestamp does not change.
$db->prepare('UPDATE announcements SET updated_at=? WHERE announcement_id=?')->execute(['2026-11-13 10:00:00',$id]);
$db->commit();
$db->beginTransaction();
try{announcementCheckEditVersion($db,$id,$initial);throw new LogicException('Stale edit accepted.');}
catch(AnnouncementEditConflict $error){editCheck($error->getCode()===409,'Stale edit must be a conflict.');}
finally{$db->rollBack();}
editCheck(getAnnouncementById($id)['content']==='<p>First tab message</p>','Stale edit lost the first message.');
$db->beginTransaction();$fresh=editSnapshot($db,$id);announcementCheckEditVersion($db,$id,$fresh);
editCheck(updateAnnouncement($id,['title'=>'Reconciled title','content'=>'<p>First tab message</p>','category_ids'=>[],'status'=>'draft']),'Reopened current version cannot save.');$db->commit();
foreach([null,'',str_repeat('0',64)] as $invalid){
    $db->beginTransaction();
    try{announcementCheckEditVersion($db,$id,$invalid);throw new LogicException('Missing/invalid version accepted.');}
    catch(AnnouncementEditConflict $error){editCheck($error->getCode()===409,'Invalid versions require reopening.');}
    finally{$db->rollBack();}
}
// Gallery and audience edits can leave all the parent row's text unchanged.
foreach(['gallery','department','program','category'] as $part){
    $db->beginTransaction();$before=editSnapshot($db,$id);$db->commit();
    if($part==='gallery')$db->prepare('INSERT INTO announcement_images(announcement_id,media_id,display_order) VALUES(?,1,0)')->execute([$id]);
    if($part==='department')$db->prepare('INSERT INTO announcement_departments(announcement_id,dept_id) VALUES(?,1)')->execute([$id]);
    if($part==='program')$db->prepare('INSERT INTO announcement_programs(announcement_id,program_code) VALUES(?,?)')->execute([$id,'TEST']);
    if($part==='category'){
        $categoryId=$db->query('SELECT category_id FROM categories ORDER BY category_id LIMIT 1')->fetchColumn();
        if(!$categoryId)continue;
        $db->prepare('INSERT INTO announcement_categories(announcement_id,category_id) VALUES(?,?)')->execute([$id,$categoryId]);
    }
    $db->beginTransaction();
    try{announcementCheckEditVersion($db,$id,$before);throw new LogicException('Association conflict accepted: '.$part);}
    catch(AnnouncementEditConflict $error){editCheck($error->getCode()===409,'Association changes need conflict protection.');}
    finally{$db->rollBack();}
}
$db->beginTransaction();$current=editSnapshot($db,$id);announcementCheckEditVersion($db,$id,$current);$db->commit();$checks++;
echo "$checks announcement edit conflict checks passed. No persistent content changed.\n";
