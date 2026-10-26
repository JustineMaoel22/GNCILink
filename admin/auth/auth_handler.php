<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../admin-functions.php';
initSession();
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&basename($_SERVER['PHP_SELF'])==='auth_handler.php'){
    header('Content-Type: application/json; charset=utf-8');
    if(!authCsrfValid())rbacFail('Your session token expired. Reload and try again.',403);
    if(!authSecurityReady())rbacFail('Authentication is temporarily unavailable. Contact your administrator.',503);
    try{
        switch($_POST['action']??''){
            case 'login':handleLogin();break;
            case 'verify_otp':handleOTPVerification();break;
            case 'resend_otp':handleResendOTP();break;
            default:rbacFail('Invalid action.',400);
        }
    }catch(Throwable $e){error_log('Authentication request failed.');rbacFail('Authentication is temporarily unavailable. Please try again later.',503);}
}
function handleLogin(): void {
    $email=is_string($_POST['email']??null)?trim($_POST['email']):'';$password=is_string($_POST['password']??null)?$_POST['password']:'';
    if(strlen($email)>254||strlen($password)>4096)rbacFail('Invalid username/email or password',401);
    $user=authCheckCredentials($email,$password);
    if(!$user)rbacFail('Invalid username/email or password',401);
    $remember=($_POST['remember_me']??'')==='1';$token=$_COOKIE['gnc_remember_me']??'';
    if(is_string($token)&&$token!==''&&verifyRememberToken((int)$user['user_id'],$token)){
        // Existing trust lasts until its original expiry, independently of this session.
        loginUser($user);
        authAudit((int)$user['user_id'], 'TRUSTED_DEVICE_LOGIN', 'Login verification satisfied by a valid remembered browser.');
        echo json_encode(['success'=>true,'message'=>'Logged in successfully','redirect'=>rbacHome()]);return;
    }
    if(!authRateAllowed('otp-send',(string)$user['user_id'],3,900))rbacFail('Please wait 15 minutes before requesting another verification code.',429);
    $otp=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
    getDB()->prepare('UPDATE users SET otp_code=?,otp_expires_at=TIMESTAMPADD(SECOND,?,NOW()) WHERE user_id=?')->execute([$otp,OTP_EXPIRY,$user['user_id']]);
    if(!sendOTPEmailPHPMailer($user['email'],$user['first_name'],$otp,(int)$user['user_id']))rbacFail('Unable to send the verification email. Try again later.',503);
    session_regenerate_id(true);
    $_SESSION['temp_user_id']=$user['user_id'];$_SESSION['temp_email']=$user['email'];$_SESSION['temp_name']=$user['first_name'];$_SESSION['temp_credential_version']=(int)$user['credential_version'];$_SESSION['remember_me']=$remember;
    authAudit((int)$user['user_id'],'OTP_SENT','Login verification code sent.');
    echo json_encode(['success'=>true,'message'=>'OTP sent to your email','redirect'=>'/admin/auth/verify-otp.php']);
}
function handleOTPVerification(): void {
    $otp=is_string($_POST['otp']??null)?trim($_POST['otp']):'';$user=authConsumeOtp($otp);
    if(!$user)rbacFail('Invalid or expired verification code. Please sign in again if you have tried five times.',401);
    $remember=!empty($_SESSION['remember_me']);loginUser($user);
    if($remember&&empty($user['must_change_password']))generateRememberToken((int)$user['user_id']);
    echo json_encode(['success'=>true,'message'=>'Login successful','redirect'=>rbacHome()]);
}
function resendLoginOtp(): bool {
    $id=(int)($_SESSION['temp_user_id']??0);if(!$id)return false;
    if(!authRateAllowed('otp-resend-minute',(string)$id,1,60)||!authRateAllowed('otp-send',(string)$id,3,900))return false;
    $q=getDB()->prepare("SELECT * FROM users WHERE user_id=? AND status='active'");$q->execute([$id]);$u=$q->fetch();
    if(!$u||(int)($u['credential_version']??0)!==(int)($_SESSION['temp_credential_version']??-1))return false;
    $otp=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
    getDB()->prepare('UPDATE users SET otp_code=?,otp_expires_at=TIMESTAMPADD(SECOND,?,NOW()) WHERE user_id=?')->execute([$otp,OTP_EXPIRY,$id]);
    return sendOTPEmailPHPMailer($u['email'],$u['first_name'],$otp,$id);
}
function handleResendOTP(): void {
    if(!resendLoginOtp())rbacFail('Unable to resend. Wait before trying again or sign in again.',429);
    echo json_encode(['success'=>true,'message'=>'A new verification code was sent.']);
}
function loginUser(array $user): void {
    session_regenerate_id(true);
    unset($_SESSION['temp_user_id'],$_SESSION['temp_email'],$_SESSION['temp_name'],$_SESSION['temp_credential_version'],$_SESSION['remember_me'],$_SESSION['otp_attempts']);
    $_SESSION['csrf_token']=bin2hex(random_bytes(32));
    $_SESSION['must_change_password']=!empty($user['must_change_password']);$_SESSION['credential_version']=(int)$user['credential_version'];
    $_SESSION['user_id']=$user['user_id'];$_SESSION['user_email']=$user['email'];$_SESSION['user_role']=$user['role'];$_SESSION['user_name']=$user['first_name'].' '.$user['last_name'];$_SESSION['otp_verified']=true;$_SESSION['last_activity']=time();
    $_SESSION['user']=array_intersect_key($user,array_flip(['user_id','first_name','last_name','email','role']));
    getDB()->prepare('UPDATE users SET last_login=NOW() WHERE user_id=?')->execute([$user['user_id']]);
    logActivity((int)$user['user_id'],'LOGIN',null,null,'Successful login.');
}
function sendOTPEmailPHPMailer(string $email,string $firstName,string $otp,?int $id=null): bool {
    return authSendMail($email,$firstName,'GNC Admin Panel - One-Time Password (OTP)',"Your GNCILink verification code is: $otp\n\nThis code expires in ".(int)ceil(OTP_EXPIRY/60)." minutes. Do not share it with anyone. If you did not request this code, contact your administrator.",$id);
}
function generateRememberToken(int $id): bool {
    $token=bin2hex(random_bytes(32));getDB()->prepare('UPDATE users SET remember_token=?,remember_token_expires_at=DATE_ADD(NOW(),INTERVAL 30 DAY) WHERE user_id=?')->execute([hash('sha256',$token),$id]);
    return setcookie('gnc_remember_me',$token,['expires'=>time()+30*86400,'path'=>'/','httponly'=>true,'secure'=>authIsHttps()||!authLocalDevelopment(),'samesite'=>'Strict']);
}
function verifyRememberToken(int $id,string $token): bool {
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))return false;
    $q=getDB()->prepare("SELECT remember_token FROM users WHERE user_id=? AND status='active' AND remember_token_expires_at>NOW()");$q->execute([$id]);$hash=$q->fetchColumn();return is_string($hash)&&hash_equals($hash,hash('sha256',$token));
}
function isHttpsRequest(): bool {return authIsHttps();}
