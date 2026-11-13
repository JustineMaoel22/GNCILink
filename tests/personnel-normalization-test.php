<?php
/** Synthetic data in an isolated, randomly named local schema only. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../database/personnel-migration.php';
if(!in_array(DB_HOST,['db','localhost','127.0.0.1'],true))throw new RuntimeException('Local development only.');
$db=getDB();$source=(string)$db->query('SELECT DATABASE()')->fetchColumn();
$test='gnci_personnel_test_'.bin2hex(random_bytes(6));$created=false;$checks=0;$fixture=null;
function identityCheck(bool $ok,string $message):void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
try {
    $schema=[];
    foreach($db->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $table){$quoted='`'.str_replace('`','``',$table[0]).'`';$schema[]=$db->query("SHOW CREATE TABLE $quoted")->fetch(PDO::FETCH_NUM)[1];}
    $db->exec("CREATE DATABASE `$test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$created=true;$db->exec("USE `$test`");
    $db->exec('SET FOREIGN_KEY_CHECKS=0');try{foreach($schema as $ddl)$db->exec($ddl);}finally{$db->exec('SET FOREIGN_KEY_CHECKS=1');}
    require_once __DIR__.'/../admin/admin-functions.php';
    $_SESSION=['user_role'=>'Super Admin'];
    $db->exec("INSERT INTO users(first_name,last_name,email,password,role,status) VALUES('Personnel','Test','personnel@example.invalid','unused','Super Admin','active')");$_SESSION['user_id']=(int)$db->lastInsertId();
    $db->exec("INSERT INTO admin_sections(section_key,title,layout) VALUES('identity-people','People','people'),('identity-offices','Offices','offices')");
    $createdPerson=saveAdministrationPerson(0,'identity-people','Same Name','Teacher',null,false);
    identityCheck($createdPerson>0,'Person save returns the persisted ID for publication reconciliation.');
    saveAdministrationPerson(0,'identity-people','Same Name','Another teacher',null,false);
    $createdOffice=saveAdministrationOffice(0,'identity-offices','Test Office','Same Name','Head','','','','','',null,false);
    identityCheck($createdOffice>0,'Office save returns the persisted ID for publication reconciliation.');
    $db->exec("INSERT INTO departments(dept_name,slug,dean_name) VALUES('Identity Department','identity-dept','Other Person')");
    saveHomepagePresident('Same Name','President','School','','','','',null,false);
    identityCheck(!personnelEnabled($db,true),'Legacy schema starts disabled.');
    personnelMigrationApply($db);
    identityCheck(personnelEnabled($db),'Migration enabled.');
    identityCheck((int)$db->query('SELECT COUNT(*) FROM personnel')->fetchColumn()===5,'Every placement gets its own identity; homonyms never auto-merge.');
    $people=$db->query('SELECT person_id,personnel_id FROM admin_people ORDER BY person_id')->fetchAll(PDO::FETCH_ASSOC);
    $first=(int)$people[0]['person_id'];$second=(int)$people[1]['person_id'];$identity=(int)$people[0]['personnel_id'];
    $office=(int)$db->query('SELECT office_id FROM admin_offices')->fetchColumn();$dept=(int)$db->query('SELECT dept_id FROM departments')->fetchColumn();
    identityCheck($identity!==(int)$people[1]['personnel_id'],'Same-name people remain distinct.');
    $count=(int)$db->query('SELECT COUNT(*) FROM personnel')->fetchColumn();personnelMigrationApply($db);
    identityCheck((int)$db->query('SELECT COUNT(*) FROM personnel')->fetchColumn()===$count,'Apply is idempotent.');
    personnelLink($db,'office:'.$office,$identity);
    saveAdministrationPerson($first,'identity-people','Renamed Person','Teacher',null,false);
    identityCheck($db->query('SELECT head_name FROM admin_offices')->fetchColumn()==='Renamed Person','Shared rename updates legacy compatibility readers.');
    identityCheck($db->query('SELECT full_name FROM personnel WHERE personnel_id='.$identity)->fetchColumn()==='Renamed Person','Canonical shared name updated.');
    identityCheck($db->query('SELECT name FROM admin_people WHERE person_id='.$second)->fetchColumn()==='Same Name','Homonym remains unchanged.');
    $sections=fetchAdministrationSections(true);$found=false;foreach($sections as $section)foreach($section['offices'] as $row)if($row['id']===$office)$found=$row['name']==='Renamed Person'&&(int)$row['personnel_id']===$identity;
    identityCheck($found,'Public/editor readers hydrate canonical identities.');
    try{saveAdministrationPerson($first,'identity-people','Must Roll Back',str_repeat('x',501),null,false);throw new LogicException('Invalid role accepted.');}catch(InvalidArgumentException|Exception $e){if($e instanceof LogicException)throw $e;}
    identityCheck($db->query('SELECT full_name FROM personnel WHERE personnel_id='.$identity)->fetchColumn()==='Renamed Person','Failed save rolls back canonical name.');
    identityCheck($db->query('SELECT head_name FROM admin_offices')->fetchColumn()==='Renamed Person','Failed save rolls back dependent names.');
    identityCheck(personnelProfiles($db)['person:'.$first]['full_name']==='Renamed Person','Rolled-back reads are not cached as current.');
    $db->beginTransaction();personnelLink($db,'person:'.$second,$identity);$db->rollBack();$GLOBALS['personnel_revision']=($GLOBALS['personnel_revision']??0)+1;
    identityCheck((int)$db->query('SELECT personnel_id FROM admin_people WHERE person_id='.$second)->fetchColumn()!==$identity,'Nested operation preserves outer rollback.');
    personnelLink($db,'office:'.$office,0);
    identityCheck((int)$db->query('SELECT personnel_id FROM admin_offices')->fetchColumn()!==$identity,'Explicit separation creates an independent identity.');
    $_SESSION['user_role']='Content Editor';try{personnelLink($db,'office:'.$office,$identity);throw new LogicException('Lower role linked identities.');}catch(RuntimeException $expected){$checks++;}$_SESSION['user_role']='Super Admin';
    try{personnelLink($db,'office:'.$office,2147483647);throw new LogicException('Unknown identity accepted.');}catch(InvalidArgumentException $expected){$checks++;}
    try{$db->exec('DELETE FROM personnel WHERE personnel_id='.$identity);throw new LogicException('Referenced identity deleted.');}catch(PDOException $expected){$checks++;}
    $dean=saveDepartmentDean($dept,'Updated Dean','Dean','','','','','',null,false);
    identityCheck(!empty($dean['personnel_id'])&&$dean['dean_name']==='Updated Dean','Department save returns normalized identity.');
    $president=saveHomepagePresident('Updated President','President','School','','','','',null,false);
    identityCheck(!empty($president['personnel_id'])&&$president['name']==='Updated President','President save returns normalized identity.');
    saveAdministrationPerson(0,'identity-people','New Person','Teacher',null,false);
    identityCheck((int)$db->query("SELECT COUNT(*) FROM admin_people WHERE name='New Person' AND personnel_id IS NOT NULL")->fetchColumn()===1,'New profiles receive a reference atomically.');
    $fixture=__DIR__.'/../uploads/personnel-normalization-'.bin2hex(random_bytes(12)).'.png';
    copy(__DIR__.'/../assets/images/test-image.png',$fixture);$webPath='/uploads/'.basename($fixture);
    $q=$db->prepare('INSERT INTO media_library(file_name,file_type,file_size,file_path,created_by) VALUES(?,?,?,?,?)');$q->execute([basename($fixture),'png',filesize($fixture),$webPath,$_SESSION['user_id']]);$media=(int)$db->lastInsertId();
    $db->prepare('UPDATE admin_people SET media_id=?,image_path=? WHERE person_id=?')->execute([$media,$webPath,$first]);
    $db->beginTransaction();saveAdministrationPerson($first,'identity-people','Renamed Person','Teacher',null,true);
    identityCheck(is_file($fixture),'Removing a portrait does not delete its file before the outer commit.');$db->rollBack();
    identityCheck((int)$db->query('SELECT media_id FROM admin_people WHERE person_id='.$first)->fetchColumn()===$media&&is_file($fixture),'Outer rollback retains the portrait and media reference.');
    // Face-policy fixture: no real face service or persisted biometric data.
    $db->exec("UPDATE admin_people SET image_path='/assets/images/test-image.png'");$db->exec("UPDATE admin_offices SET image_path='/assets/images/test-image.png'");
    $compare=static fn($path,$profiles)=>['complete'=>true,'faces'=>1,'scores'=>array_fill_keys(array_column($profiles,'profile_key'),.8)];
    $image=personPhotoPath('/assets/images/test-image.png');
    $face=personPhotoCheck($image,'Renamed Person','person:'.$first,$compare);
    identityCheck(!$face['ok'],'Same-name independent identities can still be face matches.');
    personnelLink($db,'office:'.$office,$identity);$face=personPhotoCheck($image,'Renamed Person','person:'.$first,$compare);
    identityCheck(!in_array('office:'.$office,array_column($face['matches'],'key'),true),'Other sections are excluded regardless of shared identity.');
    $db->exec("UPDATE admin_offices SET section_key='identity-people'");
    $face=personPhotoCheck($image,'Renamed Person','person:'.$first,$compare);
    identityCheck(in_array('office:'.$office,array_column($face['matches'],'key'),true),'Shared identity cannot bypass a duplicate within the same page.');
    $count=(int)$db->query('SELECT COUNT(*) FROM personnel')->fetchColumn();personnelMigrationRollback($db);
    identityCheck(!personnelEnabled($db),'Rollback disables normalized readers.');
    identityCheck($db->query('SELECT head_name FROM admin_offices')->fetchColumn()==='Renamed Person','Rollback preserves the current shared name.');
    personnelMigrationApply($db);identityCheck((int)$db->query('SELECT COUNT(*) FROM personnel')->fetchColumn()===$count,'Reapply retains reviewed identities without duplication.');
    echo "Personnel normalization: $checks checks passed in an isolated schema.\n";
} finally {
    if($fixture!==null&&is_file($fixture))unlink($fixture);
    if($db->inTransaction())$db->rollBack();$db->exec('USE `'.str_replace('`','``',$source).'`');
    if($created&&preg_match('/\Agnci_personnel_test_[a-f0-9]{12}\z/D',$test)&&$test!==$source)$db->exec("DROP DATABASE `$test`");
    personnelEnabled($db,true);
}
