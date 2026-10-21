<?php
/** Office coordinates and routes belong exclusively to the existing navigator. */
function campusOfficeBuildings(): array {
    static $buildings;
    if ($buildings !== null) return $buildings;
    $buildings = [];
    $map = file_get_contents(__DIR__ . '/../campus_navigator.html');
    // Read the navigator's JavaScript catalog, including formatted objects with
    // unquoted keys and trailing commas. Do not depend on its line layout.
    if (preg_match('/\bvar\s+B\s*=\s*\{(.*?)\};/s', $map, $match)) {
        $entryPattern = <<<'REGEX'
~["']?([a-z][a-z0-9_]*)["']?\s*:\s*\{\s*["']?name["']?\s*:\s*("(?:\\.|[^"\\])*")~i
REGEX;
        preg_match_all($entryPattern, $match[1], $entries, PREG_SET_ORDER);
        foreach ($entries as $entry) {
            $id = $entry[1];
            if (strpos($id, 'parking') !== 0) $buildings[$id] = json_decode($entry[2], true, 512, JSON_THROW_ON_ERROR);
        }
    }
    return $buildings;
}

function departmentOfficeLocation(array $dept): array {
    $buildings = campusOfficeBuildings();
    $location = [
        'name' => trim((string)($dept['office_label'] ?? '')) ?: 'Department Office',
        'building_id' => (string)($dept['office_building_id'] ?? ''),
        'floor' => (string)($dept['office_floor'] ?? ''),
        'room' => (string)($dept['office_room'] ?? ''),
        'details' => (string)($dept['office_details'] ?? ''),
    ];
    // Upgrade only explicitly named buildings and Floor/Room text already stored
    // in the database. Never infer locations from a department name or slug.
    $legacy = trim((string)($dept['dean_office'] ?? ''));
    if (!array_key_exists('office_building_id', $dept) || $dept['office_building_id'] === null) {
        foreach ($buildings as $id => $name) {
            if (preg_match('/^' . preg_quote($name, '/') . '(?:\s*,|$)/i', $legacy)) {
                $location['building_id'] = $id;
                break;
            }
        }
        if (preg_match('/(?:^|,)\s*Floor\s+([^,]+)/i', $legacy, $m)) $location['floor'] = trim($m[1]);
        if (preg_match('/(?:^|,)\s*Room\s+([^,]+)/i', $legacy, $m)) $location['room'] = trim($m[1]);
        $details = $legacy;
        if ($location['building_id'] !== '') {
            $details = preg_replace('/^' . preg_quote($buildings[$location['building_id']], '/') . '\\s*,?\\s*/i', '', $details);
        }
        $details = preg_replace('/(?:^|,)\\s*(?:Floor|Room)\\s+[^,]+/i', '', $details);
        $location['details'] = trim($details, " ,\t\n\r");
    }
    $location['building'] = $buildings[$location['building_id']] ?? '';
    $location['address'] = officeLocationAddress($location);
    // Preserve unstructured legacy directions in full until an admin updates them.
    if (($dept['office_building_id'] ?? null) === null && $legacy !== '') $location['address'] = $legacy;
    return $location;
}

function officeLocationAddress(array $location): string {
    return implode(', ', array_filter([
        $location['building'] ?? '',
        ($location['floor'] ?? '') !== '' ? 'Floor ' . $location['floor'] : '',
        ($location['room'] ?? '') !== '' ? 'Room ' . $location['room'] : '',
        $location['details'] ?? '',
    ], static function ($value) { return $value !== ''; }));
}

function departmentOfficeCard(?array $dept, string $class = 'dept-dean-contact'): string {
    if (!$dept) return '';
    $location = departmentOfficeLocation($dept);
    $preview = function_exists('cePreviewCanManage') && cePreviewCanManage();
    $editable = $preview && ($_SESSION['user_role'] ?? '') === 'Super Admin';
    if ($location['address'] === '' && !$preview) return '';
    $h = static function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
    $attrs = $editable ? ' data-ve-pick="office-location"' : '';
    // An editor click belongs to the Visual Editor, not to the visitor modal.
    if (!$preview) $attrs .= ' data-office-location="' . $h(json_encode($location, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) . '"';
    return '<button type="button" class="' . $h($class) . ' office-location-trigger"' . $attrs . ' aria-haspopup="dialog">'
        . '<i class="bi bi-geo-alt" aria-hidden="true"></i><span>'
        . '<span class="dept-contact-label" data-office-name>' . $h($location['name']) . '</span>'
        . '<span class="dept-contact-value" data-office-address>' . $h($location['address'] ?: 'Set office location') . '</span>'
        . '</span></button>';
}

function saveDepartmentOfficeLocation(int $deptId, array $input): array {
    if (($_SESSION['user_role'] ?? '') !== 'Super Admin') throw new RuntimeException('Only Super Admin can change office locations.');
    $dept = getDepartmentById($deptId);
    if (!$dept) throw new InvalidArgumentException('Department not found.');
    $data = [];
    foreach (['name' => 100, 'building_id' => 40, 'floor' => 40, 'room' => 40, 'details' => 500] as $key => $max) {
        if (!isset($input[$key]) || !is_string($input[$key])) throw new InvalidArgumentException('Invalid office location.');
        $data[$key] = trim($input[$key]);
        if (mb_strlen($data[$key]) > $max) throw new InvalidArgumentException('Office ' . $key . ' is too long.');
    }
    $buildings = campusOfficeBuildings();
    if ($data['name'] === '' || !isset($buildings[$data['building_id']])) throw new InvalidArgumentException('Enter an office name and select a campus building.');
    $data['building'] = $buildings[$data['building_id']];
    $address = officeLocationAddress($data);
    // Keep legacy consumers of dean_office working within its existing column size.
    if (mb_strlen($address) > 255) throw new InvalidArgumentException('The full office address must be at most 255 characters.');
    $db = getDB();
    $stmt = $db->prepare('UPDATE departments SET office_label = ?, office_building_id = ?, office_floor = ?, office_room = ?, office_details = ?, dean_office = ? WHERE dept_id = ?');
    $stmt->execute([$data['name'], $data['building_id'], $data['floor'], $data['room'], $data['details'], $address, $deptId]);
    logActivity((int)$_SESSION['user_id'], 'UPDATE', 'departments', $deptId, 'Updated office location');
    return departmentAdminPayload(getDepartmentById($deptId));
}
