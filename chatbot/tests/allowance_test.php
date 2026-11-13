<?php
/** Isolated file-throttle checks; no database or AI requests. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../inc/ratelimit.php';

function env(string $key, mixed $default = null): mixed { return $key === 'GNCBOT_IP_LIMIT' ? 2 : $default; }
function check(bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException($label); }
}

// A random valid IPv6 address isolates this test from visitor counters.
$_SERVER['REMOTE_ADDR'] = '2001:db8:' . implode(':', str_split(bin2hex(random_bytes(12)), 4));
$dir = rtrim(sys_get_temp_dir(), '/\\') . '/gncbot_rl';
$file = $dir . '/ip_' . hash('sha256', gncbot_client_ip());
try {
    check(gncbot_allowance() === ['limit' => 2, 'remaining' => 2, 'retry_after' => 0], 'Fresh allowance');
    check(!is_file($file), 'Status must not create or consume a counter');
    if (!is_dir($dir)) { mkdir($dir, 0700, true); }
    file_put_contents($file, implode(',', [time() - 601, time() - 60]));
    $before = file_get_contents($file);
    $status = gncbot_allowance();
    check($status['remaining'] === 1, 'Expired requests must not count');
    check($status['retry_after'] >= 539 && $status['retry_after'] <= 540, 'Rolling expiry');
    check(file_get_contents($file) === $before, 'Status must not mutate counters');
    file_put_contents($file, time() . ',' . time());
    check(gncbot_allowance()['remaining'] === 0, 'Exhausted allowance');
    check(!gncbot_throttle_ok(), 'Exhausted IP must still be rejected');
    file_put_contents($file, (string)(time() - 601));
    check(gncbot_allowance()['remaining'] === 2, 'Allowance recovers after expiry');
    echo "PASS: fresh, read-only, rolling expiry, exhausted, enforced and recovered allowance.\n";
} finally {
    if (is_file($file)) { unlink($file); }
}
