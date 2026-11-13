<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../components/enrollment-procedures-content.php';
// Isolated PDO double exercises real tab/canvas validation and rollback without a database.
class EnrollmentTabsPDO extends PDO {
    public array $rows = [], $queries = [], $backup = [];
    public bool $active = false;
    public function __construct() {}
    public function beginTransaction(): bool { $this->backup = $this->rows; $this->active = true; return true; }
    public function inTransaction(): bool { return $this->active; }
    public function commit(): bool { $this->active = false; return true; }
    public function rollBack(): bool { $this->rows = $this->backup; $this->active = false; return true; }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new EnrollmentTabsStatement($this, $query); }
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false { return $this->prepare($query); }
}
class EnrollmentTabsStatement extends PDOStatement {
    public function __construct(private EnrollmentTabsPDO $db, private string $sql) {}
    public function execute(?array $params = null): bool {
        $this->db->queries[] = [$this->sql, $params];
        if (str_starts_with($this->sql, 'INSERT INTO page_sections')) {
            $updated=false;
            if(str_contains($this->sql,'ON DUPLICATE KEY UPDATE'))foreach($this->db->rows as &$row)if($row['section_key']===$params[1]){$row['content']=$params[3];$updated=true;}
            unset($row);
            if(!$updated)$this->db->rows[] = ['section_key'=>$params[1], 'title'=>$params[2], 'content'=>$params[3]];
        }
        if (str_starts_with($this->sql, 'UPDATE page_sections')) foreach ($this->db->rows as &$row) if ($row['section_key'] === $params[5]) { $row['title']=$params[0]; $row['content']=$params[1]; }
        return true;
    }
    public function fetchColumn(int $column = 0): mixed { return 7; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return str_contains($this->sql, 'JOIN pages') ? $this->db->rows : []; }
}
$db = new EnrollmentTabsPDO(); $authorized = true;
function getDB(): PDO { global $db; return $db; }
function hasPermission(string $permission): bool { global $authorized; return $authorized; }
function richTextToStorage(string $html): string { return $html; }
function sectionToEditorHtml(string $html): string { return $html; }
function logActivity(...$args): void {}
function ceAttr(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function ceManageMark(string $mark): string { return ' ' . $mark; }
function ceMark(string $mark): string { return ' ' . $mark; }
function renderSectionContent(string $html): string { return $html; }
function checkTabs(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function refusedTabs(callable $fn): void {
    try { $fn(); } catch (InvalidArgumentException $error) { return; }
    throw new RuntimeException('Invalid tabs unexpectedly accepted.');
}
$_SESSION = ['user_id'=>1];
$step = ['step_id'=>str_repeat('a',32),'title'=>"Registrar's Office",'location'=>'Window 9','instruction'=>'Submit your report card.','illustration'=>'/assets/images/svg/enrollment-procedures/fresh-1.svg'];
$level = ['key'=>'enrollment_tab_level_0123456789abcdef','title'=>'Graduate School','content'=>'<p>Graduate enrollment instructions.</p>','steps'=>[]];
$child = ['key'=>'enrollment_tab_extra-0123456789abcdef_fedcba9876543210','title'=>'New students','content'=>'<ol><li>Bring your report card.</li></ol>','steps'=>[$step]];
$college = ['key'=>'enrollment_tab_college_1111111111111111','title'=>'Additional applicants','content'=>'<p>College instructions.</p>','steps'=>[]];
$basic = ['key'=>'enrollment_tab_basic_2222222222222222','title'=>'Basic applicants','content'=>'<p>Basic instructions.</p>','steps'=>[]];
$tabs = [$level,$child,$college,$basic];
$result = publishEnrollmentCanvas(null, '', [], [], $tabs, enrollmentTabsRevision([]));
checkTabs($result['tabs'] === $tabs && !$db->active, 'Education-level, nested, College and Basic tabs were not committed together.');
checkTabs(count(enrollmentTabsFor($result['tabs'], 'level')) === 1, 'Public parent filter failed.');
checkTabs(count(enrollmentTabsFor($result['tabs'], 'extra-0123456789abcdef')) === 1, 'Public child filter failed.');
ob_start(); enrollmentExtraTabButtons($tabs, 'level'); enrollmentExtraTabPanes($tabs, 'level'); $rendered=ob_get_clean();
checkTabs(str_contains($rendered, 'id="extra-0123456789abcdef-tab"') && str_contains($rendered, 'id="extra-fedcba9876543210-pane"') && str_contains($rendered, 'Bring your report card.'), 'Published tabs did not render with their nested content and stable targets.');
checkTabs(str_contains($rendered,'STEP 1') && str_contains($rendered,'Submit your report card.') && str_contains($rendered,'Window 9'), 'Published tab steps did not render.');
checkTabs(strpos($rendered,'data-enrollment-tab-parent="extra-0123456789abcdef"') < strpos($rendered,'Graduate enrollment instructions.'), 'Published applicant navigation must appear before the level content.');
checkTabs(str_contains($rendered,'id="extra-0123456789abcdef-overview-pane"'), 'Published parent content must retain its own overview pane.');
$escaped = $tabs; $escaped[0]['title']='<img src=x onerror=alert(1)>';
ob_start(); enrollmentExtraTabButtons($escaped, 'level'); $rendered=ob_get_clean();
checkTabs(!str_contains($rendered, '<img') && str_contains($rendered, '&lt;img'), 'Tab labels were not escaped.');
refusedTabs(fn()=>enrollmentValidateTabs([$child], []));
refusedTabs(fn()=>enrollmentValidateTabs([$level,$level], []));
$duplicate = $college; $duplicate['key']='enrollment_tab_college_0123456789abcdef';
refusedTabs(fn()=>enrollmentValidateTabs([$level,$duplicate], []));
$invalid = $level; $invalid['key']='enrollment_tab_level_bad'; refusedTabs(fn()=>enrollmentValidateTabs([$invalid], []));
$invalid = $level; $invalid['title']=''; refusedTabs(fn()=>enrollmentValidateTabs([$invalid], []));
$invalid = $level; $invalid['title']=str_repeat('a',151); refusedTabs(fn()=>enrollmentValidateTabs([$invalid], []));
$invalid = $level; $invalid['content']=str_repeat('a',100001); refusedTabs(fn()=>enrollmentValidateTabs([$invalid], []));
refusedTabs(fn()=>enrollmentValidateTabs([], $tabs));
refusedTabs(fn()=>enrollmentValidateTabs(array_fill(0,41,$level), []));
$badSteps=$child;$badSteps['steps']=[$step,$step];refusedTabs(fn()=>enrollmentValidateTabs([$level,$badSteps],[]));
$badSteps=$child;$badSteps['steps'][0]['title']='';refusedTabs(fn()=>enrollmentValidateTabs([$level,$badSteps],[]));
$badSteps=$child;$badSteps['steps'][0]['illustration']='https://example.test/untrusted.svg';refusedTabs(fn()=>enrollmentValidateTabs([$level,$badSteps],[]));
$badSteps=$child;$badSteps['steps']=array_fill(0,41,$step);refusedTabs(fn()=>enrollmentValidateTabs([$level,$badSteps],[]));
$before = $db->rows;
refusedTabs(fn()=>publishEnrollmentCanvas(null, '', [], [], $tabs, enrollmentTabsRevision([])));
checkTabs($db->rows === $before && !$db->active, 'Stale revision changed saved tabs.');
$renamed = $tabs; $renamed[0]['title']='Graduate Studies';
refusedTabs(fn()=>publishEnrollmentCanvas(null, '', ['missing-image'=>['reset'=>true,'expected'=>'']], [], $renamed, $result['tabs_revision']));
checkTabs($db->rows === $before && !$db->active, 'Failed illustration save did not roll back tab edits.');
$updated = publishEnrollmentCanvas(null, '', [], [], $renamed, $result['tabs_revision']);
checkTabs($updated['tabs'][0]['title']==='Graduate Studies' && count($db->rows)===8 && $updated['tabs'][1]['steps']===[$step], 'Tab rename duplicated or lost published content/steps.');
$authorized = false;
refusedTabs(fn()=>publishEnrollmentCanvas(null, '', [], [], $tabs, $updated['tabs_revision']));
echo "PASS: Tabs and step storage/rendering, hierarchy, duplicate IDs, required fields, illustration validation, limits, stale revisions, authorization and atomic rollback. No database accessed.\n";
