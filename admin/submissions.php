<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/admin-functions.php';
require_once __DIR__.'/../includes/content-submissions.php';
initSession();requireLogin();
header('Cache-Control: no-store, private');
require_once __DIR__.'/approval-view.php';
$error='';$success=$_SESSION['flash_success']??'';unset($_SESSION['flash_success']);
$ready=rbacSubmissionsReady();$super=rbacIsSuper();$userId=(int)$_SESSION['user_id'];
if(isset($_GET['media'])){
    $row=$ready&&is_string($_GET['media'])?rbacGetSubmission((int)$_GET['media']):null;
    if(!$row||(!$super&&(int)$row['user_id']!==$userId))rbacFail('Submission not found.',404);
    $files=rbacReadPayload((int)$row['submission_id'])['files']??[];$field=$_GET['field']??'';$file=is_string($field)?($files[$field]??null):null;
    if(!$file)rbacFail('Media not found.',404);
    header('Content-Type: '.$file['mime']);header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: default-src 'none'");header('Cache-Control: private, no-store');echo base64_decode($file['data'],true);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!is_string($_POST['csrf_token']??null)||!verifyCSRFToken($_POST['csrf_token']))throw new RuntimeException('Invalid request token. Reload and try again.');
        if(!$ready)throw new RuntimeException('Approval storage is not set up.');
        $sectionAction=($_POST['record_source']??'')==='section';
        if($sectionAction){
            $id=is_string($_POST['submission_id']??null)?(int)$_POST['submission_id']:0;
            $action=$_POST['action']??'';
            $version=$_POST['section_version']??null;$reason=$_POST['reason']??'';
            if(!is_string($version)||!is_string($action)||!is_string($reason))throw new InvalidArgumentException('Invalid page-text review.');
            approvalSectionDecision(getDB(),$id,$version,$action,$reason);
            $success=$action==='approve'?'Page text approved and published.':'Changes requested. The published page is unchanged.';
        }else{
        $id=is_string($_POST['submission_id']??null)?(int)$_POST['submission_id']:0;$row=rbacGetSubmission($id);
        if(!$row||(!$super&&(int)$row['user_id']!==$userId))throw new RuntimeException('Submission not found.');
        $revision=is_string($_POST['revision']??null)?(int)$_POST['revision']:0;$action=$_POST['action']??'';
        if($action==='approve')$success=rbacApproveRequest($id,$revision);
        elseif(in_array($action,['reject','request_revision'],true)){
            if(!is_string($_POST['reason']??null))throw new InvalidArgumentException('Provide feedback for the author.');
            rbacReviewDecision($id,$revision,$_POST['reason'],$action==='reject'?'rejected':'needs_revision');
            $success=$action==='reject'?'Submission rejected. Your feedback is available to the author.':'Revision requested. The author can review your feedback and resubmit.';
        }
        elseif($action==='delete'&&((int)$row['user_id']===$userId||$super)){
            rbacDeleteSubmission($id,$revision);
            $success='Submission deleted. Published content is unchanged.';
        }
        elseif($action==='submit'&&(int)$row['user_id']===$userId){
            rbacSubmitSaved($id,$revision);$success='Submitted for Super Admin approval.';
        }else throw new RuntimeException('This action is not available to your role.');
        }
        $_SESSION['flash_success']=$success;
        $return=[];foreach(['q','status','type','author','from','to','sort','page','size'] as $key)$return[$key]=$_POST['return_'.$key]??'';
        $return=approvalFilters($return);if($action!=='delete'){$return['id']=$id;if($sectionAction)$return['source']='section';}
        header('Location: /admin/submissions.php?'.http_build_query($return));exit;
    }catch(Throwable $e){$error=$e instanceof PDOException?'The review could not be saved. Please try again.':$e->getMessage();}
}

