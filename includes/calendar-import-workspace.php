<?php
/** Incomplete reviews are private workspace data, never publishable event payloads. */
function calendarImportReviewExpired(array $batch, array $snapshot): bool {
    $saved = $snapshot['review']['saved_at'] ?? 0;
    return $saved ? time() - (int)$saved > 30 * 86400 : (int)$batch['age_seconds'] > 86400;
}

function calendarImportReviewRows(array $edits, array $snapshot): array {
    if (!array_is_list($edits) || count($edits)>1500) throw new InvalidArgumentException('Save at most 1,500 activities.');
    $rows=[];
    $textFields=['title'=>500,'description'=>10000,'location'=>500,'start_date'=>10,'end_date'=>10,'start_time'=>5,'end_time'=>5,'program'=>100,'dept_id'=>100,'audience_group'=>30,'category_reason'=>500];
    $len=static fn($s)=>function_exists('mb_strlen')?mb_strlen($s,'UTF-8'):strlen($s);
    $existing=array_column($snapshot['existing'],null,'event_id');
    foreach ($edits as $edit) {
        if (!is_array($edit) || !isset($edit['source_index']) || !is_int($edit['source_index']) || !isset($snapshot['rows'][$edit['source_index']])) throw new InvalidArgumentException('Invalid review source. Extract the document again.');
        $row=$snapshot['rows'][$edit['source_index']];
        $row['source_index']=$edit['source_index'];
        foreach ($textFields as $field=>$limit) {
            if (!array_key_exists($field,$edit)) continue;
            if ($edit[$field]===null && $field==='dept_id') {$row[$field]='';continue;}
            if (!is_string($edit[$field]) && !($field==='dept_id' && is_int($edit[$field]))) throw new InvalidArgumentException('Invalid review field: '.$field);
            $value=(string)$edit[$field];
            if ($len($value)>$limit) throw new InvalidArgumentException('Review field is too long: '.$field);
            $row[$field]=$value;
        }
        foreach (['include','reviewed','excluded','timeCorrected'] as $field) {
            if (isset($edit[$field]) && !is_bool($edit[$field])) throw new InvalidArgumentException('Invalid review status.');
            $row[$field]=$edit[$field]??false;
        }
        foreach (['category_id','event_id'] as $field) {
            $value=$edit[$field]??null;
            if ($value!==null && $value!=='' && ((!is_int($value)&&!is_string($value)) || !preg_match('/^[0-9]{1,10}$/D',(string)$value))) throw new InvalidArgumentException('Invalid review selection.');
            $row[$field]=$value===null||$value===''?($field==='event_id'?0:null):(int)$value;
        }
        if ($row['event_id'] && !isset($existing[$row['event_id']])) throw new InvalidArgumentException('Choose an event from this imported calendar.');
        $action=$edit['action']??'create';
        if (!in_array($action,['create','update','archive'],true) || ($action==='archive' && (($snapshot['rows'][$edit['source_index']]['action']??'')!=='archive' || $row['event_id']!==($snapshot['rows'][$edit['source_index']]['event_id']??0)))) throw new InvalidArgumentException('Invalid review action.');
        $row['action']=$action;
        foreach (['dept_ids','programs'] as $field) {
            if (!array_key_exists($field,$edit)) continue;
            if (!is_array($edit[$field]) || !array_is_list($edit[$field]) || count($edit[$field])>200) throw new InvalidArgumentException('Invalid review audience.');
            foreach ($edit[$field] as $value) if ((!is_string($value)&&!is_int($value)) || strlen((string)$value)>100) throw new InvalidArgumentException('Invalid review audience.');
            $row[$field]=$edit[$field];
        }
        if (isset($edit['category_confidence'])) {
            if (!in_array($edit['category_confidence'],['high','medium','low','manual','preserved','review'],true)) throw new InvalidArgumentException('Invalid category confidence.');
            $row['category_confidence']=$edit['category_confidence'];
        }
        // Keep trusted extraction warnings/source, even when the user edits text.
        // Publication always revalidates the edited rows independently.
        $rows[]=$row;
    }
    return $rows;
}

function calendarImportReviewView(array $view): array {
    $out=[];
    foreach (['ciSearch','ciMonth','ciYear','ciDepartment','ciCategory','ciStatus','ciSort','ciPageSize','ciIssue'] as $key) {
        $value=$view[$key]??'';
        if (!is_string($value) || strlen($value)>500) throw new InvalidArgumentException('Invalid review filter.');
        $out[$key]=$value;
    }
    $out['ciWarningsOnly']=($view['ciWarningsOnly']??false)===true;
    foreach (['ciPageSize'=>['10','20','50','100'],'ciSort'=>['date','title','category'],'ciStatus'=>['','needs','ready','approved','excluded'],'ciIssue'=>['','category','date','tba','duplicate','audience','other']] as $field=>$choices) {
        if (!in_array($out[$field],$choices,true)) $out[$field]=$choices[0];
    }
    $out['step']=in_array($view['step']??2,[2,3],true)?($view['step']??2):2;
    if (isset($view['page']) && !is_int($view['page'])) throw new InvalidArgumentException('Invalid review page.');
    $out['page']=max(1,min(1500,$view['page']??1));
    $out['import_status']=in_array($view['import_status']??'draft',['draft','published'],true)?($view['import_status']??'draft'):'draft';
    $selected=$view['selected']??[];
    if (!is_array($selected)||!array_is_list($selected)||count($selected)>1500) throw new InvalidArgumentException('Invalid review selection.');
    foreach($selected as $id)if(!is_int($id)||$id<0||$id>=1500)throw new InvalidArgumentException('Invalid review selection.');
    $out['selected']=$selected;
    return $out;
}

/** Share the existing live audience catalog with resumed workspaces. */
function calendarImportReviewCatalog(PDO $db): array {
    $departmentReady=eventDepartmentsReady();
    $departments=[];
    if ($departmentReady) {
        try {$departments=$db->query('SELECT dept_id,dept_name,slug,department_type FROM departments')->fetchAll(PDO::FETCH_ASSOC);}
        catch(PDOException $e) {$departments=$db->query('SELECT dept_id,dept_name,slug FROM departments')->fetchAll(PDO::FETCH_ASSOC);}
    }
    $programDepartments=array_column($db->query('SELECT program_code,dept_id FROM programs')->fetchAll(PDO::FETCH_ASSOC),'dept_id','program_code');
    $programs=[];
    foreach(getAnnouncementProgramCategories() as $code=>$name)if($code!=='ALL')$programs[]=['code'=>$code,'name'=>$name,'dept_id'=>$programDepartments[$code]??null];
    return ['departments'=>$departments,'programs'=>$programs,'departments_ready'=>$departmentReady,'audience_groups_ready'=>eventAudienceGroupsReady()];
}
