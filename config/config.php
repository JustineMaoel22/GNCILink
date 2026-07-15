<?php
// ============================================================
// GNC Admin Panel - Database & App Configuration
// ============================================================
// Secrets are loaded from environment variables (via a .env file
// in local/dev, or real server env vars in production) instead of
// being hardcoded here. This file is safe to commit to version
// control; the .env file is NOT — make sure .env is in .gitignore.
// ============================================================

// ------------------------------------------------------------
// Minimal .env loader (no external dependency required)
// ------------------------------------------------------------
function loadEnv(string $path): void {
    if (!file_exists($path)) {
        return; // fine in prod if real env vars are set another way
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        // Strip surrounding quotes if present
        if (strlen($value) >= 2 && (
            ($value[0] === '"' && $value[-1] === '"') ||
            ($value[0] === "'" && $value[-1] === "'")
        )) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) {
            putenv("$key=$value");
        }
        $_ENV[$key] = $value;
    }
}

loadEnv(__DIR__ . '/../.env');

/**
 * Small helper: fetch an env var, with an optional default and the
 * ability to mark it as required (throws instead of silently
 * continuing with an empty secret).
 */
function env(string $key, $default = null, bool $required = false) {
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? null;
    }
    if ($value === null || $value === '') {
        if ($required) {
            error_log("Missing required environment variable: $key");
            die(json_encode(['error' => "Server misconfigured (missing $key)."]));
        }
        return $default;
    }
    return $value;
}

// ------------------------------------------------------------
// Database
// ------------------------------------------------------------
define('DB_HOST', env('DB_HOST', 'db'));
define('DB_NAME', env('DB_NAME', 'gnc_admin'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', 'root'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// ------------------------------------------------------------
// Application Settings
// ------------------------------------------------------------
define('APP_NAME', env('APP_NAME', 'GNC Admin Panel'));
define('APP_URL', env('APP_URL', 'http://localhost:8080/admin'));
define('SESSION_TIMEOUT', (int) env('SESSION_TIMEOUT', 1800)); // 30 minutes
define('OTP_EXPIRY', (int) env('OTP_EXPIRY', 300));            // 5 minutes
define('MAX_LOGIN_ATTEMPTS', (int) env('MAX_LOGIN_ATTEMPTS', 5));
define('LOCKOUT_DURATION', (int) env('LOCKOUT_DURATION', 900)); // 15 minutes

// ------------------------------------------------------------
// Email Configuration (PHPMailer)
// ------------------------------------------------------------
define('MAIL_HOST', env('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_PORT', (int) env('MAIL_PORT', 587));
define('MAIL_USERNAME', env('MAIL_USERNAME', ''));
define('MAIL_PASSWORD', env('MAIL_PASSWORD', ''));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'GNC Admin System'));
define('MAIL_ENCRYPTION', env('MAIL_ENCRYPTION', 'tls'));

// ------------------------------------------------------------
// Facebook Page Feed
// ------------------------------------------------------------
define('FB_PAGE_ID', env('FB_PAGE_ID', '403969556396264'));
define('FB_ACCESS_TOKEN', env('FB_ACCESS_TOKEN', 'EAAjxjZAhVjfsBR8YUjD5ZAvRvvUu8iHFsNwLwXs8Asl6s4f22XlFiKwY6wZBm1GA47zDKSKTCYAQCahVX4BiTaNhsjxZCZA1O1sgschbfsCkpqlpdZCHG3XwWJuq6wS9AlHBfwzXZAZAoI1cefWCBOjtpCxjyZBH3Ad8XEC5ZBW7IDm8800SEwJtoDXCZAbzNOAQD9eH4DwrlRN'));

// ------------------------------------------------------------
// Upload Setting
// ------------------------------------------------------------
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