<?php
/** Department listing order uses the existing academic listing content store. */
function departmentOrderPage(string $scope): string {
    if (!in_array($scope, ['college', 'basic-education'], true)) {
        throw new InvalidArgumentException('Invalid department listing.');
    }
    return $scope === 'college' ? 'college-index' : 'index';
}

function departmentOrderRows(string $scope): array {
    departmentOrderPage($scope);
    return array_values(array_filter(getDepartments(), static function ($row) use ($scope) {
        if (empty($row['slug'])) return false;
        return $scope === 'college' ? publicDepartmentIsCollege($row)
            : in_array($row['slug'], ['casa-gradeschool', 'hs-monte', 'jhs-regular', 'shs-regular'], true);
    }));
}

function departmentStoredOrder(string $scope): array {
    $page = departmentOrderPage($scope);
    try {
        $stmt = getDB()->prepare("SELECT content FROM basic_education_content WHERE page_key = ? AND field_key = 'department_order'");
        $stmt->execute([$page]);
        $order = json_decode((string)($stmt->fetchColumn() ?: '[]'), true);
        return is_array($order) ? array_values(array_filter($order, 'is_int')) : [];
    } catch (PDOException $e) {
        if ((string)$e->getCode() !== '42S02') error_log('Department order: ' . $e->getMessage());
        return [];
    }
}

function departmentApplyOrder(array $rows, array $order): array {
    $byId = [];
    foreach ($rows as $row) $byId[(int)$row['dept_id']] = $row;
    $sorted = [];
    foreach ($order as $id) {
        if (!isset($byId[$id])) continue;
        $sorted[] = $byId[$id]; unset($byId[$id]);
    }
    return array_merge($sorted, array_values($byId));
}

function validateDepartmentOrder(array $order, array $rows): array {
    if (!array_is_list($order) || !$order || count($order) !== count($rows)) {
        throw new InvalidArgumentException('Departments changed. Reload the editor before reordering.');
    }
    foreach ($order as $id) if (!is_int($id) || $id <= 0) throw new InvalidArgumentException('Invalid department order.');
    $expected = array_map(static fn($row) => (int)$row['dept_id'], $rows);
    $actual = $order; sort($actual); sort($expected);
    if ($actual !== $expected) throw new InvalidArgumentException('Departments changed. Reload the editor before reordering.');
    return $order;
}

function saveDepartmentOrder(string $scope, array $order): void {
    $page = departmentOrderPage($scope);
    $order = validateDepartmentOrder($order, departmentOrderRows($scope));
    // The listing text editor owns creation of this existing table.
    saveBasicEducationContent($page, [], []);
    $stmt = getDB()->prepare('INSERT INTO basic_education_content (page_key, field_key, content) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE content = VALUES(content)');
    $stmt->execute([$page, 'department_order', json_encode($order, JSON_THROW_ON_ERROR)]);
}
