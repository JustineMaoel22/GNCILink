<?php
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
        $condition=' AND e.program IN (SELECT program_code FROM programs WHERE dept_id = ?)';
        $params[]=(int)$departments[$department]['dept_id'];
    } elseif ($program!==null) {
        $condition=' AND e.program = ?';$params[]=$program;
    }
    return ['departments'=>$departments,'programs'=>$programs,'department'=>$department,'program'=>$program,
        'condition'=>$condition,'params'=>$params,'label'=>$department!==null?$departments[$department]['dept_name']:$program];
}
