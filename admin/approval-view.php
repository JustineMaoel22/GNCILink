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
/** Normalize existing stores for one queue; no records or IDs are migrated. */
function approvalSectionEligibilitySql(): string {
    // Discarding a rejected draft also resets review_status to 'none'; require
    // an actual approval audit so discarded work is never labelled approved.
    return "(ps.review_status IN ('pending','rejected') OR (ps.submitted_at IS NOT NULL AND ps.review_status='none' AND ps.reviewed_at IS NOT NULL AND ps.draft_content IS NULL AND (SELECT l.action FROM activity_logs l WHERE l.table_name='page_sections' AND l.record_id=ps.section_id AND l.created_at>=ps.submitted_at AND l.action IN ('APPROVE','REJECT','REQUEST_REVISION') ORDER BY l.log_id DESC LIMIT 1)='APPROVE'))";
}
function approvalQueueSource(PDO $db): string {
    $base = "SELECT submission_id,user_id,CONVERT(kind USING utf8mb4) COLLATE utf8mb4_unicode_ci AS kind,CONVERT(route USING utf8mb4) COLLATE utf8mb4_unicode_ci AS route,target_id,CONVERT(title USING utf8mb4) COLLATE utf8mb4_unicode_ci AS title,CONVERT(CASE status WHEN 'pending_approval' THEN 'pending' ELSE status END USING utf8mb4) COLLATE utf8mb4_unicode_ci AS status,revision,CONVERT(reason USING utf8mb4) COLLATE utf8mb4_unicode_ci AS reason,reviewed_by,reviewed_at,created_at,updated_at,'proposal' AS record_source FROM rbac_submissions";
    try {
        $db->query('SELECT ps.section_id,ps.title,ps.submitted_by,ps.submitted_at,ps.review_status,ps.reviewed_by,ps.reviewed_at,ps.review_note,ps.updated_at,ps.draft_content,p.title FROM page_sections ps JOIN pages p ON p.page_id=ps.page_id LIMIT 0');
    } catch (PDOException $e) {
        if (in_array($e->getCode(), ['42S02','42S22'], true)) return $base;
        throw $e;
    }
    return $base." UNION ALL SELECT ps.section_id,ps.submitted_by,'section','/admin/action/page-review-handler.php',ps.section_id,CONCAT(CONVERT(p.title USING utf8mb4) COLLATE utf8mb4_unicode_ci,' — ',CONVERT(COALESCE(ps.title,ps.section_key) USING utf8mb4) COLLATE utf8mb4_unicode_ci),CASE ps.review_status WHEN 'pending' THEN 'pending' WHEN 'rejected' THEN 'needs_revision' ELSE 'approved' END,1,CONVERT(ps.review_note USING utf8mb4) COLLATE utf8mb4_unicode_ci,ps.reviewed_by,ps.reviewed_at,COALESCE(ps.submitted_at,ps.updated_at),ps.updated_at,'section' FROM page_sections ps JOIN pages p ON p.page_id=ps.page_id WHERE ".approvalSectionEligibilitySql();
}

function approvalSection(PDO $db, int $id, bool $super, int $userId, bool $lock = false): ?array {
    $q=$db->prepare('SELECT ps.*,p.slug AS page_slug,p.title AS page_title,u.first_name,u.last_name FROM page_sections ps JOIN pages p ON p.page_id=ps.page_id LEFT JOIN users u ON u.user_id=ps.submitted_by WHERE ps.section_id=? AND '.approvalSectionEligibilitySql().($super?'':' AND ps.submitted_by=?').($lock?' FOR UPDATE':''));
    $q->execute($super?[$id]:[$id,$userId]);
    return $q->fetch(PDO::FETCH_ASSOC)?:null;
}
function approvalSectionVersion(array $row): string {
    return hash('sha256',json_encode(array_intersect_key($row,array_flip(['section_id','content','draft_content','review_status','submitted_by','submitted_at','updated_at','reviewed_at'])),JSON_THROW_ON_ERROR));
}
function approvalSectionDecision(PDO $db,int $id,string $version,string $action,string $reason): void {
    if(!rbacIsSuper()||!hasPermission('approve_content'))throw new RuntimeException('Only Super Admin can review these changes.');
    if(!in_array($action,['approve','request_revision'],true))throw new InvalidArgumentException('Choose a valid page-text review action.');
    if($action==='request_revision'&&(trim($reason)===''||mb_strlen($reason)>500))throw new InvalidArgumentException('Provide feedback of 1–500 characters.');
    $db->beginTransaction();
    try {
        $row=approvalSection($db,$id,true,(int)$_SESSION['user_id'],true);
        if(!$row||$row['review_status']!=='pending'||$row['draft_content']===null||!hash_equals(approvalSectionVersion($row),$version))throw new RuntimeException('These changes were updated or already reviewed. Reload before reviewing.');
        if($action==='approve'&&!publishSection($row['page_slug'],$row['section_key']))throw new RuntimeException('Could not publish the section. Please try again.');
        $q=$db->prepare('UPDATE page_sections SET review_status=?,reviewed_by=?,reviewed_at=NOW(),review_note=? WHERE section_id=?');
        $q->execute([$action==='approve'?'none':'rejected',(int)$_SESSION['user_id'],$action==='approve'?null:trim($reason),$id]);
        if(!logActivity((int)$_SESSION['user_id'],$action==='approve'?'APPROVE':'REQUEST_REVISION','page_sections',$id,($action==='approve'?'Approved and published ':'Requested changes on ').$row['page_slug'].'/'.$row['section_key']))throw new RuntimeException('Could not record the review. Please try again.');
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function approvalFilters(array $input): array {
    $string=static fn($key)=>is_string($input[$key]??null)?trim($input[$key]):'';
    $f=['q'=>mb_substr($string('q'),0,200),'status'=>'','type'=>'','author'=>'','from'=>'','to'=>'','sort'=>'newest','page'=>1,'size'=>25,'view'=>$string('view')==='drafts'?'drafts':'submissions'];
    if($string('status')==='pending_approval')$f['status']='pending';
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
    $drafts=($filters['view']??'submissions')==='drafts';
    $scope=$drafts?"s.status='draft' AND s.user_id=?":("s.status<>'draft'".($super?'':' AND s.user_id=?'));
    $scopeParams=$drafts||!$super?[$userId]:[];
    $source=approvalQueueSource($db);
    $from=' FROM ('.$source.') s LEFT JOIN users u ON u.user_id=s.user_id';
    $q=$db->prepare('SELECT s.status,COUNT(*) AS total FROM ('.$source.') s WHERE '.$scope.' GROUP BY s.status');$q->execute($scopeParams);$counts=$q->fetchAll(PDO::FETCH_KEY_PAIR);
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
    $order=['priority'=>"CASE WHEN s.status='pending' THEN 0 WHEN s.status IN ('uncertain','failed') THEN 1 ELSE 2 END,s.created_at ASC,s.submission_id ASC,s.record_source ASC",'newest'=>'s.created_at DESC,s.submission_id DESC,s.record_source ASC','oldest'=>'s.created_at ASC,s.submission_id ASC,s.record_source ASC'][$filters['sort']];
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
