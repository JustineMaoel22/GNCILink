<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
getDB()->exec(file_get_contents(__DIR__ . '/../sql/admin-onboarding-migration.sql'));
echo "Admin onboarding storage ready. Existing accounts and content preserved.\n";
