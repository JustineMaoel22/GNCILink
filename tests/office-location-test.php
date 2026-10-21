<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
function checkOffice($value, string $message): void {
    if (!$value) throw new RuntimeException($message);
}
$buildings = campusOfficeBuildings();
checkOffice(count($buildings) === 7 && $buildings['limlingan'] === 'Limlingan Hall', 'Uses actual navigator buildings');
checkOffice(!isset($buildings['parking1']), 'Parking is not an office building');
$legacy = departmentOfficeLocation(['dean_office' => 'Limlingan Hall, Floor 2, Room 202']);
checkOffice($legacy['building_id'] === 'limlingan' && $legacy['floor'] === '2' && $legacy['room'] === '202' && $legacy['details'] === '', 'Extract only existing location information');
$unknown = departmentOfficeLocation(['dean_office' => 'Ask reception']);
checkOffice($unknown['building_id'] === '' && $unknown['address'] === 'Ask reception', 'Unknown addresses must not invent a building');
$db = getDB();
$dept = $db->query('SELECT * FROM departments ORDER BY dept_id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
checkOffice((bool)$dept, 'A department is available for transactional integration tests');
$user = $db->query("SELECT user_id FROM users WHERE role = 'Super Admin' LIMIT 1")->fetchColumn();
$_SESSION = ['user_id' => (int)$user, 'user_role' => 'Super Admin'];
$data = ['name' => 'Office <test>', 'building_id' => 'banzali', 'floor' => '3', 'room' => '301', 'details' => 'Use the stairs'];
$db->beginTransaction();
try {
    $payload = saveDepartmentOfficeLocation((int)$dept['dept_id'], $data);
    checkOffice($payload['office_location']['building'] === 'Banzali Hall', 'New building reads back from database');
    checkOffice($payload['dean_office'] === 'Banzali Hall, Floor 3, Room 301, Use the stairs', 'Legacy and structured address stay synchronized');
    $card = departmentOfficeCard(getDepartmentById((int)$dept['dept_id']));
    checkOffice(strpos($card, 'Office &lt;test&gt;') !== false && strpos($card, 'data-office-location=') !== false, 'Public card escapes text and provides dynamic modal data');
    foreach ([array_merge($data, ['building_id' => 'imaginary']), array_merge($data, ['floor' => str_repeat('a', 41)]), array_merge($data, ['name' => ''])] as $invalid) {
        try { saveDepartmentOfficeLocation((int)$dept['dept_id'], $invalid); throw new RuntimeException('Invalid input was accepted'); }
        catch (InvalidArgumentException $expected) {}
    }
    $_SESSION['user_role'] = 'Content Admin';
    try { saveDepartmentOfficeLocation((int)$dept['dept_id'], $data); throw new Exception('Unauthorized update accepted'); }
    catch (RuntimeException $expected) { checkOffice(strpos($expected->getMessage(), 'Super Admin') !== false, 'Role enforced'); }
} finally {
    $db->rollBack();
}
echo "PASS: map catalog, legacy parsing, database round trip, safe rendering, validation and Super Admin access; test writes rolled back.\n";
