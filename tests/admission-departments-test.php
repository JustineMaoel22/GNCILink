<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../components/admission-page-manager.php';

// Run the real publishing validation against an isolated PDO double; no live content changes.
class AdmissionDepartmentsPDO extends PDO {
    public array $rows = [], $executed = [];
    public bool $active = false;
    public function __construct() {}
    public function beginTransaction(): bool { $this->active = true; return true; }
    public function commit(): bool { $this->active = false; return true; }
    public function rollBack(): bool { $this->active = false; return true; }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new AdmissionDepartmentsStatement($this, $query); }
}
class AdmissionDepartmentsStatement extends PDOStatement {
    public function __construct(private AdmissionDepartmentsPDO $db, private string $sql) {}
    public function execute(?array $params = null): bool { $this->db->executed[] = [$this->sql, $params]; return true; }
    public function fetchColumn(int $column = 0): mixed { return 7; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->db->rows; }
}
$db = new AdmissionDepartmentsPDO();
function getDB(): PDO { global $db; return $db; }
function richTextToStorage(string $html): string { return $html; }
function sectionToEditorHtml(string $html): string { return $html; }
function logActivity(...$args): void {}
function checkAdmission(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function admissionRefused(array $changes, array $orders): void {
    global $db, $page;
    $db->executed = [];
    try { publishAdmissionPage($page, $changes, $orders); }
    catch (InvalidArgumentException $error) {
        checkAdmission(!$db->active, 'Rejected publishing left a transaction open.');
        checkAdmission(!array_filter($db->executed, fn($query) => preg_match('/^(INSERT|UPDATE|DELETE)/', $query[0])), 'Invalid layout attempted a write.');
        return;
    }
    throw new RuntimeException('Invalid department layout was accepted.');
}
$_SESSION = ['user_id' => 1];
$page = 'basic-education-admission-requirements';
$orders = [];
foreach (basicAdmissionRequirements() as $school => $data) {
    $orders[$school] = array_map(fn($group) => $school . '_' . $group, array_keys($data['groups']));
}
foreach (basicAdmissionSectionDefaults() as $key => $data) {
    $db->rows[] = ['section_id' => count($db->rows) + 1, 'section_key' => $key, 'title' => $data['title'], 'content' => $data['content'], 'status' => 'published'];
}
$school = 'dept-0123456789abcdef';
$label = 'school_label_' . $school;
$category = $school . '_custom_fedcba9876543210';
$changes = [
    ['key' => $label, 'title' => 'Department category name', 'content' => '<p>New Department</p>', 'removed' => false],
    ['key' => $category, 'title' => 'New Students', 'content' => '<h2>New Students</h2><ol><li>Report card</li></ol>', 'removed' => false],
];
$extended = $orders + [$school => [$category]];
$result = publishAdmissionPage($page, $changes, $extended);
checkAdmission(isset($result[$label], $result[$category]) && !$db->active, 'New department and its requirements did not publish atomically.');
$inserts = array_values(array_filter($db->executed, fn($query) => str_starts_with($query[0], 'INSERT')));
checkAdmission(count($inserts) === 2 && $inserts[0][1][3] === 'New Department', 'Department label was not normalized and inserted with requirements.');
admissionRefused([$changes[1]], $extended); // Missing department definition.
admissionRefused($changes, $orders); // Missing department in layout.
admissionRefused($changes, $orders + [$school => [$category, $category]]);
$wrongScope = $extended; $wrongScope['casa'][] = $category; $wrongScope[$school] = [];
admissionRefused($changes, $wrongScope);
$invalid = $changes; $invalid[0]['removed'] = true; admissionRefused($invalid, $extended);
$invalid = $changes; $invalid[0]['key'] = 'school_label_dept-bad'; admissionRefused($invalid, $extended);
$invalid = $changes; $invalid[0]['content'] = str_repeat('x', 151); admissionRefused($invalid, $extended);
$invalid = $changes; $invalid[0]['content'] = ''; admissionRefused($invalid, $extended);
$db->rows[] = ['section_id' => 99, 'section_key' => $label, 'title' => 'Department category name', 'content' => 'Saved Department', 'status' => 'published'];
checkAdmission(isset(basicAdmissionSchoolsFromSections($db->rows)[$school]), 'Published department is missing from the public reader.');
$db->rows[] = ['section_id' => 100, 'section_key' => 'school_label_dept-1111111111111111', 'content' => 'Unpublished', 'status' => 'draft'];
checkAdmission(!isset(basicAdmissionSchoolsFromSections($db->rows)['dept-1111111111111111']), 'Unpublished department leaked into public data.');
$db->executed = [];
publishAdmissionPage($page, [$changes[1]], $extended);
checkAdmission(count(array_filter($db->executed, fn($query) => str_starts_with($query[0], 'INSERT'))) === 1, 'Existing department requirements could not be added.');
$db->rows = [['section_id' => 1, 'section_key' => 'freshmen', 'status' => 'published', 'content' => '', 'title' => 'Freshmen']];
publishAdmissionPage('college-admission-requirements', [], ['college' => ['freshmen']]);
echo "PASS: Department creation, existing department additions, public visibility, college compatibility and invalid layouts. No database accessed.\n";
