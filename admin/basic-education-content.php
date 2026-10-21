<?php
require_once __DIR__ . '/rich-text.php';
/** Academic listing text fields. Department details and programs use the shared CMS. */
function academicFeatureIconChoices(): array {
    static $choices = null;
    if ($choices !== null) return $choices;
    $choices = [];
    foreach (['grad-hat-white' => 'Graduation', 'person_play' => 'Faculty', 'psychology-white' => 'Personal development', 'real_estate_agent' => 'Community service'] as $name => $label) {
        $choices['svg:' . $name] = ['label' => $label, 'path' => '/assets/images/svg/' . $name . '.svg'];
    }
    static $catalog;
    $catalog ??= require __DIR__ . '/academic-icon-catalog.php';
    foreach ($catalog['bootstrap'] as $name) {
        $choices['bi:' . $name] = ['label' => ucwords(str_replace('-', ' ', $name)), 'glyph' => $name];
    }
    foreach ($catalog['material'] as $name) {
        $choices['material:' . $name] = ['label' => ucwords(str_replace('_', ' ', $name)), 'material' => $name];
    }
    return $choices;
}

function basicEducationSchema(): array {
    $fields = [];
    for ($number = 1; $number <= 10; $number++) {
        $description = $number % 2 === 0;
        $fields['text_' . str_pad((string)$number, 2, '0', STR_PAD_LEFT)] = ['type' => 'text', 'max' => $description ? 5000 : 255, 'label' => $description ? 'Description' : 'Heading'];
    }
    foreach (['casa-gradeschool', 'hs-monte', 'jhs-regular', 'shs-regular'] as $slug) {
        $fields['card_' . $slug . '_name'] = ['type' => 'text', 'max' => 255, 'label' => 'Department name'];
    }
    $college = [];
    $collegeText = [
        ['Page heading', 'College Departments'],
        ['Page description', 'Explore our colleges, and discover programs designed to shape your future.'],
        ['First feature heading', 'Quality Education'],
        ['First feature description', 'Curriculum designed for real-world impact.'],
        ['Second feature heading', 'Experienced Faculty'],
        ['Second feature description', 'Learn from dedicated professionals.'],
        ['Third feature heading', 'Holistic Development'],
        ['Third feature description', 'Growing minds, character and leadership.'],
        ['Fourth feature heading', 'Community Focused'],
        ['Fourth feature description', 'Committed to nation-building and service.'],
    ];
    foreach ($collegeText as $i => [$label, $default]) {
        $college['text_' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT)] = ['type' => 'text', 'max' => $i % 2 ? 5000 : 255, 'label' => $label, 'default' => $default];
    }
    foreach (['grad-hat-white', 'person_play', 'psychology-white', 'real_estate_agent'] as $i => $name) {
        $field = ['type' => 'icon', 'label' => 'Feature ' . ($i + 1) . ' icon', 'default' => 'svg:' . $name];
        $fields['icon_0' . ($i + 1)] = $college['icon_0' . ($i + 1)] = $field;
    }
    return ['index' => $fields, 'college-index' => $college];
}

function basicEducationContent(string $page): array {
    static $cache = [];
    if (isset($cache[$page])) return $cache[$page];
    $fields = basicEducationSchema()[$page] ?? [];
    foreach ($fields as &$field) $field['value'] = $field['default'] ?? '';
    unset($field);
    try {
        $stmt = getDB()->prepare('SELECT field_key, content FROM basic_education_content WHERE page_key = ?');
        $stmt->execute([$page]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($fields[$row['field_key']])) $fields[$row['field_key']]['value'] = (string)$row['content'];
        }
    } catch (PDOException $e) {
        // Unset content stays empty until an administrator saves it.
        if ((string)$e->getCode() !== '42S02') error_log('Basic Education content: ' . $e->getMessage());
    }
    return $cache[$page] = $fields;
}

function basicEducationText(string $page, string $key): string {
    $value = (string)(basicEducationContent($page)[$key]['value'] ?? '');
    $escaped = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    if((basicEducationSchema()[$page][$key]['max']??0)>255)return renderSectionContent($value);
    return (basicEducationSchema()[$page][$key]['type'] ?? '') === 'text' ? nl2br($escaped) : $escaped;
}

function basicEducationMark(string $page, string $key): string {
    $field = basicEducationSchema()[$page][$key] ?? null;
    if (!$field || !cePreviewCanManage()) return '';
    return ceField('basic-text', ['max' => $field['max'], 'label' => $field['label'], 'ph' => 'Add ' . strtolower($field['label']), 'multiline' => $field['max'] > 255, 'rich'=>$field['max']>255])
        . ' data-ve-key="' . ceAttr($key) . '"';
}

