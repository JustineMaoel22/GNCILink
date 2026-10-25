<?php
if(PHP_SAPI!=='cli'||!preg_match('/^gnci_rbac_test_[a-f0-9]{12}$/D',$argv[1]??''))exit(1);
error_reporting(E_ERROR|E_PARSE);
define('DB_NAME',$argv[1]);define('MAIL_HOST','127.0.0.1');define('MAIL_PORT',2527);define('MAIL_USERNAME','fixture@example.invalid');define('MAIL_PASSWORD','synthetic-test-only');define('MAIL_ENCRYPTION','');
function authPwnedRange(string $prefix): string {
 if(($GLOBALS['breachTest']??'')==='unavailable')throw new RuntimeException('Password safety check is temporarily unavailable.');
 $hash=strtoupper(sha1('compromised synthetic passphrase'));
 return $prefix===substr($hash,0,5)?substr($hash,5).':17':str_repeat('0',35).':0';
}
require __DIR__.'/../config/config.php';require __DIR__.'/../admin/admin-functions.php';
function verifySecurity(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);}
function failsSecurity(callable $fn,string $label): void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){return;}throw new LogicException($label);}
$db=getDB();$_SERVER['REMOTE_ADDR']='127.0.0.1';
$mailFile=sys_get_temp_dir().'/gnci-auth-mail-'.bin2hex(random_bytes(8));
$process=proc_open([PHP_BINARY,__DIR__.'/auth-smtp-sink.php',$mailFile],[0=>['pipe','r'],1=>['file','/dev/null','a'],2=>['file','/dev/null','a']],$pipes);
if(!is_resource($process))throw new RuntimeException('SMTP receiver could not start.');fclose($pipes[0]);usleep(300000);
try{
 $db->exec('DELETE FROM auth_rate_limits');
 foreach([1,2,3] as $id){
  $old='original synthetic phrase '.$id;$new='renewed lantern orchard phrase '.$id;
  $db->prepare('UPDATE users SET password=?,login_attempts=0,locked_until=NULL WHERE user_id=?')->execute([password_hash($old,PASSWORD_DEFAULT),$id]);
  for($n=0;$n<5;$n++)verifySecurity(authCheckCredentials('rbac'.$id.'@example.invalid','wrong')===null,'Bad password accepted');
  $q=$db->prepare('SELECT login_attempts,TIMESTAMPDIFF(SECOND,NOW(),locked_until) AS remaining FROM users WHERE user_id=?');$q->execute([$id]);$lock=$q->fetch();verifySecurity((int)$lock['login_attempts']===5&&(int)$lock['remaining']>=895&&(int)$lock['remaining']<=900,'Five failures did not lock for 15 minutes');
  verifySecurity(authCheckCredentials('rbac'.$id.'@example.invalid',$old)===null,'Locked account signed in');
  $db->prepare('UPDATE users SET locked_until=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE user_id=?')->execute([$id]);verifySecurity(authCheckCredentials('rbac'.$id.'@example.invalid',$old)!==null,'Expired lock did not clear');
  authRequestReset('rbac'.$id.'@example.invalid');
  $messages=array_map('json_decode',file($mailFile,FILE_IGNORE_NEW_LINES));$message=end($messages);verifySecurity((bool)preg_match('/token=([a-f0-9]{64})/',$message,$match),'SMTP reset message missing link');$token=$match[1];
  $q=$db->prepare('SELECT token_hash,TIMESTAMPDIFF(SECOND,NOW(),expires_at) AS ttl FROM auth_password_resets WHERE user_id=?');$q->execute([$id]);$reset=$q->fetch();verifySecurity($reset['token_hash']===hash('sha256',$token)&&$reset['token_hash']!==$token&&(int)$reset['ttl']>880&&(int)$reset['ttl']<=900,'Token storage or expiry incorrect');
  failsSecurity(fn()=>authResetPassword($token,$old,$old),'Current password reused');
  failsSecurity(fn()=>authResetPassword($token,'compromised synthetic passphrase','compromised synthetic passphrase'),'Compromised password accepted');
  $GLOBALS['breachTest']='unavailable';failsSecurity(fn()=>authResetPassword($token,$new,$new),'Unavailable breach check accepted');unset($GLOBALS['breachTest']);
  $db->prepare('UPDATE auth_password_resets SET expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE user_id=?')->execute([$id]);verifySecurity(!authResetTokenValid($token),'Expired token accepted');failsSecurity(fn()=>authResetPassword($token,$new,$new),'Expired reset succeeded');
  $db->prepare('UPDATE auth_password_resets SET expires_at=DATE_ADD(NOW(),INTERVAL 15 MINUTE) WHERE user_id=?')->execute([$id]);
  $q=$db->prepare('SELECT credential_version FROM users WHERE user_id=?');$q->execute([$id]);$version=(int)$q->fetchColumn();
  authResetPassword($token,$new,$new);verifySecurity(!authResetTokenValid($token),'Used reset token remains valid');failsSecurity(fn()=>authResetPassword($token,$new,$new),'Used token accepted twice');
  $q=$db->prepare('SELECT * FROM users WHERE user_id=?');$q->execute([$id]);$u=$q->fetch();verifySecurity(password_verify($new,$u['password'])&&!password_verify($old,$u['password'])&&(int)$u['credential_version']===$version+1&&!$u['remember_token']&&!$u['otp_code']&&!(int)$u['must_change_password'],'Reset did not revoke credentials');
  $messages=array_map('json_decode',file($mailFile,FILE_IGNORE_NEW_LINES));verifySecurity(str_contains(end($messages),'password was reset successfully'),'Reset notification missing');
 }
 $before=count(file($mailFile));authRequestReset('absent@example.invalid');verifySecurity(count(file($mailFile))===$before,'Unknown account received email');
 $db->exec('DELETE FROM auth_rate_limits');$version=(int)$db->query('SELECT credential_version FROM users WHERE user_id=3')->fetchColumn();$_SESSION=['temp_user_id'=>3,'temp_credential_version'=>$version];
 $db->exec("UPDATE users SET otp_code='123456',otp_expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE user_id=3");verifySecurity(authConsumeOtp('123456')===null,'Expired OTP accepted');
 $db->exec("UPDATE users SET otp_code='123456',otp_expires_at=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE user_id=3");for($n=0;$n<4;$n++)verifySecurity(authConsumeOtp('000000')===null,'Wrong OTP accepted');verifySecurity(authConsumeOtp('123456')===null,'OTP limit bypassed');
 $db->exec('DELETE FROM auth_rate_limits');$_SESSION['temp_credential_version']=$version-1;verifySecurity(authConsumeOtp('123456')===null,'Old OTP session version accepted');$_SESSION['temp_credential_version']=$version;verifySecurity(authConsumeOtp('123456')!==null,'Valid OTP refused');verifySecurity(authConsumeOtp('123456')===null,'OTP reused');
 $db->exec('DELETE FROM auth_rate_limits');for($n=0;$n<5;$n++)authRequestReset('rbac1@example.invalid');verifySecurity(count(file($mailFile))===$before+3,'Per-email request limit failed');
 // Stop the SMTP server; failure must not leave a usable recovery token.
 proc_terminate($process);proc_close($process);$process=null;$db->exec('DELETE FROM auth_rate_limits');authRequestReset('rbac2@example.invalid');
 verifySecurity(!(bool)$db->query('SELECT user_id FROM auth_password_resets WHERE user_id=2')->fetchColumn(),'Failed delivery retained token');
 verifySecurity((int)$db->query("SELECT COUNT(*) FROM auth_security_logs WHERE action='AUTH_MAIL_FAILED'")->fetchColumn()>0,'Mail failure not audited');
 verifySecurity((int)$db->query("SELECT COUNT(*) FROM auth_security_logs WHERE action='LOGIN_LOCKOUT'")->fetchColumn()===3,'Lockouts not audited for all roles');
 verifySecurity((int)$db->query("SELECT COUNT(*) FROM auth_security_logs WHERE action='PASSWORD_RESET' AND user_id IN (1,2,3)")->fetchColumn()===3,'Resets not audited for all roles');
 echo "PASS: all-role lockouts, expiry, hashed single-use reset tokens, reuse/compromise checks, session version revocation, recovery throttling, real local SMTP acceptance/notification and delivery failure auditing. No external email sent.\n";
}finally{if(is_resource($process)){proc_terminate($process);proc_close($process);}if(is_file($mailFile))unlink($mailFile);}
