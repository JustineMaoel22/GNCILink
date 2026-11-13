<?php
/** Local Apache check using an isolated pending session; no OTP/email/account writes. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/config.php';
$user=getDB()->query("SELECT user_id,email,credential_version FROM users WHERE status='active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$user) throw new RuntimeException('A local active user is required for the pending-session fixture.');
session_start();
$session=session_id();
$_SESSION=['_initiated'=>true,'last_activity'=>time(),'csrf_token'=>bin2hex(random_bytes(32)),
    'temp_user_id'=>(int)$user['user_id'],'temp_email'=>$user['email'],'temp_credential_version'=>(int)$user['credential_version']];
session_write_close();
$request=static function(string $path, bool $post=false)use($session): array {
    $ch=curl_init('http://127.0.0.1'.$path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>15,
        CURLOPT_COOKIE=>session_name().'='.$session]);
    if ($post) curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['csrf_token'=>'invalid-test-token','otp_1'=>'0'])]);
    $response=curl_exec($ch);
    if ($response===false) throw new RuntimeException('Local OTP route request failed.');
    $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status,$response];
};
try {
    foreach (['/admin/auth/verify-otp.php','/admin/verify-login'] as $legacy) {
        [$status,$response]=$request($legacy);
        if ($status!==302 || !str_contains($response,"Location: /verify-login\r\n")) throw new RuntimeException('Legacy OTP URL did not redirect cleanly.');
    }
    [$status,$response]=$request('/verify-login');
    if ($status!==200 || !str_contains($response,'action="/verify-login"') || !str_contains($response,'name="csrf_token"')) throw new RuntimeException('Clean OTP route did not render the secured form.');
    if (!str_contains($response,'Cache-Control: no-store')) throw new RuntimeException('OTP page must remain uncacheable.');
    [$status,$response]=$request('/verify-login',true);
    if ($status!==200 || !str_contains($response,'Your session token expired.') || !str_contains($response,'action="/verify-login"')) throw new RuntimeException('Clean OTP POST must retain CSRF rejection and clean form action.');
} finally {
    session_id($session);session_start();$_SESSION=[];session_destroy();
}
echo "PASS: legacy redirects, directory-free OTP form, private cache controls and CSRF-protected POST. No OTP/email/account writes.\n";
