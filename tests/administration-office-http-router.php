<?php
// Only the loopback PHP server started by administration-office-http-test.php.
if(PHP_SAPI!=='cli-server'||($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'){http_response_code(404);exit;}
$schema=getenv('GNCI_OFFICE_TEST_SCHEMA');
if(!is_string($schema)||!preg_match('/\Agnci_office_http_[a-f0-9]{12}\z/D',$schema)){http_response_code(404);exit;}
require_once __DIR__.'/../includes/browser-urls.php';
$route=gncBrowserLegacyUrl(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
if(!in_array($route,['/admin/action/page-content-handler.php','/admin/action/editor-draft-handler.php','/admin/action/editor-presence-handler.php','/admin/action/publish-confirmation-handler.php','/pages/about/administration.php'],true)){http_response_code(404);exit;}
// Emulate a shared host's non-file form-field cap for draft transport regression checks.
$fieldLimit=(int)getenv('GNCI_OFFICE_TEST_FIELD_LIMIT');
if($fieldLimit>0&&$route==='/admin/action/editor-draft-handler.php'&&strlen(is_string($_POST['snapshot']??null)?$_POST['snapshot']:'')>$fieldLimit){http_response_code(413);header('Content-Type: text/html');echo 'Request field too large';exit;}
$_SERVER['SCRIPT_FILENAME']=dirname(__DIR__).$route;
$_SERVER['SCRIPT_NAME']=$_SERVER['PHP_SELF']=$route;
require_once __DIR__.'/../config/config.php';
if(!in_array(DB_HOST,['db','127.0.0.1','localhost'],true)){http_response_code(404);exit;}
getDB()->exec("USE `$schema`");
require dirname(__DIR__).$route;
