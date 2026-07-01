<?php
/**
 * GNC Admin Panel - Authentication Handler
 * Handles login, OTP verification, and Remember Me feature
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';

// Import PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Start session
initSession();

// Only act as an API endpoint when requested directly via POST.
// When this file is included by verify-otp.php, do nothing.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && basename($_SERVER['PHP_SELF']) === 'auth_handler.php') {

    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'login':
            handleLogin();
            break;

        case 'verify_otp':
            handleOTPVerification();
            break;

        case 'resend_otp':
            handleResendOTP();
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            exit;
    }
}

// ============================================================
// LOGIN HANDLER
// ============================================================

function handleLogin() {
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';

    // Validation
    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        exit;
    }

    try {
        $db = getDB();

        // Get user
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            exit;
        }

        // Check if account is active
        if ($user['status'] !== 'active') {
            http_response_code(403);
            echo json_encode(['error' => 'Your account has been deactivated']);
            exit;
        }

        // Check if account is locked
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $timeLeft = ceil((strtotime($user['locked_until']) - time()) / 60);
            http_response_code(429);
            echo json_encode(['error' => "Account locked. Try again in $timeLeft minutes"]);
            exit;
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            $attempts  = $user['login_attempts'] + 1;
            $lockUntil = null;

            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
            }

            $stmt = $db->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE user_id = ?");
            $stmt->execute([$attempts, $lockUntil, $user['user_id']]);

            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            exit;
        }

        // Check for Remember Me token (skip OTP if valid token exists)
        if ($rememberMe) {
            $rememberToken = $_COOKIE['gnc_remember_me'] ?? null;
            if ($rememberToken && verifyRememberToken($user['user_id'], $rememberToken)) {
                loginUser($user);
                echo json_encode([
                    'success'  => true,
                    'message'  => 'Logged in successfully',
                    'redirect' => '/admin/admin-dash.php'
                ]);
                exit;
            }
        }

        // Generate OTP
        $otp          = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpExpiresAt = date('Y-m-d H:i:s', time() + OTP_EXPIRY);

        // Store OTP in database
        $stmt = $db->prepare("
            UPDATE users
            SET otp_code = ?, otp_expires_at = ?, login_attempts = 0, locked_until = NULL
            WHERE user_id = ?
        ");
        $stmt->execute([$otp, $otpExpiresAt, $user['user_id']]);

        // Send OTP email using PHPMailer
        if (sendOTPEmailPHPMailer($user['email'], $user['first_name'], $otp)) {

            // Store temp session data — keys are consistent with verify-otp.php
            $_SESSION['temp_user_id'] = $user['user_id'];
            $_SESSION['temp_email']   = $user['email'];
            $_SESSION['temp_name']    = $user['first_name'];
            $_SESSION['remember_me']  = $rememberMe;
            $_SESSION['otp_attempts'] = 0;

            logActivity($user['user_id'], 'OTP_SENT', null, null, 'OTP sent to ' . $user['email']);

            echo json_encode([
                'success'  => true,
                'message'  => 'OTP sent to your email',
                'redirect' => '/admin/auth/verify-otp.php'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to send OTP email. Please try again later.']);
        }
    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Server error occurred']);
    }
}

// ============================================================
// OTP VERIFICATION HANDLER
// ============================================================

function handleOTPVerification() {
    if (!isset($_SESSION['temp_user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Session expired. Please login again']);
        exit;
    }

    $otp = trim($_POST['otp'] ?? '');

    if (empty($otp) || !preg_match('/^\d{6}$/', $otp)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid OTP format']);
        exit;
    }

    try {
        $db     = getDB();
        $userId = $_SESSION['temp_user_id'];

        // Get user
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        // Check OTP attempts
        if (($_SESSION['otp_attempts'] ?? 0) >= 5) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many OTP attempts. Please login again']);
            exit;
        }

        // Verify OTP
        if ($user['otp_code'] !== $otp) {
            $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;
            http_response_code(401);
            echo json_encode(['error' => 'Invalid OTP']);
            exit;
        }

        // Check OTP expiry
        if (strtotime($user['otp_expires_at']) < time()) {
            http_response_code(401);
            echo json_encode(['error' => 'OTP has expired']);
            exit;
        }

        // Clear OTP
        $stmt = $db->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL, otp_verified = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);

        // Handle Remember Me
        if (!empty($_SESSION['remember_me'])) {
            generateRememberToken($userId);
        }

        // Clear temp session
        unset($_SESSION['temp_user_id'], $_SESSION['temp_email'], $_SESSION['temp_name'], $_SESSION['remember_me'], $_SESSION['otp_attempts']);

        // Login user
        loginUser($user);

        logActivity($userId, 'LOGIN', null, null, 'Successful login via OTP');

        echo json_encode([
            'success'  => true,
            'message'  => 'Login successful',
            'redirect' => '/admin/admin-dash.php'
        ]);
    } catch (Exception $e) {
        error_log('OTP verification error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Server error occurred']);
    }
}

// ============================================================
// RESEND OTP HANDLER
// ============================================================

function handleResendOTP() {
    if (!isset($_SESSION['temp_user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Session expired']);
        exit;
    }

    try {
        $db     = getDB();
        $userId = $_SESSION['temp_user_id'];

        // Get user
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        // Generate new OTP
        $otp          = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpExpiresAt = date('Y-m-d H:i:s', time() + OTP_EXPIRY);

        $stmt = $db->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE user_id = ?");
        $stmt->execute([$otp, $otpExpiresAt, $userId]);

        // Send OTP using PHPMailer
        if (sendOTPEmailPHPMailer($user['email'], $user['first_name'], $otp)) {
            $_SESSION['otp_attempts'] = 0;
            logActivity($userId, 'OTP_SENT', null, null, 'OTP resent to ' . $user['email']);

            echo json_encode([
                'success' => true,
                'message' => 'OTP resent to your email'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to send OTP']);
        }
    } catch (Exception $e) {
        error_log('Resend OTP error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Server error occurred']);
    }
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Login user and set session
 */
