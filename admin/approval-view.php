<?php
/** Presentation helpers. The calling page enforces identity and ownership. */
function approvalStatuses(): array {
    return ['pending'=>'Awaiting approval','needs_revision'=>'Needs revision','approved'=>'Approved','rejected'=>'Rejected','draft'=>'Draft','processing'=>'Under review','failed'=>'Approval failed','uncertain'=>'Needs verification'];
}
function approvalTypes(): array {
    return ['news'=>'News','announcements'=>'Announcements','events'=>'Events','pages'=>'Page content','settings'=>'Website settings'];
}
function approvalTypeSql(): string {
    return "CASE s.route WHEN '/admin/action/create-news.php' THEN 'news' WHEN '/admin/action/create-announcement.php' THEN 'announcements' WHEN '/admin/events.php' THEN 'events' WHEN '/admin/settings.php' THEN 'settings' ELSE 'pages' END";
}
function approvalFilters(array $input): array {
    $string=static fn($key)=>is_string($input[$key]??null)?trim($input[$key]):'';
    $f=['q'=>mb_substr($string('q'),0,200),'status'=>'','type'=>'','author'=>'','from'=>'','to'=>'','sort'=>'priority','page'=>1,'size'=>25];
    if(isset(approvalStatuses()[$string('status')]))$f['status']=$string('status');
    if(isset(approvalTypes()[$string('type')]))$f['type']=$string('type');
    if(preg_match('/^[1-9]\d{0,9}$/',$string('author')))$f['author']=$string('author');
    foreach(['from','to'] as $key){
        $value=$string($key);
        if(!preg_match('/^[1-9]\d{3}-\d{2}-\d{2}$/',$value))continue;
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if($date&&$date->format('Y-m-d')===$value)$f[$key]=$value;
    }
    if($f['from']&&$f['to']&&$f['from']>$f['to'])[$f['from'],$f['to']]=[$f['to'],$f['from']];
    if(in_array($string('sort'),['priority','newest','oldest'],true))$f['sort']=$string('sort');
    if(in_array((int)$string('size'),[10,25,50,100],true))$f['size']=(int)$string('size');
    $f['page']=max(1,min(2147483647,(int)$string('page')));
    return $f;
}
function approvalQueue(PDO $db,array &$filters,bool $super,int $userId): array {
    $scope=$super?'1=1':'s.user_id=?';$scopeParams=$super?[]:[$userId];
    $from=' FROM rbac_submissions s LEFT JOIN users u ON u.user_id=s.user_id';
    $q=$db->prepare('SELECT s.status,COUNT(*) AS total FROM rbac_submissions s WHERE '.$scope.' GROUP BY s.status');$q->execute($scopeParams);$counts=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    $q=$db->prepare("SELECT DISTINCT s.user_id,u.first_name,u.last_name $from WHERE $scope ORDER BY u.first_name,u.last_name,s.user_id");$q->execute($scopeParams);$authors=$q->fetchAll(PDO::FETCH_ASSOC);
    $where=[$scope];$params=$scopeParams;$type=approvalTypeSql();
    if($filters['q']!==''){
        $term='%'.strtr($filters['q'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
        $matches=array_keys(array_filter(approvalTypes(),fn($label)=>mb_stripos($label,$filters['q'])!==false));
        $extra=$matches?' OR ('.$type.') IN ('.implode(',',array_fill(0,count($matches),'?')).')':'';
        $where[]="(s.title LIKE ? ESCAPE '!' OR CONCAT_WS(' ',u.first_name,u.last_name) LIKE ? ESCAPE '!' OR ($type) LIKE ? ESCAPE '!'$extra)";
        array_push($params,$term,$term,$term);
        $params=array_merge($params,$matches);
    }
    foreach(['status'=>'s.status','type'=>"($type)",'author'=>'s.user_id'] as $key=>$field)if($filters[$key]!==''){$where[]="$field=?";$params[]=$filters[$key];}
    if($filters['from']){$where[]='s.created_at>=?';$params[]=$filters['from'].' 00:00:00';}
    if($filters['to']){$where[]='s.created_at<=?';$params[]=$filters['to'].' 23:59:59';}
    $where=' WHERE '.implode(' AND ',$where);
    $q=$db->prepare('SELECT COUNT(*)'.$from.$where);$q->execute($params);$total=(int)$q->fetchColumn();
    $pages=max(1,(int)ceil($total/$filters['size']));$filters['page']=min($filters['page'],$pages);
    $order=['priority'=>"CASE WHEN s.status='pending' THEN 0 WHEN s.status IN ('uncertain','failed') THEN 1 ELSE 2 END,s.created_at ASC,s.submission_id ASC",'newest'=>'s.created_at DESC,s.submission_id DESC','oldest'=>'s.created_at ASC,s.submission_id ASC'][$filters['sort']];
    $q=$db->prepare("SELECT s.*,u.first_name,u.last_name,($type) AS content_type $from $where ORDER BY $order LIMIT ? OFFSET ?");
    foreach($params as $i=>$param)$q->bindValue($i+1,$param,PDO::PARAM_STR);
    $q->bindValue(count($params)+1,$filters['size'],PDO::PARAM_INT);$q->bindValue(count($params)+2,($filters['page']-1)*$filters['size'],PDO::PARAM_INT);$q->execute();$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    return compact('counts','authors','total','pages','rows');
}
function approvalAuthor(array $row): string {
    return trim(($row['first_name']??'').' '.($row['last_name']??''))?:'Former account';
}
function approvalDate(?string $date): string {
    if(!$date)return 'Not recorded';
    $stamp=DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$date);
    return $stamp?$stamp->format('M j, Y · g:i A'):$date;
}
function approvalFields(array $post): array {
    $skip=['action','ajax_action','csrf_token','status','allow_incomplete','facebook_confirmed','facebook_request_key','image_framing','image_framing_json','publish_destination','import_token','category_ids_present'];
    $out=[];
    foreach($post as $key=>$value){
        if($key==='category_ids'&&is_array($value)){
            $ids=array_values(array_filter($value,static fn($id)=>is_scalar($id)&&preg_match('/^[1-9][0-9]{0,9}$/D',(string)$id)));
            $names=[];
            if($ids){$q=getDB()->prepare('SELECT category_name FROM categories WHERE category_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY category_name');$q->execute($ids);$names=$q->fetchAll(PDO::FETCH_COLUMN);}
            $out['categories']=implode(', ',$names);continue;
        }
        if(str_starts_with($key,'_')||str_ends_with($key,'_id')||in_array($key,$skip,true)||preg_match('/password|token|secret|credential|api.?key|authorization/i',$key))continue;
        if(is_array($value)){$items=[];array_walk_recursive($value,static function($v)use(&$items){if(is_scalar($v))$items[]=(string)$v;});$value=implode(', ',$items);}
        if(!is_scalar($value)&&$value!==null)continue;
        $out[$key]=trim(html_entity_decode(strip_tags(preg_replace('/<\/(?:p|div|li|h[1-6])>|<br\s*\/?\s*>/i',"\n",(string)$value)),ENT_QUOTES|ENT_HTML5,'UTF-8'));
    }
    return $out;
}
/** Compare with current stored content, never imply an unavailable historical baseline. */
function approvalCurrent(PDO $db,array $row): ?array {
    $targets=['/admin/action/create-news.php'=>['news','news_id'],'/admin/action/create-announcement.php'=>['announcements','announcement_id'],'/admin/events.php'=>['events','event_id']];
    if(empty($row['target_id'])||!isset($targets[$row['route']]))return null;
    [$table,$key]=$targets[$row['route']];
    $q=$db->prepare("SELECT * FROM `$table` WHERE `$key`=?");$q->execute([(int)$row['target_id']]);$current=$q->fetch(PDO::FETCH_ASSOC)?:null;
    if($current&&$table==='announcements'){require_once __DIR__.'/../includes/announcement-categories.php';$current['category_ids']=attachAnnouncementCategories($db,[$current])[0]['category_ids'];}
    return $current;
}
