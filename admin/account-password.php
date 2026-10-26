<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/admin-functions.php';
initSession(); requireLogin();
header('Cache-Control: no-store, max-age=0');
$passwordError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!authCsrfValid()) throw new InvalidArgumentException('Your session token expired. Reload and try again.');
        foreach (['current_password','new_password','confirm_password'] as $field) {
            if (!is_string($_POST[$field] ?? null) || strlen($_POST[$field]) > 4096) throw new InvalidArgumentException('Complete all password fields.');
        }
        changeUserPassword((int)$_SESSION['user_id'],$_POST['current_password'],$_POST['new_password'],$_POST['confirm_password']);
        $_SESSION['account_password_changed'] = true;
        header('Location: /admin/account-password.php',true,303); exit;
    } catch (InvalidArgumentException | RuntimeException $e) {
        $passwordError = $e instanceof PDOException ? 'The password could not be changed. Please try again later.' : $e->getMessage();
    } catch (Throwable $e) {
        error_log('Account password change failed.');
        $passwordError = 'The password could not be changed. Please try again later.';
    }
}
$passwordChanged = !empty($_SESSION['account_password_changed']);unset($_SESSION['account_password_changed']);
$pageTitle = 'Change Password';require __DIR__ . '/../components/header-admin.php';
?>
<div class="page-header"><div><h1>Change Password</h1><p>Choose a unique password for your account.</p></div><a href="/admin/settings.php" class="btn btn-outline-secondary">Back to Settings</a></div>
<?php if ($passwordChanged): ?><div class="alert alert-success" role="status">Your password has been changed. Other sessions and remembered devices have been revoked. Use your new password for future logins.</div><?php endif; ?>
<?php if ($passwordError): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($passwordError,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<section class="data-card p-4" style="max-width:760px"><h2 class="data-card-title">Account password</h2>
<form method="post" action="/admin/account-password.php" data-auth-form>
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken,ENT_QUOTES,'UTF-8') ?>">
<?php foreach (['current_password'=>'Current Password','new_password'=>'New Password','confirm_password'=>'Confirm New Password'] as $name=>$label): ?>
<div class="mb-3"><label class="form-label" for="<?= $name ?>"><?= $label ?></label><input class="form-control" id="<?= $name ?>" type="password" name="<?= $name ?>" autocomplete="<?= $name==='current_password'?'current-password':'new-password' ?>"<?= $name!=='current_password'?' minlength="12" maxlength="72"':'' ?> required></div>
<?php endforeach; ?>
<?php require __DIR__ . '/../components/password-guidance.php'; ?>
<label class="form-check mb-3"><input type="checkbox" class="form-check-input" id="show-passwords"> Show passwords</label>
<button type="submit" class="btn btn-gnc-gold" data-loading-text="Saving password…">Save new password</button>
</form></section>
<script src="/assets/js/auth-password.js"></script>
<?php require __DIR__ . '/../components/footer-admin.php'; ?>
