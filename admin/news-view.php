<?php
/** Read-only News list queries and validated list return paths. */
require_once __DIR__.'/announcement-view.php';
function newsFilters(array $input): array {
    $f=announcementFilters($input);
    $f=array_intersect_key($f,array_flip(['search','status','from','to','pinned','sort','size','page']));
    $category=is_string($input['category']??null)?$input['category']:'';
    $f['category']=array_key_exists($category,getNewsCategories())?$category:'';
    $f['sort']=($input['sort']??'')==='updated'?'updated':$f['sort'];
    $f['date_field']=($input['date_field']??'')==='published'?'published':'created';
    return $f;
}
function newsReturnUrl(array $input,array $extra=[]): string {
    $query=is_string($input['return_query']??null)?$input['return_query']:'';
    $parsed=[];if(strlen($query)<=3000)parse_str($query,$parsed);
    return '/admin/news.php?'.http_build_query(array_merge(newsFilters($parsed),$extra));
}
function newsListResults(PDO $db,array &$f,string $role,int $userId): array {
    $where=[];$params=[];
    if($role==='The Mirror'){$where[]='n.user_id=?';$params[]=$userId;}
    elseif($role==='Content Editor'){$where[]="(n.status<>'draft' OR n.user_id=?)";$params[]=$userId;}
    if($f['search']!==''){
        $term='%'.strtr($f['search'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
        $where[]="(n.title LIKE ? ESCAPE '!' OR n.content LIKE ? ESCAPE '!')";array_push($params,$term,$term);
    }
    foreach(['status'=>'n.status','category'=>'n.category','pinned'=>'n.is_pinned'] as $key=>$column)if($f[$key]!==''){$where[]="$column=?";$params[]=$f[$key];}
    $dateColumn=$f['date_field']==='published'?'n.published_at':'n.created_at';
    // Inputs are Philippine calendar dates; stored article timestamps are UTC.
    foreach(['from'=>'>=','to'=>'<'] as $key=>$operator)if($f[$key]){
        $date=new DateTimeImmutable($f[$key],new DateTimeZone('Asia/Manila'));
        if($key==='to')$date=$date->modify('+1 day');
        $where[]="$dateColumn $operator ?";$params[]=$date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    $where=$where?' WHERE '.implode(' AND ',$where):'';
    $q=$db->prepare('SELECT COUNT(*) FROM news n'.$where);$q->execute($params);$total=(int)$q->fetchColumn();
    $pages=max(1,(int)ceil($total/$f['size']));$f['page']=min($f['page'],$pages);
    $order=$f['sort']==='updated'?'COALESCE(n.updated_at,n.created_at) DESC,n.news_id DESC':('n.created_at '.($f['sort']==='oldest'?'ASC':'DESC').',n.news_id '.($f['sort']==='oldest'?'ASC':'DESC'));
    $q=$db->prepare('SELECT n.*,u.first_name,u.last_name,m.file_path AS image_path FROM news n LEFT JOIN users u ON u.user_id=n.user_id LEFT JOIN media_library m ON m.media_id=n.featured_image'.$where.' ORDER BY '.$order.' LIMIT ? OFFSET ?');
    foreach($params as $i=>$v)$q->bindValue($i+1,$v,PDO::PARAM_STR);
    $q->bindValue(count($params)+1,$f['size'],PDO::PARAM_INT);$q->bindValue(count($params)+2,($f['page']-1)*$f['size'],PDO::PARAM_INT);$q->execute();$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    $galleries=[];$ids=array_column($rows,'news_id');
    if($ids){$q=$db->prepare('SELECT ni.*,m.file_path FROM news_images ni LEFT JOIN media_library m ON m.media_id=ni.media_id WHERE ni.news_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY ni.display_order,ni.image_id');$q->execute($ids);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $image)$galleries[$image['news_id']][]=$image;}
    return compact('rows','total','pages','galleries');
}
