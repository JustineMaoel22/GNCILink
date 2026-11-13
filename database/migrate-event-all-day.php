<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../includes/event-edit-state.php';
$db=getDB();
if(!eventAllDayStorageReady($db))$db->exec('ALTER TABLE events ADD COLUMN is_all_day TINYINT(1) NULL DEFAULT NULL');
echo "Explicit all-day/midnight storage enabled. Existing event dates and times preserved.\n";
