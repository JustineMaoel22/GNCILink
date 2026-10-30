<?php
function eventAudienceGroupsReady(): bool {
    static $ready=null;
    if($ready!==null)return $ready;
    try { $ready=(bool)getDB()->query("SHOW COLUMNS FROM events LIKE 'audience_group'")->fetch(); }
    catch(Throwable $e){$ready=false;}
    return $ready;
}

function eventDepartmentSelection(array $event) {
    return !empty($event['audience_group'])&&$event['audience_group']!=='all'?'group:'.$event['audience_group']:($event['dept_id']??null);
}

/** Group selections use one event record and never masquerade as department IDs. */
function normalizeEventAudience($selection, string $program): array {
    if(is_string($selection)&&str_starts_with($selection,'group:')) {
        $group=substr($selection,6);
        if(!in_array($group,['basic-education','college'],true))throw new InvalidArgumentException('Choose a valid department group.');
        if(!eventAudienceGroupsReady())throw new InvalidArgumentException('Department groups need database/migrate-event-audience-groups.php to be run on this server.');
        if($program!=='ALL')throw new InvalidArgumentException('Leave Program on all programs for a department group.');
        return ['dept_id'=>null,'program'=>'ALL','audience_group'=>$group];
    }
    $department=normalizeEventDepartment($selection);
    validateEventProgramDepartment($department,$program);
    return ['dept_id'=>$department,'program'=>$program,'audience_group'=>'all'];
}

/** Graduate School has its own audience even where its stored type is college. */
function eventDepartmentGroup(array $department): string {
    if(($department['slug']??'')==='graduate-school'||strcasecmp(trim($department['dept_name']??''),'Graduate School')===0)return 'graduate';
    return ($department['department_type']??'')==='basic-education'?'basic-education':'college';
}

/** Department targeting is optional until the additive migration is deployed. */
function eventDepartmentsReady(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try { $ready = (bool)getDB()->query("SHOW COLUMNS FROM events LIKE 'dept_id'")->fetch(); }
    catch (Throwable $e) { $ready = false; }
    return $ready;
}

function normalizeEventDepartment($value): ?int {
    if ($value === null || $value === '') return null;
    if (!is_scalar($value) || !ctype_digit((string)$value) || (int)$value < 1) {
        throw new InvalidArgumentException('Choose a valid department.');
    }
    if (!eventDepartmentsReady()) throw new InvalidArgumentException('Department targeting needs the Events department migration.');
    $stmt = getDB()->prepare('SELECT dept_id FROM departments WHERE dept_id = ?');
    $stmt->execute([(int)$value]);
    if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Choose a valid department.');
    return (int)$value;
}

function validateEventProgramDepartment(?int $department, string $program): void {
    if ($department === null || $program === 'ALL') return;
    $stmt = getDB()->prepare('SELECT 1 FROM programs WHERE dept_id = ? AND program_code = ?');
    $stmt->execute([$department, $program]);
    if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Choose a program in the selected department, or leave Program on all programs.');
}
