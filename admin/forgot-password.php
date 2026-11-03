<?php
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/admin-functions.php';initSession();
$error='';$message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $submittedEmail=is_string($_POST['email']??null)?trim($_POST['email']):'';
    if(!authCsrfValid())$error='Your session token expired. Reload and try again.';
    elseif($submittedEmail===''||!filter_var($submittedEmail,FILTER_VALIDATE_EMAIL)||strlen($submittedEmail)>254)$error='Enter a valid email address.';
    else{
        try{
            if(!authSecurityReady())throw new RuntimeException('Authentication storage unavailable.');
            authRequestReset($submittedEmail);
        }catch(Throwable $e){
            error_log('Password recovery request error: '.$e->getMessage());
            authAudit(null,'RESET_REQUEST_ERROR','Password recovery request could not be processed: '.$e->getMessage());
        }
        $message='If an account is associated with this email address, you will receive a password reset link shortly.';
    }
}
$authTitle='Forgot Password';$authIntro='Enter your registered email address to request a password reset link.';
require __DIR__.'/../components/auth-page-start.php';
?>
<?php if($message):?><div class="alert alert-info" role="status"><i class="bi bi-envelope-check me-1" aria-hidden="true"></i> <?= $e($message) ?></div><?php endif;?>
<?php if($error):?><div class="alert alert-danger" role="alert"><?= $e($error) ?></div><?php endif;?>
<form method="post" data-auth-form><input type="hidden" name="csrf_token" value="<?= $e(generateCSRFToken()) ?>"><div class="form-group"><label for="email" class="form-label">Email Address</label><input type="email" id="email" name="email" class="form-control" maxlength="254" autocomplete="email" placeholder="e.g. admin@gmail.com" required></div><button class="btn-login" type="submit" data-loading-text="Sending request…">Send Reset Link</button></form>
<p class="text-center mt-3 mb-0"><a href="/admin/login.php">Back to login</a></p>
<?php require __DIR__.'/../components/auth-page-end.php';?>
