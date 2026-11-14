<?php
/** Read-only summaries. The Mirror's content and non-admin proposals are scoped in SQL. */
function roleDashboardData(?PDO $db=null): array {
    $db=$db??getDB();$id=(int)$_SESSION['user_id'];$own=rbacRole()==='The Mirror';$super=rbacIsSuper();
    $stats=['total_announcements'=>null,'total_news'=>null,'total_events'=>null,'upcoming_events'=>null,'published_content'=>null,'pending_approvals'=>null,'total_users'=>null];
    $recent=$submissions=$contentCounts=$errors=$logs=[];$submissionCounts=null;$pagePending=null;
    $types=['announcements'=>['announcement','announcement_id','total_announcements'],'news'=>['news','news_id','total_news']];
    if(hasPermission('manage_events'))$types['events']=['event','event_id','total_events'];
    foreach($types as $table=>[$type,$key,$counter]){
        try{
            $where=$own?' WHERE c.user_id=?':(!$super&&$table!=='events'?" WHERE (c.status<>'draft' OR c.user_id=?)":'');$params=$where!==''?[$id]:[];
            // Event dates are school-local wall times, independent of the DB timezone.
            $upcoming=$table==='events'?",COALESCE(SUM(c.status='published' AND c.start_date>=?),0) AS upcoming":'';
            $countParams=$table==='events'?array_merge([dashNow()->format('Y-m-d H:i:s')],$params):$params;
            $q=$db->prepare("SELECT COUNT(*) AS total,COALESCE(SUM(c.status='published'),0) AS published,COALESCE(SUM(c.status='draft'),0) AS draft,COALESCE(SUM(c.status='pending'),0) AS pending $upcoming FROM `$table` c$where");$q->execute($countParams);
            $counts=array_map('intval',$q->fetch(PDO::FETCH_ASSOC));$stats[$counter]=$counts['total'];$contentCounts[$type]=$counts;
            if($table==='events')$stats['upcoming_events']=$counts['upcoming'];
            $q=$db->prepare("SELECT ? AS type,c.`$key` AS id,u.user_id AS author_id,c.title,c.status,c.created_at,c.updated_at,CONCAT_WS(' ',u.first_name,u.last_name) AS author FROM `$table` c LEFT JOIN users u ON u.user_id=c.user_id$where ORDER BY c.updated_at DESC,c.`$key` DESC LIMIT 6");
            $q->execute(array_merge([$type],$params));$recent=array_merge($recent,$q->fetchAll(PDO::FETCH_ASSOC));
        }catch(PDOException $e){$errors['content']='Some content summaries could not be loaded.';error_log('Dashboard content unavailable: '.$type);}
    }
    if(!$super&&hasPermission('view_own_submissions')){
        try{
            $routes=['/admin/action/create-announcement.php'=>['announcement','total_announcements'],'/admin/action/create-news.php'=>['news','total_news']];
            $q=$db->prepare("SELECT route,COUNT(*) AS total FROM rbac_submissions WHERE user_id=? AND kind='request' AND status='draft' AND route IN (?,?) GROUP BY route");
            $q->execute(array_merge([$id],array_keys($routes)));
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $draft){
                [$type,$counter]=$routes[$draft['route']];$count=(int)$draft['total'];
                if(isset($contentCounts[$type])){$contentCounts[$type]['draft']+=$count;$contentCounts[$type]['total']+=$count;}
                if($stats[$counter]!==null)$stats[$counter]+=$count;
            }
            $q=$db->prepare("SELECT CASE s.route WHEN '/admin/action/create-news.php' THEN 'news' ELSE 'announcement' END AS type,s.submission_id AS id,s.user_id AS author_id,s.title,s.status,s.created_at,s.updated_at,CONCAT_WS(' ',u.first_name,u.last_name) AS author,1 AS _private_draft FROM rbac_submissions s LEFT JOIN users u ON u.user_id=s.user_id WHERE s.user_id=? AND s.kind='request' AND s.status='draft' AND s.route IN (?,?) ORDER BY s.updated_at DESC,s.submission_id DESC LIMIT 6");
            $q->execute(array_merge([$id],array_keys($routes)));$recent=array_merge($recent,$q->fetchAll(PDO::FETCH_ASSOC));
        }catch(PDOException $e){
            $errors['drafts']='Your draft summaries could not be loaded.';error_log('Dashboard private drafts unavailable.');
            foreach(['announcement'=>'total_announcements','news'=>'total_news'] as $type=>$counter){$stats[$counter]=null;if(isset($contentCounts[$type]))$contentCounts[$type]['draft']=null;}
        }
    }
    if(hasPermission('manage_page_content')||hasPermission('edit_page_content')){
        try{require_once __DIR__.'/website-publications.php';$recent=array_merge($recent,dashboardPublishedPages($db));}
        catch(PDOException $e){$errors['content']='Some content summaries could not be loaded.';error_log('Dashboard page publications unavailable.');}
    }
    if(count($contentCounts)===count($types))$stats['published_content']=array_sum(array_column($contentCounts,'published'));
    try{
        $scope=" WHERE status<>'draft'".($super?'':' AND user_id=?');$params=$super?[]:[$id];
        $q=$db->prepare('SELECT CASE status WHEN \'pending_approval\' THEN \'pending\' ELSE status END AS queue_status,COUNT(*) AS total FROM rbac_submissions'.$scope.' GROUP BY queue_status');$q->execute($params);$submissionCounts=array_map('intval',$q->fetchAll(PDO::FETCH_KEY_PAIR));
        $stats['pending_approvals']=$submissionCounts['pending']??0;
        $q=$db->prepare('SELECT submission_id,title,CASE status WHEN \'pending_approval\' THEN \'pending\' ELSE status END AS status,revision,created_at,updated_at FROM rbac_submissions'.$scope.' ORDER BY updated_at DESC,submission_id DESC LIMIT 6');$q->execute($params);$submissions=$q->fetchAll(PDO::FETCH_ASSOC);
    }catch(PDOException $e){$errors['submissions']='Submission status is temporarily unavailable.';error_log('Dashboard submissions unavailable.');}
    if($super){
        try{$pagePending=(int)$db->query("SELECT COUNT(*) FROM page_sections WHERE review_status='pending'")->fetchColumn();if($stats['pending_approvals']!==null)$stats['pending_approvals']+=$pagePending;}
        catch(PDOException $e){if(!in_array($e->getCode(),['42S02','42S22'],true))$errors['page_review']='Page-text review status is unavailable.';}
    }
    if(hasPermission('manage_users')){
        try{$stats['total_users']=(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();}
        catch(PDOException $e){$errors['users']='Account counts could not be loaded.';}
    }
    if(hasPermission('view_logs')){
        try{$logs=$db->query('SELECT l.*,u.first_name,u.last_name FROM activity_logs l LEFT JOIN users u ON u.user_id=l.user_id ORDER BY l.created_at DESC,l.log_id DESC LIMIT 6')->fetchAll(PDO::FETCH_ASSOC);}
        catch(PDOException $e){$errors['logs']='Recent activity could not be loaded.';}
    }
    usort($recent,static fn($a,$b)=>strcmp($b['updated_at'],$a['updated_at'])?:($b['id']<=>$a['id']));
    return ['stats'=>$stats,'recent'=>array_slice($recent,0,6),'submissions'=>$submissions,'submission_counts'=>$submissionCounts,'content_counts'=>$contentCounts,'page_pending'=>$pagePending,'logs'=>$logs,'errors'=>$errors];
}

