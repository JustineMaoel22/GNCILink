<?php
/** Public news feed: News's own category string, independent of author and category_id. */
function athleticsNews(PDO $db,array $query): array {
    $where="n.status = 'published' AND n.category = ?";
    $category='Athletics and Sports';
    $count=$db->prepare("SELECT COUNT(*) FROM news n WHERE $where");$count->execute([$category]);
    $total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/6));
    $requested=filter_var($query['highlights_page']??1,FILTER_VALIDATE_INT);
    $page=min($pages,max(1,$requested===false?1:$requested));$offset=($page-1)*6;
    $stmt=$db->prepare("SELECT n.news_id,n.title,n.slug,n.author,COALESCE(n.published_at,n.created_at) AS post_date,m.file_path AS image_path
        FROM news n LEFT JOIN media_library m ON m.media_id=n.featured_image
        WHERE $where ORDER BY post_date DESC,n.news_id DESC LIMIT 6 OFFSET $offset");
    $stmt->execute([$category]);
    return ['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'page'=>$page,'pages'=>$pages,'total'=>$total];
}
