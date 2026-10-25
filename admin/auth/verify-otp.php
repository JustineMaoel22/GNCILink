<?php
// admin/auth/verify-otp.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';
require_once __DIR__ . '/auth_handler.php';

initSession();

// Redirect if already fully logged in
if (isLoggedIn()) {
    header('Location: ' . rbacHome());
    exit;
}

// Must have gone through the login step first
if (!isset($_SESSION['temp_user_id'])) {
    header('Location: /admin/login.php');
    exit;
}

$error   = '';
$success = '';
$email   = $_SESSION['temp_email'] ?? '';
$name    = $_SESSION['temp_name']  ?? '';

if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// All OTP mutations require POST, CSRF, and the shared account attempt limit.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!authCsrfValid()) $error='Your session token expired. Reload and try again.';
    elseif (!authSecurityReady()) $error='Authentication is temporarily unavailable. Contact your administrator.';
    else {
        try {
            if (($_POST['action']??'')==='resend_otp') {
                if(resendLoginOtp()) $success='A new OTP has been sent to your email.';
                else $error='Unable to resend. Wait before trying again or sign in again.';
            } else {
                $otp='';foreach(range(1,6) as $i)$otp.=is_string($_POST["otp_$i"]??null)?trim($_POST["otp_$i"]):'';
                $user=authConsumeOtp($otp);
                if(!$user)$error='Invalid or expired OTP. After five attempts, wait 15 minutes and sign in again.';
                else {
                    $remember=!empty($_SESSION['remember_me']);loginUser($user);
                    if($remember&&empty($user['must_change_password']))generateRememberToken((int)$user['user_id']);
                    header('Location: '.rbacHome());exit;
                }
            }
        } catch(Throwable $e) { $error='Authentication is temporarily unavailable. Please try again later.';error_log('OTP form request failed.'); }
    }
}

