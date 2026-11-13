<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
getDB()->exec(file_get_contents(__DIR__.'/facebook-post-categories-migration.sql'));
echo "Multiple Facebook post categories enabled. Existing assignments and automatic mode preserved.\n";
