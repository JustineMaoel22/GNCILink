<?php
/** Temporary tables exercise the real handler without changing school records. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
$mode=$argv[1]??'current';
if(!in_array($mode,['legacy','current','blocked'],true))throw new InvalidArgumentException('Choose legacy, current or blocked.');
$db=getDB();
function archiveHandlerCheck(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$db->exec('CREATE TEMPORARY TABLE student_org_categories(category_id INT PRIMARY KEY,name VARCHAR(100),display_order INT DEFAULT 0)');
$db->exec('CREATE TEMPORARY TABLE student_org_gallery(image_id INT PRIMARY KEY,org_id INT,media_id INT,display_order INT DEFAULT 0)');
$db->exec('CREATE TEMPORARY TABLE activity_logs(log_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,action VARCHAR(50),table_name VARCHAR(80),record_id INT,description TEXT,created_at DATETIME)');
$column=$mode==='legacy'?'':', archived_at DATETIME NULL';
$constraint=$mode==='blocked'?', CHECK (archived_at IS NULL)':'';
$db->exec("CREATE TEMPORARY TABLE student_organizations(org_id INT PRIMARY KEY,slug VARCHAR(180),name VARCHAR(255),category_id INT,description TEXT,about_text TEXT,vision TEXT,mission TEXT,facebook_url VARCHAR(500),logo_media_id INT,cover_media_id INT,status VARCHAR(20),display_order INT DEFAULT 0 {$column} {$constraint})");
$media=$db->query('SELECT media_id FROM media_library ORDER BY media_id LIMIT 1')->fetchColumn();
archiveHandlerCheck((bool)$media,'An existing media record is required.');
// Legacy content deliberately fails current form validation; archiving must preserve it verbatim.
$db->prepare("INSERT INTO student_organizations(org_id,slug,name,category_id,description,facebook_url,logo_media_id,cover_media_id,status) VALUES(1,'archive-handler-test','Archive fixture',NULL,?,'http://legacy.example.invalid',?,?,'published')")->execute([str_repeat('x',1501),$media,$media]);
for($i=1;$i<=11;$i++)$db->prepare('INSERT INTO student_org_gallery(image_id,org_id,media_id) VALUES(?,1,?)')->execute([$i,$media]);
$before=$db->query('SELECT * FROM student_organizations WHERE org_id=1')->fetch(PDO::FETCH_ASSOC);
$galleryBefore=$db->query('SELECT * FROM student_org_gallery ORDER BY image_id')->fetchAll(PDO::FETCH_ASSOC);
session_id('gnc-org-handler-'.bin2hex(random_bytes(12)));session_start();
$_SESSION=['user_id'=>1,'user_role'=>'Super Admin','_initiated'=>true,'last_activity'=>time(),'csrf_token'=>bin2hex(random_bytes(32))];
$_SERVER['REQUEST_METHOD']='POST';
$invoke=static function(array $values,array $files=[]): array {
    $_POST=['action'=>'save','org_id'=>'1','status'=>'published','csrf_token'=>$_SESSION['csrf_token']]+$values;$_FILES=$files;
    http_response_code(200);ob_start();require __DIR__.'/../admin/action/student-organizations-handler.php';$raw=ob_get_clean();
    $json=json_decode($raw,true,512,JSON_THROW_ON_ERROR);return [http_response_code(),$json];
};
try {
    [$status,$result]=$invoke(['archived'=>'1']);
    if($mode==='legacy') {
        archiveHandlerCheck($status===409&&empty($result['success'])&&str_contains($result['error'],'migrate-student-org-archive.php'),'Missing schema returns an actionable migration error, not an upload error.');
        archiveHandlerCheck(!str_contains($result['error'],'uploaded'),'Setup error does not blame uploads.');
        [$status,$result]=$invoke(['archived'=>'0']);archiveHandlerCheck($status===409,'Restore also requires archive storage.');
    } elseif($mode==='blocked') {
        archiveHandlerCheck(empty($result['success'])&&str_contains($result['error'],'Could not archive')&&!str_contains($result['error'],'uploaded'),'Database failure identifies the archive operation.');
    } else {
        archiveHandlerCheck($status===200&&!empty($result['success'])&&!empty($result['organization']['archived_at']),'Archive-only request succeeds despite unrelated legacy validation failures.');
        archiveHandlerCheck(!array_key_exists('gallery',$result),'Archive response does not reload or rewrite gallery media.');
        archiveHandlerCheck(studentOrganizations()===[],'Archived organization is hidden publicly.');
        [$status,$result]=$invoke(['archived'=>'0']);archiveHandlerCheck($status===200&&!empty($result['success'])&&count(studentOrganizations())===1,'Restore preserves published status and public visibility.');
        // A real content edit or upload must never enter the state-only path.
        [$status,$result]=$invoke(['archived'=>'1','name'=>'Changed name']);archiveHandlerCheck($status===422&&empty($result['success']),'Mixed edits retain content validation.');
        archiveHandlerCheck(!studentOrganizationArchiveOnly(['org_id'=>1,'archived'=>'1'],['logo'=>['error'=>UPLOAD_ERR_INI_SIZE]]),'Failed uploads cannot bypass validation.');
        archiveHandlerCheck(studentOrganizationArchiveOnly(['org_id'=>1,'archived'=>'1'],['logo'=>['error'=>UPLOAD_ERR_NO_FILE]]),'Empty file controls do not become uploads.');
        $actions=$db->query('SELECT action FROM activity_logs ORDER BY log_id')->fetchAll(PDO::FETCH_COLUMN);
        archiveHandlerCheck($actions===['ARCHIVE','RESTORE'],'Archive and restore actions remain logged.');
    }
    $after=$db->query('SELECT * FROM student_organizations WHERE org_id=1')->fetch(PDO::FETCH_ASSOC);
    archiveHandlerCheck($before===$after,'Only archive state changes, then restores; all organization content and media references are preserved.');
    archiveHandlerCheck($galleryBefore===$db->query('SELECT * FROM student_org_gallery ORDER BY image_id')->fetchAll(PDO::FETCH_ASSOC),'All gallery relationships are preserved.');
} finally {$_SESSION=[];session_destroy();}
echo "PASS: {$mode} archive handler, accurate errors, preservation of organization data and media. Temporary tables expire on disconnect.\n";
