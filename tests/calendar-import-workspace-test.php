<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/calendar-import.php';
require_once __DIR__.'/../includes/content-submissions.php';
initSession();$_SESSION['user_id']=1;$_SESSION['user_role']='Super Admin';
$db=getDB();
// Connection-local tables guarantee no real review/event records are modified.
foreach([
    'events'=>'event_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,title VARCHAR(500),description TEXT,start_date DATETIME,end_date DATETIME,category_id INT,status VARCHAR(30),location VARCHAR(500),program VARCHAR(100),dept_id INT,audience_group VARCHAR(30),updated_at DATETIME',
    'departments'=>'dept_id INT PRIMARY KEY,dept_name VARCHAR(100),slug VARCHAR(100),department_type VARCHAR(30)',
    'programs'=>'program_code VARCHAR(100),program_name VARCHAR(100),dept_id INT',
    'event_departments'=>'event_id INT,dept_id INT',
    'event_programs'=>'event_id INT,program_code VARCHAR(100)',
    'calendar_import_sources'=>'event_id INT,series_id INT',
    'calendar_import_series'=>"series_id INT PRIMARY KEY,status VARCHAR(30),revision INT",
    'calendar_import_batches'=>"token CHAR(64) PRIMARY KEY,series_id INT,user_id INT,base_revision INT,state VARCHAR(30),filename VARCHAR(255),preview_json LONGTEXT,approved_json LONGTEXT,calendar_action VARCHAR(30),created_at DATETIME DEFAULT CURRENT_TIMESTAMP",
    'activity_logs'=>'log_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,action VARCHAR(50),table_name VARCHAR(100),record_id INT,description TEXT,ip_address VARCHAR(100),user_agent TEXT,created_at DATETIME DEFAULT CURRENT_TIMESTAMP'
] as $table=>$columns)$db->exec("CREATE TEMPORARY TABLE $table ($columns) ENGINE=InnoDB");
$db->exec('CREATE TEMPORARY TABLE categories(category_id INT PRIMARY KEY, category_name VARCHAR(100))');
$db->exec("INSERT INTO categories VALUES(1,'School Activities')");
$source=['title'=>'Original title','description'=>'Source description','start_date'=>'2026-11-10','end_date'=>'','start_time'=>'','end_time'=>'','category_id'=>1,'event_id'=>0,'action'=>'create','warnings'=>['Time is TBA or unclear. Please verify the schedule time.'],'source'=>'Original document text'];
$baseline=['rows'=>[$source],'existing'=>[]];$token=str_repeat('a',64);
$q=$db->prepare("INSERT INTO calendar_import_batches(token,series_id,user_id,base_revision,state,filename,preview_json) VALUES(?,1,1,0,'preview','workspace-test.docx',?)");
$q->execute([$token,json_encode($baseline)]);
function reviewAssert($condition,$message){if(!$condition)throw new RuntimeException($message);}
function reviewReject(callable $fn,$message){try{$fn();}catch(InvalidArgumentException $expected){return;}throw new RuntimeException($message);}
$_POST=['import_token'=>$token];
reviewReject(fn()=>calendarImportHandle($db,'import_review_resume',2),'Another user must not resume this review.');
$edit=$source+['source_index'=>0,'include'=>false,'reviewed'=>false,'excluded'=>false,'dept_ids'=>[],'programs'=>[]];
$edit['title']='Edited but incomplete';$edit['start_date']='';$edit['category_id']=null;
$view=['ciSearch'=>'Edited','ciIssue'=>'date','ciPageSize'=>'20','ciSort'=>'date','step'=>2,'page'=>1,'selected'=>[0],'import_status'=>'draft'];
$_POST['review_workspace']=json_encode(['version'=>0,'rows'=>[$edit],'view'=>$view]);
$saved=calendarImportHandle($db,'import_review_save',1);
reviewAssert($saved['review_version']===1,'First save must have version 1.');
$stored=json_decode($db->query("SELECT preview_json FROM calendar_import_batches")->fetchColumn(),true);
reviewAssert($stored['rows']===$baseline['rows']&&$stored['existing']===[],'Saving must preserve the original validation baseline.');
reviewAssert($db->query('SELECT COUNT(*) FROM events')->fetchColumn()==0,'Saving must not create events.');
reviewAssert($stored['review']['rows'][0]['start_date']===''&&$stored['review']['rows'][0]['category_id']===null,'Incomplete work must be saveable.');
reviewReject(fn()=>calendarImportHandle($db,'import_review_save',1),'A stale tab must not overwrite a newer save.');
reviewAssert(calendarImportHandle($db,'import_review_list',2)['reviews']===[],'Saved review lists must be owner-scoped.');
$list=calendarImportHandle($db,'import_review_list',1)['reviews'];
reviewAssert(count($list)===1&&$list[0]['review_version']===1,'The owner must see their saved review.');
$_POST=['import_token'=>$token];
$resumed=calendarImportHandle($db,'import_review_resume',1);
reviewAssert($resumed['rows'][0]['title']===$edit['title']&&$resumed['view']['ciIssue']==='date'&&$resumed['view']['selected']===[0],'Resume must restore edits, issue filter and selection.');
calendarImportHandle($db,'import_cancel',1);
reviewAssert($db->query("SELECT state FROM calendar_import_batches")->fetchColumn()==='preview','Closing an older tab must not cancel a saved review.');
// Merged/removed rows are represented by the saved list; splits can reuse a source.
$split=$edit;$split['title']='Split entry';$split['excluded']=true;
$merged=$edit;$merged['title']='Merged entry';$merged['description']='Combined event details';$merged['include']=true;$merged['reviewed']=true;
$workspace=['version'=>1,'rows'=>[$merged,$split],'view'=>$view];
$_POST=['import_token'=>$token,'review_workspace'=>json_encode($workspace)];
calendarImportHandle($db,'import_review_save',1);
$resumed=calendarImportHandle($db,'import_review_resume',1);
reviewAssert(count($resumed['rows'])===2&&$resumed['rows'][0]['include']&&$resumed['rows'][0]['reviewed']&&$resumed['rows'][1]['excluded'],'Save/resume must preserve merged/split rows, approval and exclusions.');
$_POST=['import_token'=>$token,'import_rows'=>'[]','review_version'=>'1'];
reviewReject(fn()=>calendarImportHandle($db,'import_confirm',1),'An older tab must not confirm over a newer save.');
$_POST=['import_token'=>$token,'discard_saved'=>'1','review_version'=>'1'];
reviewReject(fn()=>calendarImportHandle($db,'import_cancel',1),'An older tab must not discard a newer save.');
foreach([
    array_replace($edit,['source_index'=>9]),array_replace($edit,['event_id'=>999]),array_replace($edit,['action'=>'archive']),array_replace($edit,['title'=>['invalid']]),array_replace($edit,['dept_ids'=>[[1]]]),array_replace($edit,['include'=>'yes'])
] as $invalid)reviewReject(fn()=>calendarImportReviewRows([$invalid],$baseline),'Malformed workspace rows must be rejected.');
reviewReject(fn()=>calendarImportReviewView(['selected'=>['0']]),'Malformed view selection must be rejected.');
reviewAssert(calendarImportReviewExpired(['age_seconds'=>86401],$baseline),'Unsaved previews expire after one day.');
reviewAssert(!calendarImportReviewExpired(['age_seconds'=>86401],['review'=>['saved_at'=>time()]]),'Saved reviews extend the preview lifetime.');
reviewAssert(calendarImportReviewExpired(['age_seconds'=>0],['review'=>['saved_at'=>time()-31*86400]]),'Saved reviews expire after 30 days.');
$expired=$stored;$expired['review']['saved_at']=time()-31*86400;
$db->prepare('UPDATE calendar_import_batches SET preview_json=? WHERE token=?')->execute([json_encode($expired),$token]);
$_POST=['import_token'=>$token];
reviewReject(fn()=>calendarImportHandle($db,'import_review_resume',1),'Expired reviews must not resume.');
reviewReject(fn()=>calendarImportHandle($db,'import_review_save',1),'Expired reviews must not be renewed.');
reviewAssert(calendarImportHandle($db,'import_review_list',1)['reviews']===[],'Expired reviews must not be listed.');
$_POST=['import_token'=>$token,'discard_saved'=>'1','review_version'=>'1'];
calendarImportHandle($db,'import_cancel',1);
reviewAssert($db->query('SELECT state FROM calendar_import_batches')->fetchColumn()==='cancelled','Explicit discard removes the saved review.');
reviewAssert($db->query('SELECT COUNT(*) FROM events')->fetchColumn()==0,'Workspace operations must leave events untouched.');
foreach(['import_review_save','import_review_resume','import_review_list'] as $action)reviewAssert(!rbacShouldQueue('/admin/events.php',['ajax_action'=>$action]),'Workspace actions must not enter publishing approval.');
// Resuming must not replace an old event snapshot with a fresh database baseline.
$staleToken=str_repeat('b',64);$staleBaseline=$baseline;$staleBaseline['existing']=[['event_id'=>99,'title'=>'Old existing event']];
$q=$db->prepare("INSERT INTO calendar_import_batches(token,series_id,user_id,base_revision,state,filename,preview_json) VALUES(?,1,1,0,'preview','confirm-test.docx',?)");
$q->execute([$staleToken,json_encode($staleBaseline)]);
$valid=$source+['source_index'=>0,'include'=>true,'reviewed'=>true,'dept_ids'=>[],'programs'=>[],'program'=>'ALL'];
$_POST=['import_token'=>$staleToken,'review_workspace'=>json_encode(['version'=>0,'rows'=>[$valid],'view'=>$view])];
calendarImportHandle($db,'import_review_save',1);calendarImportHandle($db,'import_review_resume',1);
$_POST=['import_token'=>$staleToken,'review_version'=>'1','import_rows'=>json_encode([$valid]),'import_status'=>'draft'];
reviewReject(fn()=>calendarImportHandle($db,'import_confirm',1),'Changed/missing existing events must still block confirmation after resume.');
// Full positive confirmation uses temporary event tables and remains idempotent.
$db->exec("INSERT INTO calendar_import_series VALUES(1,'active',0)");
$confirmToken=str_repeat('c',64);$q->execute([$confirmToken,json_encode($baseline)]);
$_POST=['import_token'=>$confirmToken,'review_workspace'=>json_encode(['version'=>0,'rows'=>[$valid],'view'=>$view])];
calendarImportHandle($db,'import_review_save',1);
$_POST=['import_token'=>$confirmToken,'review_version'=>'1','import_rows'=>json_encode([$valid]),'import_status'=>'draft'];
reviewAssert(calendarImportHandle($db,'import_confirm',1)['success'],'A valid saved review must confirm through the normal pipeline.');
reviewAssert($db->query('SELECT status FROM events')->fetchColumn()==='draft','Save review must preserve the final draft/publication choice.');
calendarImportHandle($db,'import_confirm',1);
reviewAssert($db->query('SELECT COUNT(*) FROM events')->fetchColumn()==1,'Repeated confirmation must remain idempotent.');
echo "PASS: private save/resume/list/discard, incomplete edits, approval/exclusion/split state, source/event baseline preservation, stale tabs, invalid inputs, expiry, approval routing, draft confirmation and idempotence. Only temporary tables used.\n";
