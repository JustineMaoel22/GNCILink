<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../includes/pwa.php';
require_once __DIR__.'/../includes/auth-security.php';
$checks=0;
function originCheck(bool $ok,string $message): void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
$saved=getenv('ADMIN_PWA_ORIGIN');$savedEnv=$_ENV['ADMIN_PWA_ORIGIN']??null;
try {
    putenv('ADMIN_PWA_ORIGIN=');unset($_ENV['ADMIN_PWA_ORIGIN']);
    $_SERVER['HTTP_HOST']='gncipampanga.com';
    originCheck(gncAdminOriginRedirect('/admin/login','GET')===null,'Existing login stays unchanged until deployment is enabled');
    foreach(['https://evil.example','https://admin.gncipampanga.com.evil.example','https://admin.gncipampanga.com/admin','http://admin.gncipampanga.com','https://user@admin.gncipampanga.com','https://admin.gncipampanga.com?target=evil'] as $bad){
        putenv('ADMIN_PWA_ORIGIN='.$bad);originCheck(gncAdminPwaOrigin()===null,'Reject unsafe deployment origin');
    }
    putenv('ADMIN_PWA_ORIGIN=https://admin.gncipampanga.com');
    foreach(gncBrowserRoutes() as $alias=>$file)if(str_starts_with($file,'/admin/')){
        originCheck(gncAdminOriginRedirect($file.'?page=home','GET')==='https://admin.gncipampanga.com/admin'.$alias.'?page=home','Old admin file redirects to isolated origin');
        originCheck(gncAdminOriginRedirect($alias,'HEAD')==='https://admin.gncipampanga.com/admin'.$alias,'Old bookmarks move to isolated origin');
        originCheck(gncAdminOriginRedirect($file,'POST')===null,'Do not redirect submissions between origins');
    }
    foreach(['/admin/service-worker.js','/admin/action/page-content-handler.php','/admin/auth/auth_handler.php','/manifest.json','/programs/bsed','/'] as $path){
        originCheck(gncAdminOriginRedirect($path,'GET')===null,'Leave public/resources/action endpoints unchanged');
    }
    originCheck(gncAdminOriginRedirect('/index.php?ce_preview=1','GET')===null,'Do not move authenticated editor frames');
    originCheck(authPublicOrigin()==='https://admin.gncipampanga.com','Password reset links use admin origin');
    $_SERVER['HTTP_HOST']='evil.example';originCheck(gncAdminOriginRedirect('/admin/login','GET')===null,'Untrusted Host does not redirect');
    $_SERVER['HTTP_HOST']='admin.gncipampanga.com';
    originCheck(gncAdminOriginRedirect('/','GET')==='/admin/login','Subdomain root launches admin login');
    originCheck(gncAdminOriginRedirect('/admin/login','GET')===null,'No cross-origin redirect loop');
    $html=gncBrowserHtml('<html><head></head><body><a href="/admin/settings.php">Settings</a><a href="/">Visitor</a><a href="/pages/about/vision-mission.php">Vision</a></body></html>','/admin/admin-dash.php');
    originCheck(str_contains($html,'href="/admin/settings"'),'Sidebar remains same-origin');
    originCheck(str_contains($html,'href="https://gncipampanga.com/"'),'Visitor link goes to actual public origin');
    originCheck(str_contains($html,'href="https://gncipampanga.com/vision-mission"'),'Public page links go to actual public origin');
    originCheck(!str_contains($html,'href="/manifest.json"'),'Never inject visitor manifest on admin origin');
    originCheck(gncPwaHtml('<html><head></head></html>','/index.php')==='<html><head></head></html>','No public worker injection on admin origin');
    putenv('ADMIN_PWA_ORIGIN=');unset($_ENV['ADMIN_PWA_ORIGIN']);
    originCheck(authPublicOrigin()==='https://admin.gncipampanga.com','Admin host can be tested before activating old-link redirects');
    $_SERVER['HTTP_HOST']='admin.localhost:8080';
    originCheck(authLocalDevelopment(),'Loopback hostname supports secure-context browser tests');
    originCheck(authPublicOrigin()==='http://admin.localhost:8080','Local admin test origin remains isolated');
    echo "PASS: $checks independent-origin routing, authentication URL and preview safety checks. No database writes.\n";
} finally {
    $saved===false?putenv('ADMIN_PWA_ORIGIN'):putenv('ADMIN_PWA_ORIGIN='.$saved);
    if($savedEnv===null)unset($_ENV['ADMIN_PWA_ORIGIN']);else $_ENV['ADMIN_PWA_ORIGIN']=$savedEnv;
}