function loginUser(array $user) {
    $_SESSION['user_id']    = $user['user_id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];

    try {
        $db   = getDB();
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
    } catch (Exception $e) {
        error_log('Update last login error: ' . $e->getMessage());
    }
}

/**
 * Send OTP via email using PHPMailer
 */
function sendOTPEmailPHPMailer(string $email, string $firstName, string $otp): bool {
    try {
        if (!file_exists(__DIR__ . '/../../vendor/autoload.php')) {
            error_log('PHPMailer not installed. Please run: composer require phpmailer/phpmailer');
            return false;
        }

        require_once __DIR__ . '/../../vendor/autoload.php';

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->Port       = MAIL_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;

        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
        $mail->addAddress($email, $firstName);

        $mail->isHTML(true);
        $mail->Subject = 'GNC Admin Panel - One-Time Password (OTP)';

        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #094024; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background-color: #f9f6f1; }
                    .otp-box {
                        background-color: white;
                        border: 2px solid #EABA3B;
                        padding: 20px;
                        text-align: center;
                        margin: 20px 0;
                        border-radius: 8px;
                    }
                    .otp-code {
                        font-size: 32px;
                        font-weight: bold;
                        color: #094024;
                        letter-spacing: 5px;
                    }
                    .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'><h1>GNC Admin Panel</h1></div>
                    <div class='content'>
                        <p>Hello <strong>" . htmlspecialchars($firstName) . "</strong>,</p>
                        <p>You requested to log in to your GNC Admin Panel account. Please use the following One-Time Password (OTP) to complete your login:</p>
                        <div class='otp-box'>
                            <div class='otp-code'>" . htmlspecialchars($otp) . "</div>
                        </div>
                        <p><strong>Important:</strong></p>
                        <ul>
                            <li>This OTP will expire in 5 minutes</li>
                            <li>Do not share this code with anyone</li>
                            <li>Never give this code to GNC staff</li>
                        </ul>
                        <p>If you did not request this login, please ignore this email.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " Guagua National Colleges, Inc. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->AltBody = "Your GNC Admin Panel OTP is: $otp\n\nThis code expires in 5 minutes.\n\nDo not share this code with anyone.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('PHPMailer Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Generate Remember Me token
 */
function generateRememberToken(int $userId): bool {
    try {
        $token       = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);

        $db   = getDB();
        $stmt = $db->prepare("UPDATE users SET remember_token = ? WHERE user_id = ?");
        $stmt->execute([$hashedToken, $userId]);

        setcookie('gnc_remember_me', $token, [
            'expires'  => strtotime('+30 days'),
            'path'     => '/',
            'httponly' => true,
            'secure'   => true,
            'samesite' => 'Strict'
        ]);

        return true;
    } catch (Exception $e) {
        error_log('Generate remember token error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Verify Remember Me token
 */
function verifyRememberToken(int $userId, string $token): bool {
    try {
        $hashedToken = hash('sha256', $token);

        $db   = getDB();
        $stmt = $db->prepare("SELECT remember_token FROM users WHERE user_id = ? AND remember_token IS NOT NULL");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        return $user && hash_equals($user['remember_token'], $hashedToken);
    } catch (Exception $e) {
        error_log('Verify remember token error: ' . $e->getMessage());
        return false;
    }
}