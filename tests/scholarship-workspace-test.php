<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../components/scholarship-content.php';
initSession();$_SESSION['user_id']=1;$_SESSION['user_role']='Super Admin';$_SESSION['csrf_token']=bin2hex(random_bytes(32));
if(in_array($argv[1]??'',['unauthorized','csrf'],true)){
    $_SERVER['REQUEST_METHOD']='POST';$_POST=['entries'=>'[]'];if($argv[1]==='unauthorized')$_SESSION=[];
    register_shutdown_function(static function(){if(http_response_code()!==403)throw new RuntimeException('Unprotected scholarship write');echo "\nPASS: scholarship write rejected with HTTP 403.\n";});
    include __DIR__.'/../admin/action/scholarship-handler.php';exit;
}
$db=getDB();
$db->exec('CREATE TEMPORARY TABLE content_migrations(migration_key VARCHAR(100) PRIMARY KEY,applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
$db->exec('CREATE TEMPORARY TABLE tuition_fee_content(field_key VARCHAR(80) PRIMARY KEY,content TEXT NOT NULL)');
$db->exec('CREATE TEMPORARY TABLE activity_logs(user_id INT,action VARCHAR(100),table_name VARCHAR(100),record_id INT NULL,description TEXT,created_at DATETIME)');
$checks=0;
function scholarshipCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
$db->exec("INSERT INTO tuition_fee_content VALUES('scholarship_text_copy_10','Existing Basic Education Scholarship'),('scholarship_text_requirement_0','Existing eligibility requirement')");
if (($argv[1]??'') === 'normalized') {
    require_once __DIR__.'/../database/scholarship-categories-migration.php';
    $db->exec(str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',file_get_contents(__DIR__.'/../database/scholarship-categories.sql')));
    $db->prepare('INSERT INTO tuition_fee_content (field_key,content) VALUES (?,?)')->execute(['scholarship_categories',json_encode([['id'=>'gnc','label'=>'Existing School Awards'],['id'=>'government','label'=>'Existing Government Grants']])]);
    $beforeMigration=scholarshipCategories($db);
    scholarshipCategoryMigrationSeed($db);
    scholarshipCheck(scholarshipCategoriesEnabled($db),'Dedicated category storage enabled');
    scholarshipCheck(scholarshipCategories($db)===$beforeMigration,'Migration preserves IDs, labels and order');
    scholarshipCategoryMigrationSeed($db);
    scholarshipCheck(scholarshipCategories($db)===$beforeMigration,'Reapplying seed is idempotent');
}
$catalog=scholarshipCatalog();$categories=array_values(scholarshipCategories());
scholarshipCheck(count($catalog)===3,'All existing scholarship detail groups preserved');
scholarshipCheck($catalog[0]['title']==='Existing Basic Education Scholarship','Existing edited titles preserved');
scholarshipCheck(scholarshipFields()['requirement_0']['value']==='Existing eligibility requirement','Existing requirements preserved');
$categories=array_reverse($categories);$categories[]=['id'=>'partners','label'=>'Partner Scholarships'];
$allowed=scholarshipValidateCategories($categories);
scholarshipCheck(array_keys($allowed)===['government','gnc','partners'],'Category creation and order');
$custom=['id'=>'abcdef0123456789','category'=>'partners','title'=>'Partner Award','description'=>'Overview','url'=>'https://example.org/apply','eligibility'=>'Enrolled students','benefits'=>'Tuition support','application'=>'Submit an application','information'=>'Contact the office'];
foreach([
    static fn()=>scholarshipValidateCategories([]),
    static fn()=>scholarshipValidateCategories([['id'=>'a','label'=>'Name'],['id'=>'b','label'=>'name']]),
    static fn()=>scholarshipValidateCategories([['id'=>'../bad','label'=>'Bad']]),
    static fn()=>scholarshipValidateEntries([array_replace($custom,['category'=>'unknown'])],$allowed),
    static fn()=>scholarshipValidateEntries([array_replace($custom,['url'=>'javascript:alert(1)'])],$allowed),
    static fn()=>scholarshipValidateEntries([array_replace($custom,['pdf'=>'/config/config.php'])],$allowed),
    static fn()=>scholarshipValidateEntries([array_replace($custom,['benefits'=>['invalid']])],$allowed),
] as $invalid){try{$invalid();throw new LogicException('Invalid content accepted');}catch(InvalidArgumentException $expected){$checks++;}}
$publish=static function(array $payload):array{
    $_SERVER['REQUEST_METHOD']='POST';$_POST=array_replace(['csrf_token'=>$_SESSION['csrf_token'],'texts'=>'{}','images'=>'{}'],$payload);http_response_code(200);ob_start();
    include __DIR__.'/../admin/action/scholarship-handler.php';return json_decode(ob_get_clean(),true,32,JSON_THROW_ON_ERROR);
};
$entries=array_merge([$custom],$catalog);
$response=$publish(['categories'=>json_encode($categories),'entries'=>json_encode($entries)]);
scholarshipCheck(!empty($response['success']),'Create scholarship without PDF through the real handler');
scholarshipCheck(scholarshipCatalog()[0]['benefits']==='Tuition support','Optional scholarship details persist');
scholarshipCheck(array_keys(scholarshipCategories())===array_keys($allowed),'Category order persists');
scholarshipCheck(count(scholarshipCatalog())===4,'Legacy opportunities are included exactly once');
$before=$db->query('SELECT field_key,content FROM tuition_fee_content ORDER BY field_key')->fetchAll(PDO::FETCH_KEY_PAIR);
$response=$publish(['images'=>json_encode(['photo_1'=>['reset'=>false]])]);
scholarshipCheck(!empty($response['success']),'Retired photo payloads from older drafts are ignored');
scholarshipCheck($before===$db->query('SELECT field_key,content FROM tuition_fee_content ORDER BY field_key')->fetchAll(PDO::FETCH_KEY_PAIR),'Old photo payload cannot recreate photo records or change PDF content');
$forged=$entries;$forged[0]['pdf']='/uploads/scholarships/'.str_repeat('a',32).'.pdf';
$response=$publish(['categories'=>json_encode(array_reverse($categories)),'entries'=>json_encode($forged),'texts'=>json_encode(['copy_1'=>'Must roll back'])]);
scholarshipCheck(isset($response['error']),'Forged retained PDF path rejected');
scholarshipCheck($before===$db->query('SELECT field_key,content FROM tuition_fee_content ORDER BY field_key')->fetchAll(PDO::FETCH_KEY_PAIR),'Failed PDF validation rolls all changes back');
$response=$publish(['categories'=>json_encode(array_slice($categories,0,2)),'entries'=>json_encode($entries)]);
scholarshipCheck(isset($response['error']),'Category with remaining scholarships cannot be deleted');
$response=$publish(['categories'=>json_encode(array_slice($categories,0,2)),'entries'=>json_encode(array_reverse($catalog))]);
scholarshipCheck(!empty($response['success'])&&!isset(scholarshipCategories()['partners']),'Delete opportunity and empty category');
scholarshipCheck(scholarshipCatalog()[0]['legacy']==='athletic','Opportunity order persists');
$response=$publish(['categories'=>json_encode(array_slice($categories,0,2)),'entries'=>'[]']);
scholarshipCheck(!empty($response['success'])&&scholarshipCatalog()===[],'Deleted legacy opportunities stay deleted');
scholarshipCheck(scholarshipFields()['requirement_0']['value']==='Existing eligibility requirement','Deleting an opportunity does not destroy stored legacy fields');

if (scholarshipCategoriesEnabled($db)) {
    $expected=scholarshipCategories($db);
    $rows=$db->query('SELECT category_id AS id,category_name AS label FROM scholarship_categories ORDER BY display_order,category_id')->fetchAll(PDO::FETCH_ASSOC);
    scholarshipCheck(scholarshipValidateCategories($rows)===$expected,'Dedicated table reflects category CRUD and order');
    $db->exec("UPDATE tuition_fee_content SET content='[]' WHERE field_key='scholarship_categories'");
    scholarshipCheck(scholarshipCategories($db)===$expected,'Dedicated table is authoritative after migration');
    $db->beginTransaction();scholarshipSaveCategories($db,$expected);$db->commit();
    $changed=array_values($expected);$changed[0]['label']='Changed in rolled back transaction';
    $db->beginTransaction();scholarshipSaveCategories($db,$changed);$db->rollBack();
    scholarshipCheck(scholarshipCategories($db)===$expected&&scholarshipLegacyCategories($db)===$expected,'Category table and mirror roll back together');
    scholarshipCategoryMigrationRollback($db);
    scholarshipCheck(!scholarshipCategoriesEnabled($db)&&scholarshipCategories($db)===$expected,'Migration rollback preserves current categories');
    scholarshipCheck((int)$db->query('SELECT COUNT(*) FROM scholarship_categories')->fetchColumn()===count($expected),'Migration rollback retains category rows');
}
echo "$checks scholarship catalog checks passed. No persistent data changed.\n";
