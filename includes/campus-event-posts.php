<?php
require_once __DIR__ . '/event-categories.php';
require_once __DIR__ . '/announcement-categories.php';

function campusEventPosts(PDO $db, array $query, string $category = 'School Event', bool $includeEvents = false): array {
    // Keep categorized public posts in one dated feed, independent of the calendar month.
    $announcementCategory = announcementCategoryCondition($db, 'a', true);
    $feedSql = "SELECT a.announcement_id AS post_id, 'announcement' AS post_type,
            a.title, a.slug, COALESCE(a.published_at, a.created_at) AS post_date,
            m.file_path AS image_path, NULL AS permalink_url
        FROM announcements a
        LEFT JOIN media_library m ON m.media_id = a.featured_image
        WHERE a.status = 'published' AND $announcementCategory
        UNION ALL
        SELECT n.news_id AS post_id, 'news' AS post_type,
            n.title, n.slug, COALESCE(n.published_at, n.created_at) AS post_date,
            m.file_path AS image_path, NULL AS permalink_url
        FROM news n
        LEFT JOIN categories c ON c.category_id = n.category_id
        LEFT JOIN media_library m ON m.media_id = n.featured_image
        WHERE n.status = 'published' AND (n.category = ? OR c.category_name = ?)
        UNION ALL
        SELECT f.fb_post_id AS post_id, 'facebook' AS post_type,
            f.message AS title, NULL AS slug, f.created_time AS post_date,
            m.file_path AS image_path, f.permalink_url
        FROM fb_posts_cache f
        JOIN categories c ON c.category_id = f.category_id
        LEFT JOIN media_library m ON m.media_id = f.image_media_id
        WHERE c.category_name = ?";
    $newsCategory = $category === 'School Event' ? 'Campus Events' : $category;
    $params = [$category, $newsCategory, $category, $category];
    if ($includeEvents) {
        $feedSql .= " UNION ALL
            SELECT e.event_id AS post_id, 'event' AS post_type,
                e.title, NULL AS slug, COALESCE(e.start_date,e.created_at) AS post_date,
                m.file_path AS image_path, NULL AS permalink_url
            FROM events e JOIN " . eventCategoryTable($db) . " c ON c.category_id=e.category_id
            LEFT JOIN media_library m ON m.media_id=e.media_id
            WHERE e.status='published' AND c.category_name=?";
        $params[] = $category;
    }
    $count = $db->prepare("SELECT COUNT(*) FROM ($feedSql) AS school_posts");
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $pages = max(1, (int)ceil($total / 6));
    $requestedPage = filter_var($query['event_page'] ?? 1, FILTER_VALIDATE_INT);
    $page = min($pages, max(1, $requestedPage === false ? 1 : $requestedPage));
    $offset = ($page - 1) * 6;
    $stmt = $db->prepare("SELECT * FROM ($feedSql) AS school_posts
        ORDER BY post_date DESC, post_type ASC, post_id DESC LIMIT 6 OFFSET $offset");
    $stmt->execute($params);
    return ['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC), 'page'=>$page, 'pages'=>$pages, 'total'=>$total];
}
