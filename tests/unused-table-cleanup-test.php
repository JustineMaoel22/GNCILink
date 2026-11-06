<?php
/** Synthetic fixtures only; never drop tables in the application's original schema. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../database/unused-table-cleanup.php';
require_once __DIR__.'/../database/content-list-migration.php';
if(!in_array(DB_HOST,['db','localhost','127.0.0.1'],true))throw new RuntimeException('Local tests only.');
$db=getDB();$source=(string)$db->query('SELECT DATABASE()')->fetchColumn();$test='gnci_unused_test_'.bin2hex(random_bytes(6));$created=false;$checks=0;$archive=tempnam(sys_get_temp_dir(),'unused-table-test-');unlink($archive);
function unusedCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
try{
    $schema=[];foreach($db->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $row){$name='`'.str_replace('`','``',$row[0]).'`';$schema[]=$db->query('SHOW CREATE TABLE '.$name)->fetch(PDO::FETCH_NUM)[1];}
    $db->exec("CREATE DATABASE `$test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$created=true;$db->exec("USE `$test`");$db->exec('SET FOREIGN_KEY_CHECKS=0');try{foreach($schema as $ddl)$db->exec($ddl);}finally{$db->exec('SET FOREIGN_KEY_CHECKS=1');}
    // The test also runs after the local cleanup, when these definitions are no longer in source.
    if(!unusedTableExists($db,'office_services'))$db->exec('CREATE TABLE office_services(service_id INT AUTO_INCREMENT PRIMARY KEY,office_id INT NOT NULL,service_name VARCHAR(255) NOT NULL,FOREIGN KEY(office_id) REFERENCES offices(office_id)) ENGINE=InnoDB');
    if(!unusedTableExists($db,'program_sections'))$db->exec('CREATE TABLE program_sections(section_id INT AUTO_INCREMENT PRIMARY KEY,program_id INT NOT NULL,content JSON,FOREIGN KEY(program_id) REFERENCES programs(program_id)) ENGINE=InnoDB');
    if(!unusedTableExists($db,'community_extension_page'))$db->exec('CREATE TABLE community_extension_page(page_id TINYINT UNSIGNED PRIMARY KEY,content MEDIUMTEXT NOT NULL,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB');
    require_once __DIR__.'/../admin/admin-functions.php';require_once __DIR__.'/../includes/community-activities.php';require_once __DIR__.'/../chatbot/inc/retriever.php';
    $_SESSION=['user_role'=>'Super Admin'];$db->exec("INSERT INTO users(first_name,last_name,email,password,role,status) VALUES('Cleanup','Test','cleanup@example.invalid','unused','Super Admin','active')");$_SESSION['user_id']=(int)$db->lastInsertId();
    $db->exec("INSERT INTO admin_sections(section_key,title,layout) VALUES('cleanup-offices','Test offices','offices')");
    saveAdministrationOffice(0,'cleanup-offices','Test Records Office','Test Head','Head','','','','',"Records\nGuidance",null,false);
    $office=(int)$db->query('SELECT office_id FROM admin_offices')->fetchColumn();contentListMigrationApply($db);
    $db->exec("INSERT INTO programs(program_name,program_code,slug) VALUES('Test Program','CLN','cleanup-test')");
    $db->prepare('INSERT INTO community_extension_page(page_id,content) VALUES(1,?)')->execute([json_encode(['legacy'=>'Recoverable legacy content','unicode'=>'José / 李明'],JSON_UNESCAPED_UNICODE)]);
    $db->prepare('INSERT INTO community_activity_page(page_id,content) VALUES(1,?)')->execute([json_encode(['texts'=>['hero_title'=>'Current activities'],'activities'=>[]])]);
    $before=[];foreach(GNCI_UNUSED_TABLES as $table)$before[$table]=unusedTableSnapshot($db,$table);
    unusedTablesValidate($db,realpath(__DIR__.'/..'));$checks++;
    $db->exec('CREATE TABLE cleanup_dependency(id INT PRIMARY KEY,old_id INT,FOREIGN KEY(old_id) REFERENCES program_sections(section_id))');
    try{unusedTablesValidate($db,realpath(__DIR__.'/..'));throw new LogicException('Incoming FK accepted.');}catch(RuntimeException $expected){$checks++;}$db->exec('DROP TABLE cleanup_dependency');
    $db->exec('CREATE VIEW cleanup_legacy_view AS SELECT * FROM program_sections');
    try{unusedTablesValidate($db,realpath(__DIR__.'/..'));throw new LogicException('View dependency accepted.');}catch(RuntimeException $expected){$checks++;}$db->exec('DROP VIEW cleanup_legacy_view');
    // Nonempty obsolete tables require a new review instead of discarding unexpected data.
    $program=(int)$db->query('SELECT program_id FROM programs')->fetchColumn();$sectionColumns=array_column($db->query('SHOW COLUMNS FROM program_sections')->fetchAll(PDO::FETCH_ASSOC),'Field');
    $db->prepare(in_array('section_type',$sectionColumns,true)?"INSERT INTO program_sections(program_id,section_type) VALUES(?,'test')":'INSERT INTO program_sections(program_id) VALUES(?)')->execute([$program]);
    try{unusedTablesValidate($db,realpath(__DIR__.'/..'));throw new LogicException('Unexpected data accepted.');}catch(RuntimeException $expected){$checks++;}$db->exec('DELETE FROM program_sections');
    $before['program_sections']=unusedTableSnapshot($db,'program_sections');
    unusedTablesArchive($db,$archive);unusedTablesVerifyRollback($db,$archive);unusedCheck(is_file($archive.'.verified.json'),'Exact rollback archive is restore-verified before removal.');
    unusedTablesApply($db,realpath(__DIR__.'/..'),$archive);
    foreach(GNCI_UNUSED_TABLES as $table)unusedCheck(!unusedTableExists($db,$table),'Reviewed table removed: '.$table);
    unusedCheck((int)$db->query('SELECT COUNT(*) FROM admin_office_services')->fetchColumn()===2,'Active normalized office services retained.');
    unusedCheck((int)$db->query('SELECT COUNT(*) FROM programs')->fetchColumn()===1,'Programs retained.');
    unusedCheck(communityActivityContent($db)['texts']['hero_title']==='Current activities','Current community page retained.');
    $sections=fetchAdministrationSections();unusedCheck(($sections['cleanup-offices']['offices'][0]['services']??[])===['Records','Guidance'],'Public/admin office rendering works.');
    unusedCheck(count(getPrograms())===1,'Program reader works.');
    $retrieved=(new GncbotRetriever($db))->retrieve('records office');unusedCheck(!empty($retrieved['context']),'Office chatbot retrieval works without the legacy child table.');
    saveAdministrationOffice($office,'cleanup-offices','Test Records Office','Test Head','Head','','','','','Changed service',null,false);
    unusedCheck($db->query('SELECT service_name FROM admin_office_services')->fetchColumn()==='Changed service','Office editing still writes active services.');
    unusedTablesApply($db,realpath(__DIR__.'/..'),$archive);$checks++;
    unusedTablesRollback($db,$archive);foreach(GNCI_UNUSED_TABLES as $table)unusedCheck(unusedTableEquivalent(unusedTableSnapshot($db,$table),$before[$table]),'Rollback restores original structure/data: '.$table);
    unusedTablesRollback($db,$archive);$checks++;
    $original=file_get_contents($archive);file_put_contents($archive,$original.' ');
    try{unusedTablesReadArchive($archive);throw new LogicException('Tampered archive accepted.');}catch(RuntimeException $expected){$checks++;}file_put_contents($archive,$original);
    $db->exec("UPDATE community_extension_page SET content='New content that rollback must not overwrite'");
    try{unusedTablesRollback($db,$archive);throw new LogicException('Existing content overwritten.');}catch(RuntimeException $expected){$checks++;}
    echo "Unused-table cleanup: $checks safety, rollback and feature checks passed in an isolated schema.\n";
}finally{
    if($db->inTransaction())$db->rollBack();$db->exec('USE `'.str_replace('`','``',$source).'`');
    if($created&&preg_match('/\Agnci_unused_test_[a-f0-9]{12}\z/D',$test)&&$test!==$source)$db->exec("DROP DATABASE `$test`");
    if(is_file($archive))unlink($archive);if(is_file($archive.'.sha256'))unlink($archive.'.sha256');
    if(is_file($archive.'.verified.json'))unlink($archive.'.verified.json');
}
