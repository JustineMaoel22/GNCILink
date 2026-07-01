<?php
// admin/auth/verify-otp.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';
require_once __DIR__ . '/auth_handler.php';

initSession();

// Redirect if already fully logged in
if (isLoggedIn()) {
    header('Location: /admin/admin-dash.php');
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

// Resend OTP via GET param
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    $db  = getDB();
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $exp = date('Y-m-d H:i:s', time() + OTP_EXPIRY);

    $stmt = $db->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE user_id = ?");
    $stmt->execute([$otp, $exp, $_SESSION['temp_user_id']]);

    sendOTPEmailPHPMailer($email, $name, $otp);

    $_SESSION['flash_success'] = 'A new OTP has been sent to your email.';
    header('Location: /admin/auth/verify-otp.php');
    exit;
}

// Handle POST — OTP submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $otp = implode('', array_map(fn($i) => trim($_POST["otp_$i"] ?? ''), range(1, 6)));

        if (strlen($otp) !== 6 || !ctype_digit($otp)) {
            $error = 'Please enter a valid 6-digit code.';
        } else {
            $db   = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ? AND otp_code = ? AND otp_expires_at > NOW()");
            $stmt->execute([$_SESSION['temp_user_id'], $otp]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Invalid or expired OTP. Please try again or request a new code.';
            } else {
                // OTP verified — clear it and create full session
                $db->prepare("UPDATE users SET otp_verified = 1, otp_code = NULL, otp_expires_at = NULL, last_login = NOW() WHERE user_id = ?")
                   ->execute([$user['user_id']]);

                $_SESSION['user_id']       = $user['user_id'];
                $_SESSION['user_email']    = $user['email'];
                $_SESSION['user_role']     = $user['role'];
                $_SESSION['user_name']     = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['otp_verified']  = true;
                $_SESSION['last_activity'] = time();
                // Keep currentUser structure that admin-dash.php expects
                $_SESSION['user'] = [
                    'user_id'    => $user['user_id'],
                    'first_name' => $user['first_name'],
                    'last_name'  => $user['last_name'],
                    'email'      => $user['email'],
                    'role'       => $user['role'],
                ];

                unset($_SESSION['temp_user_id'], $_SESSION['temp_email'], $_SESSION['temp_name']);

                logActivity($user['user_id'], 'LOGIN', null, null, 'Successful login via OTP');

                // Remember me cookie
                if (!empty($_SESSION['remember_me'])) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('gnc_remember_me', $token, [
                        'expires'  => time() + (30 * 24 * 3600),
                        'path'     => '/',
                        'httponly' => true,
                        'secure'   => false, // set true in production (HTTPS)
                        'samesite' => 'Strict'
                    ]);
                    $db->prepare("UPDATE users SET remember_token = ? WHERE user_id = ?")
                       ->execute([hash('sha256', $token), $user['user_id']]);
                    unset($_SESSION['remember_me']);
                }

                header('Location: /admin/admin-dash.php');
                exit;
            }
        }
    }
}

$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
                        Code expires in <span id="countdown">5:00</span>
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

        // OTP expiry countdown (5 min)
        let seconds = 300;
        const countdown = document.getElementById('countdown');
        const countTimer = setInterval(() => {
            seconds--;
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
            window.location.href = '/admin/auth/verify-otp.php?resend=1';
        });

        document.getElementById('otpForm').addEventListener('submit', function () {
            const btn = document.getElementById('verifyBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Verifying…';
        });
    </script>
</body>
</html>