<?php
// Isolated calendar scope tests; never connects to the website database.
function getDepartments(): array { return [
    ['dept_id'=>1,'slug'=>'computing','dept_name'=>'Computing','archived_at'=>null],
    ['dept_id'=>2,'slug'=>'education','dept_name'=>'Education','archived_at'=>null],
    ['dept_id'=>3,'slug'=>'empty','dept_name'=>'Empty department','archived_at'=>null],
    ['dept_id'=>4,'slug'=>'archived','dept_name'=>'Archived','archived_at'=>'2026-01-01'],
]; }
function getProgramCategories(): array { return ['ALL'=>'All Programs','CS'=>'Computer Science','IT'=>'Information Technology','ED'=>'Education']; }
require __DIR__.'/../components/calendar-filters.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE programs (program_code TEXT,dept_id INTEGER);CREATE TABLE events (title TEXT,program TEXT,status TEXT);
    INSERT INTO programs VALUES ('CS',1),('IT',1),('ED',2);
    INSERT INTO events VALUES ('CS event','CS','published'),('IT event','IT','published'),('Education event','ED','published'),('General event','ALL','published'),('Private draft','CS','draft');");
function checkCalendar(bool $value,string $message):void { if(!$value)throw new RuntimeException($message); }
$run=function(array $query)use($db):array {
    $scope=publicCalendarFilters($query);
    $stmt=$db->prepare("SELECT title FROM events e WHERE status='published'".$scope['condition'].' ORDER BY title');
    $stmt->execute($scope['params']);return $stmt->fetchAll(PDO::FETCH_COLUMN);
};
checkCalendar($run(['department'=>'computing'])===['CS event','IT event'],'Department includes its programs only.');
checkCalendar($run(['department'=>'education'])===['Education event'],'Other department stays separate.');
checkCalendar($run(['department'=>'empty'])===[],'Department without programs yields no unrelated events.');
checkCalendar($run(['program'=>'CS'])===['CS event'],'Existing individual program filter works.');
checkCalendar($run(['department'=>'computing','program'=>'ED'])===['CS event','IT event'],'Department selection clears an incompatible program.');
checkCalendar(count($run([]))===4,'All categories includes every published event.');
checkCalendar(!isset(publicCalendarFilters([])['departments']['archived']),'Archived departments stay hidden.');
checkCalendar(publicCalendarFilters(['department'=>['computing'],'program'=>['CS']])['condition']==='','Invalid query types fail safely.');
checkCalendar(publicCalendarFilters(['department'=>"computing' OR 1=1 --"])['condition']==='','Unknown department values never enter SQL.');
echo "PASS: department/program scope, empty departments, drafts, archived departments, reset and invalid parameters. No website database changes.\n";
