<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../admin/admin-functions.php';
require_once __DIR__ . '/../components/alumni-page-content.php';
require_once __DIR__ . '/../includes/campus-event-posts.php';
$db = getDB();
if (!isset(getNewsCategories()['Alumni']) || normalizeNewsCategory('Alumni') !== 'Alumni') throw new RuntimeException('Alumni must be an allowed News category.');
$db->beginTransaction();
try {
    $category = $db->prepare('SELECT category_id FROM categories WHERE category_name=? LIMIT 1');
    $category->execute(['Alumni']);
    $categoryId = $category->fetchColumn();
    if (!$categoryId) throw new RuntimeException('Run the Alumni migration first.');
    if (textLayoutRoute('/pages/alumni.php?event_page=2&ce_preview=1') !== textLayoutRoute('/pages/alumni.php')) throw new RuntimeException('Pagination must preserve page typography and backgrounds.');
    $imageId = $db->query("SELECT media_id FROM media_library WHERE file_type IN ('jpg','jpeg','png','webp') ORDER BY media_id DESC LIMIT 1")->fetchColumn() ?: null;
    $insert = $db->prepare('INSERT INTO events (category_id,title,location,start_date,status,media_id) VALUES (?,?,?,?,?,?)');
    $published = []; $private = [];
    foreach (range(1, 8) as $index) {
        $insert->execute([$categoryId, 'Alumni gathering ' . $index, 'Campus hall', $index === 8 ? '1900-01-01 00:00:00' : '2099-01-' . sprintf('%02d', $index) . ' 09:00:00', 'published', $imageId]);
        $published[] = (int)$db->lastInsertId();
    }
    foreach (['draft', 'pending', 'archived'] as $status) {
        $insert->execute([$categoryId, 'Private alumni event', '', '2099-12-01 00:00:00', $status, null]);
        $private[] = (int)$db->lastInsertId();
    }
    $insert->execute([null, 'Other category event', '', '2099-12-01 00:00:00', 'published', null]);
    $private[] = (int)$db->lastInsertId();
    $taggedPosts = [];
    foreach ([['news', 'news_id', 'news'], ['announcements', 'announcement_id', 'announcement'], ['fb_posts_cache', 'fb_post_id', 'facebook']] as [$table, $column, $type]) {
        $id = $db->query("SELECT $column FROM $table" . ($type !== 'facebook' ? " WHERE status='published'" : '') . ' LIMIT 1')->fetchColumn();
        if ($id) {
            if ($type === 'news') {
                // News editor saves the string category, without the legacy category_id.
                $db->prepare("UPDATE news SET category='Alumni', category_id=NULL WHERE news_id=?")->execute([$id]);
            } else {
                $db->prepare("UPDATE $table SET category_id=? WHERE $column=?")->execute([$categoryId, $id]);
            }
            $taggedPosts[] = $type . ':' . $id;
        }
    }
    $first = campusEventPosts($db, [], 'Alumni', true);
    if (count($first['items']) !== 6 || $first['pages'] < 2) throw new RuntimeException('Six-card pagination failed.');
    $all = [];
    for ($page = 1; $page <= $first['pages']; $page++) {
        foreach (campusEventPosts($db, ['event_page'=>$page], 'Alumni', true)['items'] as $post) $all[] = $post['post_type'] . ':' . $post['post_id'];
    }
    if (count($all) !== count(array_unique($all))) throw new RuntimeException('Duplicate rows between pages.');
    foreach ($published as $id) if (!in_array('event:' . $id, $all, true)) throw new RuntimeException('A published or past Alumni event is missing.');
    foreach ($private as $id) if (in_array('event:' . $id, $all, true)) throw new RuntimeException('A private or other-category event leaked.');
    foreach ($taggedPosts as $key) if (!in_array($key, $all, true)) throw new RuntimeException('Tagged source missing: ' . $key);
    if (campusEventPosts($db, ['event_page'=>'invalid'], 'Alumni', true)['page'] !== 1) throw new RuntimeException('Invalid page not handled.');
    if (campusEventPosts($db, ['event_page'=>PHP_INT_MAX], 'Alumni', true)['page'] !== $first['pages']) throw new RuntimeException('Page not clamped.');
    foreach (campusEventPosts($db, [])['items'] as $post) if ($post['post_type'] === 'event') throw new RuntimeException('Default Campus Events feed changed.');
    if (in_array('--preview', $argv, true)) {
        $preview = false;
        $alumni = alumniPageContent(false);
        $escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        $plain = static fn($key) => $escape($alumni[$key] ?? '');
        $mark = static fn($key, $label) => '';
        foreach ([1, 2] as $page) {
            $_GET['event_page'] = $page;
            ob_start();
            include __DIR__ . '/../components/alumni-recent-events.php';
            file_put_contents(__DIR__ . '/../cache/alumni-recent-fixture-' . $page . '.html', ob_get_clean());
        }
    }
    echo "PASS: all published Alumni events including past dates, six-per-page coverage, no duplicates, private/other-category exclusion, tagged posts, safe page clamping and unchanged default feed. Fixtures rolled back.\n";
} finally { $db->rollBack(); }
