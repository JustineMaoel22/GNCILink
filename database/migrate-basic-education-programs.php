<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/migrate-department-page-content.php';
require_once __DIR__ . '/basic-education-program-migration.php';
$db = getDB();
prepareBasicEducationProgramMigration($db);
$result = migrateBasicEducationPrograms($db);
echo $result['already_applied'] ? "Basic Education conversion already applied; existing edits and deletions preserved.\n"
    : "Basic Education converted: {$result['programs']} populated program cards and {$result['fields']} section fields. Original saved rows and files retained.\n";
