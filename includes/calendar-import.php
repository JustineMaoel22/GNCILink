<?php
require_once __DIR__.'/calendar-docx.php';
require_once __DIR__.'/event-audience.php';

/** Reuse the normal Events audience rules, including department/program membership. */
function calendarImportAudience(array $row, array $previous = []): array {
    $selection = array_key_exists('dept_id',$row) ? eventDepartmentSelection($row) : eventDepartmentSelection($previous);
    $program = $row['program'] ?? ($previous['program'] ?? 'ALL');
    if (!is_string($program) || ($program !== 'ALL' && !isset(getAnnouncementProgramCategories()[$program]))) {
        throw new InvalidArgumentException('Choose a College or Graduate School program. For Basic Education, select its department and leave Program on all programs.');
    }
    return normalizeEventAudience($selection,$program);
}

/** Match stored department names, compact slugs and derived acronyms; never guess between matches. */
function calendarImportDetectDepartments(array $rows, array $departments): array {
    $normalize=static fn(string $value)=>trim(preg_replace('/[^\p{L}\p{N}]+/u',' ',mb_strtolower($value,'UTF-8')));
    $aliases=[];
    foreach($departments as $department) {
        $name=$normalize($department['dept_name']);$words=explode(' ',$name);$acronym='';
        foreach($words as $word)if($word!==''&&!in_array($word,['of','and','the','in','for'],true))$acronym.=mb_substr($word,0,1,'UTF-8');
        $names=[$name];
        if(mb_strlen($acronym)>=2&&mb_strlen($acronym)<=10)$names[]=$acronym;
        $slug=$normalize($department['slug']??'');
        if($slug!==''&&!str_contains($slug,' ')&&mb_strlen($slug)>=2&&mb_strlen($slug)<=10)$names[]=$slug;
        $aliases[(int)$department['dept_id']]=array_unique($names);
    }
    foreach($rows as &$row) {
        $text=' '.$normalize(($row['title']??'').' '.($row['description']??'')).' ';$matches=[];
        foreach($aliases as $id=>$names)foreach($names as $name)if($name!==''&&str_contains($text,' '.$name.' ')){$matches[]=$id;break;}
        $row['dept_id']=count($matches)===1?$matches[0]:null;
        $row['program']='ALL';
        if(count($matches)>1)$row['warnings'][]='Several departments match this activity. Choose its department before approving.';
    }
    unset($row);
    return $rows;
}

