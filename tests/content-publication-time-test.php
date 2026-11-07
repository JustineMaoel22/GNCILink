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

function publicationCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$originalTimezone = date_default_timezone_get();
try {
    foreach ([['Asia/Manila', '+08:00'], ['America/New_York', '-05:00']] as [$phpZone, $dbZone]) {
        date_default_timezone_set($phpZone);
        $db->exec("SET SESSION time_zone = '$dbZone'");
        foreach ([['createNews','updateNews','getNewsById','news','news_id'], ['createAnnouncement','updateAnnouncement','getAnnouncementById','announcements','announcement_id']] as [$create,$update,$read,$table,$idColumn]) {
            $data = ['title'=>'Publication clock regression', 'content'=>'<p>Test only.</p>', 'status'=>'draft', 'author'=>'Test editor', 'category_ids'=>[]];
            $start = time();
            $id = $create($data);
            publicationCheck((bool)$id, $create.' failed.');
            $record = $read($id);
            publicationCheck($record['published_at'] === null, 'Drafts must not acquire a publication time.');
            $created = new DateTimeImmutable($record['created_at'], new DateTimeZone('UTC'));
            publicationCheck($created->getTimestamp() >= $start - 2 && $created->getTimestamp() <= time() + 2, 'Creation time must be UTC regardless of server timezone.');
            $db->prepare("UPDATE $table SET created_at='2001-01-01 00:00:00' WHERE $idColumn=?")->execute([$id]);
            $data['status'] = 'published';
            publicationCheck($update($id,$data), 'Publication failed.');
            $record = $read($id);
            $publication = new DateTimeImmutable($record['published_at'], new DateTimeZone('UTC'));
            publicationCheck($publication->getTimestamp() >= $start - 2 && $publication->getTimestamp() <= time() + 2, 'Publication time must reflect the current clock, not draft creation or PHP local time.');
            $updated = new DateTimeImmutable($record['updated_at'], new DateTimeZone('UTC'));
            publicationCheck(abs($updated->getTimestamp()-time()) < 3, 'Update time must be UTC.');
            // A fixed historic first-publication time must survive edits and republishing.
            $firstPublished = '2026-09-30 14:02:00';
            $db->prepare("UPDATE $table SET published_at=? WHERE $idColumn=?")->execute([$firstPublished,$id]);
            foreach (['published','archived','draft','published'] as $status) {
                $data['status'] = $status;
                publicationCheck($update($id,$data), 'Status update failed.');
                publicationCheck($read($id)['published_at'] === $firstPublished, 'Later edits must preserve the original publication time.');
            }
            // Creating content directly as published must also stamp publication time.
            $direct = $create($data);
            publicationCheck(!empty($read($direct)['published_at']), 'Direct publication needs a publication time.');
            // Legacy records acquire a missing timestamp only on an explicit published save.
            $db->prepare("UPDATE $table SET published_at=NULL WHERE $idColumn=?")->execute([$direct]);
            publicationCheck($update($direct,$data), 'Legacy publication save failed.');
            publicationCheck(!empty($read($direct)['published_at']), 'Explicit publication should fill a missing legacy timestamp.');
        }
    }
    echo "PASS: News and Announcements record first publication time in UTC, handle old drafts and direct publication, and preserve it on edits/republication across server timezones. Only temporary tables were written.\n";
} finally {
    date_default_timezone_set($originalTimezone);
    $db->exec("SET SESSION time_zone = '+00:00'");
}
