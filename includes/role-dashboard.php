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
            $where=$own?' WHERE c.user_id=?':'';$params=$own?[$id]:[];
            $upcoming=$table==='events'?",COALESCE(SUM(c.status='published' AND c.start_date>=NOW()),0) AS upcoming":'';
            $q=$db->prepare("SELECT COUNT(*) AS total,COALESCE(SUM(c.status='published'),0) AS published,COALESCE(SUM(c.status='draft'),0) AS draft,COALESCE(SUM(c.status='pending'),0) AS pending $upcoming FROM `$table` c$where");$q->execute($params);
            $counts=array_map('intval',$q->fetch(PDO::FETCH_ASSOC));$stats[$counter]=$counts['total'];$contentCounts[$type]=$counts;
            if($table==='events')$stats['upcoming_events']=$counts['upcoming'];
            $q=$db->prepare("SELECT ? AS type,c.`$key` AS id,u.user_id AS author_id,c.title,c.status,c.created_at,c.updated_at,CONCAT_WS(' ',u.first_name,u.last_name) AS author FROM `$table` c LEFT JOIN users u ON u.user_id=c.user_id$where ORDER BY c.updated_at DESC,c.`$key` DESC LIMIT 6");
            $q->execute(array_merge([$type],$params));$recent=array_merge($recent,$q->fetchAll(PDO::FETCH_ASSOC));
        }catch(PDOException $e){$errors['content']='Some content summaries could not be loaded.';error_log('Dashboard content unavailable: '.$type);}
    }
    if(count($contentCounts)===count($types))$stats['published_content']=array_sum(array_column($contentCounts,'published'));
    try{
        $scope=$super?'':' WHERE user_id=?';$params=$super?[]:[$id];
        $q=$db->prepare('SELECT status,COUNT(*) AS total FROM rbac_submissions'.$scope.' GROUP BY status');$q->execute($params);$submissionCounts=array_map('intval',$q->fetchAll(PDO::FETCH_KEY_PAIR));
        $stats['pending_approvals']=$submissionCounts['pending']??0;
        $q=$db->prepare('SELECT submission_id,title,status,revision,created_at,updated_at FROM rbac_submissions'.$scope.' ORDER BY updated_at DESC,submission_id DESC LIMIT 6');$q->execute($params);$submissions=$q->fetchAll(PDO::FETCH_ASSOC);
    }catch(PDOException $e){$errors['submissions']='Submission status is temporarily unavailable.';error_log('Dashboard submissions unavailable.');}
    if($super){
        try{$pagePending=(int)$db->query("SELECT COUNT(*) FROM page_sections WHERE review_status='pending'")->fetchColumn();}
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

/** Keep analytics terminology aligned with recorded views and visitor identifiers. */
function dashboardAnalytics(PDO $db,int $days): array {
    require_once __DIR__.'/../components/page-analytics.php';
    $days=in_array($days,[7,14,30],true)?$days:14;
    // Existing helpers return empty results on errors; check storage first so a
    // missing table is shown as unavailable rather than a fabricated zero.
    $db->query('SELECT page_url,page_title,visit_count,last_visited FROM page_visits LIMIT 0');
    $db->query('SELECT page_id,visit_date,views FROM page_visits_daily LIMIT 0');
    $totals=gncPageTotals($db);$byDate=gncViewsByDate($db,$days);$visitors=null;
    try{$visitors=(int)$db->query('SELECT COUNT(DISTINCT visitor_hash) FROM site_visitors')->fetchColumn();}catch(PDOException $e){}
    return ['total_views'=>(int)array_sum(array_column($totals,'visit_count')),'unique_visitors'=>$visitors,'today_views'=>(int)($byDate[gncVisitorToday()]??0),'most_visited'=>$totals[0]??null,'top'=>array_slice($totals,0,5),'by_date'=>$byDate];
}

function dashboardContentUrl(array $item): string {
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
