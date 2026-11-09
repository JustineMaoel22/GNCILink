<?php
/** Cached management queries. No Facebook API requests. */
require_once __DIR__.'/announcement-view.php';
function facebookPostFilters(array $input): array {
    $f=announcementFilters($input);
    $f['category']=($input['category']??'')==='none'?'none':$f['category'];
    $f['pinned']=is_string($input['pinned']??null)&&in_array($input['pinned'],['0','1'],true)?$input['pinned']:'';
    return array_intersect_key($f,array_flip(['search','category','pinned','from','to','size','page']));
}
function facebookPostList(PDO $db,array &$filters): array {
    $where=[];$params=[];
    if($filters['category']==='none')$where[]='f.category_id IS NULL';
    elseif($filters['category']!==''){$where[]='f.category_id=?';$params[]=$filters['category'];}
    if($filters['pinned']!==''){$where[]='f.is_pinned=?';$params[]=$filters['pinned'];}
    if($filters['from']){$where[]='f.created_time>=?';$params[]=$filters['from'].' 00:00:00';}
    if($filters['to']){$where[]='f.created_time<=?';$params[]=$filters['to'].' 23:59:59';}
    $join=' FROM fb_posts_cache f LEFT JOIN categories c ON c.category_id=f.category_id LEFT JOIN media_library m ON m.media_id=f.image_media_id';
    $sql=$join.($where?' WHERE '.implode(' AND ',$where):'');$ids=null;
    // Stream caption search to match styled Unicode throughout the entire cache.
    if($filters['search']!==''){
        $q=$db->prepare('SELECT f.fb_post_id,f.message'.$sql.' ORDER BY f.created_time DESC,f.fb_post_id DESC');$q->execute($params);$ids=[];$needle=normalizeForSearch($filters['search']);
        while($r=$q->fetch(PDO::FETCH_ASSOC))if(mb_strpos(normalizeForSearch((string)$r['message']),$needle)!==false)$ids[]=$r['fb_post_id'];
        $total=count($ids);
    }else{$q=$db->prepare('SELECT COUNT(*)'.$sql);$q->execute($params);$total=(int)$q->fetchColumn();}
    $pages=max(1,(int)ceil($total/$filters['size']));$filters['page']=min($filters['page'],$pages);$offset=($filters['page']-1)*$filters['size'];
    if($ids!==null){$ids=array_slice($ids,$offset,$filters['size']);if(!$ids)return ['rows'=>[],'total'=>$total,'pages'=>$pages];$sql=$join.' WHERE f.fb_post_id IN ('.implode(',',array_fill(0,count($ids),'?')).')';$params=$ids;}
    $q=$db->prepare('SELECT f.fb_post_id,f.message,f.permalink_url,f.created_time,f.is_pinned,f.category_id,f.category_locked,c.category_name,m.file_path AS image_path'.$sql.' ORDER BY f.created_time DESC,f.fb_post_id DESC LIMIT ? OFFSET ?');
    foreach($params as $i=>$v)$q->bindValue($i+1,$v,PDO::PARAM_STR);
    $q->bindValue(count($params)+1,$filters['size'],PDO::PARAM_INT);$q->bindValue(count($params)+2,$ids!==null?0:$offset,PDO::PARAM_INT);$q->execute();$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) $row['category_name'] = facebookPostCategoryLabel($row['category_name']);
    unset($row);
    return compact('rows','total','pages');
}
function facebookPostImage(?string $path): string {
    $path=trim($path??'');
    return preg_match('~^/?(?:uploads|assets)/[a-zA-Z0-9_./% -]+$~D',$path)&&!str_contains($path,'..')?'/'.ltrim($path,'/'):'';
}
