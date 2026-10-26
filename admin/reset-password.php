<?php
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/admin-functions.php';initSession();
// Remove the bearer token from the address bar before loading page assets.
if(isset($_GET['token'])){
    $_SESSION['password_reset_token']=is_string($_GET['token'])?$_GET['token']:'';
    header('Location: /admin/reset-password.php',true,303);exit;
}
$token=$_SESSION['password_reset_token']??'';$error='';$valid=false;
try{$valid=authSecurityReady()&&authResetTokenValid($token);}catch(Throwable $e){error_log('Password reset storage unavailable.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!authCsrfValid())throw new InvalidArgumentException('Your session token expired. Reload and try again.');
        if(!$valid)throw new InvalidArgumentException('This reset link is invalid or expired. Request a new link.');
        if(!is_string($_POST['new_password']??null)||!is_string($_POST['confirm_password']??null))throw new InvalidArgumentException('Complete both password fields.');
        authResetPassword($token,$_POST['new_password'],$_POST['confirm_password']);authClearSession(true);header('Location: /admin/login.php?password_reset=1',true,303);exit;
    }catch(InvalidArgumentException|RuntimeException $e){$error=$e instanceof PDOException?'Password reset is temporarily unavailable. Try again later.':$e->getMessage();}
    catch(Throwable $e){$error='Password reset is temporarily unavailable. Try again later.';error_log('Password reset failed.');}
}
$authTitle='Reset Password';$authIntro='Choose a new password for your GNCILink account.';require __DIR__.'/../components/auth-page-start.php';
?>
<?php if($error):?><div class="alert alert-danger" role="alert"><?= $e($error) ?></div><?php endif;?>
<?php if(!$valid):?><div class="alert alert-warning" role="alert">This reset link is invalid or expired. <a href="/admin/forgot-password.php">Request a new link</a>.</div><?php else:?>
<form method="post" data-auth-form><input type="hidden" name="csrf_token" value="<?= $e(generateCSRFToken()) ?>">
<?php foreach(['new_password'=>'New Password','confirm_password'=>'Confirm New Password'] as $name=>$label):?><div class="form-group"><label class="form-label" for="<?= $name ?>"><?= $label ?></label><input class="form-control" id="<?= $name ?>" name="<?= $name ?>" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></div><?php endforeach;?>
<?php require __DIR__.'/../components/password-guidance.php';?>
<label class="form-check mb-3"><input type="checkbox" class="form-check-input" id="show-passwords"> Show passwords</label><button type="submit" class="btn-login" data-loading-text="Saving password…">Reset password</button></form><?php endif;?>
<p class="text-center mt-3 mb-0"><a href="/admin/login.php">Back to login</a></p>
<?php require __DIR__.'/../components/auth-page-end.php';?>