// Read the same clock used to issue and verify the code; reloads do not restart it.
$timerStmt = getDB()->prepare("SELECT GREATEST(0, COALESCE(TIMESTAMPDIFF(SECOND, NOW(), otp_expires_at), 0)) FROM users WHERE user_id = ?");
$timerStmt->execute([$_SESSION['temp_user_id']]);
$otpSecondsRemaining = (int) $timerStmt->fetchColumn();
$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php require_once __DIR__ . '/../../components/loading-feedback.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP — GNC Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/login-style.css">
    <style>
        /* OTP Specific Styles */
        .otp-inputs {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin: 24px 0;
        }

        .otp-digit {
            width: 48px;
            height: 48px;
            font-size: 24px;
            font-weight: 600;
            text-align: center;
            border: 2px solid #e0e4e8;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-family: 'Courier New', monospace;
        }

        .otp-digit:focus {
            outline: none;
            border-color: #094024;
            box-shadow: 0 0 0 3px rgba(9, 64, 36, 0.1);
        }

        .otp-digit.filled {
            border-color: #094024;
            background-color: #f0f4f8;
        }

        .otp-info {
            text-align: center;
            font-size: 14px;
            color: #6b7a8d;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .otp-info strong {
            color: #094024;
        }

        .otp-timer {
            text-align: center;
            font-size: 13px;
            color: #6b7a8d;
            margin: 16px 0;
        }

        .otp-timer span {
            font-weight: 600;
            color: #094024;
        }

        .resend-section {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #6b7a8d;
        }

        .resend-btn {
            background: none;
            border: none;
            color: #EABA3B;
            text-decoration: none;
            cursor: pointer;
            font-weight: 600;
            padding: 0;
            font-size: 13px;
            transition: color 0.2s ease;
        }

        .resend-btn:hover:not(:disabled) {
            color: #d99e1d;
        }

        .resend-btn:disabled {
            color: #ccc;
            cursor: not-allowed;
        }

        .back-link {
            text-align: center;
            margin-top: 16px;
        }

        .back-link a {
            font-size: 12px;
            color: #6b7a8d;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .back-link a:hover {
            color: #094024;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <div class="login-logo">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h1>Verify Identity</h1>
                <p>Guagua National Colleges, Inc.</p>
            </div>

            <!-- Body -->
            <div class="login-body">
                <!-- Success Alert -->
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <!-- Error Alert -->
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- OTP Info -->
                <div class="otp-info">
                    A 6-digit code has been sent to<br>
                    <strong><?= htmlspecialchars(substr($email, 0, 3) . '***@' . (explode('@', $email)[1] ?? '')) ?></strong>
                </div>

                <!-- OTP Form -->
                <form id="otpForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                    <!-- OTP Input Fields -->
                    <div class="otp-inputs">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                        <input 
                            type="text" 
                            class="otp-digit" 
                            name="otp_<?= $i ?>" 
                            id="otp<?= $i ?>"
                            maxlength="1" 
                            pattern="[0-9]" 
                            inputmode="numeric" 
                            autocomplete="off"
                        >
                        <?php endfor; ?>
                    </div>

                    <!-- Timer -->
                    <div class="otp-timer">
                        Code expires in <span id="countdown"><?= $otpSecondsRemaining > 0 ? intdiv($otpSecondsRemaining, 60) . ':' . str_pad((string) ($otpSecondsRemaining % 60), 2, '0', STR_PAD_LEFT) : 'Expired' ?></span>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-login" id="verifyBtn">
                        <i class="bi bi-shield-check"></i> Verify &amp; Sign In
                    </button>

                    <!-- Resend Section -->
                    <div class="resend-section">
                        Didn't receive the code?
                        <button type="button" class="resend-btn" id="resendBtn" disabled>
                            Resend OTP (<span id="resendTimer">60</span>s)
                        </button>
                    </div>
                </form>

                <!-- Back Link -->
                <div class="back-link">
                    <a href="/admin/login.php">
                        <i class="bi bi-arrow-left"></i> Back to login
                    </a>
                </div>
            </div>

            <!-- Footer -->
            <div class="login-footer">
                &copy; 2026 Guagua National Colleges, Inc. All rights reserved.
            </div>
        </div>
    </div>

    <script>
        // OTP digit auto-focus & paste
        const digits = document.querySelectorAll('.otp-digit');
        digits.forEach((digit, i) => {
            digit.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '').slice(-1);
                if (e.target.value && i < digits.length - 1) digits[i + 1].focus();
                e.target.classList.toggle('filled', !!e.target.value);
            });
            digit.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !digit.value && i > 0) {
                    digits[i - 1].focus();
                    digits[i - 1].classList.remove('filled');
                }
            });
            digit.addEventListener('paste', (e) => {
                const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                paste.split('').forEach((char, j) => {
                    if (digits[j]) { digits[j].value = char; digits[j].classList.add('filled'); }
                });
                if (paste.length >= 6) digits[5].focus();
                e.preventDefault();
            });
        });
        digits[0]?.focus();

        // Count down from the server's remaining lifetime without resetting on reload.
        const expiresAt = performance.now() + <?= $otpSecondsRemaining ?> * 1000;
        const countdown = document.getElementById('countdown');
        const countTimer = setInterval(() => {
            const seconds = Math.max(0, Math.ceil((expiresAt - performance.now()) / 1000));
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            countdown.textContent = m + ':' + String(s).padStart(2, '0');
            if (seconds <= 0) {
                clearInterval(countTimer);
                countdown.textContent = 'Expired';
                countdown.style.color = '#ef4444';
            }
        }, 1000);

        // Resend cooldown (60 s)
        let resendSec = 60;
        const resendTimerEl = document.getElementById('resendTimer');
        const resendBtn     = document.getElementById('resendBtn');
        const resendInterval = setInterval(() => {
            resendSec--;
            resendTimerEl.textContent = resendSec;
            if (resendSec <= 0) {
                clearInterval(resendInterval);
                resendBtn.disabled = false;
                resendBtn.innerHTML = 'Resend OTP';
            }
        }, 1000);

        resendBtn.addEventListener('click', () => {
            const form=document.createElement('form');form.method='post';form.action='/admin/auth/verify-otp.php';for(const [name,value] of Object.entries({action:'resend_otp',csrf_token:document.querySelector('input[name=csrf_token]').value})){const input=document.createElement('input');input.type='hidden';input.name=name;input.value=value;form.append(input);}document.body.append(form);resendBtn.disabled=true;form.submit();
        });

        document.getElementById('otpForm').addEventListener('submit', function () {
            const btn = document.getElementById('verifyBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Verifying…';
        });
    </script>
</body>
</html>
