<?php
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/admin-functions.php';initSession();
$error='';$message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!authCsrfValid())$error='Your session token expired. Reload and try again.';
    elseif(!is_string($_POST['email']??null)||!filter_var($_POST['email'],FILTER_VALIDATE_EMAIL)||strlen($_POST['email'])>254)$error='Enter a valid email address.';
    else{
        try{if(!authSecurityReady())throw new RuntimeException('Storage unavailable');authRequestReset(trim($_POST['email']));}
        catch(Throwable $e){error_log('Password recovery unavailable. Check authentication migration and server configuration.');authAudit(null,'RESET_REQUEST_ERROR','Password recovery request could not be processed.');}
        $message='If an account exists with this email, a password reset link has been sent.';
    }
}
$authTitle='Forgot Password';$authIntro='Enter your registered email address to request a password reset link.';
require __DIR__.'/../components/auth-page-start.php';
?>
<?php if($message):?><div class="alert alert-success" role="status"><?= $e($message) ?></div><?php endif;?>
<?php if($error):?><div class="alert alert-danger" role="alert"><?= $e($error) ?></div><?php endif;?>
<form method="post" data-auth-form><input type="hidden" name="csrf_token" value="<?= $e(generateCSRFToken()) ?>"><div class="form-group"><label for="email" class="form-label">Email Address</label><input type="email" id="email" name="email" class="form-control" maxlength="254" autocomplete="email" required></div><button class="btn-login" type="submit" data-loading-text="Sending request…">Send reset link</button></form>
<p class="text-center mt-3 mb-0"><a href="/admin/login.php">Back to login</a></p>
<?php require __DIR__.'/../components/auth-page-end.php';?>
