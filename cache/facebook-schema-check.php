<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config/config.php';
foreach(getDB()->query('SHOW COLUMNS FROM fb_posts_cache') as $row)echo $row['Field'].PHP_EOL;
