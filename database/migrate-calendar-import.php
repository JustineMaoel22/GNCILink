<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/config.php';
$db=getDB();
if(!$db->query("SHOW COLUMNS FROM events LIKE 'description'")->fetch())$db->exec('ALTER TABLE events ADD COLUMN description TEXT NULL');
$sql=file_get_contents(__DIR__.'/calendar-import-migration.sql');
$sql=preg_replace('/--[^\n]*\n/', '', $sql);
foreach(explode(';',$sql) as $statement) {
    $statement=trim($statement);
    if(str_starts_with($statement,'CREATE TABLE '))$db->exec(preg_replace('/^CREATE TABLE /','CREATE TABLE IF NOT EXISTS ',$statement));
}
if(!$db->query("SHOW COLUMNS FROM calendar_import_series LIKE 'status'")->fetch())$db->exec("ALTER TABLE calendar_import_series ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
if(!$db->query("SHOW COLUMNS FROM calendar_import_batches LIKE 'calendar_action'")->fetch())$db->exec('ALTER TABLE calendar_import_batches ADD COLUMN calendar_action VARCHAR(20) NULL');
echo "Calendar import storage is ready. Existing events are unchanged.\n";