function calendarImportReady(PDO $db): bool {
    try { $db->query('SELECT description FROM events LIMIT 0'); $db->query('SELECT token,calendar_action FROM calendar_import_batches LIMIT 0'); $db->query('SELECT status FROM calendar_import_series LIMIT 0'); return true; }
    catch (PDOException $e) { return false; }
}
function calendarImportCategories(PDO $db): array {
    return $db->query("SELECT category_id, category_name FROM categories WHERE category_name NOT IN ('Reminder','Job Opportunities','Scholarship') ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);
}
function calendarEventSignature(array $event): string {
    $fields=[];
    foreach (['event_id','title','start_date','end_date','category_id','description','status','location','program','dept_id','audience_group','user_id','updated_at'] as $key) $fields[$key]=(string)($event[$key]??'');
    return hash('sha256',json_encode($fields,JSON_UNESCAPED_UNICODE));
}
function calendarImportExisting(PDO $db, int $series): array {
    $q=$db->prepare('SELECT e.* FROM events e JOIN calendar_import_sources s ON s.event_id=e.event_id WHERE s.series_id=? ORDER BY e.start_date');
    $q->execute([$series]); return $q->fetchAll(PDO::FETCH_ASSOC);
}
function calendarTitleKey(string $title): string { return strtolower(trim(preg_replace('/\s+/u',' ',$title))); }

function calendarImportCompare(array $rows, array $existing, array $all): array {
    $used=[]; $seen=[];
    foreach ($rows as &$row) {
        $matches=array_values(array_filter($existing,static fn($e)=>calendarTitleKey($e['title'])===calendarTitleKey($row['title'])));
        if (!$matches && $row['start_date']!=='') $matches=array_values(array_filter($existing,static fn($e)=>substr($e['start_date']??'',0,10)===$row['start_date']));
        if(count($matches)===1 && !isset($used[$matches[0]['event_id']])) {
            $event=$matches[0];$id=(int)$event['event_id'];$used[$id]=true;
            $row['event_id']=$id; $row['action']='update'; $row['category_id']=$event['category_id'];
            $row['dept_id']=$event['dept_id']??null; $row['program']=$event['program']??'ALL';
            $row['audience_group']=$event['audience_group']??'all';
            if(empty($row['location_provided'])&&empty($row['location']))$row['location']=$event['location']??'';
            $row['warnings']=array_values(array_filter($row['warnings'],static fn($w)=>$w!=='Category needs review.'));
            $row['warnings'][]='Revised activity: check the changes. The current category has been kept.';
            $row['previous']=$event;
        } elseif ($matches) $row['warnings'][]='Several existing activities may match. Choose the correct existing event or exclude this row.';
        $key=calendarTitleKey($row['title']).'|'.$row['start_date'];
        if(isset($seen[$key]))$row['warnings'][]='Possible duplicate inside this document.';
        $seen[$key]=true;
        foreach ($all as $event) {
            if((int)$event['event_id']===$row['event_id'])continue;
            if(calendarTitleKey($event['title'])===calendarTitleKey($row['title']) || ($row['start_date']!==''&&substr($event['start_date']??'',0,10)===$row['start_date']&&similar_text(calendarTitleKey($event['title']),calendarTitleKey($row['title']))>min(strlen($event['title']),strlen($row['title']))*0.75)) {
                $row['warnings'][]='Possible duplicate of existing event #'.$event['event_id'].': '.$event['title'];
            }
        }
    }
    unset($row);
    foreach($existing as $event) if(!isset($used[$event['event_id']])) {
        $rows[]=['title'=>$event['title'],'start_date'=>substr($event['start_date']??'',0,10),'end_date'=>substr($event['end_date']??'',0,10),'start_time'=>'','end_time'=>'','description'=>$event['description']??'','category_id'=>$event['category_id'],'event_id'=>(int)$event['event_id'],'action'=>'archive','warnings'=>['Possibly removed from this revision. Excluded by default; select only to hide it after approval.'],'source'=>'Not matched in the revised document.','previous'=>$event];
    }
    return $rows;
}

function calendarImportValidate(array $edits, array $preview, array $existing, array $categories): array {
    if(count($edits)>1500)throw new InvalidArgumentException('Too many activities.');
    $existingById=array_column($existing,null,'event_id');$catIds=array_map('intval',array_column($categories,'category_id'));$out=[];$targets=[];$keys=[];
    foreach($edits as $index=>$edit) {
        if(!is_array($edit)||empty($edit['include']))continue;
        if(!isset($preview[$index]))throw new InvalidArgumentException('The preview changed. Upload the document again.');
        $original=$preview[$index];
        if(!is_scalar($edit['event_id']??0)||!ctype_digit((string)($edit['event_id']??0)))throw new InvalidArgumentException('Choose a valid imported event target.');
        $id=(int)($edit['event_id']??0);
        if($id && (!isset($existingById[$id])||isset($targets[$id])))throw new InvalidArgumentException('Choose each existing imported event only once. Manually created events cannot be overwritten.');
        if($id)$targets[$id]=true;
        if(empty($edit['reviewed']))throw new InvalidArgumentException('Review and approve each selected activity.');
        $operation=$original['action']==='archive'?'archive':($id?'update':'create');
        if($operation==='archive' && $id!==(int)$original['event_id'])throw new InvalidArgumentException('Invalid removal target.');
        $row=['event_id'=>$id,'action'=>$operation,'signature'=>$id?calendarEventSignature($existingById[$id]):null];
        if($operation==='archive') { $row['title']=$existingById[$id]['title'];$row['start_date']=$existingById[$id]['start_date'];$row['end_date']=$existingById[$id]['end_date'];$row['category_id']=$existingById[$id]['category_id'];$row['location']=$existingById[$id]['location']??'';$out[]=$row;continue; }
        foreach(['title'=>500,'description'=>10000,'location'=>500,'start_date'=>10,'end_date'=>10,'start_time'=>5,'end_time'=>5] as $field=>$limit) {
            if(!is_scalar($edit[$field]??''))throw new InvalidArgumentException('Invalid event field.');
            $row[$field]=trim((string)($edit[$field]??($field==='location'&&$id?($existingById[$id]['location']??''):'')));
            if(mb_strlen($row[$field])>$limit)throw new InvalidArgumentException('An event field is too long: '.$field);
        }
        if($row['title']===''||!calendarValidDate($row['start_date'])||($row['end_date']!==''&&!calendarValidDate($row['end_date'])))throw new InvalidArgumentException('Every selected activity needs a title and valid dates (1900–2050).');
        foreach(['start_time','end_time'] as $field) if($row[$field]!==''&&!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$row[$field]))throw new InvalidArgumentException('Use valid 24-hour times.');
        $start=$row['start_date'].' '.($row['start_time']?:'00:00').':00';
        $end=($row['end_date']?:($row['end_time']!==''?$row['start_date']:''));
        $end=$end!==''?$end.' '.($row['end_time']?:'00:00').':00':null;
        if($end!==null&&$end<$start)throw new InvalidArgumentException('An activity ends before it starts. Correct the date or time.');
        $row['start_date']=$start;$row['end_date']=$end;
        $cat=$edit['category_id']??'';
        if($cat!==''&&$cat!==null&&(!is_scalar($cat)||!ctype_digit((string)$cat)||!in_array((int)$cat,$catIds,true)))throw new InvalidArgumentException('Choose an existing event category.');
        $row['category_id']=$cat!==''&&$cat!==null?(int)$cat:null;
        if($row['category_id']===null)throw new InvalidArgumentException('Choose a category for every selected activity. Exclude uncertain activities until they can be categorized.');
        $row += calendarImportAudience($edit, $id ? $existingById[$id] : []);
        $key=calendarTitleKey($row['title']).'|'.$start;
        if(isset($keys[$key]))throw new InvalidArgumentException('The selection contains duplicate events. Exclude the duplicate row.');
        $keys[$key]=true;$out[]=$row;
    }
    if(!$out)throw new InvalidArgumentException('Select at least one reviewed activity.');
    return $out;
}

