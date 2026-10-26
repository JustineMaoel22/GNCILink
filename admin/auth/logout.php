<?php
require_once __DIR__.'/../../config/config.php';require_once __DIR__.'/../admin-functions.php';initSession();
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!authCsrfValid())rbacFail('Your session token expired. Reload and try again.',403);
 if(isset($_SESSION['user_id'])){
  $id=(int)$_SESSION['user_id'];
  if (($_POST['forget_device'] ?? '') === '1') {
   $token = $_COOKIE['gnc_remember_me'] ?? '';
   if (is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token)) {
    getDB()->prepare('UPDATE users SET remember_token=NULL,remember_token_expires_at=NULL WHERE user_id=? AND remember_token=?')->execute([$id, hash('sha256', $token)]);
   }
   authAudit($id, 'TRUSTED_DEVICE_REVOKED', 'Remembered browser revoked at sign out.');
  }
  logActivity($id,'LOGOUT',null,null,'User logged out.');
 }
 authClearSession(($_POST['forget_device'] ?? '') === '1');header('Location: /admin/login.php',true,303);exit;
}
$authTitle='Sign out';$authIntro='Confirm that you want to sign out of GNCILink.';require __DIR__.'/../../components/auth-page-start.php';
?>
<form method="post" data-auth-form><input type="hidden" name="csrf_token" value="<?= $e(generateCSRFToken()) ?>"><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="forget_device" value="1" id="forget-device"><label class="form-check-label" for="forget-device">Forget this device and require a verification code next time</label></div><button type="submit" class="btn-login">Sign out</button></form><p class="text-center mt-3"><a href="<?= $e(isLoggedIn()?rbacHome():'/admin/login.php') ?>">Go back</a></p>
<?php require __DIR__.'/../../components/auth-page-end.php';?>
