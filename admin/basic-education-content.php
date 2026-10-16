<?php
/** Fixed Basic Education fields: updates only; no program/card creation. */
function basicEducationSchema(): array {
    static $schema;
    return $schema ??= json_decode(file_get_contents(__DIR__ . '/../config/basic-education-content.json'), true, 512, JSON_THROW_ON_ERROR);
}

function basicEducationContent(string $page): array {
    static $cache = [];
    if (isset($cache[$page])) return $cache[$page];
    $fields = basicEducationSchema()[$page] ?? [];
    foreach ($fields as &$field) $field['value'] = $field['default'];
    unset($field);
    try {
        $stmt = getDB()->prepare('SELECT field_key, content FROM basic_education_content WHERE page_key = ?');
        $stmt->execute([$page]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($fields[$row['field_key']])) $fields[$row['field_key']]['value'] = (string)$row['content'];
        }
    } catch (PDOException $e) {
        // Existing pages render their original content until the first edit creates the table.
        if ((string)$e->getCode() !== '42S02') error_log('Basic Education content: ' . $e->getMessage());
    }
    return $cache[$page] = $fields;
}

function basicEducationText(string $page, string $key): string {
    $value = (string)(basicEducationContent($page)[$key]['value'] ?? '');
    $escaped = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    return (basicEducationSchema()[$page][$key]['type'] ?? '') === 'text' ? nl2br($escaped) : $escaped;
}

function basicEducationMark(string $page, string $key): string {
    $field = basicEducationSchema()[$page][$key] ?? null;
    if (!$field || !cePreviewCanManage()) return '';
    return ceField('basic-text', ['max' => $field['max'], 'label' => $field['label'], 'multiline' => $field['max'] > 255])
        . ' data-ve-key="' . ceAttr($key) . '"';
}

function basicEducationEditorBlock(string $page): string {
    return cePreviewCanManage() ? ceData('veBasicData', ['page' => $page, 'fields' => basicEducationContent($page)]) : '';
}

function validateBasicEducationChanges(string $page, array $texts, array $files): array {
    $schema = basicEducationSchema()[$page] ?? null;
    if (!$schema) throw new Exception('Unknown Basic Education page.');
    $clean = [];
    foreach ($texts as $key => $value) {
        if (!isset($schema[$key]) || $schema[$key]['type'] !== 'text' || !is_string($value)) {
            throw new Exception('Only existing Basic Education fields can be edited.');
        }
        $value = trim(str_replace("\r\n", "\n", $value));
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

function saveBasicEducationContent(string $page, array $texts, array $files): void {
    $clean = validateBasicEducationChanges($page, $texts, $files);
    $db = getDB();
    $db->exec('CREATE TABLE IF NOT EXISTS basic_education_content (
        page_key VARCHAR(60) NOT NULL, field_key VARCHAR(100) NOT NULL,
        content MEDIUMTEXT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (page_key, field_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
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
    if ($dept && (in_array($dept['slug'] ?? '', ['casa-gradeschool', 'hs-monte', 'jhs-regular', 'shs-regular'], true)
        || ($dept['department_type'] ?? '') === 'basic-education')) {
        throw new Exception('Basic Education has a fixed set of programs. Edit its existing content instead.');
    }
}
