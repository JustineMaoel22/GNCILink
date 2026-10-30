<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/calendar-import.php';
require_once __DIR__.'/../components/calendar-filters.php';
define('CURRENT_PROGRAM','GROUP_TEST');
require_once __DIR__.'/../components/bulletin-board.php';
function groupCheck(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$db=getDB();groupCheck(eventAudienceGroupsReady(),'Run the audience group migration first.');
foreach(['basic-education','college'] as $group) {
    $audience=normalizeEventAudience('group:'.$group,'ALL');
    groupCheck($audience['dept_id']===null&&$audience['audience_group']===$group,'Group normalizes without a fake department ID');
    try{normalizeEventAudience('group:'.$group,'BSIT');throw new RuntimeException('Group with a specific program accepted');}catch(InvalidArgumentException $e){}
}
try{normalizeEventAudience('group:forged','ALL');throw new RuntimeException('Forged group accepted');}catch(InvalidArgumentException $e){}
$db->beginTransaction();
try {
    $ids=[];
    $insert=$db->prepare("INSERT INTO events(title,start_date,status,program,audience_group) VALUES (?,DATE_ADD(NOW(),INTERVAL 3 DAY),'published','ALL',?)");
    foreach(['basic-education','college','all'] as $group){$insert->execute(['Group regression '.$group,$group]);$ids[$group]=(int)$db->lastInsertId();}
    $seenGroups=[];
    foreach(getDepartments() as $department) {
        if(empty($department['slug'])||!empty($department['archived_at']))continue;
        $group=eventDepartmentGroup($department);$seenGroups[$group]=true;
        $filter=publicCalendarFilters(['department'=>$department['slug']]);
        $query=$db->prepare('SELECT event_id FROM events e WHERE e.event_id IN ('.implode(',',$ids).')'.$filter['condition']);$query->execute($filter['params']);
        $actual=array_map('intval',$query->fetchAll(PDO::FETCH_COLUMN));
        groupCheck($actual===($group==='graduate'?[]:[$ids[$group]]),'Department filter respects '.$group.' group: '.$department['dept_name']);
    }
    groupCheck(isset($seenGroups['basic-education'],$seenGroups['college'],$seenGroups['graduate']),'All three audience types exercised');
    foreach($db->query('SELECT p.program_code,d.* FROM programs p JOIN departments d ON d.dept_id=p.dept_id')->fetchAll(PDO::FETCH_ASSOC) as $program) {
        $group=eventDepartmentGroup($program);$filter=publicCalendarFilters(['program'=>$program['program_code']]);
        $query=$db->prepare('SELECT event_id FROM events e WHERE e.event_id IN ('.implode(',',$ids).')'.$filter['condition']);$query->execute($filter['params']);
        $actual=array_map('intval',$query->fetchAll(PDO::FETCH_COLUMN));
        groupCheck($actual===($group==='graduate'?[]:[$ids[$group]]),'Program calendar filter respects group');
        $bulletin=array_map('intval',array_column(gnc_get_global_events($program['program_code'],10000),'event_id'));
        foreach($ids as $audience=>$id)groupCheck(in_array($id,$bulletin,true)===($audience==='all'||$audience===$group),'Program bulletin respects group');
    }
    $old=['dept_id'=>null,'audience_group'=>'college','program'=>'ALL'];
    groupCheck(calendarImportAudience($old)['audience_group']==='college','Stored group survives import apply');
    groupCheck(calendarImportAudience(['dept_id'=>'','program'=>'ALL'],$old)['audience_group']==='all','All-departments reset clears group');
    $compared=calendarImportCompare([['event_id'=>0,'title'=>'Group event','start_date'=>'2026-11-01','category_id'=>2,'warnings'=>[]]],[$old+['event_id'=>99,'title'=>'Group event','start_date'=>'2026-11-01','category_id'=>2]],[]);
    groupCheck($compared[0]['audience_group']==='college','Revised document preserves group');
}finally{$db->rollBack();}
echo "PASS: department groups, validation, revised imports, department/program public calendar and bulletin isolation, including Graduate School exclusion; fixtures rolled back.\n";
