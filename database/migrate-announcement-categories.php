<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
getDB()->exec(file_get_contents(__DIR__ . '/announcement-categories-migration.sql'));
echo "Multiple announcement categories are available. Existing category assignments preserved.\n";
