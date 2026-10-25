<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';
require_once __DIR__ . '/../../includes/facebook-oauth.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
initSession();
requireLogin();
requirePermission('manage_settings');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw new FacebookPublishingError('Start Facebook sign-in from Facebook Integration.');
    if (!facebookOAuthReady()) throw new FacebookPublishingError('Facebook connection needs a database update by the site developer.');
    facebookOAuthConsumeState(is_string($_GET['state'] ?? null) ? $_GET['state'] : '');
    if (isset($_GET['error'])) throw new FacebookPublishingError('Facebook sign-in was cancelled or permission was declined. Your existing connection has not changed.');
    facebookOAuthComplete(is_string($_GET['code'] ?? null) ? $_GET['code'] : '');
    $_SESSION['facebook_integration_notice'] = 'Facebook sign-in completed. Choose your Page and confirm below to connect it.';
} catch (FacebookPublishingError $e) {
    unset($_SESSION['facebook_oauth_pending']);
    $_SESSION['facebook_integration_error'] = $e->getMessage();
} catch (Throwable $e) {
    unset($_SESSION['facebook_oauth_pending']);
    $_SESSION['facebook_integration_error'] = 'Facebook sign-in could not finish. Please select Connect Facebook to try again.';
}
header('Location: /admin/facebook-integration.php');
exit;