$filters=approvalFilters($_GET);
$counts=$authors=$rows=[];$total=0;$pages=1;$selectedRow=null;$payload=[];$queueError='';$reviewError='';
$selected=is_string($_GET['id']??null)?max(0,(int)$_GET['id']):0;
$selectedSource=($_GET['source']??'')==='section'?'section':'proposal';
if($_SERVER['REQUEST_METHOD']==='POST'&&$error!==''){
    $return=[];foreach(['q','status','type','author','from','to','sort','page','size'] as $key)$return[$key]=$_POST['return_'.$key]??'';
    $filters=approvalFilters($return);$selected=$id??0;$selectedSource=($_POST['record_source']??'')==='section'?'section':'proposal';
}
if($ready){
    try{
        $queue=approvalQueue(getDB(),$filters,$super,$userId);extract($queue,EXTR_OVERWRITE);
    }catch(Throwable $e){$queueError='The review queue could not be loaded. Please try again.';error_log('Approval queue unavailable.');}
    if($selected){
        try{
            if($selectedSource==='section'){
                $selectedRow=approvalSection(getDB(),$selected,$super,$userId);
                if(!$selectedRow){http_response_code(404);$reviewError='These page-text changes are unavailable or you do not have access to them.';}
            }else{
            $q=getDB()->prepare('SELECT s.*,u.first_name,u.last_name FROM rbac_submissions s LEFT JOIN users u ON u.user_id=s.user_id WHERE s.submission_id=?'.($super?'':' AND s.user_id=?'));
            $q->execute($super?[$selected]:[$selected,$userId]);$selectedRow=$q->fetch(PDO::FETCH_ASSOC)?:null;
            if(!$selectedRow){http_response_code(404);$reviewError='This submission is unavailable or you do not have access to it.';}
            else $payload=rbacReadPayload($selected);
            }
        }catch(Throwable $e){$selectedRow=null;$reviewError='The proposal could not be loaded. Please try again.';error_log('Approval proposal unavailable.');}
    }
}
$pageTitle=$super?'Approvals':'My Submissions';require __DIR__.'/../components/header-admin.php';
$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$reviewUrl=fn($changes=[])=>'/admin/submissions.php?'.http_build_query(array_merge($filters,$changes));
$statuses=approvalStatuses();$types=approvalTypes();
$active=count(array_filter(array_intersect_key($filters,array_flip(['q','status','type','author','from','to'])),fn($v)=>$v!==''));
?>
<link rel="stylesheet" href="/assets/css/admin-approvals.css?v=<?= (int)filemtime(__DIR__.'/../assets/css/admin-approvals.css') ?>">
<div class="approval-page">
<div class="page-header"><div><h1><?= $e($pageTitle) ?></h1><p><?= $super?'Review page text and content proposals together, give feedback and approve changes when they are ready.':'Follow your proposals, review feedback and send revisions for approval.' ?></p></div><span class="approval-header-note"><i class="bi bi-shield-check" aria-hidden="true"></i> Published content stays unchanged until approval</span></div>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= $e($error) ?></div><?php endif; ?>
<?php if($success): ?><div class="alert alert-success" role="status"><?= $e($success) ?></div><?php endif; ?>
<?php if(!$ready): ?><div class="alert alert-warning" role="alert">Approval storage is unavailable. Contact your system administrator. No changes can be approved here until it is restored.</div><?php endif; ?>
<?php if($queueError): ?><div class="alert alert-danger" role="alert"><?= $e($queueError) ?> <a href="<?= $e($reviewUrl()) ?>">Retry</a></div><?php endif; ?>
<nav class="approval-overview" aria-label="Filter submissions by status">
<?php foreach(['pending'=>'Awaiting approval','approved'=>'Approved','needs_revision'=>'Needs revision'] as $state=>$label): ?>
<a class="data-card approval-stat approval-stat-<?= $e($state) ?><?= $filters['status']===$state?' active':'' ?>" href="<?= $e($reviewUrl(['status'=>$state,'page'=>1])) ?>"<?= $filters['status']===$state?' aria-current="page"':'' ?>><span><?= $e($label) ?></span><strong><?= $queueError||!$ready?'—':number_format((int)($counts[$state]??0)) ?></strong></a>
<?php endforeach; ?>
</nav>
<form class="data-card approval-toolbar" method="get" action="/admin/submissions.php" id="approval-filters">
<div class="approval-filter-main"><label for="approval-search">Search submissions<input class="form-control" id="approval-search" name="q" type="search" maxlength="200" value="<?= $e($filters['q']) ?>" placeholder="Search title, author or content type"></label><label for="approval-status">Status<select class="form-select" id="approval-status" name="status"><option value="">All statuses</option><?php foreach($statuses as $key=>$label): ?><option value="<?= $e($key) ?>"<?= $filters['status']===$key?' selected':'' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label><button class="btn btn-gnc" type="submit">Apply filters</button><a class="btn btn-outline-secondary" href="/admin/submissions.php">Clear filters</a></div>
<details class="approval-more"<?= $filters['type']||$filters['author']||$filters['from']||$filters['to']?' open':'' ?>><summary>More filters <span>Content type, author and submission date</span></summary><div class="approval-filter-grid">
<label for="approval-type">Content type<select class="form-select" name="type" id="approval-type"><option value="">All content</option><?php foreach($types as $key=>$label): ?><option value="<?= $key ?>"<?= $filters['type']===$key?' selected':'' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
<label for="approval-author">Submitted by<select class="form-select" name="author" id="approval-author"><option value="">All authors</option><?php foreach($authors as $author): ?><option value="<?= (int)$author['user_id'] ?>"<?= $filters['author']===(string)$author['user_id']?' selected':'' ?>><?= $e(approvalAuthor($author)) ?></option><?php endforeach; ?></select></label>
<label for="approval-from">From date<input class="form-control" type="date" id="approval-from" name="from" value="<?= $e($filters['from']) ?>"></label><label for="approval-to">To date<input class="form-control" type="date" id="approval-to" name="to" value="<?= $e($filters['to']) ?>"></label>
</div></details>
<div class="approval-preferences"><label for="approval-sort">Sort by<select class="form-select" id="approval-sort" name="sort"><?php foreach(['priority'=>'Pending first · oldest first','newest'=>'Newest first','oldest'=>'Oldest first'] as $key=>$label): ?><option value="<?= $key ?>"<?= $filters['sort']===$key?' selected':'' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label><label for="approval-size">Rows per page<select class="form-select" id="approval-size" name="size"><?php foreach([10,25,50,100] as $size): ?><option<?= $filters['size']===$size?' selected':'' ?>><?= $size ?></option><?php endforeach; ?></select></label></div>
</form>
<div class="approval-results"><h2>Review queue</h2><p role="status"><?= number_format($total) ?> matching <?= $total===1?'submission':'submissions' ?><?= $active?' · '.$active.' active '.($active===1?'filter':'filters'):'' ?></p></div>
<?php if($ready&&!$queueError&&!$rows): ?><div class="data-card approval-empty"><i class="bi bi-inbox" aria-hidden="true"></i><h3><?= $active?'No matching submissions':'Your queue is clear' ?></h3><p><?= $active?'Try another search or widen your filters.':'New proposals will appear here when they are submitted.' ?></p><?php if($active): ?><a href="/admin/submissions.php">Clear filters</a><?php endif; ?></div><?php endif; ?>
<?php if($rows): ?>
<div class="data-card approval-list"><table class="table approval-table"><caption class="visually-hidden">Submitted proposals. Open Review submission to inspect content and take action.</caption><thead><tr><th scope="col">Content</th><th scope="col">Submitted by</th><th scope="col">Submitted</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Review</span></th></tr></thead><tbody>
<?php foreach($rows as $item): ?>
<tr<?= $item['status']==='pending'?' class="approval-row-pending"':'' ?>><td data-label="Content"><span class="approval-type"><?= $e($types[$item['content_type']]) ?></span><strong class="approval-row-title"><?= $e($item['title']) ?></strong><?php if((int)$item['revision']>1): ?><span class="approval-row-revision">Revision <?= (int)$item['revision'] ?></span><?php endif; ?></td><td data-label="Submitted by"><?= $e(approvalAuthor($item)) ?></td><td data-label="Submitted"><time title="<?= $e($item['created_at']) ?>"><?= $e(approvalDate($item['created_at'])) ?></time></td><td data-label="Status"><span class="approval-status approval-status-<?= $e($item['status']) ?>"><?= $e($statuses[$item['status']]??$item['status']) ?></span></td><td class="approval-row-action"><a class="btn btn-outline-secondary" data-review-open href="<?= $e($reviewUrl(['id'=>(int)$item['submission_id'],'source'=>$item['record_source']==='section'?'section':null])) ?>#submission-review" aria-label="<?= $e('Review submission: '.$item['title']) ?>">Review submission <i class="bi bi-arrow-right" aria-hidden="true"></i></a></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<nav class="approval-pagination" aria-label="Submission pagination"><p>Showing <?= number_format(($filters['page']-1)*$filters['size']+1) ?>–<?= number_format(min($total,$filters['page']*$filters['size'])) ?> of <?= number_format($total) ?></p><div><?php if($filters['page']>1): ?><a class="btn btn-outline-secondary" href="<?= $e($reviewUrl(['page'=>1])) ?>">First</a><a class="btn btn-outline-secondary" rel="prev" href="<?= $e($reviewUrl(['page'=>$filters['page']-1])) ?>">Previous</a><?php endif; ?><span>Page <?= number_format($filters['page']) ?> of <?= number_format($pages) ?></span><?php if($filters['page']<$pages): ?><a class="btn btn-outline-secondary" rel="next" href="<?= $e($reviewUrl(['page'=>$filters['page']+1])) ?>">Next</a><a class="btn btn-outline-secondary" href="<?= $e($reviewUrl(['page'=>$pages])) ?>">Last</a><?php endif; ?></div></nav>
<?php endif; ?>
<p id="approval-loading" role="status" hidden>Loading submission…</p><p id="approval-load-error" class="alert alert-danger" role="alert" hidden>Could not open the review. <a href="<?= $e($reviewUrl()) ?>" id="approval-retry">Open submission page</a></p>
<?php if($reviewError): ?><p class="alert alert-danger" role="alert"><?= $e($reviewError) ?></p><?php endif; ?>
<?php if($selectedRow): $row=$selectedRow;try{include __DIR__.'/partials/'.($selectedSource==='section'?'section-review.php':'submission-review.php');}catch(Throwable $e){error_log('Approval preview unavailable.');?><p class="alert alert-danger" role="alert">Some proposal details could not be loaded. Reload before taking any action.</p><?php } endif; ?>
<dialog id="approval-dialog" aria-labelledby="review-title"><div id="approval-dialog-content"></div></dialog>
</div>
<script src="/assets/js/admin-approvals.js?v=<?= (int)filemtime(__DIR__.'/../assets/js/admin-approvals.js') ?>"></script>
<?php require __DIR__.'/../components/footer-admin.php'; ?>
