<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/admin-functions.php';
initSession();
if (isLoggedIn()) {
    header('Location: ' . rbacHome());
    exit;
}
$authTitle = 'Welcome Back';
$authIntro = 'Sign in to your GNCILink Admin Panel.';
$authScript = '/assets/js/admin-login.js';
require __DIR__ . '/../components/auth-page-start.php';
?>
<?php if (isset($_GET['timeout'])): ?>
<div class="alert alert-warning" role="alert">Your session has expired. Please sign in again.</div>
<?php endif; ?>
<?php if (isset($_GET['password_reset'])): ?>
<div class="alert alert-success" role="status">Your password was reset successfully. Sign in with your new password.</div>
<?php endif; ?>
<div id="alert-container" role="alert" aria-live="assertive"></div>
<form id="login-form" method="post" action="/admin/auth/auth_handler.php" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $e(generateCSRFToken()) ?>">
    <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input class="form-control" type="email" id="email" name="email" placeholder="Enter your email address" autocomplete="username" inputmode="email" maxlength="254" aria-describedby="email-error" required>
        <p class="field-error" id="email-error" aria-live="polite"></p>
    </div>
    <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="login-password-field">
            <input class="form-control" type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" aria-describedby="password-error" required>
            <button class="login-password-toggle" type="button" id="show-login-password" aria-label="Show password" aria-controls="password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
        </div>
        <p class="field-error" id="password-error" aria-live="polite"></p>
    </div>
    <div class="remember-device">
        <div class="form-check"><input class="form-check-input" type="checkbox" id="remember-me" name="remember_me"><label class="form-check-label" for="remember-me">Remember this device for 30 days</label></div>
        <a href="/admin/forgot-password.php">Forgot Password?</a>
    </div>
    <button class="btn-login" type="submit" id="sign-in-button"><span class="loading-spinner" id="loading-spinner" aria-hidden="true"></span><span id="btn-text">Sign In</span></button>
</form>
<?php require __DIR__ . '/../components/auth-page-end.php'; ?>
