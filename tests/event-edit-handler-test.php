<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/event-edit-state.php';
$_SERVER['HTTP_HOST']='localhost';session_id(bin2hex(random_bytes(12)));session_start();
$_SESSION=['user_id'=>1,'user_role'=>'Super Admin','credential_version'=>1,'_initiated'=>true,'last_activity'=>time(),'csrf_token'=>str_repeat('c',64)];
$db=getDB();$mode=$argv[1]??'stale';
foreach(['events','event_departments','event_programs','activity_logs','csrf_tokens'] as $table){
    $ddl=$db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
    $ddl=preg_replace('/^CREATE TABLE/','CREATE TEMPORARY TABLE',$ddl);
    $ddl=preg_replace('/^\s*CONSTRAINT[^\n]*\n?/m','',$ddl);
    $db->exec(preg_replace('/,\s*\)/',"\n)",$ddl));
}
if(!eventAllDayStorageReady($db))$db->exec('ALTER TABLE events ADD COLUMN is_all_day TINYINT NULL DEFAULT NULL');
$db->prepare('INSERT INTO csrf_tokens(user_id,token,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))')->execute([1,$_SESSION['csrf_token']]);
$db->exec("INSERT INTO events(user_id,title,location,start_date,end_date,status,is_all_day) VALUES(1,'Original title','Original location','2026-11-14 00:00:00','2026-11-14 01:00:00','draft',0)");$id=(int)$db->lastInsertId();
$db->beginTransaction();$baseline=eventEditState($db,$id);$db->commit();
if($mode==='stale')$db->prepare('UPDATE events SET location=? WHERE event_id=?')->execute(['NEW LOCATION A',$id]);
if($mode==='audience')$db->prepare('INSERT INTO event_programs(event_id,program_code) VALUES(?,?)')->execute([$id,'TEST']);
$_SERVER['REQUEST_METHOD']='POST';
$_POST=['ajax_action'=>$mode==='fetch'?'fetch_one':'update','event_id'=>(string)$id,'edit_version'=>$mode==='missing'?'':$baseline['version'],'csrf_token'=>$_SESSION['csrf_token'],'title'=>'Changed title B','location'=>'Original location','start_date'=>'2026-11-14','start_time'=>'00:00','end_date'=>'2026-11-14','end_time'=>'01:00','status'=>'draft'];
if($mode==='all-day'){$_POST['start_time']='';$_POST['end_time']='';}
if(in_array($mode,['create','create-audit-failure','create-audience-failure'],true)){$_POST['ajax_action']='create';unset($_POST['event_id']);}
if($mode==='create-audit-failure')$db->exec('ALTER TABLE activity_logs RENAME COLUMN description TO unavailable_description');
if($mode==='create-audience-failure')$db->exec('ALTER TABLE event_programs RENAME COLUMN event_id TO unavailable_event_id');
ob_start();
register_shutdown_function(function()use($db,$mode,$id,$baseline){
    $result=json_decode(ob_get_clean(),true,512,JSON_THROW_ON_ERROR);
    $row=$db->query('SELECT * FROM events WHERE event_id='.(int)($result['event_id']??$id))->fetch(PDO::FETCH_ASSOC);
    if(in_array($mode,['create-audit-failure','create-audience-failure'],true)){
        if(!empty($result['success'])||$db->inTransaction()||(int)$db->query('SELECT COUNT(*) FROM events')->fetchColumn()!==1)throw new RuntimeException('Failed event creation did not roll back.');
    }elseif(in_array($mode,['stale','audience','missing'],true)){
        if(!empty($result['success'])||http_response_code()!==409||$row['title']!=='Original title')throw new RuntimeException('Conflict not rejected.');
        if($mode==='stale'&&$row['location']!=='NEW LOCATION A')throw new RuntimeException('Newer location was lost.');
    }elseif($mode==='fetch'){
        if(empty($result['success'])||$result['event']['edit_version']!==$baseline['version']||(int)$result['event']['is_all_day']!==0)throw new RuntimeException('Fetch baseline/midnight failed.');
    }else{
        if(empty($result['success'])||$row['title']!=='Changed title B')throw new RuntimeException('Fresh save failed: '.json_encode($result));
        if((int)$row['is_all_day']!==($mode==='all-day'?1:0)||substr($row['start_date'],11)!=='00:00:00')throw new RuntimeException('All-day/midnight flag lost.');
    }
    echo "PASS: Events $mode handler; temporary event, audience, CSRF and activity tables only.\n";
});
require __DIR__.'/../admin/events.php';
