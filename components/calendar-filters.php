<?php
require_once __DIR__ . '/../includes/event-audience.php';
/** Calendar filters share the program/department records managed by Content Editor. */
function publicCalendarFilters(array $query): array
{
    $departments=[];
    foreach (getDepartments() as $department) {
        if (!empty($department['archived_at']) || empty($department['slug'])) continue;
        $departments[$department['slug']]=$department;
    }
    $programs=getProgramCategories();
    $department=is_string($query['department']??null) && isset($departments[$query['department']]) ? $query['department'] : null;
    $program=is_string($query['program']??null) && $query['program']!=='ALL' && isset($programs[$query['program']]) ? $query['program'] : null;
    if ($department!==null) $program=null;
    $condition='';$params=[];
    if ($department!==null) {
        $departmentId=(int)$departments[$department]['dept_id'];
        if (eventDepartmentsReady()) {
            $condition=" AND (e.dept_id = ? OR (e.dept_id IS NULL AND e.program IN (SELECT program_code FROM programs WHERE dept_id = ?)))";
            $params=[$departmentId,$departmentId];
        } else {
            $condition=' AND e.program IN (SELECT program_code FROM programs WHERE dept_id = ?)';
            $params[]=$departmentId;
        }
        if(eventAudienceGroupsReady()) {
            $condition=' AND ('.substr($condition,5).' OR e.audience_group = ?)';
            $params[]=eventDepartmentGroup($departments[$department]);
        }
    } elseif ($program!==null) {
        $condition=' AND e.program = ?';$params[]=$program;
        if (eventDepartmentsReady()) {
            $condition=" AND (e.program = ? OR (e.program = 'ALL' AND e.dept_id = (SELECT dept_id FROM programs WHERE program_code = ? LIMIT 1)))";
            $params[]=$program;
        }
        if(eventAudienceGroupsReady()) {
            $condition=' AND ('.substr($condition,5)." OR EXISTS (SELECT 1 FROM programs gp JOIN departments gd ON gd.dept_id=gp.dept_id WHERE gp.program_code=? AND gd.department_type=e.audience_group AND (e.audience_group<>'college' OR (COALESCE(gd.slug,'')<>'graduate-school' AND gd.dept_name<>'Graduate School'))))";
            $params[]=$program;
        }
    }
    return ['departments'=>$departments,'programs'=>$programs,'department'=>$department,'program'=>$program,
        'condition'=>$condition,'params'=>$params,'label'=>$department!==null?$departments[$department]['dept_name']:$program];
}
