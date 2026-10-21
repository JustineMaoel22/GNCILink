<?php
// Isolated SQLite persistence checks; no live site content is read or changed.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../admin/rich-text.php';
require_once __DIR__ . '/../components/admission-page-manager.php';
if (!function_exists('mb_strlen')) { function mb_strlen(string $value, ?string $encoding=null): int { return preg_match_all('/./us',$value); } }
class AdmissionTestDB extends PDO {
    public function prepare(string $query,array $options=[]): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE','',$query),$options); }
}
function getDB(): PDO { static $db;return $db ??= new AdmissionTestDB('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }
$failAudit=false;
function logActivity(...$args): void { if($GLOBALS['failAudit'])throw new RuntimeException('Simulated persistence failure'); }
function checkAdmission(bool $condition,string $label): void { if(!$condition)throw new RuntimeException($label); }
function rejectedAdmission(callable $work,string $label): void { try {$work();}catch(InvalidArgumentException $error){return;}throw new RuntimeException($label); }
$_SESSION=['user_id'=>1];$db=getDB();
$db->exec('CREATE TABLE pages (page_id INTEGER PRIMARY KEY,slug TEXT,status TEXT)');
$db->exec("CREATE TABLE page_sections (section_id INTEGER PRIMARY KEY AUTOINCREMENT,page_id INTEGER,section_key TEXT,title TEXT,content TEXT,draft_content TEXT,status TEXT,review_status TEXT DEFAULT 'none',review_note TEXT,display_order INTEGER,updated_by INTEGER,UNIQUE(page_id,section_key))");
$db->exec("INSERT INTO pages VALUES (1,'college-admission-requirements','active'),(2,'basic-education-admission-requirements','active')");
foreach(['college-admission-requirements'=>collegeAdmissionSectionDefaults(),'basic-education-admission-requirements'=>basicAdmissionSectionDefaults()] as $page=>$defaults) {
    $id=$page==='college-admission-requirements'?1:2;$order=0;
    foreach($defaults as $key=>$row)$db->prepare("INSERT INTO page_sections (page_id,section_key,title,content,status,display_order) VALUES (?,?,?,?,'published',?)")->execute([$id,$key,$row['title'],$row['content'],++$order]);
}
function admissionRows(int $id): array { $q=getDB()->prepare('SELECT * FROM page_sections WHERE page_id=? ORDER BY display_order,section_id');$q->execute([$id]);$out=[];foreach($q->fetchAll() as $row)$out[$row['section_key']]=$row;return $out; }
foreach([['college-admission-requirements',1,'college','freshmen','transferees'],['basic-education-admission-requirements',2,'casa','casa_new','casa_old']] as [$page,$id,$scope,$rename,$remove]) {
    $rows=admissionRows($id);$orders=[];
    foreach($rows as $key=>$row)if(($name=admissionCategoryScope($page,$key))!==null && $row['status']==='published')$orders[$name][]=$key;
    $new=($scope==='college'?'':$scope.'_').'custom_0123456789abcdef';
    $orders[$scope]=array_values(array_diff($orders[$scope],[$remove]));array_unshift($orders[$scope],$new);
    $changes=[
        ['key'=>$rename,'title'=>'Renamed category','content'=>'<h2>Renamed category</h2><ol><li>Second requirement</li><li>First requirement</li></ol><script>alert(1)</script>','removed'=>false],
        ['key'=>$remove,'title'=>'Deleted category','content'=>$rows[$remove]['content'],'removed'=>true],
        ['key'=>$new,'title'=>'New category','content'=>'<h2>New category</h2><ol><li>New requirement</li></ol>','removed'=>false],
    ];
    publishAdmissionPage($page,$changes,$orders);$after=admissionRows($id);
    checkAdmission($after[$remove]['status']==='draft','Category deletion is persisted without destroying its row');
    checkAdmission($after[$rename]['title']==='Renamed category' && strpos($after[$rename]['content'],'Second requirement')<strpos($after[$rename]['content'],'First requirement'),'Rename and checklist order persist');
    checkAdmission(!str_contains($after[$rename]['content'],'script') && !str_contains($after[$rename]['content'],'alert(1)'),'Published HTML is sanitized');
    $scoped=array_keys(array_filter($after,static fn($row)=>$row['status']==='published'&&admissionCategoryScope($page,$row['section_key'])===$scope));
    checkAdmission($scoped===$orders[$scope],'Category order persists within its scope');
    publishAdmissionPage($page,$changes,$orders);
    checkAdmission(count(admissionRows($id))===count($after),'Retry does not duplicate categories');
    $before=admissionRows($id);$bad=$orders;$bad[$scope]=[$new];
    rejectedAdmission(fn()=>publishAdmissionPage($page,$changes,$bad),'Incomplete layouts must be rejected');
    checkAdmission(admissionRows($id)===$before,'Rejected layout changes are atomic');
    $GLOBALS['failAudit']=true;
    try{publishAdmissionPage($page,[['key'=>$rename,'title'=>'Must roll back','content'=>'<h2>Must roll back</h2>','removed'=>false]],$orders);throw new LogicException('Expected injected failure');}catch(RuntimeException $error){checkAdmission($error->getMessage()==='Simulated persistence failure','Expected injected persistence failure');}
    $GLOBALS['failAudit']=false;
    checkAdmission(admissionRows($id)===$before,'A late failure rolls back every change');
    if($id===2){$cross=$orders;array_pop($cross['casa']);$cross['montessori'][]=$new;rejectedAdmission(fn()=>publishAdmissionPage($page,[],$cross),'Cross-school ordering must be rejected');}
}
checkAdmission(admissionCategoryLabel('<h2>A &amp; B</h2>','Fallback')==='A & B','Category labels decode safe heading text');
checkAdmission(admissionCategoryLabel('<h2></h2>','Fallback')==='Fallback','Empty headings retain an accessible tab label');
echo "PASS: both admission pages persist rename/delete/order changes, sanitize HTML, retry safely, reject invalid layouts, and roll back failures atomically in an isolated database.\n";
