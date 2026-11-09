<?php
// Test-only router/worker. Never execute on a production PHP SAPI.
if(!in_array(PHP_SAPI,['cli','cli-server'],true)||!getenv('GNCI_LIMIT_TEST_DIR')){http_response_code(404);exit;}
require_once __DIR__.'/../includes/request-protection.php';
$dir=getenv('GNCI_LIMIT_TEST_DIR');
if(PHP_SAPI==='cli') {echo gncRequestConsume('parallel',7,60,null,$dir.'/parallel')['allowed']?'1':'0';exit;}
putenv('REQUEST_LIMIT_DIR='.$dir.'/http');
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/health'){echo 'ready';return;}
if($path==='/broken'){putenv('REQUEST_LIMIT_DIR='.__FILE__);gncRequestLimit('broken','ip',1,60);}
if($path==='/user')gncRequestLimit('user',is_string($_GET['id']??null)?$_GET['id']:'1',2,60);
else gncRequestLimit('endpoint:'.$path,gncRequestIp(),2,60);
header('Content-Type: application/json');echo '{"success":true}';
