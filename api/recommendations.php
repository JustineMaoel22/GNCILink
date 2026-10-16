<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../components/recommendation-catalog.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { http_response_code(405); echo '{"items":[]}'; exit; }
// One shared snapshot for all visitors, bounded to 60 seconds. No session or profile data.
$cache = sys_get_temp_dir().'/gnc-rec-'.hash('sha256', __DIR__.DB_NAME).'.json';
$lock = fopen($cache.'.lock', 'c');
try {
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Catalog lock unavailable');
    if (is_file($cache) && time()-filemtime($cache)<60) {
        $json=file_get_contents($cache);
    } else {
        $json=json_encode(['items'=>gncRecCatalog(getDB())], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        file_put_contents($cache, $json, LOCK_EX);
    }
    header('Cache-Control: public, max-age=30');
    echo $json;
} catch (Throwable $e) {
    error_log('Recommendation catalog: '.$e->getMessage());
    http_response_code(503); header('Cache-Control: no-store'); echo '{"items":[]}';
} finally { if ($lock) { flock($lock, LOCK_UN); fclose($lock); } }
