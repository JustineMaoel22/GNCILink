<?php
/** News uses its own category code, not categories.category_id. Keep the code
 * within the existing VARCHAR(20) column; the admin shows the full label. */
function communityImpactPosts(PDO $db, array $query): array {
    $category = 'Community Extension';
    $count = $db->prepare("SELECT COUNT(*) FROM news WHERE status='published' AND category=?");
    $count->execute([$category]);
    $total = (int)$count->fetchColumn();
    $pages = max(1, (int)ceil($total / 6));
    $requested = filter_var($query['impact_page'] ?? 1, FILTER_VALIDATE_INT);
    $page = min($pages, max(1, $requested === false ? 1 : $requested));
    $offset = ($page - 1) * 6;
    $select = $db->prepare("SELECT n.news_id,n.title,n.slug,COALESCE(n.published_at,n.created_at) AS post_date,
        m.file_path AS image_path FROM news n LEFT JOIN media_library m ON m.media_id=n.featured_image
        WHERE n.status='published' AND n.category=?
        ORDER BY COALESCE(n.published_at,n.created_at) DESC,n.news_id DESC LIMIT 6 OFFSET $offset");
    $select->execute([$category]);
    return ['items'=>$select->fetchAll(PDO::FETCH_ASSOC),'page'=>$page,'pages'=>$pages,'total'=>$total];
}
