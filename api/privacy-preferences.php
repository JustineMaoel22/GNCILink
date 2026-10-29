<?php
// Clear only the optional HttpOnly visitor identifier, never login cookies.
require_once __DIR__.'/../includes/privacy.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD']??'')!=='POST' || ($_SERVER['HTTP_X_GNC_PRIVACY']??'')!=='1'
    || ($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site') { http_response_code(403);echo '{"ok":false}';exit; }
$origin=$_SERVER['HTTP_ORIGIN']??null;
if ($origin!==null && strcasecmp((string)parse_url($origin,PHP_URL_HOST).(parse_url($origin,PHP_URL_PORT)?':'.parse_url($origin,PHP_URL_PORT):''),(string)($_SERVER['HTTP_HOST']??''))!==0) {
    http_response_code(403);echo '{"ok":false}';exit;
}
if (!gncPrivacyAllows('analytics')) setcookie('gnc_vid','',['expires'=>time()-3600,'path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off']);
if (!gncPrivacyAllows('personalization') || !gncPrivacyAllows('external')) setcookie('gnci_feedback_identity','',['expires'=>time()-3600,'path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off']);
echo '{"ok":true}';
