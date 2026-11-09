<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../components/recommendation-catalog.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { http_response_code(405); echo '{"items":[]}'; exit; }
// One shared snapshot for all visitors, bounded to 60 seconds. No session or profile data.
$cache = sys_get_temp_dir().'/gnc-rec-v2-'.hash('sha256', __DIR__.DB_NAME).'.json';
$lock = fopen($cache.'.lock', 'c');
try {
    if (!$lock || !flock($lock, LOCK_EX|LOCK_NB)) {
        // Do not tie up PHP workers behind a slow catalog rebuild.
        if (is_file($cache) && time()-filemtime($cache)<300 && ($stale=@file_get_contents($cache))!==false && is_array(json_decode($stale,true))) {
            header('Cache-Control: public, max-age=10');echo $stale;exit;
        }
        throw new RuntimeException('Catalog lock unavailable');
    }
    if (is_file($cache) && time()-filemtime($cache)<60) {
        $json=file_get_contents($cache);
    } else {
        $json=json_encode(['items'=>gncRecCatalog(getDB())], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        file_put_contents($cache, $json, LOCK_EX);
    }
    header('Cache-Control: public, max-age=30');
    echo $json;
} catch (Throwable $e) {
    error_log('Recommendation catalog unavailable.');
    http_response_code(503); header('Cache-Control: no-store'); header('Retry-After: 30'); echo '{"items":[]}';
} finally { if ($lock) { flock($lock, LOCK_UN); fclose($lock); } }
