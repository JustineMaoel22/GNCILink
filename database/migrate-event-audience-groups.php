<?php
/** Additive and repeatable; existing events retain their audiences. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
$db=getDB();
if(!$db->query("SHOW COLUMNS FROM events LIKE 'audience_group'")->fetch()) {
    $db->exec("ALTER TABLE events ADD COLUMN audience_group VARCHAR(20) NOT NULL DEFAULT 'all'");
}
echo "Event audience group storage is ready. Existing events are unchanged.\n";
