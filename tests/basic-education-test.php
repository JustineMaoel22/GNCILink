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
function ceManageMark(string $attributes): string { return cePreviewCanManage() ? ' ' . $attributes : ''; }
function ceField(string $name, array $options): string { return ' data-ve-field="' . $name . '"'; }
function ceAttr($value): string { return htmlspecialchars((string)$value, ENT_QUOTES); }
function ceData(string $id, array $data): string { return '<script id="' . $id . '">' . json_encode($data, JSON_HEX_TAG) . '</script>'; }

saveBasicEducationContent('index', ['text_01' => 'Updated heading', 'text_02' => '<script>alert(1)</script>', 'icon_01' => 'bi:book', 'icon_02' => 'material:science'], []);
check(str_contains(academicFeatureIcon('index', 'icon_02', 'basic-edu-feature-icon'), 'material-symbols-outlined') && str_contains(academicFeatureIcon('index', 'icon_02', 'basic-edu-feature-icon'), '>science</span>'), 'Saved Material Symbols render on the public page');
check(count(array_filter(array_keys(academicFeatureIconChoices()), fn($key) => str_starts_with($key, 'bi:'))) > 2000, 'Picker includes the full Bootstrap Icons catalog');
check(count(array_filter(array_keys(academicFeatureIconChoices()), fn($key) => str_starts_with($key, 'material:'))) > 200, 'Picker includes hundreds of Material Symbols');
check(str_contains(academicFeatureIcon('index', 'icon_01', 'basic-edu-feature-icon'), 'bi-book'), 'Basic Education renders the saved icon');
check(!str_contains(academicFeatureIcon('index', 'icon_01', 'basic-edu-feature-icon'), 'data-ve-icon'), 'Visitors receive no icon editing controls');
check(basicEducationText('index', 'text_01') === 'Updated heading', 'Published edits persist and render');
check(!str_contains(basicEducationText('index', 'text_02'), '<script>'), 'Published text is escaped');
check(basicEducationText('index', 'text_03') === '', 'Unset fields stay empty without hardcoded defaults');
check(basicEducationMedia(null, 'Photo', 'photo') === '', 'Missing public media never uses a fallback image');
check(!basicEducationHasFields('index', ['text_03']), 'Empty cards can be hidden from visitors');
check(basicEducationEditorBlock('index') === '' && basicEducationMark('index', 'text_01') === '', 'Visitors receive no editing markers');
$preview = true;
check(str_contains(basicEducationMedia(null, 'Photo', 'photo'), 'Add image'), 'Empty image slots remain editable');
check(str_contains(basicEducationEditorBlock('index'), 'Updated heading'), 'Editor receives saved content');
check(str_contains(basicEducationMark('index', 'text_01'), 'basic-text'), 'Managers receive editing markers');
rejected(fn() => saveBasicEducationContent('new-page', [], []), 'Cannot add a page');
rejected(fn() => saveBasicEducationContent('hs-monte', ['new_program' => 'New'], []), 'Cannot add program fields');
rejected(fn() => validateBasicEducationChanges('hs-monte', ['image_01' => '/arbitrary.jpg'], []), 'Image paths cannot be posted as text');
rejected(fn() => validateBasicEducationChanges('hs-monte', [], ['image_99' => []]), 'Cannot add program pictures');
check(array_keys(basicEducationSchema()) === ['index', 'college-index'], 'Only listing fields use the PHP metadata; department programs have no fixed slots');
saveBasicEducationContent('college-index', ['text_01' => 'Updated <college> heading', 'text_02' => '', 'icon_02' => 'bi:star'], []);
check(str_contains(academicFeatureIcon('college-index', 'icon_02', 'cp-feature-icon'), 'bi-star'), 'College renders the saved icon');
check(str_contains(academicFeatureIcon('college-index', 'icon_04', 'cp-feature-icon'), '/real_estate_agent.svg'), 'Unchanged icons retain their original artwork');
rejected(fn() => validateBasicEducationChanges('college-index', ['icon_01' => 'svg:../../file'], []), 'Only approved icon choices can be saved');
rejected(fn() => validateBasicEducationChanges('index', ['icon_01' => '<script>'], []), 'Arbitrary icon markup is rejected');
check(basicEducationText('college-index', 'text_01') === 'Updated &lt;college&gt; heading', 'College listing edits persist and render safely');
check(basicEducationText('college-index', 'text_02') === '', 'Cleared College listing text stays empty');
check(basicEducationText('college-index', 'text_03') === 'Quality Education', 'Unchanged College features keep their current text');
getDB()->exec('CREATE TABLE departments (dept_id INTEGER, slug TEXT, department_type TEXT)');
getDB()->exec("INSERT INTO departments VALUES (1, 'hs-monte', 'basic-education'), (2, 'coe', 'college'), (3, 'custom-basic', 'basic-education')");
assertProgramDepartmentAllowed(1);
assertProgramDepartmentAllowed(3);
assertProgramDepartmentAllowed(2);
check(true, 'Basic Education and College share program creation');
