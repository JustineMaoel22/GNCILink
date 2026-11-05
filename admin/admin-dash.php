<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/admin-functions.php';
initSession();requireLogin();
header('Cache-Control: no-store, private');
require_once __DIR__.'/../includes/role-dashboard.php';
require_once __DIR__.'/approval-view.php';
require_once __DIR__.'/activity-log-view.php';
$super=rbacIsSuper();$own=rbacRole()==='The Mirror';
$data=roleDashboardData();$stats=$data['stats'];$counts=$data['submission_counts'];
$days=is_string($_GET['days']??null)?(int)$_GET['days']:14;
if(!in_array($days,[7,14,30],true))$days=14;
$analytics=null;$analyticsError=false;
if(hasPermission('view_analytics')){
    try{$analytics=dashboardAnalytics(getDB(),$days);}catch(Throwable $exception){$analyticsError=true;error_log('Dashboard analytics unavailable.');}
}
$tasks=[];
if($super){
    if(($stats['pending_approvals']??0)>0)$tasks[]=['Proposals awaiting approval',$stats['pending_approvals'],'Review content before applying changes.','/admin/submissions.php?status=pending','inboxes'];
    if(($data['page_pending']??0)>0)$tasks[]=['Page text awaiting review',$data['page_pending'],'Review individual page sections in Content Editor.','/admin/content-edit.php?page=review','layout-text-window'];
    foreach($data['content_counts'] as $type=>$content)if($content['pending']>0){
        $routes=['announcement'=>'/admin/announcement.php?status=pending','news'=>'/admin/news.php?status=pending','event'=>'/admin/events.php'];
        $tasks[]=['Pending '.$type.' content',$content['pending'],'Saved content with a pending status; separate from proposals.',$routes[$type],'file-earmark-text'];
    }
}else{
    foreach(['needs_revision'=>['Revision requested','Read the feedback and update your proposal.'],'rejected'=>['Rejected proposals','Read the reason before revising or resubmitting.'],'failed'=>['Approval failed','Review the error and check your proposal.'],'uncertain'=>['Needs verification','Check with your Super Admin before another submission.'],'draft'=>['Saved proposal drafts','Continue your draft and submit it when ready.']] as $status=>[$label,$hint])if(($counts[$status]??0)>0)$tasks[]=[$label,$counts[$status],$hint,'/admin/submissions.php?status='.$status,'file-earmark-text'];
}
$actions=[];
if(hasPermission('create_announcement'))$actions[]=['Create announcement','/admin/announcement.php?modal=create','megaphone'];
if(hasPermission('create_news'))$actions[]=['Write news article','/admin/news.php?modal=create','newspaper'];
if(hasPermission('create_event'))$actions[]=['Plan events','/admin/events.php','calendar-event'];
if(hasPermission('manage_page_content'))$actions[]=['Edit website pages','/admin/content-edit.php','layout-text-window'];
if(hasPermission('manage_approvals'))$actions[]=['Review approvals','/admin/submissions.php?status=pending','inboxes'];
elseif(hasPermission('view_own_submissions'))$actions[]=['My submissions','/admin/submissions.php','send'];
if(hasPermission('manage_users'))$actions[]=['Manage users','/admin/users.php','people'];
$cards=[['Announcements',$stats['total_announcements'],'/admin/announcement.php','megaphone','announcement'],['News articles',$stats['total_news'],'/admin/news.php','newspaper','news']];
if(hasPermission('manage_events'))$cards[]=['Upcoming events',$stats['upcoming_events'],'/admin/events.php','calendar-event','event'];
$cards[]=[$super?'Awaiting approval':'My pending proposals',$stats['pending_approvals'],'/admin/submissions.php?status=pending','hourglass-split','submission'];
$statusLabels=array_merge(approvalStatuses(),['published'=>'Published','draft'=>'Draft','archived'=>'Archived']);
$pageTitle='Dashboard';require __DIR__.'/../components/header-admin.php';
$e=fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$number=fn($value)=>$value===null?'Unavailable':number_format((int)$value);
$dashboardPictures=profilePictureUrls(array_merge(array_column($data['recent'],'author_id'),array_column($data['logs'],'user_id')));
$avatar=static function($id,string $name)use($dashboardPictures,$e): string {
    $picture=$dashboardPictures[(int)$id]??null;
    $initial=mb_strtoupper(mb_substr(trim($name)?:'?',0,1));
    return '<span class="dashboard-user-avatar" aria-hidden="true">'.($picture?'<img src="'.$e($picture).'" alt="" loading="lazy">':$e($initial)).'</span>';
};
?>
<link rel="stylesheet" href="/assets/css/admin-dashboard.css?v=<?= (int)filemtime(__DIR__.'/../assets/css/admin-dashboard.css') ?>">
<div class="dashboard-page">
<div class="page-header dashboard-header"><div><h1>Dashboard</h1><p><span id="dashGreeting"><?= $e(dashGreeting()) ?></span>, <?= $e($currentUser['first_name']??'Admin') ?>. <?= $own?'Here is the latest on your own content.':($super?'Here is your website at a glance.':'Manage content and follow your proposals.') ?></p></div><div class="dashboard-date"><time id="dashDate"><?= $e(dashNow()->format('l, F j, Y')) ?></time><span><i class="bi bi-clock" aria-hidden="true"></i> <span id="dashClockTime"><?= $e(dashNow()->format('g:i A')) ?></span> PHT</span></div></div>
<?php if($data['errors']): ?><div class="dashboard-error" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> Some dashboard information could not be loaded. Unavailable totals are marked below. <a href="/admin/admin-dash.php?days=<?= $days ?>">Retry</a></div><?php endif; ?>
<section aria-label="<?= $own?'Your content summary':'Content overview' ?>" class="dashboard-stats">
<?php foreach($cards as [$label,$value,$route,$icon,$type]): $content=$data['content_counts'][$type]??null; ?>
<a class="data-card dashboard-stat" href="<?= $e($route) ?>"><div class="dashboard-stat-title"><span><?= $e($label) ?></span><i class="bi bi-<?= $e($icon) ?>" aria-hidden="true"></i></div><strong<?= $value===null?' class="dashboard-unavailable"':'' ?>><?= $e($number($value)) ?></strong><span class="dashboard-stat-note"><?php if($type==='event'): ?>Published events yet to start<?php elseif($content): ?><?= number_format($content['published']) ?> published &middot; <?= number_format($content['draft']) ?> <?= $content['draft']===1?'draft':'drafts' ?><?php else: ?><?= $super?'Proposals in the review queue':'Awaiting Super Admin review' ?><?php endif; ?></span><?php if($type==='event'&&$stats['total_events']!==null): ?><span class="dashboard-stat-note"><?= number_format($stats['total_events']) ?> events in total</span><?php endif; ?></a>
<?php endforeach; ?>
</section>
<div class="dashboard-context"><span><i class="bi bi-check-circle" aria-hidden="true"></i> <?= $e($number($stats['published_content'])) ?> published items<?= $own?' in your content':'' ?></span><?php if(hasPermission('manage_users')): ?><a href="/admin/users.php"><i class="bi bi-people" aria-hidden="true"></i> <?= $e($number($stats['total_users'])) ?> admin accounts</a><?php endif; ?><span><?= $e(rbacRole()) ?> workspace</span></div>
<div class="dashboard-work-grid">
<section class="data-card dashboard-attention" aria-labelledby="dashboard-attention-title"><header class="dashboard-card-heading"><div><h2 id="dashboard-attention-title"><?= $super?'Needs your attention':'Your next steps' ?></h2><p><?= $super?'Review outstanding content requests.':'Pick up drafts and respond to review feedback.' ?></p></div><i class="bi bi-inboxes" aria-hidden="true"></i></header>
<?php if($tasks): ?><div class="dashboard-tasks"><?php foreach($tasks as [$label,$count,$hint,$route,$icon]): ?><a class="dashboard-task" href="<?= $e($route) ?>"><span class="dashboard-task-count"><?= number_format($count) ?></span><div><h3><?= $e($label) ?></h3><p><?= $e($hint) ?></p></div><i class="bi bi-arrow-right" aria-hidden="true"></i></a><?php endforeach; ?></div><?php elseif($counts===null||($super&&(isset($data['errors']['content'])||isset($data['errors']['page_review'])))): ?><div class="dashboard-empty"><h3>Review status unavailable</h3><p>Open <?= $super?'Approvals':'My Submissions' ?> to check the queue or try reloading.</p><a href="/admin/submissions.php">Check submissions</a></div><?php else: ?><div class="dashboard-empty dashboard-clear"><i class="bi bi-check2-circle" aria-hidden="true"></i><div><h3><?= $super?'No outstanding review tasks':'No review actions needed' ?></h3><p><?= !$super&&($stats['pending_approvals']??0)>0?'Your pending proposals are waiting for Super Admin review.':'Use a quick action to start your next update.' ?></p></div></div><?php endif; ?>
</section>
<section class="data-card dashboard-quick" aria-labelledby="dashboard-quick-title"><header class="dashboard-card-heading"><div><h2 id="dashboard-quick-title">Quick actions</h2><p>Start common tasks in fewer steps.</p></div></header><div class="dashboard-actions"><?php foreach($actions as [$label,$route,$icon]): ?><a href="<?= $e($route) ?>"><i class="bi bi-<?= $e($icon) ?>" aria-hidden="true"></i><span><?= $e($label) ?></span></a><?php endforeach; ?></div><footer class="dashboard-useful"><a href="/admin/facebook-posts.php">Browse Facebook posts</a><a href="/admin/help.php">Help / User Guide</a></footer></section>
</div>
<div class="dashboard-detail-grid">
<section class="data-card" id="recent-content" aria-labelledby="dashboard-content-title"><header class="dashboard-card-heading"><div><h2 id="dashboard-content-title">Recent content</h2><p><?= $own?'Your latest news and announcements.':'Recent changes across content you can manage.' ?></p></div></header>
<?php if(!$data['recent']): ?><div class="dashboard-empty"><h3><?= isset($data['errors']['content'])?'Content summary unavailable':'No content yet' ?></h3><p><?= isset($data['errors']['content'])?'Open a content module or reload to try again.':'Create an announcement or article using the quick actions.' ?></p></div><?php else: ?><ul class="dashboard-content-list"><?php foreach($data['recent'] as $item): ?><li><div class="dashboard-item-main"><span class="dashboard-type"><?= $e(['announcement'=>'Announcement','news'=>'News article','event'=>'Event'][$item['type']]) ?></span><a href="<?= $e(dashboardContentUrl($item)) ?>"><?= $e($item['title']) ?></a><p><?= $avatar($item['author_id']??null,trim($item['author'])?:'Former account') ?><?= $e(trim($item['author'])?:'Former account') ?> &middot; <time datetime="<?= $e(str_replace(' ','T',$item['updated_at'])) ?>" title="<?= $e($item['updated_at']) ?>"><?= $e(dashTimeAgo($item['updated_at'])) ?></time></p></div><span class="dashboard-status dashboard-status-<?= $e($item['status']) ?>"><?= $e($statusLabels[$item['status']]??ucfirst($item['status'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
</section>
<?php if(hasPermission('view_logs')): ?>
<section class="data-card" aria-labelledby="dashboard-activity-title"><header class="dashboard-card-heading"><div><h2 id="dashboard-activity-title">Recent activity</h2><p>Latest recorded actions in the admin panel.</p></div><a href="/admin/logs.php">View all logs <i class="bi bi-arrow-right" aria-hidden="true"></i></a></header>
<?php if(!$data['logs']): ?><div class="dashboard-empty"><h3><?= isset($data['errors']['logs'])?'Activity unavailable':'No activity recorded' ?></h3><p><?= isset($data['errors']['logs'])?'Open Activity Logs or reload to try again.':'New recorded actions will appear here.' ?></p></div><?php else: ?><ul class="dashboard-activity-list"><?php foreach($data['logs'] as $log): ?><li><span class="dashboard-status dashboard-action-<?= activityLogTone($log['action']) ?>"><?= $e(activityLogLabel($log['action'])) ?></span><div><p class="dashboard-log-description"><?= $e(activityLogDescription($log)) ?></p><span><?= $avatar($log['user_id']??null,activityLogUser($log)) ?><?= $e(activityLogUser($log)) ?> &middot; <?= $e(activityLogModule($log['table_name'])) ?> &middot; <time title="<?= $e($log['created_at']) ?>"><?= $e(dashTimeAgo($log['created_at'])) ?></time></span></div></li><?php endforeach; ?></ul><?php endif; ?>
</section>
<?php else: ?>
<section class="data-card" aria-labelledby="dashboard-submissions-title"><header class="dashboard-card-heading"><div><h2 id="dashboard-submissions-title">My submissions</h2><p>Latest saved proposals and their review status.</p></div><a href="/admin/submissions.php">View all <i class="bi bi-arrow-right" aria-hidden="true"></i></a></header>
<?php if(!$data['submissions']): ?><div class="dashboard-empty"><h3><?= $counts===null?'Submissions unavailable':'No submissions yet' ?></h3><p><?= $counts===null?'Open My Submissions or reload to try again.':'Your drafts and submitted proposals will appear here.' ?></p></div><?php else: ?><ul class="dashboard-content-list"><?php foreach($data['submissions'] as $submission): ?><li><div class="dashboard-item-main"><a href="/admin/submissions.php?id=<?= (int)$submission['submission_id'] ?>"><?= $e($submission['title']) ?></a><p>Revision <?= (int)$submission['revision'] ?> &middot; <time title="<?= $e($submission['updated_at']) ?>"><?= $e(dashTimeAgo($submission['updated_at'])) ?></time></p></div><span class="dashboard-status dashboard-status-<?= $e($submission['status']) ?>"><?= $e($statusLabels[$submission['status']]??ucfirst($submission['status'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
</section>
<?php endif; ?>
</div>
<?php if(hasPermission('view_analytics')): ?>
<section class="data-card dashboard-analytics" id="website-activity" aria-labelledby="dashboard-analytics-title"><header class="dashboard-card-heading"><div><h2 id="dashboard-analytics-title">Website activity</h2><p>Recorded page views and visitors. Page views are not unique people.</p></div><nav class="dashboard-range" aria-label="Visitor trend date range"><?php foreach([7,14,30] as $range): ?><a href="?days=<?= $range ?>#website-activity"<?= $days===$range?' aria-current="page"':'' ?>><?= $range ?> days</a><?php endforeach; ?></nav></header>
<?php if($analyticsError): ?><div class="dashboard-empty"><h3>Analytics are unavailable</h3><p>No visitor totals can be confirmed right now.</p><a href="?days=<?= $days ?>#website-activity">Reload analytics</a></div><?php elseif($analytics): $byDate=$analytics['by_date'];$max=max(1,max($byDate?:[0]));$axis=ceil($max/100)*100;$period=array_sum($byDate); ?>
<div class="dashboard-analytics-stats"><?php foreach([['Page views today',$analytics['today_views'],'Since midnight in the Philippines'],['Total page views',$analytics['total_views'],'All recorded page opens'],['Recorded visitors',$analytics['unique_visitors'],'All time, based on visitor tracking']] as [$label,$value,$hint]): ?><div><span><?= $e($label) ?></span><strong><?= $e($number($value)) ?></strong><small><?= $e($hint) ?></small></div><?php endforeach; ?></div>
<div class="dashboard-analytics-grid"><div class="dashboard-trend"><h3>Page views over <?= $days ?> days <span><?= number_format($period) ?> views</span></h3>
<?php if($period===0): ?><p class="dashboard-chart-empty">No page views recorded in this period.</p><?php endif; ?>
<div class="dashboard-chart" role="group" aria-label="Daily recorded page views"><div class="dashboard-chart-axis" aria-hidden="true"><span><?= number_format($axis) ?></span><span><?= number_format($axis/2) ?></span><span>0</span></div><div class="dashboard-chart-bars">
<?php foreach($byDate as $date=>$views): ?><button type="button" class="dashboard-bar" style="--bar-height:<?= $views===0?0:max(1,round($views/$axis*100,2)) ?>%" aria-label="<?= $e(date('M j, Y',strtotime($date)).': '.$views.' page views') ?>"><span class="dashboard-bar-value"></span><span class="dashboard-bar-tip"><?= $e(date('M j',strtotime($date))) ?>: <?= number_format($views) ?></span></button><?php endforeach; ?>
</div></div><div class="dashboard-chart-dates"><span><?= $e(date('M j',strtotime(array_key_first($byDate)))) ?></span><span><?= $e(date('M j, Y',strtotime(array_key_last($byDate)))) ?></span></div>
<details class="dashboard-daily-totals"><summary>View daily totals</summary><table class="table"><thead><tr><th scope="col">Date</th><th scope="col">Page views</th></tr></thead><tbody><?php foreach($byDate as $date=>$views): ?><tr><th scope="row"><?= $e(date('M j, Y',strtotime($date))) ?></th><td><?= number_format($views) ?></td></tr><?php endforeach; ?></tbody></table></details></div>
<div class="dashboard-popular"><h3>Most visited pages <span>All time</span></h3><?php if(!$analytics['top']): ?><p class="dashboard-note">No page visits recorded yet.</p><?php else: ?><ol><?php foreach($analytics['top'] as $popular): ?><li><div><strong title="<?= $e($popular['page_url']) ?>"><?= $e($popular['page_title']) ?></strong><span>Last visit: <?= $e(gncFormatLastVisited($popular['last_visited'])) ?></span></div><span><?= number_format($popular['visit_count']) ?><small>views</small></span></li><?php endforeach; ?></ol><?php endif; ?></div></div>
<?php endif; ?></section>
<?php endif; ?>
</div>
<script src="/assets/js/admin-dashboard.js?v=<?= (int)filemtime(__DIR__.'/../assets/js/admin-dashboard.js') ?>"></script>
<?php require __DIR__.'/../components/footer-admin.php'; ?>
