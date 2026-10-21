<?php
// CLI only: php database/migrate-office-locations.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
$db = getDB();
$existing = $db->query('SHOW COLUMNS FROM departments')->fetchAll(PDO::FETCH_COLUMN);
foreach (['office_building_id' => 40, 'office_floor' => 40, 'office_room' => 40, 'office_details' => 500] as $column => $size) {
    if (!in_array($column, $existing, true)) {
        $db->exec("ALTER TABLE departments ADD COLUMN $column VARCHAR($size) NULL");
        echo "Added $column\n";
    }
}
// Move the former public-template fallbacks into the database. These are the
// exact existing addresses, not new map coordinates or inferred room numbers.
// Never replace a saved address, assigned building, or office name.
$legacyOffices = [
    ['cased', 'CASED Office', 'Limlingan Hall, Floor 2, Room 202'],
    ['cba', 'CBA Office', 'Limlingan Hall, Floor 2, Room 203'],
    ['coa', 'Accountancy Office', 'Limlingan Hall, Floor 2, Room 210'],
    ['coe', 'COE Office', 'Limlingan Hall, Floor 2, Room 205'],
    ['camp', 'CAMP Office', 'Limlingan Hall, Floor 2, Room 206'],
    ['conursing', 'Nursing Office', 'Limlingan Hall, Floor 2, Room 208'],
];
$address = $db->prepare("UPDATE departments SET dean_office = ? WHERE slug = ? AND office_building_id IS NULL AND COALESCE(TRIM(dean_office), '') = ''");
$name = $db->prepare("UPDATE departments SET office_label = ? WHERE slug = ? AND COALESCE(TRIM(office_label), '') = ''");
$db->beginTransaction();
try {
    foreach ($legacyOffices as [$slug, $label, $location]) {
        $address->execute([$location, $slug]);
        $name->execute([$label, $slug]);
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}
echo "Office location schema ready. Existing locations preserved.\n";
