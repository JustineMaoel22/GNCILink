<?php
/** Read-only cPanel preflight. Run with the same PHP version/configuration as the site. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
$failures=0;
function preflight(bool $ok,string $label): void {global $failures;echo ($ok?'PASS':'CHECK').': '.$label.PHP_EOL;if(!$ok)$failures++;}
preflight(PHP_VERSION_ID>=80200,'PHP 8.2 or newer');
foreach(['pdo_mysql','curl','mbstring','fileinfo','gd','exif','zip'] as $extension)preflight(extension_loaded($extension),'PHP extension '.$extension);
preflight(class_exists('Imagick'),'Imagick upload optimization');
$app=parse_url(APP_URL);
preflight(($app['scheme']??'')==='https'&&!in_array($app['host']??'',['localhost','127.0.0.1','admin.localhost'],true),'Production APP_URL uses the actual HTTPS domain');
$auth=parse_url((string)env('AUTH_PUBLIC_URL',''));
preflight(($auth['scheme']??'')==='https'&&!empty($auth['host']),'AUTH_PUBLIC_URL explicitly configured for HTTPS');
preflight(!filter_var(ini_get('display_errors'),FILTER_VALIDATE_BOOLEAN),'PHP display_errors disabled');
preflight((bool)filter_var(ini_get('log_errors'),FILTER_VALIDATE_BOOLEAN),'PHP log_errors enabled');
preflight(is_writable(UPLOAD_PATH),'Uploads writable by this PHP account');
preflight(is_file(UPLOAD_PATH.'.htaccess'),'Upload execution restrictions deployed');
preflight(in_array(MAIL_TRANSPORT,['mail','smtp'],true),'Email transport is mail or smtp');
preflight((bool)filter_var(MAIL_FROM_ADDRESS,FILTER_VALIDATE_EMAIL),'Email sender address configured (value withheld)');
if (MAIL_TRANSPORT==='mail') {
    preflight(function_exists('mail'),'cPanel/local PHP mail service available');
} elseif (MAIL_TRANSPORT==='smtp') {
    preflight(MAIL_HOST!==''&&MAIL_USERNAME!==''&&MAIL_PASSWORD!=='','SMTP credentials configured (values withheld)');
    preflight(is_file(__DIR__.'/../vendor/autoload.php'),'SMTP dependencies deployed');
}
preflight(trim((string)env('FACE_CHECK_PYTHON',''))!==''&&is_dir((string)env('FACE_CHECK_MODELS',''))&&function_exists('proc_open'),'Personnel face-check runtime configured');
$limitDir=(string)env('REQUEST_LIMIT_DIR','');
$root=str_replace('\\','/',realpath(__DIR__.'/..'));
$resolved=$limitDir!==''?realpath($limitDir):false;
preflight($resolved!==false&&is_writable($resolved)&&!str_starts_with(str_replace('\\','/',$resolved).'/', $root.'/'),'Persistent writable request-limit directory outside document root');
try{
    $db=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    preflight(true,'Database connection');
    $grants=$db->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_COLUMN);
    $broad=false;foreach($grants as $grant)if(str_contains($grant,' ON *.* ')&&!str_starts_with($grant,'GRANT USAGE '))$broad=true;
    preflight(!$broad&&strtolower(DB_USER)!=='root','Database account has no global administrative grants');
    foreach(['users'=>['credential_version','must_change_password'],'auth_rate_limits'=>['bucket_hash'],'auth_password_resets'=>['token_hash'],'auth_security_logs'=>['log_id'],'content_editor_presence'=>['peer_key'],'content_editor_versions'=>['scope_key']] as $table=>$columns){
        try{$db->query('SELECT '.implode(',',array_map(static fn($c)=>'`'.$c.'`',$columns)).' FROM `'.$table.'` LIMIT 0');preflight(true,'Required schema '.$table);}
        catch(Throwable $e){preflight(false,'Required schema '.$table);}
    }
}catch(Throwable $e){preflight(false,'Database connection/schema inspection (details withheld)');}
echo "Manual checks still required: web-SAPI settings, HTTPS certificate, email inbox delivery, provider connectivity, backup restore and production workflows.\n";
exit($failures?1:0);
