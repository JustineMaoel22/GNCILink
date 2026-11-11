<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/admin-functions.php';
initSession();
requireLogin();
requirePermission('view_logs');
require_once __DIR__.'/activity-log-view.php';
header('Cache-Control: no-store, max-age=0');
$filters = activityLogFilters(array_diff_key($_GET, ['module' => true]));
$logs = $actions = $users = $modules = [];
$total = 0; $pages = 1; $error = '';
try {
    $result = activityLogResults(getDB(), $filters);
    extract($result, EXTR_OVERWRITE);
} catch (Throwable $exception) {
    error_log('Activity log view unavailable.');
    $error = 'Activity logs could not be loaded. Please try again. If this continues, contact your system administrator.';
}
$e = fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$url = fn($changes = []) => '/admin/logs.php?'.http_build_query(array_merge($filters, $changes));
$active = count(array_filter(array_intersect_key($filters, array_flip(['q','action','user','from','to'])), fn($v) => $v !== ''));
$pageTitle = 'Activity Logs';
require __DIR__.'/../components/header-admin.php';
?>
<link rel="stylesheet" href="/assets/css/admin-logs.css?v=<?= (int)filemtime(__DIR__.'/../assets/css/admin-logs.css') ?>">
<div class="logs-page">
<div class="page-header"><div><h1>Activity Logs</h1><p>See who did what, and when. Search and review recorded activity across the admin panel.</p></div><span class="logs-readonly"><i class="bi bi-shield-check" aria-hidden="true"></i> Read-only history</span></div>
<nav class="logs-tabs" aria-label="Log source">
<?php foreach(['activity'=>'Activity','security'=>'Login & security'] as $source=>$label): ?>
<a href="<?= $e($url(['source'=>$source,'page'=>1,'action'=>'','module'=>''])) ?>"<?= $filters['source']===$source?' aria-current="page"':'' ?>><?= $e($label) ?></a>
<?php endforeach; ?>
</nav>
<form class="data-card logs-filters" method="get" action="/admin/logs.php" id="logs-filters" data-auto-filters>
<input type="hidden" name="source" value="<?= $e($filters['source']) ?>">
<div class="logs-search-row">
<label class="logs-search" for="log-search">Search activity<input class="form-control" type="search" name="q" id="log-search" value="<?= $e($filters['q']) ?>" maxlength="200" placeholder="Search name, email, action or description"></label>
<label for="log-action">Action<select class="form-select" name="action" id="log-action"><option value="">All actions</option><?php foreach($actions as $action): ?><option value="<?= $e($action) ?>"<?= $filters['action']===$action?' selected':'' ?>><?= $e(activityLogLabel($action)) ?></option><?php endforeach; ?></select></label>
<noscript><button class="btn btn-gnc" type="submit">Apply filters</button></noscript>

