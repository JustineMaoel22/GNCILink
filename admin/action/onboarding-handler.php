<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';
initSession(); requireLogin();
require_once __DIR__ . '/../../includes/admin-onboarding.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); rbacFail('Use POST for tutorial updates.', 405); }
if (!authCsrfValid()) rbacFail('Your session token expired. Reload and try again.', 403);
if (!empty($_SESSION['must_change_password'])) rbacFail('Change your temporary password first.', 403);
if (!in_array(rbacRole(), ['Super Admin', 'Content Editor', 'The Mirror'], true)) rbacFail('Access denied.', 403);
$status = $_POST['status'] ?? null;
if (!is_string($status) || !in_array($status, ['completed', 'skipped'], true)) rbacFail('Choose a valid tutorial action.', 422);
try {
    adminOnboardingSave((int)$_SESSION['user_id'], $status);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('Admin onboarding status could not be saved.');
    rbacFail('Your tutorial progress could not be saved. Please try again later.', 503);
}
