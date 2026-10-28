<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/config.php';
$db=getDB();
$column=$db->query("SHOW COLUMNS FROM student_organizations LIKE 'archived_at'")->fetch();
if (!$column) $db->exec('ALTER TABLE student_organizations ADD COLUMN archived_at DATETIME NULL DEFAULT NULL');
echo "Student organization archive column ready. Existing records and publication states preserved.\n";
