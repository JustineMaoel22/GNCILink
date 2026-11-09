<?php
/** Authentication controls shared by every admin role. No credentials belong in logs. */
require_once __DIR__.'/auth-email-templates.php';
require_once __DIR__.'/admin-origin.php';
function authIp(): string {
    $ip=$_SERVER['REMOTE_ADDR']??'';
    return filter_var($ip,FILTER_VALIDATE_IP)?$ip:'unknown';
}
function authIsHttps(): bool {
    if((!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')||(int)($_SERVER['SERVER_PORT']??0)===443||strtolower((string)($_SERVER['REQUEST_SCHEME']??''))==='https')return true;
    if(strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))==='https'||strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL']??''))==='on')return true;
    if(!empty($_SERVER['HTTP_CF_VISITOR'])&&str_contains((string)$_SERVER['HTTP_CF_VISITOR'],'"scheme":"https"'))return true;
    $trusted=array_filter(array_map('trim',explode(',',(string)env('AUTH_TRUSTED_PROXIES',''))));
    return in_array(authIp(),$trusted,true)&&($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https';
}
function authNormalizeUrl(string $url): string {
    $url=trim($url);
    if($url==='')return '';
    if(!preg_match('#^https?://#i',$url)){
        $host=strtolower((string)preg_replace('#[:/].*$#','',$url));
        $scheme=in_array($host,['localhost','127.0.0.1','::1','[::1]'],true)?'http://':'https://';
        $url=$scheme.ltrim($url,'/');
    }
    return $url;
}
function authLocalDevelopment(): bool {
    $local=['localhost','127.0.0.1','::1','[::1]'];
    $configuredHost=strtolower((string)parse_url(authNormalizeUrl((string)APP_URL),PHP_URL_HOST));
    $configured=in_array($configuredHost,$local,true);
    $reqHost=strtolower((string)parse_url('http://'.($_SERVER['HTTP_HOST']??$_SERVER['SERVER_NAME']??''),PHP_URL_HOST));
    if(in_array($reqHost,$local,true)||$reqHost==='admin.localhost')return true;
    return $configured&&PHP_SAPI==='cli';
}
function authPublicOrigin(): string {
    // Reset links and transport redirects belong to the configured admin app, not the enclosing visitor app.
    $adminOrigin=gncAdminPwaOrigin() ?? gncAdminRequestOrigin();
    if ($adminOrigin!==null) return $adminOrigin;
    $local=['localhost','127.0.0.1','::1','[::1]'];
    $explicit=trim((string)env('AUTH_PUBLIC_URL',''));
    $candidate=$explicit!==''?$explicit:(string)APP_URL;
    $parts=parse_url(authNormalizeUrl($candidate));
    // If AUTH_PUBLIC_URL was not set and APP_URL still points to localhost while running on a live domain, use the live host over HTTPS.
    $reqHostRaw=trim((string)($_SERVER['HTTP_HOST']??$_SERVER['SERVER_NAME']??''));
    $reqParts=$reqHostRaw!==''?parse_url('http://'.$reqHostRaw):false;
    $reqHost=strtolower((string)($reqParts['host']??''));
    if($explicit===''&&$reqHost!==''&&!in_array($reqHost,$local,true)&&preg_match('/^[a-z0-9.-]+$/i',$reqHost)&&in_array(strtolower((string)($parts['host']??'')),$local,true)){
        $scheme=authIsHttps()||!in_array($reqHost,$local,true)?'https':'http';
        $port=isset($reqParts['port'])&&(int)$reqParts['port']!==80&&(int)$reqParts['port']!==443?':'.(int)$reqParts['port']:'';
        return $scheme.'://'.$reqHost.$port;
    }
    if(!$parts||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||!in_array($parts['scheme']??'',['https','http'],true))throw new RuntimeException('Authentication URL is not configured.');
    $host=strtolower((string)$parts['host']);
    $scheme=strtolower((string)$parts['scheme']);
    if($scheme!=='https'&&!in_array($host,$local,true)){
        $scheme='https';
    }
    return $scheme.'://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'');
}
function authEnforceTransport(): void {
    if(PHP_SAPI==='cli'||rbacRequestRoute()==='')return;
    header('Cache-Control: no-store, max-age=0');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');
    if(authIsHttps()){header('Strict-Transport-Security: max-age=31536000');return;}
    // Approval replay authenticates the private worker session and nonce at the RBAC boundary.
    $worker=$_SESSION['_rbac_worker']??null;
    $localWorker=in_array(authIp(),['127.0.0.1','::1'],true)||(isset($_SERVER['SERVER_ADDR'])&&authIp()===$_SERVER['SERVER_ADDR']);
    if($localWorker&&is_array($worker)&&rbacIsSuper()&&($worker['expires']??0)>=time()&&($worker['route']??'')===rbacRequestRoute()&&empty($worker['used'])&&is_string($_SERVER['HTTP_X_GNCI_REVIEW']??null)&&hash_equals($worker['nonce']??'',$_SERVER['HTTP_X_GNCI_REVIEW']))return;
    if(authLocalDevelopment())return;
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(403);exit('HTTPS is required. Open the secure login page and try again.');}
    try{$origin=authPublicOrigin();if(!str_starts_with($origin,'https://'))throw new RuntimeException('HTTPS origin required.');}catch(Throwable $e){http_response_code(503);exit('Secure authentication is not configured.');}
    $uri=$_SERVER['REQUEST_URI']??'/admin/login.php';
    if(!str_starts_with($uri,'/admin/')||preg_match('/[\r\n]/',$uri))$uri='/admin/login.php';
    header('Location: '.$origin.$uri,true,302);exit;
}
function authAudit(?int $userId,string $action,string $description): void {
    try{getDB()->prepare('INSERT INTO auth_security_logs(user_id,action,ip_address,description) VALUES(?,?,?,?)')->execute([$userId,$action,authIp(),substr($description,0,255)]);}
    catch(Throwable $e){error_log('Authentication audit unavailable; action='.$action.': '.$e->getMessage());}
}
function authEnsureSchema(): void {
    $db=getDB();
    $db->exec('CREATE TABLE IF NOT EXISTS auth_password_resets (
        user_id INT NOT NULL PRIMARY KEY,
        token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $db->exec('CREATE TABLE IF NOT EXISTS auth_rate_limits (
        bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
        attempts INT UNSIGNED NOT NULL DEFAULT 1,
        expires_at DATETIME NOT NULL,
        INDEX auth_rate_expiry(expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $db->exec('CREATE TABLE IF NOT EXISTS auth_security_logs (
        log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(50) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        description VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX auth_log_time(created_at),
        INDEX auth_log_user(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
function authSecurityReady(): bool {
    try{
        getDB()->query('SELECT token_hash FROM auth_password_resets LIMIT 0');
        getDB()->query('SELECT bucket_hash FROM auth_rate_limits LIMIT 0');
        getDB()->query('SELECT log_id FROM auth_security_logs LIMIT 0');
        return firstLoginPasswordReady();
    }catch(Throwable $e){
        try{
            authEnsureSchema();
            getDB()->query('SELECT token_hash FROM auth_password_resets LIMIT 0');
            getDB()->query('SELECT bucket_hash FROM auth_rate_limits LIMIT 0');
            getDB()->query('SELECT log_id FROM auth_security_logs LIMIT 0');
            return firstLoginPasswordReady();
        }catch(Throwable $migrateErr){
            error_log('Authentication security schema initialization failed: '.$migrateErr->getMessage());
            return false;
        }
    }
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
        $q=$db->prepare('SELECT * FROM users WHERE LOWER(TRIM(email))=LOWER(TRIM(?)) FOR UPDATE');$q->execute([$email]);$user=$q->fetch();
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
function authSanitizeMailError(string $msg): string {
    $secrets=array_filter([MAIL_PASSWORD,preg_replace('/\s+/','',MAIL_PASSWORD)]);
    foreach($secrets as $secret){
        if($secret!=='')$msg=str_replace($secret,'[REDACTED]',$msg);
    }
    $msg=preg_replace('/https?:\/\/\S*token=[a-f0-9]+/i','[RESET_URL]',$msg);
    $msg=preg_replace('/\s+/',' ',trim($msg));
    return $msg!==''?$msg:'Unknown mail transport error';
}
function authLoadMailer(): bool {
    if(class_exists(\PHPMailer\PHPMailer\PHPMailer::class))return true;
    $autoload=__DIR__.'/../vendor/autoload.php';
    if(is_file($autoload)){require_once $autoload;if(class_exists(\PHPMailer\PHPMailer\PHPMailer::class))return true;}
    $src=__DIR__.'/../vendor/phpmailer/phpmailer/src';
    if(is_file($src.'/PHPMailer.php')&&is_file($src.'/SMTP.php')&&is_file($src.'/Exception.php')){
        require_once $src.'/Exception.php';require_once $src.'/PHPMailer.php';require_once $src.'/SMTP.php';
        return class_exists(\PHPMailer\PHPMailer\PHPMailer::class);
    }
    return false;
}
/** Email is successful when PHPMailer SMTP or verified host MTA accepts the message. */
function authSendMail(string $email,string $name,string $subject,string $body,?int $id=null,?string $htmlBody=null): bool {
    $host=trim((string)MAIL_HOST);
    $user=trim((string)MAIL_USERNAME);
    $rawPass=trim((string)MAIL_PASSWORD);
    $fromAddr=trim((string)env('MAIL_FROM_ADDRESS',$user));
    if(!filter_var($fromAddr,FILTER_VALIDATE_EMAIL))$fromAddr=filter_var($user,FILTER_VALIDATE_EMAIL)?$user:'no-reply@gnc.edu.ph';
    $fromName=trim((string)MAIL_FROM_NAME)?:'GNC Admin System';
    $errors=[];

    if($host!==''&&$user!==''&&$rawPass!==''&&authLoadMailer()){
        $compactPass=preg_replace('/\s+/','',$rawPass);
        // Gmail App Passwords are 16 chars often copied as 19 chars with spaces ("xxxx xxxx xxxx xxxx").
        $passwords=($compactPass!==''&&$compactPass!==$rawPass)?[$compactPass,$rawPass]:[$rawPass];
        $cfgPort=(int)MAIL_PORT?:587;
        $cfgEnc=strtolower(trim((string)MAIL_ENCRYPTION));
        $primaryEnc=($cfgPort===465||$cfgEnc==='ssl'||$cfgEnc==='smtps')
            ?\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            :\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $transports=[[$cfgPort,$primaryEnc]];
        // Only add automatic port fallback for real external SMTP servers (not custom local test ports)
        if(!in_array(strtolower($host),['localhost','127.0.0.1','::1'],true)&&in_array($cfgPort,[465,587],true)){
            $altPort=$cfgPort===465?587:465;
            $altEnc=$altPort===465?\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS:\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $transports[]=[$altPort,$altEnc];
        }

        foreach($transports as [$port,$enc]){
            foreach($passwords as $passAttempt){
                try{
                    $mail=new \PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host=$host;
                    $mail->Port=$port;
                    $mail->SMTPAuth=true;
                    $mail->Username=$user;
                    $mail->Password=$passAttempt;
                    $mail->SMTPSecure=$enc;
                    $mail->Timeout=12;
                    $mail->SMTPDebug=0;
                    if(authLocalDevelopment()){
                        $mail->SMTPOptions=['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true]];
                    }
                    $mail->setFrom($fromAddr,$fromName);
                    $mail->addAddress($email,$name);
                    $mail->CharSet='UTF-8';
                    $mail->Subject=$subject;
                    if($htmlBody!==null&&$htmlBody!==''){
                        $mail->isHTML(true);
                        $mail->Body=$htmlBody;
                        $mail->AltBody=$body;
                    }else{
                        $mail->isHTML(false);
                        $mail->Body=$body;
                    }
                    if(!$mail->send())throw new RuntimeException('SMTP did not accept the message.');
                    return true;
                }catch(Throwable $e){
                    $err=authSanitizeMailError($e->getMessage());
                    $errors[]="SMTP($host:$port): $err";
                    // If the error is a network/socket timeout rather than authentication failure, skip testing second password format on same dead port
                    if(!preg_match('/authenticate|username|password|535|534/i',$err))break;
                }
            }
        }
    }else{
        $missing=[];
        if($host==='')$missing[]='MAIL_HOST';
        if($user==='')$missing[]='MAIL_USERNAME';
        if($rawPass==='')$missing[]='MAIL_PASSWORD';
        if(!authLoadMailer())$missing[]='PHPMailer(vendor)';
        $errors[]='Missing configuration: '.implode(', ',$missing);
    }

    // Hosted cPanel fallback: if external SMTP is blocked by the host's outbound SMTP firewall, attempt local MTA delivery when not running against a local mock SMTP port.
    if(!authLocalDevelopment()&&function_exists('mail')){
        $safeFrom=str_replace(["\r","\n"],'',$fromAddr);
        $headers="From: $fromName <$safeFrom>\r\nReply-To: $safeFrom\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8";
        $message=$body;
        if($htmlBody!==null&&$htmlBody!==''){
            $boundary='gnci_alt_'.bin2hex(random_bytes(16));
            $headers="From: $fromName <$safeFrom>\r\nReply-To: $safeFrom\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$boundary\"";
            $message=authEmailAlternativeBody($body,$htmlBody,$boundary);
        }
        if(@mail($email,$subject,$message,$headers)){
            error_log('Authentication email sent via local MTA fallback after SMTP error: '.implode(' | ',$errors));
            return true;
        }
        $errors[]='Local mail() fallback also failed';
    }

    $summary=authSanitizeMailError(implode(' | ',$errors));
    authAudit($id,'AUTH_MAIL_FAILED','Email delivery failed: '.$summary);
    error_log('Authentication email delivery failed for user '.($id??'unknown').': '.$summary);
    return false;
}
function authRequestReset(string $email): bool {
    $normalizedEmail=strtolower(trim($email));
    try{getDB()->exec('DELETE FROM auth_password_resets WHERE expires_at<=NOW()');}catch(Throwable $e){}
    $allowedIp=authRateAllowed('reset-ip',authIp(),10,900);
    $allowedEmail=authRateAllowed('reset-email',$normalizedEmail,3,900);
    $allowedCooldown=authRateAllowed('reset-cooldown',$normalizedEmail,1,60);
    if(!$allowedIp||!$allowedEmail||!$allowedCooldown){
        authAudit(null,'RESET_RATE_LIMIT','Password recovery rate limit reached.');
        return false;
    }
    $q=getDB()->prepare("SELECT user_id,first_name,email FROM users WHERE LOWER(TRIM(email))=LOWER(TRIM(?)) AND status='active' LIMIT 1");
    $q->execute([$normalizedEmail]);
    $u=$q->fetch();
    authAudit($u?(int)$u['user_id']:null,'FORGOT_PASSWORD','Password recovery requested.');
    if(!$u)return true;
    $token=bin2hex(random_bytes(32));
    $hash=hash('sha256',$token);
    try{
        $url=authPublicOrigin().'/admin/reset-password.php?token='.$token;
    }catch(Throwable $e){
        authAudit((int)$u['user_id'],'AUTH_CONFIGURATION_ERROR','Invalid public URL for password recovery: '.$e->getMessage());
        error_log('Password recovery URL configuration error: '.$e->getMessage());
        return false;
    }
    getDB()->prepare('INSERT INTO auth_password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 15 MINUTE)) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),expires_at=VALUES(expires_at),created_at=NOW()')->execute([$u['user_id'],$hash]);
    $plainBody="Hello {$u['first_name']},\n\nA password reset was requested for your GNCILink Admin Panel account.\n\nOpen this link within 15 minutes:\n$url\n\nThis link can be used once. If you did not request it, ignore this email. Your password has not changed.";
    $htmlBody=authBuildResetEmailHtml((string)$u['first_name'],$url);
    if(!authSendMail($u['email'],(string)$u['first_name'],'GNCILink - Reset your password',$plainBody,(int)$u['user_id'],$htmlBody)){
        getDB()->prepare('DELETE FROM auth_password_resets WHERE user_id=? AND token_hash=?')->execute([$u['user_id'],$hash]);
        return false;
    }
    authAudit((int)$u['user_id'],'RESET_EMAIL_SENT','Password reset link emailed to registered account.');
    return true;
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
    authSendMail($u['email'],$u['first_name'],'GNCILink - Your password was changed',"Your GNCILink password was reset successfully. Existing sessions have been signed out.\n\nIf you did not make this change, contact your Super Admin immediately.",(int)$id,authBuildPasswordChangedEmailHtml((string)$u['first_name']));
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
