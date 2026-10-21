<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
$db = getDB();
if (($argv[1] ?? '') === 'seed') {
    $dir = dirname(__DIR__) . '/uploads/announcements'; if (!is_dir($dir)) mkdir($dir, 0755, true);
    $path = '/uploads/announcements/' . bin2hex(random_bytes(16)) . '.png';
    copy(dirname(__DIR__) . '/assets/images/admission-header.png', dirname(__DIR__) . $path);
    $_SESSION['user_id'] = (int)$db->query('SELECT user_id FROM users ORDER BY user_id LIMIT 1')->fetchColumn();
    $_SESSION['user_role'] = 'Super Admin';
    $db->prepare('INSERT INTO media_library (file_name,file_type,file_size,file_path,created_by) VALUES (?,?,?,?,?)')->execute(['FRAME-TEST.png','png',filesize(dirname(__DIR__).$path),$path,$_SESSION['user_id']]);
    $media = (int)$db->lastInsertId();
    $id = createAnnouncement(['title' => 'FRAME-TEST-' . bin2hex(random_bytes(5)), 'content' => 'Image display integration fixture.', 'featured_image' => $media, 'status' => 'published']);
    if (!$id) throw new RuntimeException('Could not create test fixture');
    echo json_encode(getAnnouncementById($id));
} elseif (($argv[1] ?? '') === 'inspect') {
    $row = getAnnouncementById((int)($argv[2] ?? 0));
    if (!$row || !str_starts_with($row['title'], 'FRAME-')) throw new RuntimeException('Unknown test announcement');
    $path = $row['image_path'];
    echo json_encode(['announcement' => $row, 'path' => $path, 'hash' => hash_file('sha256', dirname(__DIR__).$path), 'frame' => imageFramingAll()[$path] ?? null]);
} elseif (($argv[1] ?? '') === 'find') {
    $title = (string)($argv[2] ?? '');
    if (!str_starts_with($title, 'FRAME-UPLOAD-')) throw new RuntimeException('Unknown test prefix');
    $q = $db->prepare('SELECT announcement_id FROM announcements WHERE title=?'); $q->execute([$title]);
    echo json_encode($q->fetchColumn());
} elseif (($argv[1] ?? '') === 'cleanup') {
    foreach ($db->query("SELECT announcement_id FROM announcements WHERE title LIKE 'FRAME-TEST-%' OR title LIKE 'FRAME-UPLOAD-%'")->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $row = getAnnouncementById((int)$id); $path = $row['image_path']; $media = (int)$row['featured_image'];
        $db->prepare('DELETE FROM image_display_settings WHERE image_path=?')->execute([$path]);
        $db->prepare('DELETE FROM announcements WHERE announcement_id=?')->execute([$id]);
        if (preg_match('#^/uploads/announcements/[a-f0-9]{32}\.png$#D',$path)) {
            $db->prepare('DELETE FROM media_library WHERE media_id=?')->execute([$media]);
            @unlink(dirname(__DIR__).$path);
        }
    }
    echo "Framing fixtures removed.\n";
} else { throw new RuntimeException('Use seed, inspect, find or cleanup.'); }
