<?php
require_once __DIR__.'/content-submissions.php';

/** Private proposals are list rows only; they never replace published records. */
function privateArticleDraftRows(PDO $db,array $filters,string $kind): array {
    if(!hasPermission('view_own_submissions')||rbacIsSuper()||!in_array($filters['status'],['','draft'],true)||$filters['pinned']==='1')return [];
    if(!rbacSubmissionsReady())return [];
    $route='/admin/action/create-'.$kind.'.php';
    $q=$db->prepare("SELECT s.*,u.first_name,u.last_name FROM rbac_submissions s LEFT JOIN users u ON u.user_id=s.user_id WHERE s.user_id=? AND s.kind='request' AND s.route=? AND s.status='draft'");
    $q->execute([(int)$_SESSION['user_id'],$route]);$rows=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $saved){
        $payload=rbacReadPayload((int)$saved['submission_id']);$post=$payload['post']??[];
        if(!in_array($post['action']??'', ['create','update'],true))continue;
        $title=(string)($post['title']??$saved['title']);$content=(string)($post['content']??'');
        if($filters['search']!==''&&mb_stripos($title,$filters['search'])===false&&mb_stripos($content,$filters['search'])===false)continue;
        $categories=(array)($post['category_ids']??(!empty($post['category_id'])?[$post['category_id']]:[]));
        if($filters['category']!==''&&($kind==='news'?($post['category']??'')!==$filters['category']:!in_array($filters['category'],array_map('strval',$categories),true)))continue;
        $departments=(array)($post['dept_ids']??(!empty($post['dept_id'])?[$post['dept_id']]:[]));
        $programs=(array)($post['programs']??(!empty($post['program'])?[$post['program']]:[]));
        if(($filters['dept']??'')!==''&&!in_array($filters['dept'],array_map('strval',$departments),true))continue;
        if(($filters['program']??'')!==''&&!in_array($filters['program'],$programs,true))continue;
        if(($filters['date_field']??'created')==='published'&&($filters['from']||$filters['to']))continue;
        $date=substr($saved['created_at'],0,10);
        if($kind==='news')$date=(new DateTimeImmutable($saved['created_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Manila'))->format('Y-m-d');
        if(($filters['from']&&$date<$filters['from'])||($filters['to']&&$date>$filters['to']))continue;
        $id=(int)$saved['submission_id'];$image=null;
        foreach($payload['files']??[] as $field=>$file){if(str_starts_with($file['mime']??'','image/')){$image='/admin/submissions.php?'.http_build_query(['media'=>$id,'view'=>'drafts','field'=>$field]);break;}}
        $rows[]=array_merge($saved,[
            $kind.'_id'=>-$id,'_private_draft'=>true,'_draft_id'=>$id,'_draft_categories'=>$categories,
            '_draft_audience'=>['dept_ids'=>$departments,'programs'=>$programs],
            'title'=>$title,'content'=>$content,'status'=>'draft','is_pinned'=>0,'image_path'=>$image,
            'author'=>$post['author']??'','category'=>$post['category']??'Others','category_id'=>$post['category_id']??null,
            'program'=>$post['program']??null,'dept_id'=>$post['dept_id']??null,'published_at'=>null,
        ]);
    }
    return $rows;
}

/** Fetch only the live prefix needed to paginate the combined, ordered list. */
function articleListWithDrafts(PDO $db,array &$filters,string $kind,callable $liveList): array {
    $drafts=privateArticleDraftRows($db,$filters,$kind);
    if(!$drafts)return $liveList($db,$filters);
    $page=$filters['page'];$size=$filters['size'];$liveFilters=$filters;
    $liveFilters['page']=1;$liveFilters['size']=$page*$size;
    $live=$liveList($db,$liveFilters);$rows=array_merge($live['rows'],$drafts);
    $updated=$filters['sort']==='updated';$direction=$filters['sort']==='oldest'?1:-1;
    usort($rows,static function($a,$b)use($updated,$direction,$kind){
        $time=strcmp($updated?($a['updated_at']??$a['created_at']):$a['created_at'],$updated?($b['updated_at']??$b['created_at']):$b['created_at']);
        $tie=((int)!empty($a['_private_draft'])<=>(int)!empty($b['_private_draft']))?:((int)($a['_draft_id']??$a[$kind.'_id'])<=>(int)($b['_draft_id']??$b[$kind.'_id']));
        return $direction*($time?:$tie);
    });
    $total=$live['total']+count($drafts);$pages=max(1,(int)ceil($total/$size));$filters['page']=min($page,$pages);
    $rows=array_slice($rows,($filters['page']-1)*$size,$size);$galleries=$live['galleries'];
    return compact('rows','total','pages','galleries');
}