function calendarImportApply(PDO $db, array $batch, array $rows, string $status, int $actor): void {
    if(!hasPermission('publish_event'))throw new InvalidArgumentException('Only an approver can apply calendar imports.');
    $db->beginTransaction();
    try {
        $q=$db->prepare('SELECT * FROM calendar_import_series WHERE series_id=? FOR UPDATE');$q->execute([$batch['series_id']]);$series=$q->fetch(PDO::FETCH_ASSOC);
        $q=$db->prepare('SELECT state FROM calendar_import_batches WHERE token=? FOR UPDATE');$q->execute([$batch['token']]);$state=$q->fetchColumn();
        if($state==='applied') { $db->commit();return; }
        if(!in_array($state,['ready','submitted'],true)||!$series||(int)$series['revision']!==(int)$batch['base_revision'])throw new InvalidArgumentException('This calendar has changed. Upload the revised document again to compare fresh records.');
        if($series['status']==='deleted')throw new InvalidArgumentException('This calendar has been deleted.');
        $calendarAction=$batch['calendar_action']??null;
        if($calendarAction==='delete'&&!hasPermission('delete_event'))throw new InvalidArgumentException('You do not have permission to delete calendar events.');
        if(!$calendarAction&&$series['status']!=='active')throw new InvalidArgumentException('This calendar is archived. Create a new calendar for future imports.');
        $cats=array_map('intval',array_column(calendarImportCategories($db),'category_id'));
        // Lock all events to serialize duplicate checks with other imports and manual updates.
        $all=$db->query('SELECT * FROM events FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
        $existing=array_column(calendarImportExisting($db,(int)$batch['series_id']),null,'event_id');
        if($calendarAction) {
            $expected=array_map('intval',array_column($rows,'event_id'));$actual=array_map('intval',array_keys($existing));sort($expected);sort($actual);
            if($expected!==$actual)throw new InvalidArgumentException('Calendar events changed since review. Open a fresh preview.');
        }
        foreach($rows as $row) {
            $id=(int)$row['event_id'];
            if($id && (!isset($existing[$id])||!hash_equals($row['signature'],calendarEventSignature($existing[$id]))))throw new InvalidArgumentException('An existing event changed since preview. Upload again before applying this import.');
            if($row['action']==='delete') {
                if($calendarAction!=='delete'||!$id)throw new InvalidArgumentException('Invalid calendar deletion.');
                $db->prepare('DELETE FROM calendar_import_sources WHERE event_id=? AND series_id=?')->execute([$id,$batch['series_id']]);
                $db->prepare('DELETE FROM events WHERE event_id=?')->execute([$id]);
                logActivity($actor,'DELETE','events',$id,'Deleted imported calendar event: '.$row['title']);continue;
            }
            if($row['action']==='archive') {
                $db->prepare("UPDATE events SET status='archived' WHERE event_id=?")->execute([$id]);
                logActivity($actor,'UPDATE','events',$id,'Archived event explicitly removed during calendar import.');continue;
            }
            if(!in_array((int)$row['category_id'],$cats,true))throw new InvalidArgumentException('An event category changed. Preview the document again.');
            foreach($all as $event) if((int)$event['event_id']!==$id&&calendarTitleKey($event['title'])===calendarTitleKey($row['title'])&&$event['start_date']===$row['start_date'])throw new InvalidArgumentException('An identical event already exists: '.$row['title'].'. Exclude it or update its imported record.');
            $audience=calendarImportAudience($row,$id?$existing[$id]:[]);
            $values=[$row['title'],$row['start_date'],$row['end_date'],$row['category_id'],$row['description'],$status,$row['location']??($id?($existing[$id]['location']??''):''),$audience['program']];
            $hasDepartments=eventDepartmentsReady();
            if($hasDepartments)$values[]=$audience['dept_id'];
            $hasGroups=eventAudienceGroupsReady();
            if($hasGroups)$values[]=$audience['audience_group'];
            if($id) {
                $values[]=$id;
                $db->prepare('UPDATE events SET title=?, start_date=?, end_date=?, category_id=?, description=?, status=?, location=?, program=?'.($hasDepartments?', dept_id=?':'').($hasGroups?', audience_group=?':'').' WHERE event_id=?')->execute($values);
            } else {
                $values[]=rbacContentOwnerId();
                $db->prepare('INSERT INTO events(title,start_date,end_date,category_id,description,status,location,program'.($hasDepartments?',dept_id':'').($hasGroups?',audience_group':'').',user_id) VALUES(?,?,?,?,?,?,?,?,'.($hasDepartments?'?,':'').($hasGroups?'?,':'').'?)')->execute($values);
                $id=(int)$db->lastInsertId();
                $db->prepare('INSERT INTO calendar_import_sources(event_id,series_id) VALUES(?,?)')->execute([$id,$batch['series_id']]);
            }
            $all[]=['event_id'=>$id,'title'=>$row['title'],'start_date'=>$row['start_date']];
            logActivity($actor,$row['action']==='create'?'CREATE':'UPDATE','events',$id,'Calendar import: '.$row['title']);
            if($status==='published')logActivity($actor,'PUBLISH','events',$id,'Published reviewed calendar import.');
        }
        $db->prepare('UPDATE calendar_import_series SET revision=revision+1, status=? WHERE series_id=?')->execute([$calendarAction==='delete'?'deleted':($calendarAction==='archive'?'archived':'active'),$batch['series_id']]);
        $db->prepare("UPDATE calendar_import_batches SET state='applied' WHERE token=?")->execute([$batch['token']]);
        logActivity($actor,$calendarAction==='delete'?'DELETE':($calendarAction==='archive'?'ARCHIVE':'IMPORT'),'calendar_import_series',(int)$batch['series_id'],'Applied '.count($rows).' reviewed calendar operations.');
        $db->commit();
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack();throw $e; }
}

/** Called only after login and the existing Events CSRF check. */
function calendarImportHandle(PDO $db, string $action, int $userId): array {
    if(!hasPermission('create_event')||!hasPermission('edit_event')) { http_response_code(403);throw new InvalidArgumentException('You do not have permission to import calendars.'); }
    if(!calendarImportReady($db))throw new InvalidArgumentException('Calendar import storage is not ready. Ask the developer to apply database/calendar-import-migration.sql.');
    $categories=calendarImportCategories($db);
    if($action==='import_manage_preview') {
        $operation=$_POST['calendar_action']??'';
        if(!in_array($operation,['archive','delete'],true))throw new InvalidArgumentException('Choose Archive or Delete.');
        if($operation==='delete'&&!hasPermission('delete_event')) {http_response_code(403);throw new InvalidArgumentException('You do not have permission to delete calendar events.');}
        $q=$db->prepare("SELECT * FROM calendar_import_series WHERE series_id=? AND status<>'deleted'");$q->execute([(int)($_POST['series_id']??0)]);$series=$q->fetch(PDO::FETCH_ASSOC);
        if(!$series)throw new InvalidArgumentException('Imported calendar not found.');
        if($operation==='archive'&&$series['status']==='archived')throw new InvalidArgumentException('This calendar is already archived.');
        $existing=calendarImportExisting($db,(int)$series['series_id']);
        $rows=[];
        foreach($existing as $event)$rows[]=['event_id'=>(int)$event['event_id'],'title'=>$event['title'],'start_date'=>$event['start_date'],'end_date'=>$event['end_date'],'location'=>$event['location']??'','category_id'=>$event['category_id'],'action'=>$operation,'signature'=>calendarEventSignature($event)];
        $token=bin2hex(random_bytes(32));
        $db->prepare('INSERT INTO calendar_import_batches(token,series_id,user_id,base_revision,filename,preview_json,calendar_action) VALUES(?,?,?,?,?,?,?)')->execute([$token,$series['series_id'],$userId,$series['revision'],$series['name'],json_encode(['rows'=>$rows,'existing'=>$existing],JSON_THROW_ON_ERROR),$operation]);
        logActivity($userId,'IMPORT_PREVIEW','calendar_import_series',(int)$series['series_id'],'Reviewed calendar '.$operation.' request.');
        return ['success'=>true,'token'=>$token,'rows'=>$rows,'calendar_name'=>$series['name'],'calendar_action'=>$operation];
    }
    if($action==='import_preview') {
        $file=$_FILES['calendar_file']??[];
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')||strtolower(pathinfo($file['name']??'',PATHINFO_EXTENSION))!=='docx')throw new InvalidArgumentException('Choose a Word .docx file up to 5 MB.');
        $year=filter_var($_POST['start_year']??'',FILTER_VALIDATE_INT);
        if(!$year||$year<1900||$year>2050)throw new InvalidArgumentException('Enter the calendar year (1900–2050).');
        $seriesId=(int)($_POST['series_id']??0);
        $series=null;
        if($seriesId) {
            $q=$db->prepare('SELECT * FROM calendar_import_series WHERE series_id=?');$q->execute([$seriesId]);$series=$q->fetch(PDO::FETCH_ASSOC);
            if(!$series||$series['status']!=='active')throw new InvalidArgumentException('Choose an active calendar for revised documents.');
            $year=(int)$series['start_year'];
        }
        $rows=calendarExtractXml(calendarDocxXml($file['tmp_name']),$year,$categories);
        if(eventDepartmentsReady())$rows=calendarImportDetectDepartments($rows,$db->query('SELECT dept_id,dept_name,slug FROM departments')->fetchAll(PDO::FETCH_ASSOC));
        if(!$series) {
            $name=trim((string)($_POST['calendar_name']??''));
            if($name===''||mb_strlen($name)>200)throw new InvalidArgumentException('Enter a calendar name up to 200 characters.');
            $db->prepare('INSERT INTO calendar_import_series(name,start_year,user_id) VALUES(?,?,?)')->execute([$name,$year,$userId]);
            $seriesId=(int)$db->lastInsertId();$series=['revision'=>0];
        }
        $existing=calendarImportExisting($db,$seriesId);
        $all=$db->query('SELECT event_id,title,start_date FROM events')->fetchAll(PDO::FETCH_ASSOC);
        $rows=calendarImportCompare($rows,$existing,$all);
        $token=bin2hex(random_bytes(32));
        $db->prepare('INSERT INTO calendar_import_batches(token,series_id,user_id,base_revision,filename,preview_json) VALUES(?,?,?,?,?,?)')->execute([$token,$seriesId,$userId,$series['revision'],mb_substr(basename($file['name']),0,255),json_encode(['rows'=>$rows,'existing'=>$existing],JSON_THROW_ON_ERROR)]);
        logActivity($userId,'IMPORT_PREVIEW','events',null,'Prepared calendar import preview ('.count($rows).' rows).');
        $programDepartments=array_column($db->query('SELECT program_code,dept_id FROM programs')->fetchAll(PDO::FETCH_ASSOC),'dept_id','program_code');
        $programs=[];
        foreach(getAnnouncementProgramCategories() as $code=>$name)if($code!=='ALL')$programs[]=['code'=>$code,'name'=>$name,'dept_id'=>$programDepartments[$code]??null];
        return ['success'=>true,'token'=>$token,'rows'=>$rows,'existing'=>$existing,'categories'=>$categories,
            'departments'=>$db->query('SELECT dept_id,dept_name FROM departments ORDER BY dept_name')->fetchAll(PDO::FETCH_ASSOC),
            'programs'=>$programs,'departments_ready'=>eventDepartmentsReady(),'audience_groups_ready'=>eventAudienceGroupsReady()];
    }
    $token=$_POST['import_token']??'';
    if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/',$token))throw new InvalidArgumentException('Invalid import preview.');
    $q=$db->prepare('SELECT *, TIMESTAMPDIFF(SECOND,created_at,NOW()) AS age_seconds FROM calendar_import_batches WHERE token=?');$q->execute([$token]);$batch=$q->fetch(PDO::FETCH_ASSOC);
    $worker=!empty($_SESSION['_rbac_worker']);
    if(!$batch||(int)$batch['user_id']!==($worker?rbacContentOwnerId():$userId))throw new InvalidArgumentException('Import preview not found for your account.');
    if($action==='import_cancel') {
        $db->prepare("UPDATE calendar_import_batches SET state='cancelled', preview_json='[]' WHERE token=? AND state='preview'")->execute([$token]);
        logActivity($userId,'IMPORT_CANCEL','events',null,'Cancelled calendar preview.');return ['success'=>true];
    }
    if($action!=='import_confirm')throw new InvalidArgumentException('Unknown calendar import action.');
    if($batch['state']==='applied')return ['success'=>true,'message'=>'This import has already been applied.'];
    if($worker) {
        if($batch['state']!=='submitted')throw new InvalidArgumentException('This import is not awaiting approval.');
        calendarImportApply($db,$batch,json_decode($batch['approved_json'],true,64,JSON_THROW_ON_ERROR),'published',$userId);
        return ['success'=>true,'message'=>!empty($batch['calendar_action'])?'Calendar '.$batch['calendar_action'].' request approved and applied.':'Calendar import approved and published.'];
    }
    if($batch['state']!=='preview')throw new InvalidArgumentException('This preview has already been submitted. Upload again to make another revision.');
    if((int)$batch['age_seconds']>86400)throw new InvalidArgumentException('This preview expired. Upload again to compare current events.');
    $snapshot=json_decode($batch['preview_json'],true,64,JSON_THROW_ON_ERROR);
    $preview=$snapshot['rows'];
    $raw=(string)($_POST['import_rows']??'[]');
    if(strlen($raw)>20*1024*1024)throw new InvalidArgumentException('The edited calendar is too large. Import fewer activities at a time.');
    $edits=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($edits))throw new InvalidArgumentException('Invalid preview selection.');
    $existing=calendarImportExisting($db,(int)$batch['series_id']);
    // Preserve the preview snapshot: concurrent edits must not become the new baseline.
    $existingById=array_column($existing,null,'event_id');
    foreach($snapshot['existing'] as $event) if(!isset($existingById[$event['event_id']])||calendarEventSignature($event)!==calendarEventSignature($existingById[$event['event_id']]))throw new InvalidArgumentException('An imported event changed since preview. Upload again before confirming.');
    if(!empty($batch['calendar_action'])) {
        if(($_POST['reviewed_all']??'')!=='1')throw new InvalidArgumentException('Review the affected events and confirm this calendar action.');
        if($batch['calendar_action']==='delete'&&!hasPermission('delete_event')) {http_response_code(403);throw new InvalidArgumentException('You do not have permission to delete calendar events.');}
        $rows=$preview;
    } else $rows=calendarImportValidate($edits,$preview,$existing,$categories);
    $status=hasPermission('publish_event')&&($_POST['import_status']??'')==='published'?'published':'draft';
    $q=$db->prepare("UPDATE calendar_import_batches SET approved_json=?, state='ready' WHERE token=? AND state='preview'");
    $q->execute([json_encode($rows,JSON_THROW_ON_ERROR),$token]);
    if($q->rowCount()!==1)throw new InvalidArgumentException('This preview is already being confirmed.');
    try {
        if(!hasPermission('publish_event')) {
            $id=rbacSaveSubmission('request','/admin/events.php','Calendar '.($batch['calendar_action']?:'import').': '.$batch['filename'],['post'=>['ajax_action'=>'import_confirm','import_token'=>$token],'files'=>[]]);
            $db->prepare("UPDATE calendar_import_batches SET state='submitted' WHERE token=? AND state='ready'")->execute([$token]);
            return ['success'=>true,'submitted'=>true,'submission_id'=>$id,'message'=>'Calendar request submitted for Super Admin approval. Published content is unchanged.'];
        }
        calendarImportApply($db,$batch,$rows,$status,$userId);
        return ['success'=>true,'message'=>!empty($batch['calendar_action'])?($batch['calendar_action']==='delete'?'Imported calendar and its events deleted.':'Imported calendar archived. Its events are hidden from the website.') : ($status==='published'?'Reviewed calendar events published.':'Calendar events saved as drafts. Publish them through Events when ready.')];
    } catch(Throwable $e) {
        $db->prepare("UPDATE calendar_import_batches SET state='preview' WHERE token=? AND state='ready'")->execute([$token]);throw $e;
    }
}
