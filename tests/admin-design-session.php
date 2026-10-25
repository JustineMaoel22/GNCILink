<?php
/** Temporary CLI-only session for read-only browser checks of authenticated admin pages. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
$action = $argv[1] ?? '';
if ($action === 'create') {
    $user = getDB()->query("SELECT user_id, role FROM users WHERE status = 'active' AND role = 'Super Admin' LIMIT 1")->fetch();
    if (!$user) throw new RuntimeException('An active admin is needed for the read-only layout check.');
    session_id('gnc-design-test-' . bin2hex(random_bytes(16)));
    session_start();
    $_SESSION = ['user_id' => (int)$user['user_id'], 'user_role' => $user['role'], '_initiated' => true, 'last_activity' => time()];
    $result = ['name' => session_name(), 'value' => session_id()];
    session_write_close();
    echo json_encode($result);
} elseif ($action === 'destroy' && preg_match('/^gnc-design-test-[a-f0-9]{32}$/D', $argv[2] ?? '')) {
    session_id($argv[2]); session_start(); $_SESSION = []; session_destroy();
} else {
    throw new InvalidArgumentException('Choose create or destroy with a test session ID.');
}
