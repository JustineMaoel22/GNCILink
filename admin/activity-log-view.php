<?php
/** Read-only queries and presentation, used after the view_logs permission check. */
function activityLogLabel(string $action): string {
    $labels = ['LOGIN'=>'Login','LOGOUT'=>'Logout','FACEBOOK_PUBLISH'=>'Publish to Facebook','IMPORT_PREVIEW'=>'Preview calendar import','IMPORT_CANCEL'=>'Cancel calendar import','SAVE_DRAFT'=>'Save draft','RESUBMIT'=>'Resubmit for approval','ASSIGN_ROLE'=>'Change user role','PASSWORD_CHANGED'=>'Password changed','PASSWORD_CHANGE_FAILED'=>'Password change failed','APPROVAL_ERROR'=>'Approval failed'];
    return $labels[$action] ?? ucfirst(strtolower(str_replace('_',' ',$action)));
}
function activityLogTone(string $action): string {
    if (preg_match('/FAIL|ERROR|REJECT|DELETE/', $action)) return 'danger';
    if (strpos($action,'PUBLISH')!==false) return 'purple';
    if (preg_match('/CREATE|APPROVE/', $action)) return 'success';
    if (preg_match('/UPDATE|EDIT|ASSIGN/', $action)) return 'blue';
    return 'neutral';
}
function activityLogModule(?string $module): string {
    if($module==='website_pages')return 'Website pages';
    $labels = [''=>'General','authentication'=>'Login & security','announcements'=>'Announcements','news'=>'News','events'=>'Events','users'=>'Users','page_sections'=>'Content Editor','rbac_submissions'=>'Content approvals','calendar_import_series'=>'Calendar imports','fb_posts_cache'=>'Facebook posts','admin_sections'=>'Administration sections','admin_people'=>'Administration people','admin_offices'=>'Administration offices'];
    return $labels[$module??''] ?? ucfirst(str_replace('_',' ',(string)$module));
}
function activityLogUser(array $row): string {
    $name = trim(($row['first_name']??'').' '.($row['last_name']??''));
    return $name !== '' ? $name : (empty($row['user_id']) ? 'Unidentified account' : 'Former user #'.(int)$row['user_id']);
}
function activityLogDescription(array $row): string {
    $description = trim((string)($row['description']??''));
    // Suppress a potentially secret-bearing legacy message rather than guessing
    // credential boundaries. The original record remains unchanged in storage.
    $secrets = '/(?:password|passwd|pwd|token|secret|credential|api[ _-]?key|authorization|cookie|session[ _-]?id)\s*["\x27]?\s*[:=]|\bBearer\s+\S+|-----BEGIN [\w ]*PRIVATE KEY|https?:\/\/[^\s\/]+:[^\s\/]+@|[?&](?:code|key|access_token|token)=/i';
    if (preg_match($secrets,$description) || preg_match('/\b(?:temporary|initial|new|current|old) password\s+(?:is\s+)?\S+/i',$description)) return 'Description hidden because it may contain sensitive account information.';
    if(($row['table_name']??'')==='website_pages'){
        $publication=json_decode($description,true);
        if(is_array($publication)&&is_string($publication['title']??null))return 'Published website page: '.$publication['title'];
    }
    return $description !== '' ? $description : activityLogLabel($row['action']).' · '.activityLogModule($row['table_name']);
}
function activityLogFilters(array $input): array {
    $value = static fn($key) => is_string($input[$key]??null) ? trim($input[$key]) : '';
    $filters = ['source'=>$value('source')==='security'?'security':'activity','q'=>mb_substr($value('q'),0,200),'action'=>mb_substr($value('action'),0,255),'user'=>'','module'=>mb_substr($value('module'),0,100),'from'=>'','to'=>'','size'=>25,'sort'=>$value('sort')==='oldest'?'oldest':'newest','page'=>1];
    if (preg_match('/^\d{1,10}$/',$value('user'))) $filters['user']=(string)(int)$value('user');
    foreach(['from','to'] as $key) {
        if (!preg_match('/^[1-9]\d{3}-\d{2}-\d{2}$/',$value($key))) continue;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d',$value($key));
        if ($date && $date->format('Y-m-d')===$value($key)) $filters[$key]=$value($key);
    }
    if ($filters['from'] && $filters['to'] && $filters['from']>$filters['to']) [$filters['from'],$filters['to']]=[$filters['to'],$filters['from']];
    if (in_array((int)$value('size'),[10,25,50,100],true)) $filters['size']=(int)$value('size');
    $filters['page']=max(1,min(2147483647,(int)$value('page')));
    return $filters;
}
function activityLogResults(PDO $db, array &$filters): array {
    $security = $filters['source']==='security';
    $table = $security ? 'auth_security_logs' : 'activity_logs';
    $module = $security ? "'authentication'" : "COALESCE(l.table_name,'')";
    $from = " FROM $table l LEFT JOIN users u ON u.user_id=l.user_id";
    $actions = $db->query("SELECT DISTINCT action FROM $table ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
    $modules = $db->query("SELECT DISTINCT $module AS module FROM $table l ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
    $users = $db->query("SELECT DISTINCT COALESCE(l.user_id,0) AS user_id,u.first_name,u.last_name $from ORDER BY u.first_name,u.last_name,user_id")->fetchAll(PDO::FETCH_ASSOC);
    $where = []; $params = [];
    if ($filters['q']!=='') {
        $term = '%'.strtr($filters['q'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
        $parts = [];
        foreach(["CONCAT_WS(' ',u.first_name,u.last_name)",'u.email','l.description',"REPLACE(l.action,'_',' ')",'l.action'] as $field) {
            $parts[]="$field LIKE ? ESCAPE '!'"; $params[]=$term;
        }
        $matchingActions = array_values(array_filter($actions,fn($action)=>mb_stripos(activityLogLabel($action),$filters['q'])!==false));
        if ($matchingActions) { $parts[]='l.action IN ('.implode(',',array_fill(0,count($matchingActions),'?')).')'; $params=array_merge($params,$matchingActions); }
        $where[]='('.implode(' OR ',$parts).')';
    }
    foreach(['action'=>'l.action','user'=>'COALESCE(l.user_id,0)','module'=>$module] as $key=>$field) if ($filters[$key]!=='') { $where[]="$field = ?"; $params[]=($key==='module' && $filters[$key]==='__general')?'':$filters[$key]; }
    if ($filters['from']) { $where[]='l.created_at >= ?'; $params[]=$filters['from'].' 00:00:00'; }
    if ($filters['to']) { $where[]='l.created_at <= ?'; $params[]=$filters['to'].' 23:59:59'; }
    $where = $where ? ' WHERE '.implode(' AND ',$where) : '';
    $statement = $db->prepare('SELECT COUNT(*)'.$from.$where);
    $statement->execute($params);
    $total=(int)$statement->fetchColumn();
    $pages=max(1,(int)ceil($total/$filters['size']));
    $filters['page']=min($filters['page'],$pages);
    $direction=$filters['sort']==='oldest'?'ASC':'DESC';
    $columns=$security?"'authentication' AS table_name,NULL AS record_id,l.ip_address":"l.table_name,l.record_id,NULL AS ip_address";
    $statement=$db->prepare("SELECT l.log_id,l.user_id,l.action,l.description,l.created_at,$columns,u.first_name,u.last_name".$from.$where." ORDER BY l.created_at $direction,l.log_id $direction LIMIT ? OFFSET ?");
    foreach($params as $index=>$param) $statement->bindValue($index+1,$param,PDO::PARAM_STR);
    $statement->bindValue(count($params)+1,$filters['size'],PDO::PARAM_INT);
    $statement->bindValue(count($params)+2,($filters['page']-1)*$filters['size'],PDO::PARAM_INT);
    $statement->execute();
    $logs=$statement->fetchAll(PDO::FETCH_ASSOC);
    return compact('logs','actions','users','modules','total','pages');
}
function activityLogPublicRecord(array $row): array {
    return ['user'=>activityLogUser($row),'action'=>activityLogLabel($row['action']),'module'=>activityLogModule($row['table_name']),'time'=>$row['created_at'],'description'=>activityLogDescription($row)];
}
