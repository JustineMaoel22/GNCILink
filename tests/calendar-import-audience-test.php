<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/calendar-import.php';
initSession();
$_SESSION['user_id']=1; $_SESSION['user_role']='Super Admin';
$db=getDB();
// Connection-scoped tables shadow real data, including logs; import commits remain isolated.
foreach([
    'departments'=>'dept_id INT PRIMARY KEY,dept_name VARCHAR(100),slug VARCHAR(100),department_type VARCHAR(30)',
    'programs'=>'program_code VARCHAR(30) PRIMARY KEY,program_name VARCHAR(100),dept_id INT',
    'events'=>'event_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,title VARCHAR(500),start_date DATETIME,end_date DATETIME NULL,category_id INT,description TEXT,status VARCHAR(30),location VARCHAR(500),program VARCHAR(30),dept_id INT NULL,audience_group VARCHAR(30),updated_at DATETIME NULL',
    'event_departments'=>'event_id INT,dept_id INT,PRIMARY KEY(event_id,dept_id)',
    'event_programs'=>'event_id INT,program_code VARCHAR(30),PRIMARY KEY(event_id,program_code)',
    'categories'=>'category_id INT PRIMARY KEY,category_name VARCHAR(100)',
    'calendar_import_sources'=>'event_id INT PRIMARY KEY,series_id INT',
    'calendar_import_series'=>'series_id INT PRIMARY KEY,revision INT,status VARCHAR(30)',
    'calendar_import_batches'=>'token VARCHAR(64) PRIMARY KEY,state VARCHAR(30)',
    'activity_logs'=>'log_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,action VARCHAR(50),table_name VARCHAR(100),record_id INT,description TEXT,ip_address VARCHAR(100),user_agent TEXT,created_at DATETIME DEFAULT CURRENT_TIMESTAMP'
] as $table=>$columns)$db->exec("CREATE TEMPORARY TABLE $table ($columns) ENGINE=InnoDB");
$db->exec("INSERT INTO departments VALUES(1,'College One','one','college'),(2,'College Two','two','college'),(3,'Junior High','jhs','basic-education')");
$db->exec("INSERT INTO programs VALUES('TSTA','Test A',1),('TSTB','Test B',2),('BASIC-X','Basic track',3)");
$db->exec("INSERT INTO categories VALUES(1,'School Event')");
$db->exec("INSERT INTO calendar_import_series VALUES(1,0,'active')");
function audienceCheck(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$selection=['dept_ids'=>['1',2,'1'],'programs'=>['TSTA','TSTB','TSTA']];
$normalized=calendarImportAudience($selection);
audienceCheck($normalized['dept_ids']===[1,2]&&$normalized['programs']===['TSTA','TSTB'],'Normalize and deduplicate multiple selections.');
foreach([
    ['dept_ids'=>'1','programs'=>[]],['dept_ids'=>[[1]],'programs'=>[]],
    ['dept_ids'=>[999],'programs'=>[]],['dept_ids'=>[1],'programs'=>['TSTB']],
    ['dept_ids'=>[3],'programs'=>['BASIC-X']],['dept_ids'=>[],'programs'=>[['TSTA']]],
    ['dept_ids'=>null,'programs'=>[]]
] as $bad){try{calendarImportAudience($bad);throw new RuntimeException('Invalid audience accepted.');}catch(InvalidArgumentException $expected){}}
$edit=$selection+['title'=>'Audience fixture','start_date'=>'2026-11-10','category_id'=>1,'include'=>true,'reviewed'=>true,'source_index'=>0];
$rows=calendarImportValidate([$edit],[['action'=>'create']],[],[['category_id'=>1]]);
$batch=['series_id'=>1,'token'=>str_repeat('a',64),'base_revision'=>0];
$db->prepare("INSERT INTO calendar_import_batches VALUES(?,'ready')")->execute([$batch['token']]);
calendarImportApply($db,$batch,$rows,'draft',1);
$existing=calendarImportExisting($db,1);
audienceCheck(count($existing)===1&&$existing[0]['dept_ids']===[1,2]&&$existing[0]['programs']===['TSTA','TSTB'],'Import stores and reads all audience links.');
audienceCheck($existing[0]['status']==='draft','Import preserves draft status.');
$comparison=calendarImportCompare([['title'=>'Audience fixture','start_date'=>'2026-11-10','warnings'=>[],'event_id'=>0]],$existing,[]);
audienceCheck($comparison[0]['dept_ids']===[1,2]&&$comparison[0]['programs']===['TSTA','TSTB'],'Revision preserves multiple audiences.');
$changed=$existing[0];$changed['programs']=['TSTA'];
audienceCheck(calendarEventSignature($existing[0])!==calendarEventSignature($changed),'Audience changes invalidate stale approvals.');
$legacy=calendarImportAudience(['dept_id'=>null,'program'=>'ALL'],$existing[0]);
audienceCheck($legacy['dept_ids']===[1,2]&&$legacy['programs']===['TSTA','TSTB'],'Legacy payload retains existing selections.');
$group=calendarImportAudience(['dept_id'=>'group:basic-education','program'=>'ALL']);
audienceCheck($group['audience_group']==='basic-education','Legacy groups remain intact.');
audienceCheck(calendarImportAudience($group)['audience_group']==='basic-education','Applying a normalized group preserves its audience.');
$edit['event_id']=$existing[0]['event_id'];$edit['dept_ids']=[];$edit['programs']=[];
$rows=calendarImportValidate([$edit],[['action'=>'update']],$existing,[['category_id'=>1]]);
$batch['token']=str_repeat('b',64);$batch['base_revision']=1;
$db->prepare("INSERT INTO calendar_import_batches VALUES(?,'ready')")->execute([$batch['token']]);
calendarImportApply($db,$batch,$rows,'draft',1);
$cleared=calendarImportExisting($db,1)[0];
audienceCheck($cleared['dept_ids']===[]&&$cleared['programs']===[]&&$cleared['dept_id']===null&&$cleared['program']==='ALL','Clearing removes all links and primary values.');
echo "PASS: multi-audience normalization, invalid/Basic Education program rejection, import persistence, draft status, revision retention, stale audience detection, legacy groups, and clearing. Only temporary tables used.\n";
