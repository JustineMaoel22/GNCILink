<?php
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
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// ------------------------------------------------------------
// Application Settings
// ------------------------------------------------------------
define('APP_NAME', $_ENV['APP_NAME'] ?? 'GNC Admin System');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost:8080/admin');
define('SESSION_TIMEOUT', (int) $_ENV['SESSION_TIMEOUT'] ?? 1800); // 30 minutes
define('OTP_EXPIRY', (int) $_ENV['OTP_EXPIRY'] ?? 300);            // 5 minutes
define('MAX_LOGIN_ATTEMPTS', (int) $_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5);
define('LOCKOUT_DURATION', (int) $_ENV['LOCKOUT_DURATION'] ?? 900); // 15 minutes

// ------------------------------------------------------------
// Email Configuration (PHPMailer)
// ------------------------------------------------------------
define('MAIL_HOST', $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com');
define('MAIL_PORT', (int) $_ENV['MAIL_PORT'] ?? 587);
define('MAIL_USERNAME', $_ENV['MAIL_USERNAME'] ?? '');
define('MAIL_PASSWORD', $_ENV['MAIL_PASSWORD'] ?? '');
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? 'GNC Admin System');
define('MAIL_ENCRYPTION', $_ENV['MAIL_ENCRYPTION'] ?? 'tls');

// ------------------------------------------------------------
// Facebook Page Feed
// ------------------------------------------------------------
define('FB_PAGE_ID', $_ENV['FB_PAGE_ID']);
define('FB_ACCESS_TOKEN', $_ENV['FB_ACCESS_TOKEN']);

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