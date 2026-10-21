<?php

function campusEventPosts(PDO $db, array $query): array {
    // Keep both post types in one dated feed, independent of the calendar month.
    $feedSql = "SELECT a.announcement_id AS post_id, 'announcement' AS post_type,
            a.title, a.slug, COALESCE(a.published_at, a.created_at) AS post_date,
            m.file_path AS image_path, NULL AS permalink_url
        FROM announcements a
        JOIN categories c ON c.category_id = a.category_id
        LEFT JOIN media_library m ON m.media_id = a.featured_image
        WHERE a.status = 'published' AND c.category_name = ?
        UNION ALL
        SELECT n.news_id AS post_id, 'news' AS post_type,
            n.title, n.slug, COALESCE(n.published_at, n.created_at) AS post_date,
            m.file_path AS image_path, NULL AS permalink_url
        FROM news n
        JOIN categories c ON c.category_id = n.category_id
        LEFT JOIN media_library m ON m.media_id = n.featured_image
        WHERE n.status = 'published' AND c.category_name = ?
        UNION ALL
        SELECT f.fb_post_id AS post_id, 'facebook' AS post_type,
            f.message AS title, NULL AS slug, f.created_time AS post_date,
            m.file_path AS image_path, f.permalink_url
        FROM fb_posts_cache f
        JOIN categories c ON c.category_id = f.category_id
        LEFT JOIN media_library m ON m.media_id = f.image_media_id
        WHERE c.category_name = ?";
    $params = ['School Event', 'School Event', 'School Event'];
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
