<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../admin/admin-functions.php';
require_once __DIR__ . '/../admin/announcement-view.php';
require_once __DIR__ . '/../admin/approval-view.php';
require_once __DIR__ . '/../includes/campus-event-posts.php';
initSession();
$db = getDB();
$_SESSION['user_id'] = (int)$db->query("SELECT user_id FROM users WHERE role='Super Admin' AND status='active' LIMIT 1")->fetchColumn();
$_SESSION['user_role'] = 'Super Admin';
function categoryCheck(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$categories = $db->query("SELECT category_id,category_name FROM categories WHERE category_name IN ('Alumni','School Activities') ORDER BY category_name")->fetchAll(PDO::FETCH_KEY_PAIR);
categoryCheck(count($categories) === 2, 'Install the Alumni and School Activities categories first.');
$ids = array_reverse(array_keys($categories));
$db->beginTransaction();
try {
    categoryCheck(normalizeAnnouncementCategoryIds($db, [$ids[0], (string)$ids[1], $ids[0]]) === $ids, 'Selections normalize and deduplicate.');
    foreach (['1', [[1]], ['bad'], ['0'], ['9999999999']] as $bad) {
        try { normalizeAnnouncementCategoryIds($db, $bad); throw new RuntimeException('Invalid categories accepted.'); }
        catch (InvalidArgumentException $expected) {}
    }
    $id = createAnnouncement(['title'=>'Multiple category verification ' . bin2hex(random_bytes(4)), 'content'=>'<p>Integration test.</p>', 'category_ids'=>$ids, 'status'=>'published']);
    $record = getAnnouncementById($id);
    categoryCheck($record['category_ids'] === $ids, 'Create/read retain both categories.');
    categoryCheck(str_contains($record['category_name'], 'Alumni') && str_contains($record['category_name'], 'School Activities'), 'Readable labels include both categories.');
    foreach ($ids as $categoryId) {
        $filters = announcementFilters(['category'=>(string)$categoryId, 'search'=>$record['title']]);
        $list = announcementList($db, $filters);
        categoryCheck($list['total'] === 1 && (int)$list['rows'][0]['announcement_id'] === $id, 'Admin filters match secondary categories without duplicates.');
    }
    $feed = campusEventPosts($db, [], 'Alumni', true);
    categoryCheck(in_array($id, array_map('intval', array_column(array_filter($feed['items'], static fn($row)=>$row['post_type']==='announcement'), 'post_id')), true), 'Alumni gallery includes the tagged announcement.');
    $fields = approvalFields(['category_ids'=>$ids, 'category_ids_present'=>'1']);
    categoryCheck(isset($fields['categories']) && count($fields) === 1 && str_contains($fields['categories'], 'Alumni'), 'Approval shows names and excludes the transport marker.');
    categoryCheck(updateAnnouncement($id, ['title'=>$record['title'], 'content'=>$record['content'], 'category_id'=>$record['category_id'], 'status'=>'archived']), 'Archive succeeds.');
    categoryCheck(getAnnouncementById($id)['category_ids'] === $ids, 'Archive preserves extra tags.');
    categoryCheck(updateAnnouncement($id, ['title'=>$record['title'], 'content'=>$record['content'], 'category_ids'=>[$ids[1]], 'status'=>'draft']), 'Edit categories succeeds.');
    categoryCheck(getAnnouncementById($id)['category_ids'] === [$ids[1]], 'Removed category does not remain assigned.');
    categoryCheck(updateAnnouncement($id, ['title'=>$record['title'], 'content'=>$record['content'], 'category_ids'=>[]]), 'Clearing categories succeeds.');
    categoryCheck(getAnnouncementById($id)['category_ids'] === [] && getAnnouncementById($id)['category_id'] === null, 'Clear removes all tags and the legacy primary.');
    categoryCheck(updateAnnouncement($id, ['title'=>$record['title'], 'content'=>$record['content'], 'category_ids'=>$ids]), 'Reassign categories succeeds.');
    categoryCheck(deleteAnnouncement($id), 'Delete announcement succeeds.');
    $remaining=$db->prepare('SELECT COUNT(*) FROM announcement_categories WHERE announcement_id=?');$remaining->execute([$id]);
    categoryCheck((int)$remaining->fetchColumn()===0, 'Deleting an announcement removes its category links.');
    echo "PASS: multi-category create/read/edit/clear, archive preservation, secondary-category filters, Alumni feed, readable approvals, invalid ID rejection. All changes rolled back.\n";
} finally { $db->rollBack(); }