</div>
<details class="logs-more"<?= array_filter(array_intersect_key($filters,array_flip(['user','from','to'])),fn($v)=>$v!=='')?' open':'' ?>>
<summary>More filters <span class="logs-muted">User and date range</span></summary>
<div class="logs-filter-grid">
<label for="log-user">User<select class="form-select" name="user" id="log-user"><option value="">All users</option><?php foreach($users as $user): ?><option value="<?= (int)$user['user_id'] ?>"<?= $filters['user']===(string)$user['user_id']?' selected':'' ?>><?= $e(activityLogUser($user)) ?></option><?php endforeach; ?></select></label>
<label for="log-from">From date<input class="form-control" type="date" id="log-from" name="from" value="<?= $e($filters['from']) ?>"></label>
<label for="log-to">To date<input class="form-control" type="date" id="log-to" name="to" value="<?= $e($filters['to']) ?>"></label>
</div></details>
<div class="logs-preferences">
<label for="log-size">Rows per page <select class="form-select" id="log-size" name="size"><?php foreach([10,25,50,100] as $size): ?><option<?= $filters['size']===$size?' selected':'' ?>><?= $size ?></option><?php endforeach; ?></select></label>
<label for="log-sort">Order <select class="form-select" id="log-sort" name="sort"><option value="newest"<?= $filters['sort']==='newest'?' selected':'' ?>>Newest first</option><option value="oldest"<?= $filters['sort']==='oldest'?' selected':'' ?>>Oldest first</option></select></label>
<span class="logs-muted">Dates and times use the server's recorded time.</span>
</div></form>
<?php if($error): ?>
<div class="alert alert-danger" role="alert"><?= $e($error) ?> <a href="<?= $e($url()) ?>">Retry</a></div>
<?php else: ?>
<div class="logs-result-heading"><h2><?= $filters['source']==='security'?'Login & security':'Recorded activity' ?></h2><p role="status"><?= number_format($total) ?> matching <?= $total===1?'record':'records' ?><?= $active?' · '.$active.' active '.($active===1?'filter':'filters'):'' ?></p></div>
<?php if(!$logs): ?>
<div class="data-card logs-empty"><i class="bi bi-search" aria-hidden="true"></i><h3><?= $active?'No activities match your filters':'No activity recorded yet' ?></h3><p><?= $active?'Try a different search, widen the date range or clear the filters.':'Recorded events will appear here when they are available.' ?></p><?php if($active): ?><a href="<?= $e('/admin/logs.php?source='.$filters['source']) ?>">Clear filters</a><?php endif; ?></div>
<?php else: ?>
<div class="data-card logs-table-wrap">
<table class="table logs-table"><caption class="visually-hidden">Activity history. Use View details to inspect an entry.</caption><thead><tr><th scope="col">Date & time</th><th scope="col">User</th><th scope="col">Action</th><th scope="col">Details</th><th scope="col"><span class="visually-hidden">Review</span></th></tr></thead><tbody>
<?php foreach($logs as $index=>$log): $stamp = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $log['created_at']); ?>
<tr data-log-row="<?= $index ?>">
<td data-label="Date & time"><time datetime="<?= $e(str_replace(' ','T',$log['created_at'])) ?>" title="<?= $e($log['created_at']) ?>"><?= $e($stamp?$stamp->format('M j, Y'):$log['created_at']) ?><span class="logs-time"><?= $e($stamp?$stamp->format('g:i:s A'):'') ?></span></time></td>
<td data-label="User"><span class="logs-user"><?= $e(activityLogUser($log)) ?></span></td>
<td data-label="Action"><span class="logs-badge logs-badge-<?= activityLogTone($log['action']) ?>"><?= $e(activityLogLabel($log['action'])) ?></span></td>
<td data-label="Details"><span class="logs-module"><?= $e(activityLogModule($log['table_name'])) ?></span><span class="logs-description"><?= $e(activityLogDescription($log)) ?></span></td>
<td class="logs-review"><a class="logs-detail-link" href="<?= $e($url(['detail'=>(int)$log['log_id']])) ?>#log-details" data-log-open="<?= $index ?>" aria-label="<?= $e('View details: '.activityLogLabel($log['action']).' by '.activityLogUser($log)) ?>">View details <span aria-hidden="true">→</span></a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<nav class="logs-pagination" aria-label="Activity log pagination"><p>Showing <?= number_format(($filters['page']-1)*$filters['size']+1) ?>–<?= number_format(min($total,$filters['page']*$filters['size'])) ?> of <?= number_format($total) ?></p><div>
<?php if($filters['page']>1): ?><a class="btn btn-outline-secondary" href="<?= $e($url(['page'=>1])) ?>">First</a><a class="btn btn-outline-secondary" href="<?= $e($url(['page'=>$filters['page']-1])) ?>" rel="prev">Previous</a><?php endif; ?>
<span>Page <?= number_format($filters['page']) ?> of <?= number_format($pages) ?></span>
<?php if($filters['page']<$pages): ?><a class="btn btn-outline-secondary" href="<?= $e($url(['page'=>$filters['page']+1])) ?>" rel="next">Next</a><a class="btn btn-outline-secondary" href="<?= $e($url(['page'=>$pages])) ?>">Last</a><?php endif; ?>
</div></nav>
<?php endif; endif; ?>
<p id="logs-loading" role="status" hidden>Loading activity logs…</p>
<?php
$detailId = is_string($_GET['detail']??null) ? filter_var($_GET['detail'],FILTER_VALIDATE_INT) : null;
$selectedLog = null;
foreach($logs as $log) if((int)$log['log_id']===$detailId) $selectedLog=$log;
?>
<section class="data-card logs-fallback" id="log-details"<?= $selectedLog?'':' hidden' ?>>
<?php if($selectedLog): ?><h2>Activity details</h2><p><?= $e(activityLogUser($selectedLog).' · '.activityLogLabel($selectedLog['action']).' · '.$selectedLog['created_at']) ?></p><p><?= $e(activityLogModule($selectedLog['table_name'])) ?></p><p><?= $e(activityLogDescription($selectedLog)) ?></p><a href="<?= $e($url()) ?>">Back to results</a><?php endif; ?>
</section>
<dialog id="activity-dialog" aria-labelledby="activity-dialog-title">
<div class="logs-dialog-heading"><div><p class="logs-muted">Recorded activity</p><h2 id="activity-dialog-title">Activity details</h2></div><button type="button" class="btn btn-outline-secondary" id="logs-close" aria-label="Close activity details"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
<dl class="logs-detail-fields"><dt>User</dt><dd id="detail-user"></dd><dt>Action</dt><dd id="detail-action"></dd><dt>Module</dt><dd id="detail-module"></dd><dt>Exact date & time</dt><dd id="detail-time"></dd><dt>Description</dt><dd id="detail-description"></dd></dl>
</dialog></div>
<script type="application/json" id="activity-log-data"><?= json_encode(array_map('activityLogPublicRecord',$logs),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<script src="/assets/js/admin-logs.js?v=<?= (int)filemtime(__DIR__.'/../assets/js/admin-logs.js') ?>"></script>
<?php require __DIR__.'/../components/footer-admin.php';?>