function basicEducationHasFields(string $page, array $keys): bool {
    $fields = basicEducationContent($page);
    foreach ($keys as $key) {
        if (trim((string)($fields[$key]['value'] ?? '')) !== '') return true;
    }
    return false;
}

/** No fallback photographs/logos; empty slots exist only in the editor. */
function basicEducationMedia(?string $path, string $alt, string $class, string $editorAttributes = ''): string {
    $path = trim((string)$path);
    $h = static function (string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); };
    if ($path !== '') return '<img src="' . $h($path) . '" alt="' . $h($alt) . '" class="' . $h($class) . '" loading="lazy"' . $editorAttributes . '>';
    if (!cePreviewCanManage()) return '';
    return '<span class="be-empty-media ' . $h($class) . '" role="button" tabindex="0" aria-label="Add image"' . $editorAttributes . '><i class="bi bi-image" aria-hidden="true"></i><span>Add image</span></span>';
}

function basicEducationEditorBlock(string $page): string {
    return cePreviewCanManage() ? ceData('veBasicData', ['page' => $page, 'fields' => basicEducationContent($page), 'icons' => academicFeatureIconChoices()]) : '';
}

function academicFeatureIcon(string $page, string $key, string $class): string {
    $field = basicEducationContent($page)[$key];
    $choices = academicFeatureIconChoices();
    $choice = $choices[$field['value']] ?? $choices[$field['default']];
    $h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $attributes = ceManageMark('data-ve-icon="academic-feature" data-ve-key="' . $h($key) . '" role="button" tabindex="0" aria-label="Change feature icon"');
    $icon = isset($choice['path']) ? '<img src="' . $h($choice['path']) . '" alt="" aria-hidden="true">'
        : (isset($choice['material']) ? '<span class="material-symbols-outlined" aria-hidden="true">' . $h($choice['material']) . '</span>'
            : '<i class="bi bi-' . $h($choice['glyph']) . '" aria-hidden="true"></i>');
    return '<div class="' . $h($class) . '"' . $attributes . '>' . $icon . '</div>';
}

function validateBasicEducationChanges(string $page, array $texts, array $files): array {
    $schema = basicEducationSchema()[$page] ?? null;
    if (!$schema) throw new Exception('Unknown Basic Education page.');
    $clean = [];
    foreach ($texts as $key => $value) {
        if (!isset($schema[$key]) || !in_array($schema[$key]['type'], ['text', 'icon'], true) || !is_string($value)) {
            throw new Exception('Only existing Basic Education fields can be edited.');
        }
        $value = trim(str_replace("\r\n", "\n", $value));
        if ($schema[$key]['type'] === 'icon') {
            if (!isset(academicFeatureIconChoices()[$value])) throw new Exception('Choose an icon from the icon picker.');
            $clean[$key] = $value;
            continue;
        }
        if($schema[$key]['max']>255)$value=richTextNormalize($value);
        if (mb_strlen($value) > $schema[$key]['max']) throw new Exception('The text is too long.');
        $clean[$key] = $value;
    }
    foreach ($files as $key => $file) {
        if (!isset($schema[$key]) || $schema[$key]['type'] !== 'image') {
            throw new Exception('Only existing program pictures can be replaced.');
        }
    }
    return $clean;
}

function ensureAcademicContentTable(): void {
    $db = getDB();
    $db->exec('CREATE TABLE IF NOT EXISTS basic_education_content (
        page_key VARCHAR(60) NOT NULL, field_key VARCHAR(100) NOT NULL,
        content MEDIUMTEXT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (page_key, field_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

function saveBasicEducationContent(string $page, array $texts, array $files): void {
    $clean = validateBasicEducationChanges($page, $texts, $files);
    $db = getDB();
    ensureAcademicContentTable();
    $uploads = [];
    try {
        foreach ($files as $key => $file) {
            $uploads[] = $upload = storeDepartmentUpload($file, 'homepage_image');
            $clean[$key] = $upload['path'];
        }
        $db->beginTransaction();
        $stmt = $db->prepare('INSERT INTO basic_education_content (page_key, field_key, content) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE content = VALUES(content)');
        foreach ($clean as $key => $value) $stmt->execute([$page, $key, $value]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        foreach ($uploads as $upload) discardStoredDepartmentUpload($upload);
        throw $e;
    }
}

function assertProgramDepartmentAllowed(int $deptId): void {
    if ($deptId <= 0) return;
    $stmt = getDB()->prepare('SELECT * FROM departments WHERE dept_id = ?');
    $stmt->execute([$deptId]);
    $dept = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dept) throw new Exception('Department not found.');
}
