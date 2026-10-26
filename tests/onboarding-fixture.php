<?php
if (PHP_SAPI !== 'cli' || !preg_match('/^gnci_rbac_test_[a-f0-9]{12}$/D', $argv[1] ?? '')) exit(1);
error_reporting(E_ERROR | E_PARSE);
define('DB_NAME', $argv[1]);
require __DIR__ . '/../config/config.php';
$db = getDB();
$action = $argv[2] ?? '';
if ($action === 'install') {
    $db->exec(file_get_contents(__DIR__ . '/../sql/admin-onboarding-migration.sql'));
    // Re-running the additive migration preserves recorded completion.
    $db->exec(file_get_contents(__DIR__ . '/../sql/admin-onboarding-migration.sql'));
} elseif ($action === 'inspect') {
    echo json_encode($db->query('SELECT user_id,status FROM admin_onboarding ORDER BY user_id')->fetchAll());
} elseif ($action === 'reset') {
    $db->exec('DELETE FROM admin_onboarding');
} elseif ($action === 'force-password') {
    $db->exec('UPDATE users SET must_change_password=1 WHERE user_id=3');
} else exit(1);
