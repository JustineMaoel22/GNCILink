<?php
/** Local integration test. Creates and removes isolated events, calendars and submissions. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ob_start();
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/calendar-import.php';
require_once __DIR__.'/../includes/content-submissions.php';
require_once __DIR__.'/calendar-docx-test.php';
$db=getDB();
calendarCheck(calendarImportReady($db),'Run the additive calendar import migration first.');
calendarCheck(class_exists('ZipArchive'),'Enable PHP ZIP before running upload tests.');
$admin=$db->query("SELECT user_id,role,credential_version FROM users WHERE status='active' AND role='Super Admin' AND must_change_password=0 LIMIT 1")->fetch();
$editor=$db->query("SELECT user_id,role,credential_version FROM users WHERE status='active' AND role IN ('Content Editor','Content Admin') AND must_change_password=0 LIMIT 1")->fetch();
calendarCheck((bool)$admin&&(bool)$editor,'Active Super Admin and Content Editor required.');
$tag='CalendarTest'.bin2hex(random_bytes(6));$series=[];$submissions=[];$sessions=[];$eventIds=[];$csrfTokens=[];
$path=tempnam(sys_get_temp_dir(),'gnc-calendar-http-');
$makeSession=static function(array $user)use(&$sessions): string {
    session_id('gnc-calendar-test-'.bin2hex(random_bytes(12)));session_start();
    $_SESSION=['user_id'=>(int)$user['user_id'],'user_role'=>$user['role'],'credential_version'=>(int)$user['credential_version'],'_initiated'=>true,'last_activity'=>time()];
    $id=session_id();$sessions[]=$id;session_write_close();return $id;
};
$http=static function(string $session,?array $post=null,string $url='/admin/events.php'): array {
    $curl=curl_init('http://127.0.0.1'.$url);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>45,CURLOPT_COOKIE=>session_name().'='.$session,CURLOPT_FOLLOWLOCATION=>$post===null,CURLOPT_MAXREDIRS=>3]);
    if($post!==null)curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post]);
    $raw=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$err=curl_error($curl);curl_close($curl);
    if($raw===false)throw new RuntimeException($err);return [$status,$raw];
};
$post=static function(string $session,string $token,array $values)use($http): array {
    [$status,$raw]=$http($session,array_merge(['csrf_token'=>$token],$values));$result=json_decode($raw,true);
    calendarCheck(is_array($result),'JSON expected (HTTP '.$status.'): '.substr(strip_tags($raw),0,160));return $result;
};
$makeDoc=static function(string $date,string $title,?string $location='Main Gym')use($path): void {
    $xml='<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl>';
    $table=[['Date','Activity','Description'],[$date,$title,'School description <safe>']];
    if($location!==null){$table[0][]='Venue';$table[1][]=$location;}
    foreach($table as $cells){$xml.='<w:tr>';foreach($cells as $cell)$xml.='<w:tc><w:p><w:r><w:t>'.htmlspecialchars($cell,ENT_XML1).'</w:t></w:r></w:p></w:tc>';$xml.='</w:tr>';}
    $zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);$zip->addFromString('[Content_Types].xml','<Types/>');$zip->addFromString('word/document.xml',$xml.'</w:tbl></w:body></w:document>');$zip->close();
};
try {
    $adminSession=$makeSession($admin);$editorSession=$makeSession($editor);
    $tokens=[];
    foreach([$adminSession,$editorSession] as $session){[$status,$html]=$http($session);calendarCheck($status===200&&preg_match('/id="csrfToken" value="([^"]+)"/',$html,$m),'Authenticated Events UI (HTTP '.$status.', login='.(int)str_contains($html,'login-form').')');$tokens[$session]=$m[1];$csrfTokens[]=$m[1];}
    foreach(['import_preview','import_manage_preview','import_confirm','import_cancel'] as $action){$result=$post($adminSession,'invalid',['ajax_action'=>$action]);calendarCheck(empty($result['success']),'CSRF rejected: '.$action);}
    [$status,$raw]=$http('', ['ajax_action'=>'import_preview']);calendarCheck($status!==200||empty(json_decode($raw,true)['success']),'Unauthenticated upload rejected');
    $makeDoc('Jan. 15','Foundation '.$tag);
    $preview=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_preview','calendar_name'=>$tag,'start_year'=>2026,'calendar_file'=>new CURLFile($path,'application/vnd.openxmlformats-officedocument.wordprocessingml.document','calendar.docx')]);
    calendarCheck(!empty($preview['success'])&&count($preview['rows'])===1,'DOCX preview: '.($preview['message']??''));
    $q=$db->prepare('SELECT series_id FROM calendar_import_batches WHERE token=?');$q->execute([$preview['token']]);$seriesId=(int)$q->fetchColumn();$series[]=$seriesId;
    calendarCheck(count(calendarImportExisting($db,$seriesId))===0,'Preview does not write events');
    $edits=$preview['rows'];$edits[0]['include']=true;$edits[0]['reviewed']=true;
    calendarCheck(!empty($preview['departments_ready'])&&!empty($preview['departments'])&&!empty($preview['programs']),'Department and program options provided');
    $basicPrograms=$db->query("SELECT p.program_code FROM programs p JOIN departments d ON d.dept_id=p.dept_id WHERE d.department_type='basic-education'")->fetchAll(PDO::FETCH_COLUMN);
    calendarCheck(!array_intersect($basicPrograms,array_column($preview['programs'],'code')),'Basic Education tracks excluded from import program choices');
    if($basicPrograms){
        $bad=$edits;$bad[0]['program']=$basicPrograms[0];
        $result=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_confirm','import_token'=>$preview['token'],'import_rows'=>json_encode($bad)]);
        calendarCheck(empty($result['success'])&&str_contains($result['message'],'College or Graduate School'),'Basic Education tracks rejected server-side');
    }
    $targetProgram=current(array_filter($preview['programs'],static fn($program)=>!empty($program['dept_id'])));
    calendarCheck((bool)$targetProgram,'A department-linked program is required');
    $edits[0]['dept_id']=$targetProgram['dept_id'];$edits[0]['program']=$targetProgram['code'];
    $cleared=calendarImportAudience(['dept_id'=>null,'program'=>'ALL'],$edits[0]);
    calendarCheck($cleared['dept_id']===null&&$cleared['program']==='ALL','Explicit all-departments reset overrides existing assignment');
    $bad=$edits;$bad[0]['dept_id']='999999999';
    $result=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_confirm','import_token'=>$preview['token'],'import_rows'=>json_encode($bad)]);calendarCheck(empty($result['success']),'Forged department rejected');
    $bad=$edits;$bad[0]['program']='NOT-A-PROGRAM';
    $result=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_confirm','import_token'=>$preview['token'],'import_rows'=>json_encode($bad)]);calendarCheck(empty($result['success']),'Forged program rejected');
    $otherDepartment=current(array_filter($preview['departments'],static fn($dept)=>(int)$dept['dept_id']!==(int)$targetProgram['dept_id']));
    calendarCheck((bool)$otherDepartment,'Two departments required for membership validation');
    $bad=$edits;$bad[0]['dept_id']=$otherDepartment['dept_id'];
    $result=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_confirm','import_token'=>$preview['token'],'import_rows'=>json_encode($bad)]);calendarCheck(empty($result['success']),'Mismatched department and program rejected');
    calendarCheck($edits[0]['location']==='Main Gym','Venue extracted into preview');$edits[0]['location']='Reviewed Hall <safe>';
    $bad=$edits;$bad[0]['start_date']='2026-02-30';
    $result=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_confirm','import_token'=>$preview['token'],'import_rows'=>json_encode($bad)]);calendarCheck(empty($result['success']),'Invalid date blocks confirmation');
    $result=$post($editorSession,$tokens[$editorSession],['ajax_action'=>'import_confirm','import_token'=>$preview['token'],'import_rows'=>json_encode($edits)]);calendarCheck(empty($result['success']),'Cannot confirm another account preview');
    $result=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_confirm','import_token'=>$preview['token'],'import_rows'=>json_encode($edits),'import_status'=>'draft']);
    calendarCheck(!empty($result['success']),'Draft import: '.($result['message']??''));
    $created=calendarImportExisting($db,$seriesId);calendarCheck(count($created)===1&&$created[0]['status']==='draft'&&$created[0]['description']==='School description <safe>','Draft persisted with description');
    calendarCheck($created[0]['location']==='Reviewed Hall <safe>','Edited location persisted');
    calendarCheck((int)$created[0]['dept_id']===(int)$targetProgram['dept_id']&&$created[0]['program']===$targetProgram['code'],'Imported department and program persisted');
    $id=(int)$created[0]['event_id'];$eventIds[]=$id;
    $repeat=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_confirm','import_token'=>$preview['token']]);calendarCheck(!empty($repeat['success'])&&count(calendarImportExisting($db,$seriesId))===1,'Confirmation is idempotent');
    // Simulate an administrator's category correction, then upload a date revision.
    $holiday=(int)$db->query("SELECT category_id FROM categories WHERE category_name='Holiday' LIMIT 1")->fetchColumn();
    $result=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'update','event_id'=>$id,'title'=>$created[0]['title'],'start_date'=>'2026-01-15','category_id'=>$holiday,'status'=>'draft','program'=>$targetProgram['code'],'location'=>$created[0]['location']]);
    calendarCheck(!empty($result['success']),'Imported category can be corrected with the normal Events editor');
    $result=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'fetch_one','event_id'=>$id]);calendarCheck((int)$result['event']['category_id']===$holiday&&$result['event']['description']==='School description <safe>','Normal edit preserves optional description when omitted');
    $makeDoc('Jan. 16','Foundation '.$tag,null);
    $revised=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_preview','series_id'=>$seriesId,'start_year'=>2026,'calendar_file'=>new CURLFile($path,'application/zip','revised.docx')]);
    calendarCheck(!empty($revised['success'])&&(int)$revised['rows'][0]['event_id']===$id&&(int)$revised['rows'][0]['category_id']===$holiday,'Revision preserves corrected category');
    calendarCheck($revised['rows'][0]['location']==='Reviewed Hall <safe>','Revision without venue preserves existing location');
    calendarCheck((int)$revised['rows'][0]['dept_id']===(int)$targetProgram['dept_id']&&$revised['rows'][0]['program']===$targetProgram['code'],'Revised import preserves department/program corrections');
    $changes=$revised['rows'];$changes[0]['include']=true;$changes[0]['reviewed']=true;
    $result=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_confirm','import_token'=>$revised['token'],'import_rows'=>json_encode($changes),'import_status'=>'published']);calendarCheck(!empty($result['success']),'Revision publishes: '.($result['message']??''));
    [$status,$html]=$http('',null,'/event.php?id='.$id);calendarCheck($status===200&&str_contains($html,$tag)&&str_contains($html,'School description &lt;safe&gt;'),'Published event description is safely displayed (HTTP '.$status.', title='.(int)str_contains($html,$tag).', description='.(int)str_contains($html,'School description &lt;safe&gt;').')');
    calendarCheck(str_contains($html,'Reviewed Hall &lt;safe&gt;'),'Published location escaped safely');
    // An editor's new import must stay isolated until the real approver executes it.
    $makeDoc('Feb. 9–13','Examination '.$tag);
    $queued=$post($editorSession,$tokens[$editorSession],['ajax_action'=>'import_preview','calendar_name'=>$tag.' Editor','start_year'=>2026,'calendar_file'=>new CURLFile($path,'application/zip','editor.docx')]);
    calendarCheck(!empty($queued['success']),'Editor may prepare a preview');
    $q=$db->prepare('SELECT series_id FROM calendar_import_batches WHERE token=?');$q->execute([$queued['token']]);$editorSeries=(int)$q->fetchColumn();$series[]=$editorSeries;
    $changes=$queued['rows'];$changes[0]['include']=true;$changes[0]['reviewed']=true;
    $changes[0]['dept_id']='group:college';$changes[0]['program']='ALL';
    $result=$post($editorSession,$tokens[$editorSession],['ajax_action'=>'import_confirm','import_token'=>$queued['token'],'import_rows'=>json_encode($changes),'import_status'=>'published']);
    calendarCheck(!empty($result['submitted'])&&count(calendarImportExisting($db,$editorSeries))===0,'Editor cannot publish or change events before approval');$submission=(int)$result['submission_id'];$submissions[]=$submission;
    [$status,$html]=$http($adminSession,null,'/admin/submissions.php?id='.$submission);calendarCheck($status===200&&str_contains($html,'Examination '.$tag)&&str_contains($html,'2026-02-13'),'Approver sees actual batch details');
    calendarCheck(str_contains($html,'Main Gym'),'Approver sees imported location');
    $targetDepartment=current(array_filter($preview['departments'],static fn($dept)=>(int)$dept['dept_id']===(int)$targetProgram['dept_id']));
    calendarCheck(str_contains($html,'All College'),'Approver sees department group assignment');
    session_id($adminSession);session_start();
    $message=rbacApproveRequest($submission,1);session_write_close();
    $saved=calendarImportExisting($db,$editorSeries);
    calendarCheck(count($saved)===1&&$saved[0]['status']==='published'&&(int)$saved[0]['user_id']===(int)$editor['user_id'],'Real approval publishes with original author: '.$message);
    calendarCheck($saved[0]['dept_id']===null&&$saved[0]['program']==='ALL'&&$saved[0]['audience_group']==='college','Department group targeting survives approval');
    $eventIds[]=(int)$saved[0]['event_id'];
    // Verify the published multi-day activity is present in the real website calendar.
    [$status,$html]=$http('',null,'/?evt_month=2&evt_year=2026');calendarCheck($status===200&&substr_count($html,'Examination '.$tag)>=5,'Multi-day event appears on each calendar date');
    $result=$post($editorSession,$tokens[$editorSession],['ajax_action'=>'import_confirm','import_token'=>$queued['token']]);calendarCheck(!empty($result['success'])&&count(calendarImportExisting($db,$editorSeries))===1,'Approved imports cannot be applied twice');
    // A concurrent edit invalidates a preview instead of overwriting the change.
    $makeDoc('Jan. 17','Foundation '.$tag);
    $stale=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_preview','series_id'=>$seriesId,'start_year'=>2026,'calendar_file'=>new CURLFile($path,'application/zip','stale.docx')]);
    calendarCheck(!empty($stale['success']),'Stale-preview fixture');
    $db->prepare('UPDATE events SET description=? WHERE event_id=?')->execute(['Manually corrected after preview',$id]);
    $changes=$stale['rows'];$changes[0]['include']=true;$changes[0]['reviewed']=true;
    $result=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_confirm','import_token'=>$stale['token'],'import_rows'=>json_encode($changes),'import_status'=>'published']);calendarCheck(empty($result['success'])&&str_contains($result['message'],'changed since preview'),'Concurrent edit is protected');
    $post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_cancel','import_token'=>$stale['token']]);
    // A new calendar cannot duplicate an identical existing activity.
    $makeDoc('Jan. 16','Foundation '.$tag);
    $duplicate=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_preview','calendar_name'=>$tag.' Duplicate','start_year'=>2026,'calendar_file'=>new CURLFile($path,'application/zip','duplicate.docx')]);
    calendarCheck(!empty($duplicate['success'])&&count($duplicate['rows'][0]['warnings'])>0,'Possible duplicate highlighted');
    $q=$db->prepare('SELECT series_id FROM calendar_import_batches WHERE token=?');$q->execute([$duplicate['token']]);$series[]=(int)$q->fetchColumn();
    $changes=$duplicate['rows'];$changes[0]['include']=true;$changes[0]['reviewed']=true;
    $result=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_confirm','import_token'=>$duplicate['token'],'import_rows'=>json_encode($changes),'import_status'=>'published']);calendarCheck(empty($result['success'])&&str_contains($result['message'],'identical event'),'Exact duplicates blocked at save');
    // Missing events are excluded until an administrator explicitly approves archiving.
    $makeDoc('Mar. 1','School Added '.$tag);
    $removal=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_preview','series_id'=>$seriesId,'start_year'=>2026,'calendar_file'=>new CURLFile($path,'application/zip','removal.docx')]);
    calendarCheck(!empty($removal['success'])&&count($removal['rows'])===2,'Revision identifies possible removal and addition');
    $changes=$removal['rows'];
    foreach($changes as &$change){$change['include']=$change['action']==='archive';$change['reviewed']=true;}unset($change);
    $result=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_confirm','import_token'=>$removal['token'],'import_rows'=>json_encode($changes),'import_status'=>'published']);calendarCheck(!empty($result['success']),'Explicit removal can be approved');
    $remaining=calendarImportExisting($db,$seriesId);calendarCheck(count($remaining)===1&&$remaining[0]['status']==='archived','Only approved removal applied; new excluded activity not saved');
    // Whole-calendar operations must preserve manually created events.
    $manual=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'create','title'=>'Manual '.$tag,'start_date'=>'2026-01-16','category_id'=>$holiday,'status'=>'draft','program'=>'ALL']);
    calendarCheck(!empty($manual['success']),'Manual event fixture');$manualId=(int)$manual['event_id'];$eventIds[]=$manualId;
    $archive=$post($editorSession,$tokens[$editorSession],['ajax_action'=>'import_manage_preview','series_id'=>$editorSeries,'calendar_action'=>'archive']);
    calendarCheck(!empty($archive['success'])&&count($archive['rows'])===1,'Archive preview shows only imported events');
    $result=$post($editorSession,$tokens[$editorSession],['ajax_action'=>'import_confirm','import_token'=>$archive['token']]);calendarCheck(empty($result['success']),'Whole-calendar operation requires explicit review');
    $result=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_confirm','import_token'=>$archive['token'],'reviewed_all'=>'1']);calendarCheck(empty($result['success']),'Another account cannot confirm management preview');
    $result=$post($editorSession,$tokens[$editorSession],['ajax_action'=>'import_confirm','import_token'=>$archive['token'],'reviewed_all'=>'1']);
    calendarCheck(!empty($result['submitted'])&&calendarImportExisting($db,$editorSeries)[0]['status']==='published','Editor archive waits for approval');$archiveSubmission=(int)$result['submission_id'];$submissions[]=$archiveSubmission;
    session_id($adminSession);session_start();$message=rbacApproveRequest($archiveSubmission,1);session_write_close();
    calendarCheck(calendarImportExisting($db,$editorSeries)[0]['status']==='archived','Approved archive hides imported events: '.$message);
    $q=$db->prepare('SELECT status FROM calendar_import_series WHERE series_id=?');$q->execute([$editorSeries]);calendarCheck($q->fetchColumn()==='archived','Calendar archive status persists');
    $delete=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_manage_preview','series_id'=>$editorSeries,'calendar_action'=>'delete']);calendarCheck(!empty($delete['success']),'Archived calendars may be deleted');
    $result=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_confirm','import_token'=>$delete['token'],'reviewed_all'=>'1']);calendarCheck(!empty($result['success'])&&count(calendarImportExisting($db,$editorSeries))===0,'Calendar delete removes imported event records');
    $q=$db->prepare('SELECT COUNT(*) FROM events WHERE event_id=?');$q->execute([$manualId]);calendarCheck((int)$q->fetchColumn()===1,'Manual events survive whole-calendar deletion');
    $result=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_confirm','import_token'=>$delete['token'],'reviewed_all'=>'1']);calendarCheck(!empty($result['success']),'Whole-calendar deletion is idempotent');
    $result=$post($adminSession,$tokens[$adminSession],['ajax_action'=>'import_manage_preview','series_id'=>$editorSeries,'calendar_action'=>'archive']);calendarCheck(empty($result['success']),'Deleted calendars cannot be managed again');
    // Changed events invalidate management previews as well as upload previews.
    $delete=$post($editorSession,$tokens[$editorSession],['ajax_action'=>'import_manage_preview','series_id'=>$seriesId,'calendar_action'=>'delete']);calendarCheck(!empty($delete['success']),'Editor may propose calendar deletion');
    $db->prepare('UPDATE events SET description=? WHERE event_id=?')->execute(['Changed after delete preview',$id]);
    $result=$post($editorSession,$tokens[$editorSession],['ajax_action'=>'import_confirm','import_token'=>$delete['token'],'reviewed_all'=>'1']);calendarCheck(empty($result['success'])&&str_contains($result['message'],'changed since preview'),'Stale delete preview cannot remove edited records');
    $post($editorSession,$tokens[$editorSession],['ajax_action'=>'import_cancel','import_token'=>$delete['token']]);
    $delete=$post($editorSession,$tokens[$editorSession],['ajax_action'=>'import_manage_preview','series_id'=>$seriesId,'calendar_action'=>'delete']);
    $result=$post($editorSession,$tokens[$editorSession],['ajax_action'=>'import_confirm','import_token'=>$delete['token'],'reviewed_all'=>'1']);calendarCheck(!empty($result['submitted'])&&count(calendarImportExisting($db,$seriesId))===1,'Editor delete does not remove records before approval');$deleteSubmission=(int)$result['submission_id'];$submissions[]=$deleteSubmission;
    [$status,$html]=$http($adminSession,null,'/admin/submissions.php?id='.$deleteSubmission);calendarCheck($status===200&&str_contains($html,'permanently deletes')&&str_contains($html,'Foundation '.$tag),'Approval clearly identifies permanent calendar deletion');
    session_id($adminSession);session_start();$message=rbacApproveRequest($deleteSubmission,1);session_write_close();calendarCheck(count(calendarImportExisting($db,$seriesId))===0,'Approved calendar deletion removes only linked events: '.$message);
    echo "PASS: authenticated DOCX upload, preview isolation, CSRF, ownership, invalid input, draft storage, idempotency, revisions, category preservation, public rendering and real editor-to-approver publication.\n";
    echo "PASS: whole-calendar archive/delete review, approval, stale-preview protection, idempotency and preservation of manually created events.\n";
} finally {
    foreach($series as $seriesId){$q=$db->prepare('SELECT event_id FROM calendar_import_sources WHERE series_id=?');$q->execute([$seriesId]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $eventId)$eventIds[]=(int)$eventId;}
    foreach(array_unique($eventIds) as $eventId){$db->prepare('DELETE FROM calendar_import_sources WHERE event_id=?')->execute([$eventId]);$db->prepare('DELETE FROM events WHERE event_id=?')->execute([$eventId]);}
    foreach($series as $seriesId){$db->prepare('DELETE FROM calendar_import_batches WHERE series_id=?')->execute([$seriesId]);$db->prepare('DELETE FROM calendar_import_series WHERE series_id=?')->execute([$seriesId]);}
    foreach($submissions as $submission){$db->prepare('DELETE FROM rbac_submission_chunks WHERE submission_id=?')->execute([$submission]);$db->prepare('DELETE FROM rbac_submissions WHERE submission_id=?')->execute([$submission]);}
    foreach($csrfTokens as $token)$db->prepare('DELETE FROM csrf_tokens WHERE token=?')->execute([$token]);
    foreach($sessions as $session){if(session_status()===PHP_SESSION_ACTIVE)session_write_close();session_id($session);session_start();$_SESSION=[];session_destroy();}
    unlink($path);
}
