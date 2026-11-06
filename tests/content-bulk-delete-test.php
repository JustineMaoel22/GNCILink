<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/../admin/admin-functions.php';require_once __DIR__.'/../includes/content-bulk-delete.php';require_once __DIR__.'/../includes/content-submissions.php';
$db=getDB();$checks=0;
function bulkCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function bulkRefused(callable $fn):void{try{$fn();throw new LogicException('Invalid bulk deletion accepted.');}catch(RuntimeException|InvalidArgumentException $e){global $checks;$checks++;}}
$db->exec('CREATE TEMPORARY TABLE activity_logs(user_id INT,action VARCHAR(100),table_name VARCHAR(100),record_id INT,description TEXT,created_at DATETIME) ENGINE=InnoDB');
$_SESSION=['user_id'=>1,'user_role'=>'Super Admin'];
foreach(['news','announcement','event'] as $kind){
    $table=bulkContentTable($kind);$db->exec("CREATE TEMPORARY TABLE `$table`({$kind}_id INT PRIMARY KEY,user_id INT,title VARCHAR(100)) ENGINE=InnoDB");
    $db->exec("INSERT INTO `$table` VALUES(1,1,'First'),(2,1,'Second'),(3,2,'Third')");
    bulkRefused(fn()=>deleteSelectedContent($kind,[1,999]));
    bulkCheck((int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn()===3,'A missing record prevents partial deletion.');
    $_SESSION['user_role']='The Mirror';bulkRefused(fn()=>deleteSelectedContent($kind,[1,2]));$_SESSION['user_role']='Super Admin';
    bulkCheck(deleteSelectedContent($kind,['2','1','1'])===2,'Selection deduplicates and deletes the exact requested rows.');
    bulkCheck($db->query("SELECT {$kind}_id FROM `$table`")->fetchColumn()===3,'Unselected content remains.');
    bulkCheck((int)$db->query("SELECT COUNT(*) FROM activity_logs WHERE table_name='$table'")->fetchColumn()===2,'Each selected deletion is audited.');
}
foreach([null,[],['0'],['1 OR 1=1'],[['1']],array_fill(0,501,'1')] as $invalid)bulkRefused(fn()=>bulkContentIds($invalid));
bulkCheck(bulkContentIds(['5','2','5'])===[2,5],'IDs canonicalize to a sorted unique set.');
bulkCheck(rbacShouldQueue('/admin/events.php',['action'=>'get','ajax_action'=>'bulk_delete']),'A conflicting action field cannot bypass approval routing.');
$_SESSION['user_role']='Content Editor';
bulkCheck(rbacValidatePostOwnership('/admin/action/create-news.php',['action'=>'bulk_delete','ids'=>[3]])===0,'Content Editors can submit an authorized batch without deleting it.');
bulkCheck((int)$db->query('SELECT COUNT(*) FROM news')->fetchColumn()===1,'Proposal validation leaves live content unchanged.');
$_SESSION['user_role']='Super Admin';
$db->exec('ALTER TABLE activity_logs DROP COLUMN created_at');
bulkRefused(fn()=>deleteSelectedContent('news',[3]));
bulkCheck((int)$db->query('SELECT COUNT(*) FROM news')->fetchColumn()===1,'An audit failure rolls back the entire deletion.');
echo "$checks bulk-deletion checks passed. Only temporary records were deleted.\n";
