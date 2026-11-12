<?php
/** Department-local audience rules shared by Announcements and News. */
function contentAudienceMatches(array $audience, ?string $program, ?int $department, array $programDepartments): bool {
    $departments = array_map('intval', $audience['dept_ids'] ?? []);
    $programs = $audience['programs'] ?? [];
    if ($program !== null) {
        $department = isset($programDepartments[$program]) ? (int)$programDepartments[$program] : null;
        if ($departments && !in_array($department, $departments, true)) return false;
        // With departments selected, each department has its own program restriction.
        $scoped = $departments ? array_values(array_filter($programs, static fn($code) =>
            isset($programDepartments[$code]) && (int)$programDepartments[$code] === $department)) : $programs;
        return !$scoped || in_array($program, $scoped, true);
    }
    if ($department !== null) {
        if ($departments) return in_array($department, $departments, true);
        if (!$programs) return true;
        foreach ($programs as $code) if (isset($programDepartments[$code]) && (int)$programDepartments[$code] === $department) return true;
        return false;
    }
    return true;
}

function validatedContentAudience($rawDepartments, $rawPrograms): array {
    foreach ([$rawDepartments, $rawPrograms] as $values) {
        if (!is_array($values)) throw new InvalidArgumentException('Choose a valid audience.');
        foreach ($values as $value) if (!is_string($value) && !is_int($value)) throw new InvalidArgumentException('Choose a valid audience.');
    }
    foreach ($rawDepartments as $id) if (!ctype_digit((string)$id) || (int)$id < 1) throw new InvalidArgumentException('Choose a valid department.');
    $choices = getProgramCategories();
    foreach ($rawPrograms as $code) if (!isset($choices[$code]) || $code === 'ALL') throw new InvalidArgumentException('Choose a valid program.');
    $audience = normalizeMultiAudienceInput($rawDepartments, $rawPrograms, $choices);
    if ($audience['dept_ids']) {
        $query = getDB()->prepare('SELECT COUNT(*) FROM departments WHERE dept_id IN (' . implode(',', array_fill(0, count($audience['dept_ids']), '?')) . ')');
        $query->execute($audience['dept_ids']);
        if ((int)$query->fetchColumn() !== count($audience['dept_ids'])) throw new InvalidArgumentException('A selected department no longer exists.');
    }
    validateMultiAudienceProgramDepartments($audience);
    return $audience;
}

function newsAudienceReady(PDO $db): bool {
    try {
        $db->query('SELECT news_id,dept_id FROM news_departments LIMIT 0');
        $db->query('SELECT news_id,program_code FROM news_programs LIMIT 0');
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02') return false;
        throw $e;
    }
}

function contentAudienceDescription(array $audience): string {
    $ids = $audience['dept_ids'] ?? [];
    $codes = $audience['programs'] ?? [];
    if (!$ids) return $codes ? 'Programs: ' . implode(', ', $codes) : 'School-wide (default)';
    static $departments, $programDepartments;
    $departments ??= array_column(getDB()->query('SELECT dept_id,dept_name FROM departments')->fetchAll(PDO::FETCH_ASSOC), 'dept_name', 'dept_id');
    $programDepartments ??= getProgramDepartmentMap();
    $parts = [];
    foreach ($ids as $id) {
        $selected = array_values(array_filter($codes, static fn($code) => isset($programDepartments[$code]) && (int)$programDepartments[$code] === (int)$id));
        $total = count(array_filter($programDepartments, static fn($department) => (int)$department === (int)$id));
        $parts[] = ($departments[$id] ?? 'Department ' . $id) . ': ' . (!$selected || count($selected) === $total ? 'whole department' : implode(', ', $selected));
    }
    return implode('; ', $parts);
}

function getNewsAudiencesBatch(PDO $db, array $rows): array {
    $audiences = [];
    foreach ($rows as $row) $audiences[(int)$row['news_id']] = ['dept_ids' => [], 'programs' => []];
    if (!$audiences || !newsAudienceReady($db)) return $audiences;
    $ids = array_keys($audiences);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    foreach (['news_departments' => ['dept_id', 'dept_ids'], 'news_programs' => ['program_code', 'programs']] as $table => [$column, $key]) {
        $query = $db->prepare("SELECT news_id,`$column` AS value FROM `$table` WHERE news_id IN ($placeholders) ORDER BY `$column`");
        $query->execute($ids);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) $audiences[(int)$row['news_id']][$key][] = $key === 'dept_ids' ? (int)$row['value'] : $row['value'];
    }
    return $audiences;
}

function saveNewsAudience(PDO $db, int $id, array $audience): void {
    if (!newsAudienceReady($db)) throw new RuntimeException('Run the News audience migration before saving audience selections.');
    foreach (['news_departments' => ['dept_id', 'dept_ids'], 'news_programs' => ['program_code', 'programs']] as $table => [$column, $key]) {
        $db->prepare("DELETE FROM `$table` WHERE news_id=?")->execute([$id]);
        $insert = $db->prepare("INSERT INTO `$table` (news_id,`$column`) VALUES (?,?)");
        foreach ($audience[$key] as $value) $insert->execute([$id, $value]);
    }
}
