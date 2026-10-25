<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
function programCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$choices = getAnnouncementProgramCategories();
programCheck(isset($choices['ALL'], $choices['BEEd'], $choices['BSEd']), 'Everyone and College education degrees remain available');
$rows = getDB()->query("SELECT p.program_code, d.department_type FROM programs p JOIN departments d ON d.dept_id = p.dept_id WHERE p.program_name IS NOT NULL AND p.program_name <> ''")->fetchAll(PDO::FETCH_ASSOC);
$excluded = 0;
foreach ($rows as $row) {
    $code = $row['program_code'];
    if ($row['department_type'] === 'basic-education') {
        $excluded++;
        programCheck(!isset($choices[$code]), 'Basic Education track excluded: ' . $code);
        programCheck(isset(getProgramCategories()[$code]), 'Legacy labels and list filtering preserved');
        try {
            normalizeAnnouncementProgramCategory($code);
            throw new RuntimeException('Basic Education track accepted for a new announcement');
        } catch (InvalidArgumentException $e) {}
        programCheck(normalizeAnnouncementProgramCategory($code, $code) === $code, 'Existing tag survives an unrelated edit');
    } else {
        programCheck(isset($choices[$code]), 'College/Graduate program retained: ' . $code);
        programCheck(normalizeAnnouncementProgramCategory($code) === $code, 'College/Graduate program accepted');
    }
}
programCheck(normalizeAnnouncementProgramCategory(null) === 'ALL', 'Optional program defaults to Everyone');
echo "PASS: $excluded Basic Education tracks excluded; College/Graduate choices and existing tags preserved. No records changed.\n";
