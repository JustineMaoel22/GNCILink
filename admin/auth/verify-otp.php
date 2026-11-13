<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';
require_once __DIR__ . '/auth_handler.php';
initSession();
if (isLoggedIn()) { header('Location: ' . rbacHome()); exit; }
if (!isset($_SESSION['temp_user_id'])) { header('Location: /admin/login.php'); exit; }

$error = '';
$success = '';
$email = is_string($_SESSION['temp_email'] ?? null) ? $_SESSION['temp_email'] : '';
if (isset($_SESSION['flash_success'])) {
    $success = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!authCsrfValid()) $error = 'Your session token expired. Reload and try again.';
    elseif (!authSecurityReady()) $error = 'Authentication is temporarily unavailable. Contact your administrator.';
    else {
        try {
            if (($_POST['action'] ?? '') === 'resend_otp') {
                if (resendLoginOtp()) $success = 'A new verification code has been sent to your email.';
                else $error = 'Unable to resend. Wait before trying again or sign in again.';
            } else {
                $otp = '';
                foreach (range(1, 6) as $i) $otp .= is_string($_POST["otp_$i"] ?? null) ? trim($_POST["otp_$i"]) : '';
                $user = authConsumeOtp($otp);
                if (!$user) $error = 'Invalid or expired code. After five attempts, wait 15 minutes and sign in again.';
                else {
                    $remember = !empty($_SESSION['remember_me']);
                    loginUser($user);
                    if ($remember && empty($user['must_change_password'])) generateRememberToken((int) $user['user_id']);
                    header('Location: ' . rbacHome());
                    exit;
                }
            }
        } catch (Throwable $e) {
            $error = 'Authentication is temporarily unavailable. Please try again later.';
            error_log('OTP form request failed.');
        }
    }
}
$timerStmt = getDB()->prepare('SELECT GREATEST(0, COALESCE(TIMESTAMPDIFF(SECOND, NOW(), otp_expires_at), 0)) FROM users WHERE user_id = ?');
$timerStmt->execute([$_SESSION['temp_user_id']]);
$otpSecondsRemaining = (int) $timerStmt->fetchColumn();
$maskedEmail = substr($email, 0, 3) . '***@' . (explode('@', $email)[1] ?? '');
$authTitle = 'Verify Your Identity';
$authIntro = 'Enter the six-digit code sent to your email to finish signing in.';
$authScript = '/assets/js/auth-otp.js';
require __DIR__ . '/../../components/auth-page-start.php';
?>
<?php if ($success): ?><div class="alert alert-success" role="status"><?= $e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= $e($error) ?></div><?php endif; ?>
<p class="otp-meta">Code sent to <strong><?= $e($maskedEmail) ?></strong>. Check your spam folder if it has not arrived.</p>
<form id="otpForm" method="post" action="<?= $e(gncBrowserUrl('/admin/auth/verify-otp.php')) ?>" data-expires-in="<?= $otpSecondsRemaining ?>" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $e(generateCSRFToken()) ?>">
    <label class="form-label" for="otp1">Verification code</label>
    <div class="otp-inputs" role="group" aria-label="Six-digit verification code">
        <?php for ($i = 1; $i <= 6; $i++): ?>
        <input class="otp-digit" type="text" name="otp_<?= $i ?>" id="otp<?= $i ?>" aria-label="Digit <?= $i ?> of 6" maxlength="<?= $i === 1 ? 6 : 1 ?>" pattern="[0-9]" inputmode="numeric" autocomplete="<?= $i === 1 ? 'one-time-code' : 'off' ?>" required>
        <?php endfor; ?>
    </div>
    <p class="field-error" id="otp-error" role="alert" aria-live="assertive"></p>
    <p class="otp-timer">Code <strong id="countdown"><?= $otpSecondsRemaining > 0 ? intdiv($otpSecondsRemaining, 60) . ':' . str_pad((string) ($otpSecondsRemaining % 60), 2, '0', STR_PAD_LEFT) . ' remaining' : 'expired' ?></strong></p>
    <button type="submit" class="btn-login" id="verifyBtn">Verify &amp; Sign In</button>
    <div class="resend-section">Didn't receive the code? <button type="submit" class="resend-btn" id="resendBtn" name="action" value="resend_otp" formnovalidate disabled>Resend code (<span id="resendTimer">60</span>s)</button></div>
</form>
<p class="back-link"><a href="/admin/login.php">Back to sign in</a></p>
<?php require __DIR__ . '/../../components/auth-page-end.php'; ?>
