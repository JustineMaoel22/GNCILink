<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../admin/admin-functions.php';
initSession();
$db = getDB();
$_SESSION['user_id'] = (int)$db->query("SELECT user_id FROM users WHERE role='Super Admin' AND status='active' LIMIT 1")->fetchColumn();
$_SESSION['user_role'] = 'Super Admin';
if (!$_SESSION['user_id']) throw new RuntimeException('A local Super Admin account is required.');
// All writes, including logs and category synchronization, stay connection-local.
foreach ([
    'news' => 'news_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,category VARCHAR(100),title VARCHAR(500),slug VARCHAR(500),content MEDIUMTEXT,author VARCHAR(255),featured_image INT NULL,status VARCHAR(30),published_at DATETIME NULL,created_at DATETIME,updated_at DATETIME NULL,is_pinned INT DEFAULT 0,pinned_at DATETIME NULL',
    'announcements' => 'announcement_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,title VARCHAR(500),slug VARCHAR(500),content MEDIUMTEXT,category_id INT NULL,program VARCHAR(30),dept_id INT NULL,featured_image INT NULL,status VARCHAR(30),created_at DATETIME,updated_at DATETIME NULL,is_pinned INT DEFAULT 0,pinned_at DATETIME NULL,published_at DATETIME NULL',
    'activity_logs' => 'log_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,action VARCHAR(100),table_name VARCHAR(100),record_id INT,description TEXT,created_at DATETIME',
    'announcement_categories' => 'announcement_id INT,category_id INT,PRIMARY KEY(announcement_id,category_id)',
] as $table => $columns) {
    $db->exec("CREATE TEMPORARY TABLE $table ($columns) ENGINE=InnoDB");
}
$html = '<h1>Heading</h1><p class="ql-align-center"><strong>Bold</strong> <em>Italic</em> <u>Underline</u> <s>Strike</s></p><ol><li>Ordered</li></ol><ul><li>Bullet</li></ul><blockquote>Quote</blockquote><p><a href="https://example.com">Link</a></p>';
foreach ([['createNews', 'updateNews', 'getNewsById'], ['createAnnouncement', 'updateAnnouncement', 'getAnnouncementById']] as [$create, $update, $read]) {
    $data = ['title' => 'Rich text regression', 'content' => $html, 'status' => 'draft', 'author' => 'Test editor', 'category_ids' => []];
    $id = $create($data);
    if (!$id || $read($id)['content'] !== $html) throw new RuntimeException($create . ' did not retain formatting.');
    foreach (['draft', 'published', 'archived'] as $status) {
        $data['status'] = $status;
        if (!$update($id, $data)) throw new RuntimeException($update . ' failed.');
        $record = $read($id);
        if ($record['content'] !== $html || $record['status'] !== $status) throw new RuntimeException('Formatting changed during ' . $status . '.');
        $safe = sectionToEditorHtml($record['content']);
        if (!str_contains($safe, '<h1>') || !str_contains($safe, '<s>') || !str_contains($safe, 'text-align:center')) throw new RuntimeException('Reopening or previewing lost formatting.');
    }
}
echo "PASS: News and Announcements retain formatting through create, update, publish, archive, read and editor/preview conversion. Only temporary tables were written.\n";
