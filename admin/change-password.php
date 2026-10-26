<?php
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/admin-functions.php';initSession();requireLogin();
header('Cache-Control: no-store, max-age=0');header('Pragma: no-cache');header('Referrer-Policy: no-referrer');
$error='';$changed=false;
if(empty($_SESSION['must_change_password'])){header('Location: '.rbacHome());exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!is_string($_POST['csrf_token']??null)||!verifyCSRFToken($_POST['csrf_token']))throw new RuntimeException('Your session token expired. Reload and try again.');
  foreach(['current_password','new_password','confirm_password'] as $field)if(!is_string($_POST[$field]??null))throw new InvalidArgumentException('Complete all password fields.');
  changeFirstLoginPassword((int)$_SESSION['user_id'],$_POST['current_password'],$_POST['new_password'],$_POST['confirm_password']);$changed=true;
 }catch(Throwable $e){$error=$e instanceof PDOException?'The password could not be changed. Please try again.':$e->getMessage();}
}
$authTitle=$changed?'Password changed':'Welcome to GNC Admin Panel';$authIntro=$changed?'Your account is ready. Continue for a guided introduction to your workspace.':'Choose your own password before using the admin panel. This step is required for your new account.';
require __DIR__.'/../components/auth-page-start.php';
?>
<?php if($changed):?><div class="alert alert-success" role="status">Your new password is saved. Your temporary password no longer works.</div><a class="btn-login d-block text-center" href="<?= $e(rbacHome()) ?>">Continue to Dashboard</a>
<?php else:?>
<?php if($error):?><div class="alert alert-danger" role="alert"><?= $e($error) ?></div><?php endif;?>
<form method="post" action="/admin/change-password.php" data-auth-form><input type="hidden" name="csrf_token" value="<?= $e(generateCSRFToken()) ?>">
<?php foreach(['current_password'=>'Current Temporary Password','new_password'=>'New Password','confirm_password'=>'Confirm New Password'] as $name=>$label):?><div class="form-group"><label class="form-label" for="<?= $name ?>"><?= $label ?></label><input class="form-control" type="password" id="<?= $name ?>" name="<?= $name ?>" autocomplete="<?= $name==='current_password'?'current-password':'new-password' ?>"<?= $name!=='current_password'?' minlength="12" maxlength="72"':'' ?> required></div><?php endforeach;?>
<?php require __DIR__.'/../components/password-guidance.php';?>
<label class="form-check mb-3"><input class="form-check-input" type="checkbox" id="show-passwords"> Show passwords</label><button class="btn-login" type="submit" data-loading-text="Saving password?">Save new password</button></form><a class="d-inline-block mt-3" href="/admin/auth/logout.php">Sign out</a>
<?php endif;require __DIR__.'/../components/auth-page-end.php';?>
