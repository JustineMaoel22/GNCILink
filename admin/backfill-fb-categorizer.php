<?php
/**
 * One-time backfill: auto-categorize existing Facebook posts.
 * Place at /admin/backfill-fb-categories.php, open it once while logged in
 * as Super Admin, then delete the file (or keep it to re-run after you tune
 * the keyword lists in config/fb-categorizer.php).
 *
 * Posts with category_locked = 1 (manually set) are never touched.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/fb-categorizer.php';
require_once __DIR__ . '/admin-functions.php';

initSession();
requireLogin();

if (($_SESSION['user_role'] ?? '') !== 'Super Admin') {
    http_response_code(403);
    exit('Super Admin only.');
}

$db  = getDB();
$map = gncLoadCategoryMap($db);

$rows = $db->query("SELECT fb_post_id, message FROM fb_posts_cache WHERE category_locked = 0")->fetchAll();
$upd  = $db->prepare("UPDATE fb_posts_cache SET category_id = ? WHERE fb_post_id = ?");

$counts = [];
foreach ($rows as $r) {
    $catId = gncCategoryIdForMessage((string)$r['message'], $map);
    $upd->execute([$catId, $r['fb_post_id']]);
    $name = $catId ? (array_search($catId, $map, true) ?: 'unknown') : 'none';
    $counts[$name] = ($counts[$name] ?? 0) + 1;
}

header('Content-Type: text/plain; charset=utf-8');
echo "Processed " . count($rows) . " Facebook posts\n\n";
foreach ($counts as $name => $n) {
    echo str_pad(ucwords($name), 14) . $n . "\n";
}