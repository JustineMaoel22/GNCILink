<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
getDB()->exec(file_get_contents(__DIR__ . '/news-audience.sql'));
echo "News audience tables are ready. Existing articles and publication states are unchanged.\n";
