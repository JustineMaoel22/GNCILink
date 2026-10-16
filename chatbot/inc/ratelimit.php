<?php
/**
 * Tiny file-based throttle (no DB table, no PHP session - so it never touches
 * the admin session). Protects the Gemini quota/cost from abuse.
 *   per IP : GNCBOT_IP_LIMIT requests / 10 minutes   (default 20)
 *   global : GNCBOT_DAILY_LIMIT requests / day       (default 3000)
 */
declare(strict_types=1);

function gncbot_client_ip(): string
{
    $hdr = function_exists('env') ? env('CHATBOT_TRUSTED_IP_HEADER', '') : '';   // e.g. HTTP_CF_CONNECTING_IP behind Cloudflare
    $ip  = ($hdr && !empty($_SERVER[$hdr])) ? trim(explode(',', (string)$_SERVER[$hdr])[0]) : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/** @return bool true = allowed */
function gncbot_throttle_ok(): bool
{
    $ipLimit    = (int)(function_exists('env') ? env('GNCBOT_IP_LIMIT', 20) : 20);
    $dailyLimit = (int)(function_exists('env') ? env('GNCBOT_DAILY_LIMIT', 3000) : 3000);
    $dir = rtrim(sys_get_temp_dir(), '/\\') . '/gncbot_rl';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) { return true; } // fail-open: never break the site

    $touch = function (string $file, int $window, int $limit): bool {
        $fh = @fopen($file, 'c+');
        if (!$fh) { return true; }
        flock($fh, LOCK_EX);
        $raw = stream_get_contents($fh);
        $now = time();
        $hits = array_values(array_filter(array_map('intval', $raw !== '' ? explode(',', $raw) : []), fn($t) => $t > $now - $window));
        $ok = count($hits) < $limit;
        if ($ok) { $hits[] = $now; }
        ftruncate($fh, 0); rewind($fh); fwrite($fh, implode(',', array_slice($hits, -$limit)));
        flock($fh, LOCK_UN); fclose($fh);
        return $ok;
    };

    if (!$touch($dir . '/ip_' . hash('sha256', gncbot_client_ip()), 600, max(1, $ipLimit))) { return false; }
    return $touch($dir . '/day_' . date('Ymd'), 86400, max(1, $dailyLimit));
}
