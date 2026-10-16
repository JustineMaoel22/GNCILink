<?php
// php -d extension=mbstring -d extension=pdo_sqlite tests/basic-education-test.php
require __DIR__ . '/../admin/basic-education-content.php';
// Exercise persistence in an isolated SQLite database, adapting MySQL-only syntax.
class BasicTestDatabase extends PDO {
    public function exec(string $statement): int|false {
        if (str_starts_with($statement, 'CREATE TABLE IF NOT EXISTS basic_education_content')) {
            $statement = 'CREATE TABLE IF NOT EXISTS basic_education_content (page_key TEXT, field_key TEXT, content TEXT, PRIMARY KEY(page_key, field_key))';
        }
        return parent::exec($statement);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(str_replace('ON DUPLICATE KEY UPDATE content = VALUES(content)',
            'ON CONFLICT(page_key, field_key) DO UPDATE SET content = excluded.content', $query), $options);
    }
}
function getDB(): PDO {
    static $db;
    return $db ??= new BasicTestDatabase('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
function check(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException($label);
    echo "PASS: $label\n";
}
function rejected(callable $operation, string $label): void {
    try { $operation(); } catch (Exception $e) { check(true, $label); return; }
    throw new RuntimeException($label);
}
function cePreviewCanManage(): bool { return $GLOBALS['preview'] ?? false; }
function ceField(string $name, array $options): string { return ' data-ve-field="' . $name . '"'; }
function ceAttr($value): string { return htmlspecialchars((string)$value, ENT_QUOTES); }
function ceData(string $id, array $data): string { return '<script id="' . $id . '">' . json_encode($data, JSON_HEX_TAG) . '</script>'; }

saveBasicEducationContent('index', ['text_01' => 'Updated heading', 'text_02' => '<script>alert(1)</script>'], []);
check(basicEducationText('index', 'text_01') === 'Updated heading', 'Published edits persist and render');
check(!str_contains(basicEducationText('index', 'text_02'), '<script>'), 'Published text is escaped');
check(basicEducationText('index', 'text_03') !== '', 'Untouched fields keep their defaults');
check(basicEducationEditorBlock('index') === '' && basicEducationMark('index', 'text_01') === '', 'Visitors receive no editing markers');
$preview = true;
check(str_contains(basicEducationEditorBlock('index'), 'Updated heading'), 'Editor receives saved content');
check(str_contains(basicEducationMark('index', 'text_01'), 'basic-text'), 'Managers receive editing markers');
rejected(fn() => saveBasicEducationContent('new-page', [], []), 'Cannot add a page');
rejected(fn() => saveBasicEducationContent('hs-monte', ['new_program' => 'New'], []), 'Cannot add program fields');
rejected(fn() => validateBasicEducationChanges('hs-monte', ['image_01' => '/arbitrary.jpg'], []), 'Image paths cannot be posted as text');
rejected(fn() => validateBasicEducationChanges('hs-monte', [], ['image_99' => []]), 'Cannot add program pictures');
check(count(array_filter(basicEducationSchema()['hs-monte'], fn($f) => $f['type'] === 'image')) === 6, 'HS Montessori retains six program cards');
check(count(array_filter(basicEducationSchema()['shs-regular'], fn($f) => $f['type'] === 'image')) === 5, 'SHS retains five program cards');
getDB()->exec('CREATE TABLE departments (dept_id INTEGER, slug TEXT, department_type TEXT)');
getDB()->exec("INSERT INTO departments VALUES (1, 'hs-monte', 'basic-education'), (2, 'coe', 'college'), (3, 'custom-basic', 'basic-education')");
rejected(fn() => assertProgramDepartmentAllowed(1), 'Basic Education program creation is blocked');
rejected(fn() => assertProgramDepartmentAllowed(3), 'Basic Education type is blocked regardless of slug');
assertProgramDepartmentAllowed(2);
check(true, 'College program creation remains allowed');