/** Authors' private drafts and waiting proposals are separate dashboard entries. */
function dashboardAuthorNextSteps(array $data): array {
    $tasks=[];
    foreach(['announcement'=>['Announcement drafts','/admin/announcement.php?status=draft'],'news'=>['News drafts','/admin/news.php?status=draft']] as $type=>[$label,$route]){
        $count=$data['content_counts'][$type]['draft']??0;
        if($count>0)$tasks[]=[$label,$count,'Continue editing, or submit when ready.',$route,'file-earmark-text'];
    }
    $pending=$data['stats']['pending_approvals']??0;
    if($pending>0)$tasks[]=['Awaiting Super Admin review',$pending,'Your submitted proposals are waiting for review.','/admin/submissions.php?status=pending','hourglass-split'];
    return $tasks;
}

/** Keep analytics terminology aligned with recorded views and visitor identifiers. */
function dashboardAnalytics(PDO $db,int $days): array {
    require_once __DIR__.'/../components/page-analytics.php';
    $days=in_array($days,[7,14,30],true)?$days:14;
    // Existing helpers return empty results on errors; check storage first so a
    // missing table is shown as unavailable rather than a fabricated zero.
    $db->query('SELECT page_url,page_title,visit_count,last_visited FROM page_visits LIMIT 0');
    $db->query('SELECT page_id,visit_date,views FROM page_visits_daily LIMIT 0');
    $totals=gncPageTotals($db);$byDate=gncViewsByDate($db,$days);$visitors=$todayVisitors=null;
    try{
        $q=$db->prepare('SELECT COUNT(DISTINCT visitor_hash) AS total,COUNT(DISTINCT CASE WHEN visit_date=? THEN visitor_hash END) AS today FROM site_visitors');
        $q->execute([gncVisitorToday()]);$visitorCounts=$q->fetch(PDO::FETCH_ASSOC);
        $visitors=(int)$visitorCounts['total'];$todayVisitors=(int)$visitorCounts['today'];
    }catch(PDOException $e){}
    return ['total_views'=>(int)array_sum(array_column($totals,'visit_count')),'unique_visitors'=>$visitors,'today_visitors'=>$todayVisitors,'today_views'=>(int)($byDate[gncVisitorToday()]??0),'most_visited'=>$totals[0]??null,'top'=>array_slice($totals,0,5),'by_date'=>$byDate];
}

function dashboardContentUrl(array $item): string {
    if(!empty($item['_private_draft']))return '/admin/'.($item['type']==='news'?'news':'announcement').'.php?'.http_build_query(['revise'=>(int)$item['id']]);
    if($item['type']==='page')return '/admin/content-edit.php?'.http_build_query(['page'=>$item['page'],'sub'=>$item['sub']??'']);
    if($item['type']==='event')return '/admin/events.php';
    $page=$item['type']==='news'?'news':'announcement';
    return '/admin/'.$page.'.php?'.http_build_query(['modal'=>'edit','id'=>(int)$item['id']]);
}

function dashNow(): DateTimeImmutable {return new DateTimeImmutable('now',new DateTimeZone('Asia/Manila'));}
function dashGreeting(): string {
    $hour=(int)dashNow()->format('G');return $hour>=5&&$hour<12?'Good morning':($hour>=12&&$hour<18?'Good afternoon':'Good evening');
}
function dashTimeAgo(?string $value,?int $now=null): string {
    $stamp=$value?strtotime($value):false;if(!$stamp)return 'Date unavailable';
    $elapsed=($now??time())-$stamp;
    if($elapsed<0)return date('M j, Y g:i A',$stamp);
    if($elapsed<60)return 'Just now';
    if($elapsed<3600){$count=(int)floor($elapsed/60);return $count.' min ago';}
    if($elapsed<86400){$count=(int)floor($elapsed/3600);return $count.' '.($count===1?'hour':'hours').' ago';}
    if($elapsed<172800)return 'Yesterday';
    return date('M j, Y',$stamp);
}
