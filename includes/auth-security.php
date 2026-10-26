<?php
/** Authentication controls shared by every admin role. No credentials belong in logs. */
function authIp(): string {
    $ip=$_SERVER['REMOTE_ADDR']??'';
    return filter_var($ip,FILTER_VALIDATE_IP)?$ip:'unknown';
}
function authIsHttps(): bool {
    if((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||(int)($_SERVER['SERVER_PORT']??0)===443)return true;
    $trusted=array_filter(array_map('trim',explode(',',(string)env('AUTH_TRUSTED_PROXIES',''))));
    return in_array(authIp(),$trusted,true)&&($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https';
}
function authLocalDevelopment(): bool {
    $local=['localhost','127.0.0.1','::1','[::1]'];
    $configured=in_array(strtolower((string)parse_url(APP_URL,PHP_URL_HOST)),$local,true);
    $requested=strtolower((string)parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST));
    return $configured&&(PHP_SAPI==='cli'||in_array($requested,$local,true));
}
function authPublicOrigin(): string {
    $url=(string)env('AUTH_PUBLIC_URL',APP_URL);$parts=parse_url($url);
    if(!$parts||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||!in_array($parts['scheme']??'',['https','http'],true))throw new RuntimeException('Authentication URL is not configured.');
    if(($parts['scheme']??'')!=='https'&&!in_array(strtolower($parts['host']),['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Authentication URL must use HTTPS.');
    return $parts['scheme'].'://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'');
}
function authEnforceTransport(): void {
    if(PHP_SAPI==='cli'||rbacRequestRoute()==='')return;
    header('Cache-Control: no-store, max-age=0');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');
    if(authIsHttps()){header('Strict-Transport-Security: max-age=31536000');return;}
    // Approval replay authenticates the private worker session and nonce at the RBAC boundary.
    $worker=$_SESSION['_rbac_worker']??null;
    if(in_array(authIp(),['127.0.0.1','::1'],true)&&is_array($worker)&&rbacIsSuper()&&($worker['expires']??0)>=time()&&($worker['route']??'')===rbacRequestRoute()&&empty($worker['used'])&&is_string($_SERVER['HTTP_X_GNCI_REVIEW']??null)&&hash_equals($worker['nonce']??'',$_SERVER['HTTP_X_GNCI_REVIEW']))return;
    if(authLocalDevelopment())return;
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(403);exit('HTTPS is required. Open the secure login page and try again.');}
    try{$origin=authPublicOrigin();if(!str_starts_with($origin,'https://'))throw new RuntimeException('HTTPS origin required.');}catch(Throwable $e){http_response_code(503);exit('Secure authentication is not configured.');}
    $uri=$_SERVER['REQUEST_URI']??'/admin/login.php';
    if(!str_starts_with($uri,'/admin/')||preg_match('/[\r\n]/',$uri))$uri='/admin/login.php';
    header('Location: '.$origin.$uri,true,302);exit;
}
function authAudit(?int $userId,string $action,string $description): void {
    try{getDB()->prepare('INSERT INTO auth_security_logs(user_id,action,ip_address,description) VALUES(?,?,?,?)')->execute([$userId,$action,authIp(),$description]);}
    catch(Throwable $e){error_log('Authentication audit unavailable; action='.$action);}
}
function authSecurityReady(): bool {
    try{getDB()->query('SELECT token_hash FROM auth_password_resets LIMIT 0');getDB()->query('SELECT bucket_hash FROM auth_rate_limits LIMIT 0');getDB()->query('SELECT log_id FROM auth_security_logs LIMIT 0');return firstLoginPasswordReady();}catch(Throwable $e){return false;}
}
function authRateAllowed(string $scope,string $key,int $limit,int $seconds): bool {
    $hash=hash('sha256',$scope.':'.$key);$db=getDB();
    $q=$db->prepare('INSERT INTO auth_rate_limits(bucket_hash,attempts,expires_at) VALUES(?,1,TIMESTAMPADD(SECOND,?,NOW())) ON DUPLICATE KEY UPDATE attempts=IF(expires_at<=NOW(),1,attempts+1),expires_at=IF(expires_at<=NOW(),TIMESTAMPADD(SECOND,?,NOW()),expires_at)');
    $q->execute([$hash,$seconds,$seconds]);$q=$db->prepare('SELECT attempts FROM auth_rate_limits WHERE bucket_hash=?');$q->execute([$hash]);
    return (int)$q->fetchColumn()<=$limit;
}
function authCsrfValid(): bool {return is_string($_POST['csrf_token']??null)&&verifyCSRFToken($_POST['csrf_token']);}
/** End the login session without forgetting a separately trusted browser. */
function authClearSession(bool $forgetDevice = false): void {
    $_SESSION=[];
    if(session_status()===PHP_SESSION_ACTIVE){$p=session_get_cookie_params();setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>true,'samesite'=>'Lax']);session_destroy();}
    if ($forgetDevice) {
        setcookie('gnc_remember_me','',['expires'=>time()-3600,'path'=>'/','secure'=>authIsHttps()||!authLocalDevelopment(),'httponly'=>true,'samesite'=>'Strict']);
        unset($_COOKIE['gnc_remember_me']);
    }
}
function authCheckCredentials(string $email,string $password): ?array {
    if(!authRateAllowed('login-ip',authIp(),30,900)){authAudit(null,'LOGIN_RATE_LIMIT','Login rate limit reached.');return null;}
    $db=getDB();$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT * FROM users WHERE email=? FOR UPDATE');$q->execute([$email]);$user=$q->fetch();
        if(!$user){password_verify($password,'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');$db->commit();authAudit(null,'LOGIN_FAILED','Invalid login credentials.');return null;}
        $id=(int)$user['user_id'];$locked=!empty($user['locked_until'])&&strtotime($user['locked_until'])>time();
        if($locked||$user['status']!=='active'){$db->commit();authAudit($id,'LOGIN_FAILED','Login refused.');return null;}
        if(!password_verify($password,$user['password'])){
            $attempts=(!empty($user['locked_until'])?0:(int)$user['login_attempts'])+1;
            $db->prepare('UPDATE users SET login_attempts=?,locked_until=IF(? >= 5,DATE_ADD(NOW(),INTERVAL 15 MINUTE),NULL) WHERE user_id=?')->execute([$attempts,$attempts,$id]);
            $db->commit();authAudit($id,'LOGIN_FAILED','Invalid login credentials.');if($attempts>=5)authAudit($id,'LOGIN_LOCKOUT','Login locked for 15 minutes after five failed attempts.');return null;
        }
        $db->prepare('UPDATE users SET login_attempts=0,locked_until=NULL WHERE user_id=?')->execute([$id]);$db->commit();return $user;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function authConsumeOtp(string $otp): ?array {
    $id=(int)($_SESSION['temp_user_id']??0);
    if(!$id||!authRateAllowed('otp-verify',(string)$id,5,900))return null;
    $db=getDB();$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT *,otp_expires_at>NOW() AS otp_current FROM users WHERE user_id=? FOR UPDATE');$q->execute([$id]);$u=$q->fetch();
        $valid=$u&&$u['status']==='active'&&!empty($u['otp_current'])&&is_string($u['otp_code'])&&hash_equals($u['otp_code'],$otp)
            &&isset($_SESSION['temp_credential_version'])&&(int)$_SESSION['temp_credential_version']===(int)$u['credential_version'];
        if(!$valid){$db->commit();authAudit($id,'OTP_FAILED','Invalid or expired verification code.');return null;}
        $db->prepare('UPDATE users SET otp_code=NULL,otp_expires_at=NULL,otp_verified=1 WHERE user_id=?')->execute([$id]);$db->commit();return $u;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
/** Email is successful only when PHPMailer reports SMTP acceptance. */
function authSendMail(string $email,string $name,string $subject,string $body,?int $id=null): bool {
    try{
        if(!MAIL_HOST||!MAIL_USERNAME||!MAIL_PASSWORD||!is_file(__DIR__.'/../vendor/autoload.php'))throw new RuntimeException('Mail configuration missing.');
        require_once __DIR__.'/../vendor/autoload.php';$mail=new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();$mail->Host=MAIL_HOST;$mail->Port=MAIL_PORT;$mail->SMTPAuth=true;$mail->Username=MAIL_USERNAME;$mail->Password=MAIL_PASSWORD;$mail->SMTPSecure=MAIL_ENCRYPTION;$mail->Timeout=15;$mail->SMTPDebug=0;
        $mail->setFrom((string)env('MAIL_FROM_ADDRESS',MAIL_USERNAME),MAIL_FROM_NAME);$mail->addAddress($email,$name);$mail->CharSet='UTF-8';$mail->Subject=$subject;$mail->Body=$body;$mail->isHTML(false);
        if(!$mail->send())throw new RuntimeException('SMTP did not accept the message.');return true;
    }catch(Throwable $e){authAudit($id,'AUTH_MAIL_FAILED','Authentication email could not be sent. Check SMTP configuration and provider logs.');error_log('Authentication email delivery failed; check SMTP configuration/provider logs.');return false;}
}
function authRequestReset(string $email): void {
    $allowed=authRateAllowed('reset-ip',authIp(),10,900);
    $allowed=authRateAllowed('reset-email',strtolower($email),3,900)&&$allowed;
    if(!$allowed){authAudit(null,'RESET_RATE_LIMIT','Password recovery rate limit reached.');return;}
    $q=getDB()->prepare("SELECT user_id,first_name,email FROM users WHERE email=? AND status='active'");$q->execute([$email]);$u=$q->fetch();
    authAudit($u?(int)$u['user_id']:null,'FORGOT_PASSWORD','Password recovery requested.');if(!$u)return;
    $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
    try{$url=authPublicOrigin().'/admin/reset-password.php?token='.$token;}
    catch(Throwable $e){authAudit((int)$u['user_id'],'AUTH_CONFIGURATION_ERROR','Set a valid HTTPS AUTH_PUBLIC_URL for password recovery.');return;}
    getDB()->prepare('INSERT INTO auth_password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 15 MINUTE)) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),expires_at=VALUES(expires_at),created_at=NOW()')->execute([$u['user_id'],$hash]);
    if(!authSendMail($u['email'],$u['first_name'],'GNCILink - Reset your password',"A password reset was requested for your GNCILink account.\n\nOpen this link within 15 minutes:\n$url\n\nThis link can be used once. If you did not request it, ignore this email. Your password has not changed.",(int)$u['user_id']))getDB()->prepare('DELETE FROM auth_password_resets WHERE user_id=? AND token_hash=?')->execute([$u['user_id'],$hash]);
}
function authResetTokenValid(string $token): bool {
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))return false;
    $q=getDB()->prepare("SELECT r.user_id FROM auth_password_resets r JOIN users u ON u.user_id=r.user_id WHERE token_hash=? AND expires_at>NOW() AND u.status='active'");$q->execute([hash('sha256',$token)]);return (bool)$q->fetchColumn();
}
function authResetPassword(string $token,string $password,string $confirmation): void {
    if(!authRateAllowed('reset-submit',authIp(),20,900))throw new RuntimeException('Too many attempts. Try again in 15 minutes.');
    if(!authResetTokenValid($token))throw new InvalidArgumentException('This reset link is invalid or expired. Request a new link.');
    validateNewPassword($password,$confirmation);authCheckCompromisedPassword($password);
    $db=getDB();$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT user_id FROM auth_password_resets WHERE token_hash=? AND expires_at>NOW() FOR UPDATE');$q->execute([hash('sha256',$token)]);$id=$q->fetchColumn();
        if(!$id)throw new InvalidArgumentException('This reset link is invalid or expired. Request a new link.');
        $q=$db->prepare('SELECT * FROM users WHERE user_id=? FOR UPDATE');$q->execute([$id]);$u=$q->fetch();
        if(!$u||$u['status']!=='active')throw new InvalidArgumentException('This reset link is invalid or expired. Request a new link.');
        if(password_verify($password,$u['password']))throw new InvalidArgumentException('Choose a password different from your current password.');
        $db->prepare('UPDATE users SET password=?,must_change_password=0,password_changed_at=NOW(),credential_version=credential_version+1,remember_token=NULL,remember_token_expires_at=NULL,otp_code=NULL,otp_expires_at=NULL,login_attempts=0,locked_until=NULL WHERE user_id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
        $db->prepare('DELETE FROM auth_password_resets WHERE user_id=?')->execute([$id]);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    authAudit((int)$id,'PASSWORD_RESET','Password reset completed; previous sessions and credentials revoked.');
    authSendMail($u['email'],$u['first_name'],'GNCILink - Your password was changed',"Your GNCILink password was reset successfully. Existing sessions have been signed out.\n\nIf you did not make this change, contact your Super Admin immediately.",(int)$id);
}
// Separate transport function permits deterministic, isolated tests without external password queries.
if(!function_exists('authPwnedRange')){
function authPwnedRange(string $prefix): string {
    if(!function_exists('curl_init'))throw new RuntimeException('Password safety check is temporarily unavailable. Try again later.');
    $curl=curl_init('https://api.pwnedpasswords.com/range/'.$prefix);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_HTTPHEADER=>['Add-Padding: true','User-Agent: GNCILink-Password-Security'],CURLOPT_FOLLOWLOCATION=>false]);
    $body=curl_exec($curl);$code=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
    if(!is_string($body)||$code!==200||!preg_match('/^[A-F0-9]{35}:\d+/m',$body)){authAudit(null,'PASSWORD_CHECK_UNAVAILABLE','Compromised password service unavailable.');throw new RuntimeException('Password safety check is temporarily unavailable. Try again later.');}return $body;
}}
function authCheckCompromisedPassword(string $password): void {
    $hash=strtoupper(sha1($password));$body=authPwnedRange(substr($hash,0,5));
    foreach(explode("\n",$body) as $line){$pair=explode(':',trim($line));if(count($pair)===2&&hash_equals(substr($hash,5),$pair[0])&&(int)$pair[1]>0)throw new InvalidArgumentException('This password has appeared in a data breach. Choose a different password or passphrase.');}
}
