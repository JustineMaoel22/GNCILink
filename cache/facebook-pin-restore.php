<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config/config.php';
$db=getDB();
if(($argv[1]??'')==='snapshot')echo json_encode($db->query('SELECT fb_post_id,is_pinned,pinned_at FROM fb_posts_cache')->fetchAll(PDO::FETCH_ASSOC));
elseif(($argv[1]??'')==='restore'){
    $rows=json_decode(file_get_contents(__DIR__.'/facebook-pin-snapshot.json'),true,512,JSON_THROW_ON_ERROR);
    $q=$db->prepare('UPDATE fb_posts_cache SET is_pinned=?,pinned_at=? WHERE fb_post_id=?');
    foreach($rows as $row)$q->execute([$row['is_pinned'],$row['pinned_at'],$row['fb_post_id']]);
}
