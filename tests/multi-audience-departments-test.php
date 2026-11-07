<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../admin/admin-functions.php';
$db = getDB();
// Connection-local fixture shadows real program records without changing them.
$db->exec('CREATE TEMPORARY TABLE programs (program_code VARCHAR(30) PRIMARY KEY, dept_id INT NULL)');
$db->exec("INSERT INTO programs VALUES ('A',1),('B',2),('UNASSIGNED',NULL)");
foreach ([
    [[], ['A', 'B'], true],
    [[1], [], true],
    [[1], ['A'], true],
    [[1, 2], ['A', 'B'], true],
    [[1], ['B'], false],
    [[1], ['UNKNOWN'], false],
    [[1], ['UNASSIGNED'], false],
] as [$departments, $programs, $expected]) {
    $valid = true;
    try { validateMultiAudienceProgramDepartments(['dept_ids' => $departments, 'programs' => $programs]); }
    catch (InvalidArgumentException $e) { $valid = false; }
    if ($valid !== $expected) throw new RuntimeException('Unexpected department/program validation result.');
}
echo "PASS: department/program validation accepts matching or unrestricted audiences and rejects mismatches.\n";
