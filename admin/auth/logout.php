<?php
/**
 * GNC Admin Logout - /admin/auth/logout.php
 */

require_once(__DIR__ . '/auth_handler.php');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent caching - prevents back button access
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Log the logout activity BEFORE destroying session
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    logActivity($user_id, 'LOGOUT', null, null, 'User logged out');
}

// Clear all session variables explicitly
$_SESSION = array();

// Delete remember-me cookie if it exists
if (isset($_COOKIE['gnc_remember'])) {
    setcookie('gnc_remember', '', array(
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => false,  // Set to true if using HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ));
    unset($_COOKIE['gnc_remember']);
}

// Delete session cookie explicitly
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', array(
        'expires' => time() - 3600,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Strict'
    ));
}

// Destroy session data
session_destroy();

// Redirect to login page (UP one level from /admin/auth/ to /admin/)
header('Location: /admin/login.php');
exit;
?>