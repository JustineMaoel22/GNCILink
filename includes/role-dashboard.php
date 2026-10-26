<?php
/** Read-only dashboard data. Mirror content and all proposal counts are scoped server-side. */
function roleDashboardData(): array {
    $db = getDB(); $id = (int)$_SESSION['user_id']; $own = rbacRole() === 'The Mirror';
    $stats = ['total_announcements'=>0,'total_news'=>0,'total_events'=>0,'published_content'=>0,'pending_approvals'=>0];
    $recent = []; $submissions = [];
    $types = ['announcements'=>['announcement','announcement_id','total_announcements'], 'news'=>['news','news_id','total_news']];
    if (hasPermission('manage_events')) $types['events'] = ['event','event_id','total_events'];
    foreach ($types as $table => [$type,$key,$counter]) {
        try {
            $where = $own ? ' WHERE c.user_id=?' : '';
            $q = $db->prepare("SELECT COUNT(*) AS total,COALESCE(SUM(c.status='published'),0) AS published FROM `$table` c" . $where);
            $q->execute($own ? [$id] : []); $counts = $q->fetch();
            $stats[$counter] = (int)$counts['total']; $stats['published_content'] += (int)$counts['published'];
            $q = $db->prepare("SELECT ? AS type,c.`$key` AS id,c.title,c.status,c.created_at,CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS author FROM `$table` c LEFT JOIN users u ON u.user_id=c.user_id" . $where . ' ORDER BY c.created_at DESC LIMIT 8');
            $q->execute($own ? [$type,$id] : [$type]); $recent = array_merge($recent,$q->fetchAll());
        } catch (PDOException $e) { error_log('Role dashboard content unavailable: '.$type); }
    }
    try {
        $q = $db->prepare("SELECT COUNT(*) FROM rbac_submissions WHERE user_id=? AND status='pending'");$q->execute([$id]);$stats['pending_approvals'] = (int)$q->fetchColumn();
        $q = $db->prepare('SELECT submission_id,title,status,updated_at AS created_at FROM rbac_submissions WHERE user_id=? ORDER BY updated_at DESC,submission_id DESC LIMIT 8');$q->execute([$id]);$submissions = $q->fetchAll();
    } catch (PDOException $e) { error_log('Role dashboard submissions unavailable.'); }
    usort($recent,static fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));
    return ['stats'=>$stats,'recent'=>array_slice($recent,0,8),'submissions'=>$submissions];
}
