<?php
// Used only by the isolated CLI test server. It never overrides the live server.
$name=getenv('GNC_RBAC_TEST_DB');
if(PHP_SAPI!=='cli-server'||!is_string($name)||!preg_match('/^gnci_rbac_test_[a-f0-9]{12}$/D',$name)){http_response_code(404);exit;}
ini_set('display_errors','0');
define('DB_NAME',$name); // config.php's later define cannot overwrite this isolated name.
// Never contact real mail providers from the isolated HTTP suite.
define('MAIL_HOST','127.0.0.1');define('MAIL_PORT',2528);define('MAIL_USERNAME','fixture@example.invalid');define('MAIL_PASSWORD','synthetic-only');define('MAIL_ENCRYPTION','');
// Deterministic breach-service fixture, available only in this isolated CLI server.
function authPwnedRange(string $prefix): string {return str_repeat('0',35).':0';}
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
if($path==='/.env'||preg_match('~^/(?:\.git|config|database|sql|tests|includes|vendor)(?:/|$)~',$path)){http_response_code(403);exit;}
$root=realpath(__DIR__.'/..');$file=realpath($root.($path==='/'?'/index.php':$path));
if(!$file||!str_starts_with($file,$root.'/')){http_response_code(404);exit;}
if(is_dir($file))$file.='/index.php';
if(pathinfo($file,PATHINFO_EXTENSION)!=='php')return false;
$_SERVER['SCRIPT_FILENAME']=$file;$_SERVER['PHP_SELF']=$path;
require $file;
