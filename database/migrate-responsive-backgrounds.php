<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
getDB()->exec(file_get_contents(__DIR__.'/responsive-backgrounds-migration.sql'));
echo "Responsive background storage is ready. Existing images and content are unchanged.\n";
