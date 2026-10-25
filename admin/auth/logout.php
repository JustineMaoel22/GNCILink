<?php
require_once __DIR__.'/../../config/config.php';require_once __DIR__.'/../admin-functions.php';initSession();
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!authCsrfValid())rbacFail('Your session token expired. Reload and try again.',403);
 if(isset($_SESSION['user_id'])){
  $id=(int)$_SESSION['user_id'];getDB()->prepare('UPDATE users SET remember_token=NULL,remember_token_expires_at=NULL WHERE user_id=?')->execute([$id]);
  logActivity($id,'LOGOUT',null,null,'User logged out.');
 }
 authClearSession();header('Location: /admin/login.php',true,303);exit;
}
$authTitle='Sign out';$authIntro='Confirm that you want to sign out of GNCILink.';require __DIR__.'/../../components/auth-page-start.php';
?>
<form method="post" data-auth-form><input type="hidden" name="csrf_token" value="<?= $e(generateCSRFToken()) ?>"><button type="submit" class="btn-login">Sign out</button></form><p class="text-center mt-3"><a href="<?= $e(isLoggedIn()?rbacHome():'/admin/login.php') ?>">Go back</a></p>
<?php require __DIR__.'/../../components/auth-page-end.php';?>
