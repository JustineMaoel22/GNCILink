<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
$db = getDB();
$_SESSION['user_id'] = (int)$db->query('SELECT user_id FROM users ORDER BY user_id LIMIT 1')->fetchColumn();
$_SESSION['user_role'] = 'Super Admin';
$db->beginTransaction();
try {
    $title = 'Incomplete publishing test ' . bin2hex(random_bytes(5));
    $data = ['title' => $title, 'content' => '', 'author' => '', 'status' => 'published', 'allow_incomplete' => true];
    $news = createNews($data);
    $announcement = createAnnouncement($data);
    if (!$news || !$announcement) throw new RuntimeException('Confirmed incomplete descriptions must remain publishable');
    foreach (['news' => ['news_id', $news], 'announcements' => ['announcement_id', $announcement]] as $table => [$key, $id]) {
        $stmt = $db->prepare("SELECT content, status FROM $table WHERE $key = ?");
        $stmt->execute([$id]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row['content'] !== '' || $row['status'] !== 'published') throw new RuntimeException('Existing publishing workflow did not preserve the confirmed empty description');
    }
} finally { $db->rollBack(); }
echo "PASS: confirmed incomplete News and Announcements publish using the existing helpers; test writes rolled back.\n";
