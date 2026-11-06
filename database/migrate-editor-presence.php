<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/config.php';
$db=getDB();
foreach (explode(';',file_get_contents(__DIR__.'/../sql/editor-presence-migration.sql')) as $statement) {
    if (trim($statement)!=='') $db->exec($statement);
}
echo "Content Editor presence tables are ready. Existing website content was not changed.\n";
