<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
function env(string $key,$default=null){return getenv($key)?:$default;}
define('APP_URL','https://gncipampanga.com/admin');
require_once __DIR__.'/../includes/auth-security.php';
$checks=0;
function deploymentCheck(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
putenv('AUTH_TRUSTED_PROXIES=');putenv('ADMIN_PWA_ORIGIN=');putenv('AUTH_PUBLIC_URL=');
$_SERVER=['REMOTE_ADDR'=>'192.0.2.5','HTTP_HOST'=>'gncipampanga.com','SERVER_PORT'=>80];
deploymentCheck(!authIsHttps(),'plain HTTP');
foreach(['HTTP_X_FORWARDED_PROTO'=>'https','HTTP_X_FORWARDED_SSL'=>'on','HTTP_CF_VISITOR'=>'{"scheme":"https"}'] as $key=>$value){
    $_SERVER[$key]=$value;deploymentCheck(!authIsHttps(),'untrusted '.$key);unset($_SERVER[$key]);
}
$_SERVER['HTTPS']='on';deploymentCheck(authIsHttps(),'native cPanel HTTPS');unset($_SERVER['HTTPS']);
$_SERVER['HTTP_X_FORWARDED_PROTO']='https';putenv('AUTH_TRUSTED_PROXIES=192.0.2.5');
deploymentCheck(authIsHttps(),'explicit trusted proxy');
$_SERVER['HTTP_X_FORWARDED_PROTO']='https,http';deploymentCheck(!authIsHttps(),'ambiguous proxy chain rejected');
$_SERVER['HTTP_HOST']='localhost';deploymentCheck(!authLocalDevelopment(),'forged localhost cannot enable development on production');
$_SERVER['HTTP_HOST']='attacker.invalid';deploymentCheck(authPublicOrigin()==='https://gncipampanga.com','reset origin independent of arbitrary Host');
echo "PASS: $checks deployment transport checks.\n";
