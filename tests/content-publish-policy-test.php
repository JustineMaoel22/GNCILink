<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../components/athletics-sports-manager.php';
require_once __DIR__.'/../components/contact-page-content.php';
function policyCheck(bool $ok,string $message):void { if(!$ok)throw new RuntimeException($message); }
function policyReject(callable $work):void { try{$work();}catch(InvalidArgumentException|Exception $error){return;}throw new RuntimeException('Invalid content accepted'); }

foreach ([null,0,1,true,[],['1'],'true'] as $invalid) {
    $_POST['allow_incomplete']=$invalid;
    policyCheck(!contentPublishAllowsIncomplete(),'Override requires explicit string value');
}
$_POST=[];
policyReject(fn()=>administrationCleanLine('',255,'a name',true));
policyReject(fn()=>contactPageValidate('contact_email',''));
$sports=athleticsSportsWithImages(athleticsSportsDefaults());
$sports['texts']['hero_title']='';
policyReject(fn()=>validateAthleticsSports($sports,$sports));

$_POST['allow_incomplete']='1';
policyCheck(administrationCleanLine('',255,'a name',true)==='','Administration allows missing text');
policyCheck(cleanDepartmentDetails(['dept_name'=>''])['dept_name']==='','Department allows missing name');
foreach(array_keys(contactPageDefaults()) as $key)policyCheck(contactPageValidate($key,'')==='','Contact fields allow missing content');
policyReject(fn()=>contactPageValidate('contact_email','invalid email'));
policyReject(fn()=>contactPageValidate('contact_facebook','javascript:alert(1)'));
policyReject(fn()=>administrationCleanLine(str_repeat('a',256),255,'a name',true));
$sports['sports']=[['id'=>'sport-blank','name'=>'','type'=>'','image'=>'']];
$sports['teams']=[['id'=>'team-blank','name'=>'','image'=>'']];
$sports['facilities']=[['id'=>'facility-blank','name'=>'','image'=>'']];
policyCheck(validateAthleticsSports($sports,$sports)['sports'][0]['name']==='','Sports allows missing headings and names');
$bad=$sports;$bad['sports'][0]['name']=str_repeat('a',121);policyReject(fn()=>validateAthleticsSports($bad,$sports));
$bad=$sports;$bad['facebook_url']='javascript:alert(1)';policyReject(fn()=>validateAthleticsSports($bad,$sports));

$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->sqliteCreateFunction('NOW',fn()=>date('Y-m-d H:i:s'));
$db->exec('CREATE TABLE student_achievements(achievement_id INTEGER PRIMARY KEY,title TEXT,description TEXT,program_organization TEXT,category_label TEXT,academic_year TEXT,image_path TEXT,status TEXT,is_featured INTEGER,sort_order INTEGER,achievers TEXT,thumbnail_path TEXT,created_at TEXT,updated_at TEXT)');
$id=saveStudentAchievement($db,0,['title'=>'','status'=>'published'],null);
policyCheck($db->query('SELECT status FROM student_achievements WHERE achievement_id='.$id)->fetchColumn()==='published','Incomplete achievement is actually published without photo or year');
policyReject(fn()=>saveStudentAchievement($db,0,['title'=>'','status'=>'published','academic_year'=>'invalid'],null));
unset($_POST['allow_incomplete']);
policyReject(fn()=>saveStudentAchievement($db,0,['title'=>'','status'=>'published'],null));
echo "PASS: explicit override, missing administration/department/contact/sports/achievement content, persisted publication, and retained length/URL/year validation. No saved site content changed.\n";
