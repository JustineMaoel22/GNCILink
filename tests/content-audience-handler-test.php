<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') === 'all') {
    foreach (['news','announcement'] as $kind) foreach (['create','update','department','clear','invalid','csrf','denied'] as $scenario) {
        passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.$kind.' '.$scenario, $status);
        if ($status !== 0) exit($status);
    }
    exit;
}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
initSession();
$db = getDB();
$kind = $argv[1] ?? 'news';
$scenario = $argv[2] ?? 'create';
if (!in_array($kind,['news','announcement'],true) || !in_array($scenario,['create','update','department','clear','invalid','csrf','denied'],true)) throw new RuntimeException('Invalid test scenario.');
$_SESSION['user_id'] = (int)$db->query("SELECT user_id FROM users WHERE role='Super Admin' AND status='active' LIMIT 1")->fetchColumn();
$_SESSION['user_role'] = $scenario === 'denied' ? 'Unknown role' : 'Super Admin';
$_SESSION['_initiated'] = true;
$_SESSION['last_activity'] = time();
foreach ([
    'news'=>'news_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,category VARCHAR(100),title VARCHAR(500),slug VARCHAR(500),content MEDIUMTEXT,author VARCHAR(255),featured_image INT NULL,status VARCHAR(30),published_at DATETIME NULL,created_at DATETIME,updated_at DATETIME NULL,is_pinned INT DEFAULT 0,pinned_at DATETIME NULL',
    'announcements'=>'announcement_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,title VARCHAR(500),slug VARCHAR(500),content MEDIUMTEXT,category_id INT NULL,program VARCHAR(30),dept_id INT NULL,featured_image INT NULL,status VARCHAR(30),created_at DATETIME,updated_at DATETIME NULL,is_pinned INT DEFAULT 0,pinned_at DATETIME NULL,published_at DATETIME NULL',
    'activity_logs'=>'log_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,action VARCHAR(100),table_name VARCHAR(100),record_id INT,description TEXT,created_at DATETIME',
    'announcement_categories'=>'announcement_id INT,category_id INT,PRIMARY KEY(announcement_id,category_id)',
    'announcement_departments'=>'announcement_id INT,dept_id INT,PRIMARY KEY(announcement_id,dept_id)',
    'announcement_programs'=>'announcement_id INT,program_code VARCHAR(20),PRIMARY KEY(announcement_id,program_code)',
    'news_departments'=>'news_id INT,dept_id INT,PRIMARY KEY(news_id,dept_id)',
    'news_programs'=>'news_id INT,program_code VARCHAR(20),PRIMARY KEY(news_id,program_code)',
] as $table=>$columns) $db->exec("CREATE TEMPORARY TABLE `$table` ($columns) ENGINE=InnoDB");
$db->exec('CREATE TEMPORARY TABLE departments (dept_id INT PRIMARY KEY, dept_name VARCHAR(50)) ENGINE=InnoDB');
$db->exec("INSERT INTO departments VALUES(1,'One'),(2,'Two')");
$db->exec('CREATE TEMPORARY TABLE programs (program_code VARCHAR(20) PRIMARY KEY, program_name VARCHAR(50), dept_id INT) ENGINE=InnoDB');
$db->exec("INSERT INTO programs VALUES('A1','Alpha One',1),('A2','Alpha Two',1),('B1','Beta One',2),('B2','Beta Two',2)");
$table = $kind === 'news' ? 'news' : 'announcements';
$idColumn = $kind.'_id';
$isUpdate = in_array($scenario,['update','department','clear'],true);
$id = 0;
if ($isUpdate) {
    $data = ['title'=>'Audience handler fixture','content'=>'<p>Fixture</p>','status'=>'draft','author'=>'Test','category'=>array_key_first(getNewsCategories()),'category_ids'=>[]];
    $id = $kind === 'news' ? createNews($data) : createAnnouncement($data);
    $audience = validatedContentAudience(['1','2'],['A1','B1']);
    if ($kind === 'news') saveNewsAudience($db,$id,$audience);
    else saveAnnouncementAudience($db,$id,$audience['dept_ids'],$audience['programs']);
}
$depts = $scenario === 'clear' ? [] : ['1','2'];
$programs = $scenario === 'clear' ? [] : ['B1'];
if ($scenario === 'department') { $depts=['1'];$programs=[]; }
if ($scenario === 'invalid') $depts=['1'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['action'=>$isUpdate?'update':'create',$idColumn=>(string)$id,'title'=>'Audience handler fixture','content'=>'<p>Fixture</p>','author'=>'Test','category'=>array_key_first(getNewsCategories()),'status'=>'draft','audience_present'=>'1','dept_ids'=>$depts,'programs'=>$programs,'csrf_token'=>$scenario==='csrf'?'invalid':generateCSRFToken()];
$_FILES = [];
register_shutdown_function(static function () use ($db,$kind,$scenario,$table,$idColumn,$depts,$programs) {
    try {
        $rows=$db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (in_array($scenario,['invalid','csrf','denied'],true)) {
            if ($rows || empty($_SESSION['flash_errors'])) throw new RuntimeException('Invalid or unauthorized save was not rejected.');
        } else {
            if (count($rows)!==1 || !empty($_SESSION['flash_errors']) || $rows[0]['status']!=='draft') throw new RuntimeException('Draft save failed: '.json_encode($_SESSION['flash_errors']??[]));
            $row=$rows[0];
            $audience=$kind==='news'?getNewsAudiencesBatch($db,[$row])[(int)$row[$idColumn]]:getAnnouncementAudience($db,(int)$row[$idColumn],$row['dept_id'],$row['program']);
            if ($audience['dept_ids']!==array_map('intval',$depts) || $audience['programs']!==$programs) throw new RuntimeException('Handler did not preserve the exact audience.');
            if ($db->inTransaction()) throw new RuntimeException('Handler left a transaction open.');
        }
        echo "PASS: $kind / $scenario real handler; temporary tables only, no Facebook requests.\n";
    } catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
    finally { $_SESSION=[];session_destroy(); }
});
require __DIR__.'/../admin/action/create-'.($kind==='news'?'news':'announcement').'.php';
