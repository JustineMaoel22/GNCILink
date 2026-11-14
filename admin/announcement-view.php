<?php
require_once __DIR__ . '/../includes/announcement-categories.php';
/** Read-only list queries for the announcements management page. */
function announcementFilters(array $input): array {
    $text=static fn($key)=>is_string($input[$key]??null)?trim($input[$key]):'';
    $f=['search'=>mb_substr($text('search'),0,200),'status'=>'','category'=>'','dept'=>'','program'=>mb_substr($text('program'),0,20),'from'=>'','to'=>'','pinned'=>$text('pinned')==='1'?'1':'','sort'=>$text('sort')==='oldest'?'oldest':'newest','size'=>10,'page'=>1];
    if(in_array($text('status'),['draft','pending','published','archived'],true))$f['status']=$text('status');
    foreach(['category','dept'] as $key)if(preg_match('/^[1-9]\d{0,9}$/',$text($key)))$f[$key]=$text($key);
    foreach(['from','to'] as $key){$v=$text($key);if(!preg_match('/^[1-9]\d{3}-\d{2}-\d{2}$/',$v))continue;$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);if($d&&$d->format('Y-m-d')===$v)$f[$key]=$v;}
    if($f['from']&&$f['to']&&$f['from']>$f['to'])[$f['from'],$f['to']]=[$f['to'],$f['from']];
    if(in_array((int)$text('size'),[10,25,50,100],true))$f['size']=(int)$text('size');
    $f['page']=max(1,min(2147483647,(int)$text('page')));return $f;
}
function announcementReturnUrl(array $input): string {
    $query=is_string($input['return_query']??null)?$input['return_query']:'';
    $parsed=[];if(strlen($query)<=3000)parse_str($query,$parsed);
    return '/admin/announcement.php?'.http_build_query(announcementFilters($parsed));
}
function announcementList(PDO $db,array &$filters): array {
    $where=[];$params=[];
    if(function_exists('rbacRole')&&in_array(rbacRole(),['Content Editor','The Mirror'],true)){$where[]="(a.status<>'draft' OR a.user_id=?)";$params[]=(int)$_SESSION['user_id'];}
    if($filters['search']!==''){$term='%'.strtr($filters['search'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where[]="(a.title LIKE ? ESCAPE '!' OR a.content LIKE ? ESCAPE '!')";array_push($params,$term,$term);}
    foreach(['status'=>'a.status','pinned'=>'a.is_pinned'] as $key=>$field)if($filters[$key]!==''){$where[]="$field=?";$params[]=$filters[$key];}
    if($filters['category']!==''){$where[]=announcementCategoryCondition($db,'a');$params[]=$filters['category'];}
    if($filters['program']!==''){$where[]='(a.program=? OR EXISTS(SELECT 1 FROM announcement_programs ap WHERE ap.announcement_id=a.announcement_id AND ap.program_code=?))';array_push($params,$filters['program'],$filters['program']);}
    if($filters['dept']!==''){$where[]='(a.dept_id=? OR EXISTS(SELECT 1 FROM announcement_departments ad WHERE ad.announcement_id=a.announcement_id AND ad.dept_id=?))';array_push($params,$filters['dept'],$filters['dept']);}
    if($filters['from']){$where[]='a.created_at>=?';$params[]=$filters['from'].' 00:00:00';}
    if($filters['to']){$where[]='a.created_at<=?';$params[]=$filters['to'].' 23:59:59';}
    $where=$where?' WHERE '.implode(' AND ',$where):'';
    $q=$db->prepare('SELECT COUNT(*) FROM announcements a'.$where);$q->execute($params);$total=(int)$q->fetchColumn();
    $pages=max(1,(int)ceil($total/$filters['size']));$filters['page']=min($filters['page'],$pages);$order=$filters['sort']==='oldest'?'ASC':'DESC';
    $q=$db->prepare("SELECT a.*,u.first_name,u.last_name,c.category_name,m.file_path AS image_path,m.media_id FROM announcements a LEFT JOIN users u ON u.user_id=a.user_id LEFT JOIN categories c ON c.category_id=a.category_id LEFT JOIN media_library m ON m.media_id=a.featured_image $where ORDER BY a.created_at $order,a.announcement_id $order LIMIT ? OFFSET ?");
    foreach($params as $i=>$value)$q->bindValue($i+1,$value,PDO::PARAM_STR);
    $q->bindValue(count($params)+1,$filters['size'],PDO::PARAM_INT);$q->bindValue(count($params)+2,($filters['page']-1)*$filters['size'],PDO::PARAM_INT);$q->execute();$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    $rows=attachAnnouncementCategories($db,$rows);
    $galleries=[];$ids=array_column($rows,'announcement_id');
    if($ids){$q=$db->prepare('SELECT ai.*,m.file_path FROM announcement_images ai JOIN media_library m ON m.media_id=ai.media_id WHERE ai.announcement_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY ai.display_order,ai.image_id');$q->execute($ids);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $image)$galleries[$image['announcement_id']][]=$image;}
    return compact('rows','total','pages','galleries');
}
function announcementPinnedItems(PDO $db): array {
    return $db->query("SELECT 'announcement' AS source,CONVERT(announcement_id USING utf8mb4) COLLATE utf8mb4_unicode_ci AS item_id,title,status FROM announcements WHERE is_pinned=1 UNION ALL SELECT 'news' AS source,CONVERT(news_id USING utf8mb4) COLLATE utf8mb4_unicode_ci AS item_id,title,status FROM news WHERE is_pinned=1 UNION ALL SELECT 'facebook' AS source,CONVERT(fb_post_id USING utf8mb4) COLLATE utf8mb4_unicode_ci AS item_id,LEFT(message,140) AS title,'imported' AS status FROM fb_posts_cache WHERE is_pinned=1")->fetchAll(PDO::FETCH_ASSOC);
}
