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
    require_once __DIR__.'/../../includes/request-protection.php';
    return gncRequestIp();
}

/** Read the visitor's rolling allowance without consuming a message. */
function gncbot_allowance(): ?array
{
    $limit = max(1, (int)(function_exists('env') ? env('GNCBOT_IP_LIMIT', 20) : 20));
    $file = rtrim(sys_get_temp_dir(), '/\\') . '/gncbot_rl/ip_' . hash('sha256', gncbot_client_ip());
    $hits = [];
    $now = time();
    if (is_file($file)) {
        $fh = @fopen($file, 'r');
        if (!$fh) { return null; }
        if (!flock($fh, LOCK_SH|LOCK_NB)) { fclose($fh); return null; }
        $raw = stream_get_contents($fh);
        flock($fh, LOCK_UN); fclose($fh);
        if ($raw === false) { return null; }
        $hits = array_values(array_filter(array_map('intval', explode(',', $raw)), fn($t) => $t > $now - 600));
    }
    return [
        'limit' => $limit,
        'remaining' => max(0, $limit - count($hits)),
        'retry_after' => $hits ? max(1, min($hits) + 600 - $now) : 0,
    ];
}

/** @return bool true = allowed */
function gncbot_throttle_ok(): bool
{
    $ipLimit    = (int)(function_exists('env') ? env('GNCBOT_IP_LIMIT', 20) : 20);
    $dailyLimit = (int)(function_exists('env') ? env('GNCBOT_DAILY_LIMIT', 3000) : 3000);
    $dir = rtrim(sys_get_temp_dir(), '/\\') . '/gncbot_rl';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) { return false; }

    $touch = function (string $file, int $window, int $limit): bool {
        $fh = @fopen($file, 'c+');
        if (!$fh) { return false; }
        if (!flock($fh, LOCK_EX|LOCK_NB)) { fclose($fh); return false; }
        $raw = stream_get_contents($fh);
        $now = time();
        $hits = array_values(array_filter(array_map('intval', $raw !== '' ? explode(',', $raw) : []), fn($t) => $t > $now - $window));
        $ok = count($hits) < $limit;
        if ($ok) { $hits[] = $now; }
        $data=implode(',', array_slice($hits, -$limit));
        $written=ftruncate($fh, 0); rewind($fh); $written=$written && fwrite($fh,$data)===strlen($data) && fflush($fh);
        flock($fh, LOCK_UN); fclose($fh);
        return $ok && $written;
    };

    if (!$touch($dir . '/ip_' . hash('sha256', gncbot_client_ip()), 600, max(1, $ipLimit))) { return false; }
    return $touch($dir . '/day_' . date('Ymd'), 86400, max(1, $dailyLimit));
}
