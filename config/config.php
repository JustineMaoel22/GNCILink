<?php
// ============================================================
// GNC Admin Panel - Database Configuration
// ============================================================

define('DB_HOST', 'db');
define('DB_NAME', 'gnc_admin');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'GNC Admin Panel');
define('APP_URL', 'http://localhost:8080/admin');
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('OTP_EXPIRY', 300);       // 5 minutes
define('MAX_LOGIN_ATTEMPTS', 5); // 5 Attempts
define('LOCKOUT_DURATION', 900); // 15 minutes

// Email Configuration (PHPMailer)
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'justinemaoel.gnc@gmail.com');
define('MAIL_PASSWORD', 'uyxp wtvz hvnb yxwl');
define('MAIL_FROM_NAME', 'GNC Admin System');
define('MAIL_ENCRYPTION', 'tls');

// Upload Settings
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

/**
 * Get database connection (PDO)
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            die(json_encode(['error' => 'Database connection failed.']));
        }
    }
    return $pdo;
}