<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../components/department-order.php';
class DepartmentOrderTestDB extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(str_replace('ON DUPLICATE KEY UPDATE content = VALUES(content)', 'ON CONFLICT(page_key,field_key) DO UPDATE SET content=excluded.content', $query), $options);
    }
}
function getDB(): PDO { static $db; return $db ??= new DepartmentOrderTestDB('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); }
function saveBasicEducationContent(...$args): void { getDB()->exec('CREATE TABLE IF NOT EXISTS basic_education_content (page_key TEXT,field_key TEXT,content TEXT,PRIMARY KEY(page_key,field_key))'); }
function getDepartments(): array { return $GLOBALS['rows']; }
function publicDepartmentIsCollege(array $row): bool { return $row['department_type']==='college' && $row['slug']!=='graduate-school'; }
function check(bool $value,string $label): void { if(!$value)throw new RuntimeException($label); }
function rejects(callable $fn): void { try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Invalid order accepted'); }
$rows=[['dept_id'=>1,'slug'=>'arts','department_type'=>'college'],['dept_id'=>2,'slug'=>'engineering','department_type'=>'college'],['dept_id'=>3,'slug'=>'casa-gradeschool','department_type'=>'basic-education'],['dept_id'=>4,'slug'=>'jhs-regular','department_type'=>'basic-education'],['dept_id'=>5,'slug'=>'graduate-school','department_type'=>'college']];
saveBasicEducationContent();
check(departmentStoredOrder('college')===[],'Empty order falls back to existing order');
saveDepartmentOrder('college',[2,1]);saveDepartmentOrder('basic-education',[4,3]);
check(departmentStoredOrder('college')===[2,1],'College order persisted');check(departmentStoredOrder('basic-education')===[4,3],'Basic Education order persisted independently');
saveDepartmentOrder('college',[1,2]);check(departmentStoredOrder('college')===[1,2],'Existing order updated');
rejects(fn()=>saveDepartmentOrder('college',[1,3]));rejects(fn()=>saveDepartmentOrder('college',[1,1]));rejects(fn()=>saveDepartmentOrder('college',[1]));rejects(fn()=>saveDepartmentOrder('college',['1',2]));rejects(fn()=>saveDepartmentOrder('other',[1,2]));
check(departmentStoredOrder('college')===[1,2],'Rejected changes do not overwrite order');
$sorted=departmentApplyOrder($rows,[4,999,4,2]);check(array_column($sorted,'dept_id')===[4,2,1,3,5],'Deleted, duplicated and new departments handled safely');
$rows[]=['dept_id'=>6,'slug'=>'new-college','department_type'=>'college'];rejects(fn()=>saveDepartmentOrder('college',[2,1]));
echo "PASS: independent persisted listing order, updates, scope/membership validation, stale lists and new/deleted department fallback.\n";
