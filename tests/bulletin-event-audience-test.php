<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('CURRENT_PROGRAM', 'BSCE');
require_once __DIR__ . '/../components/bulletin-board.php';
$db = getDB();
foreach ([
    'programs' => 'program_code VARCHAR(20) PRIMARY KEY,dept_id INT',
    'departments' => 'dept_id INT PRIMARY KEY,dept_name VARCHAR(100),slug VARCHAR(100),department_type VARCHAR(30)',
    'events' => 'event_id INT PRIMARY KEY,title VARCHAR(100),location VARCHAR(100),start_date DATETIME,end_date DATETIME NULL,status VARCHAR(20),program VARCHAR(20),dept_id INT NULL,audience_group VARCHAR(30)',
    'event_programs' => 'event_id INT,program_code VARCHAR(20)',
    'event_departments' => 'event_id INT,dept_id INT',
] as $table => $columns) $db->exec("CREATE TEMPORARY TABLE $table ($columns)");
$db->exec("INSERT INTO programs VALUES('BSCE',1),('BAEL',2),('BEEd',2),('BSIT',3),('MA',4),('JHS',5)");
$db->exec("INSERT INTO departments VALUES(1,'Engineering','coe','college'),(2,'Education','cased','college'),(3,'Computing','coc','college'),(4,'Graduate School','graduate-school','college'),(5,'Junior High','jhs','basic-education')");
$insert = $db->prepare("INSERT INTO events VALUES(?,?,'Campus','2099-12-25 09:00:00',NULL,?,?,?,'all')");
foreach ([
    [1,'School-wide','published','ALL',null],
    [2,'Selected programs','published','ALL',null],
    [3,'Selected departments','published','ALL',null],
    [4,'Matching program wrong department','published','BSCE',2],
    [5,'Legacy department','published','ALL',1],
    [6,'Legacy program','published','BSCE',null],
    [7,'Stale program','published','BSCE',null],
    [8,'Stale department','published','ALL',1],
    [9,'Draft','draft','ALL',null],
    [10,'College group','published','ALL',null],
    [11,'Today all day','published','ALL',null],
    [12,'Ongoing event','published','ALL',null],
    [13,'Past event','published','ALL',null],
    [14,'Pending event','pending','ALL',null],
] as [$id,$title,$status,$program,$department]) $insert->execute([$id,$title,$status,$program,$department]);
$db->exec("INSERT INTO event_programs VALUES(2,'BAEL'),(2,'BEEd'),(7,'BAEL')");
$db->exec('INSERT INTO event_departments VALUES(3,1),(3,3),(8,2)');
$db->exec("UPDATE events SET audience_group='college' WHERE event_id=10");
$db->exec('UPDATE events SET start_date=CURRENT_DATE() WHERE event_id=11');
$db->exec('UPDATE events SET start_date=DATE_SUB(NOW(),INTERVAL 1 DAY),end_date=DATE_ADD(NOW(),INTERVAL 1 DAY) WHERE event_id=12');
$db->exec('UPDATE events SET start_date=DATE_SUB(NOW(),INTERVAL 2 DAY) WHERE event_id=13');
foreach ([
    'BSCE'=>[1,3,5,6,10,11,12],
    'BAEL'=>[1,2,7,8,10,11,12],
    'BEEd'=>[1,2,8,10,11,12],
    'BSIT'=>[1,3,10,11,12],
    'MA'=>[1,11,12],
    'JHS'=>[1,11,12],
    'UNKNOWN'=>[1,11,12],
] as $program=>$expected) {
    $actual=array_map('intval',array_column(gnc_get_global_events($program,50),'event_id'));
    sort($actual);
    if ($actual!==$expected) throw new RuntimeException('Incorrect event audience for '.$program.': '.json_encode($actual));
}
if (count(gnc_get_global_events('BSCE',2))!==2) throw new RuntimeException('Limit regression.');
// Compare the destination calendar's audience to the Bulletin Board query.
function getDepartments(): array { return getDB()->query('SELECT * FROM departments')->fetchAll(PDO::FETCH_ASSOC); }
function getProgramCategories(): array {
    return array_fill_keys(getDB()->query('SELECT program_code FROM programs')->fetchAll(PDO::FETCH_COLUMN), 'Program');
}
function getProgramDepartmentMap(): array {
    return array_column(getDB()->query('SELECT program_code,dept_id FROM programs')->fetchAll(PDO::FETCH_ASSOC), 'dept_id', 'program_code');
}
require_once __DIR__ . '/../components/calendar-filters.php';
foreach (['BSCE','BAEL','BEEd','BSIT','MA','JHS'] as $program) {
    $filters=publicCalendarFilters(['program'=>$program]);
    if ($filters['program']!==$program) throw new RuntimeException('Calendar lost originating program.');
    $stmt=$db->prepare("SELECT e.event_id FROM events e WHERE e.status='published' AND COALESCE(e.end_date,e.start_date)>=CURRENT_DATE()".$filters['condition']);
    $stmt->execute($filters['params']);
    $actual=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));sort($actual);
    $expected=array_map('intval',array_column(gnc_get_global_events($program,50),'event_id'));sort($expected);
    if ($actual!==$expected) throw new RuntimeException('Calendar/Bulletin mismatch for '.$program);
}
$filters=publicCalendarFilters(['department'=>'coe']);
$stmt=$db->prepare('SELECT e.event_id FROM events e WHERE 1=1'.$filters['condition']);
$stmt->execute($filters['params']); // Also guard the department filter's parameter count.
echo "PASS: program/department lists override legacy fields; both dimensions, groups, publication status, all-day/ongoing dates and limits are respected. Temporary fixtures only.\n";
