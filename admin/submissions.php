<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/admin-functions.php';
require_once __DIR__.'/../includes/content-submissions.php';
initSession();requireLogin();
$error='';$success=$_SESSION['flash_success']??'';unset($_SESSION['flash_success']);
$ready=rbacSubmissionsReady();$super=rbacIsSuper();$userId=(int)$_SESSION['user_id'];
if(isset($_GET['media'])){
    $row=$ready?rbacGetSubmission((int)$_GET['media']):null;
    if(!$row||(!$super&&(int)$row['user_id']!==$userId))rbacFail('Submission not found.',404);
    $files=rbacReadPayload((int)$row['submission_id'])['files']??[];$field=$_GET['field']??'';$file=is_string($field)?($files[$field]??null):null;
    if(!$file)rbacFail('Media not found.',404);
    header('Content-Type: '.$file['mime']);header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: default-src 'none'");header('Cache-Control: private, no-store');echo base64_decode($file['data'],true);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!verifyCSRFToken($_POST['csrf_token']??''))throw new RuntimeException('Invalid request token. Reload and try again.');
        if(!$ready)throw new RuntimeException('Approval storage is not set up.');
        $id=(int)($_POST['submission_id']??0);$row=rbacGetSubmission($id);
        if(!$row||(!$super&&(int)$row['user_id']!==$userId))throw new RuntimeException('Submission not found.');
        $revision=(int)($_POST['revision']??0);$action=$_POST['action']??'';
        if($action==='approve')$success=rbacApproveRequest($id,$revision);
        elseif($action==='reject'){rbacRejectSubmission($id,$revision,(string)($_POST['reason']??''));$success='Submission rejected. The author can see your reason and revise it.';}
        elseif($action==='submit'&&(int)$row['user_id']===$userId){
            $q=getDB()->prepare("UPDATE rbac_submissions SET status='pending',reason=NULL,reviewed_by=NULL,reviewed_at=NULL,updated_at=NOW() WHERE submission_id=? AND revision=? AND status IN ('draft','rejected','failed')");$q->execute([$id,$revision]);if($q->rowCount()!==1)throw new RuntimeException('This submission changed. Reload before submitting.');
            logActivity($userId,'RESUBMIT','rbac_submissions',$id,'Sent saved proposal for approval.');$success='Submitted for Super Admin approval.';
        }else throw new RuntimeException('This action is not available to your role.');
        $_SESSION['flash_success']=$success;header('Location: /admin/submissions.php?id='.$id);exit;
    }catch(Throwable $e){$error=$e instanceof PDOException?'The review could not be saved. Please try again.':$e->getMessage();}
}
$selected=(int)($_GET['id']??0);$rows=[];
if($ready){$sql='SELECT s.*,u.first_name,u.last_name FROM rbac_submissions s LEFT JOIN users u ON u.user_id=s.user_id WHERE 1=1';$params=[];if(!$super){$sql.=' AND s.user_id=?';$params[]=$userId;}if($selected){$sql.=' AND s.submission_id=?';$params[]=$selected;}$sql.=' ORDER BY s.updated_at DESC,s.submission_id DESC LIMIT 100';$q=getDB()->prepare($sql);$q->execute($params);$rows=$q->fetchAll();}
$pageTitle=$super?'Approvals':'My Submissions';require __DIR__.'/../components/header-admin.php';
$e=fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
$counts=array_count_values(array_column($rows,'status'));
?>
<link rel="stylesheet" href="/assets/css/admin-approvals.css?v=<?= (int)filemtime(__DIR__.'/../assets/css/admin-approvals.css') ?>">
<div class="approval-page">
<div class="page-header"><div><h1><?= $e($pageTitle) ?></h1><p><?= $super?'Review proposed content before it reaches the public website.':'Track drafts, approvals and rejection reasons. Published content stays unchanged until Super Admin approves.' ?></p></div></div>
<?php if($error):?><div class="alert alert-danger" role="alert"><?= $e($error) ?></div><?php endif;?>
<?php if($success):?><div class="alert alert-success" role="status"><?= $e($success) ?></div><?php endif;?>
<?php if(!$ready):?><div class="alert alert-warning">Approval storage needs developer setup: import <code>sql/rbac-migration.sql</code>. No proposed changes are applied while storage is unavailable.</div><?php endif;?>
<?php if($selected):?><p><a href="/admin/submissions.php">View all submissions</a></p><?php endif;?>
<?php if(!$rows):?><div class="data-card approval-empty"><i class="bi bi-inbox" aria-hidden="true"></i><h2>No submissions yet</h2><p><?= $super?'Content submitted for review will appear here.':'Your saved drafts and submissions will appear here.' ?></p></div><?php endif;?>
<?php if($rows):?>
<div class="approval-overview" aria-label="<?= $selected?'Selected submission summary':'Recent submissions summary' ?>">
 <?php foreach(['pending'=>'Awaiting approval','approved'=>'Approved','rejected'=>'Needs revision'] as $state=>$label):?><div class="data-card approval-stat approval-stat-<?= $e($state) ?>"><span><?= $e($label) ?></span><strong><?= (int)($counts[$state]??0) ?></strong></div><?php endforeach;?>
</div>
<div class="data-card approval-toolbar">
 <div><h2><?= $selected?'Submission details':'Review queue' ?></h2><p><?= $selected?'Review the content and choose an action.':'Your most recent 100 submissions, updated first.' ?></p></div>
 <div class="approval-filters"><label class="approval-search"><span class="visually-hidden">Search submissions</span><i class="bi bi-search" aria-hidden="true"></i><input type="search" id="approval-search" class="form-control" placeholder="Search title, author or type…"></label><label><span class="visually-hidden">Filter by status</span><select id="approval-status" class="form-select"><option value="">All statuses</option><?php foreach(['pending','approved','rejected','draft','failed','processing','uncertain'] as $state):?><option value="<?= $state ?>"><?= ucfirst($state) ?></option><?php endforeach;?></select></label></div>
</div>
<p class="approval-results text-muted small" id="approval-results" role="status"><?= count($rows) ?> submission<?= count($rows)===1?'':'s' ?> shown</p>
<?php endif;?>
<?php foreach($rows as $row):$id=(int)$row['submission_id'];$payload=rbacReadPayload($id);$typeLabel=rbacSubmissionLabel($row,$payload);?>
<section class="data-card approval-card mb-4" id="submission-<?= $id ?>" data-approval-status="<?= $e($row['status']) ?>" data-approval-search="<?= $e($row['title'].' '.$row['first_name'].' '.$row['last_name'].' '.$typeLabel) ?>">
 <div class="approval-card-heading">
 <div class="approval-heading-main"><span class="approval-type"><?= $e($typeLabel) ?></span><h2 class="data-card-title"><?= $e($row['title']) ?></h2></div>
 <span class="approval-status approval-status-<?= $e($row['status']) ?>"><span aria-hidden="true"></span><?= $e(ucfirst($row['status'])) ?></span>
 </div>
 <div class="approval-meta"><span><i class="bi bi-person" aria-hidden="true"></i> <?= $e($row['first_name'].' '.$row['last_name']) ?></span><span><i class="bi bi-clock" aria-hidden="true"></i> <?= $e($row['updated_at']) ?></span><span>#<?= $id ?> &middot; Revision <?= (int)$row['revision'] ?></span></div>
 <?php if($row['reason']):?><div class="alert alert-<?= $row['status']==='rejected'?'danger':'warning' ?>"><?= nl2br($e($row['reason'])) ?></div><?php endif;?>
 <?php if($row['status']==='pending'&&!$super):?><p class="text-muted">Waiting for Super Admin approval. Your submission is saved and will appear on the website after approval.</p><?php endif;?>
 <?php if($row['kind']==='snapshot'):
    $url='/admin/content-edit.php?'.http_build_query(['page'=>$payload['page'],'review_submission'=>$id,'route'=>$payload['route']]);?>
 <a class="btn btn-outline-secondary mb-3" href="<?= $e($url) ?>"><?= $super?'Review proposed page changes':'Open saved page changes' ?></a>
 <?php if($super&&$row['status']==='pending'):?><p class="small text-muted">Review the saved canvas, then use Approve and publish inside the Content Editor. Nothing is published by opening it.</p><?php endif;?>
 <?php else:?>
 <details class="approval-preview"<?= $selected?' open':'' ?>><summary><span><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Proposed content</span><span class="approval-preview-hint">View details</span></summary><dl class="approval-fields">
 <?php foreach($payload['post']??[] as $field=>$value):
    // Transport fields and crop metadata belong to the saved request, not its content preview.
    if(str_starts_with($field,'_')||str_ends_with($field,'_id')||in_array($field,['action','ajax_action','csrf_token','status','allow_incomplete','facebook_confirmed','facebook_request_key','image_framing','image_framing_json','publish_destination','import_token'],true))continue;
    if(is_array($value)){
        $items=[];array_walk_recursive($value,static function($item)use(&$items){if(is_scalar($item))$items[]=(string)$item;});
        $value=implode(', ',$items);
    }
    $preview=trim(strip_tags((string)$value));if($preview==='')continue;
 ?><dt><?= $e(ucwords(str_replace('_',' ',$field))) ?></dt><dd style="white-space:pre-wrap;overflow-wrap:anywhere"><?= $e(mb_substr($preview,0,12000)) ?></dd><?php endforeach;?>
 </dl>
 <?php if(($row['route']??'')==='/admin/events.php'&&($payload['post']['ajax_action']??'')==='import_confirm'):
    include __DIR__.'/partials/calendar-import-review.php';
 endif;?>
 <?php foreach($payload['files']??[] as $field=>$file):$media='/admin/submissions.php?'.http_build_query(['media'=>$id,'field'=>$field]);?>
 <div class="approval-attachment"><?php if(str_starts_with($file['mime'],'image/')):?><img src="<?= $e($media) ?>" alt="Submitted image" loading="lazy"><?php else:?><i class="bi bi-paperclip" aria-hidden="true"></i><?php endif;?><a href="<?= $e($media) ?>" target="_blank" rel="noopener"><?= $e($file['name']) ?><span class="visually-hidden"> (opens in a new tab)</span></a></div>
 <?php endforeach;?></details>
 <?php endif;?>
 <div class="approval-actions">
 <?php if($super&&$row['status']==='pending'&&$row['kind']==='request'):?>
 <form method="post" data-review-confirm="Approve and apply these changes to the website?"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="submission_id" value="<?= $id ?>"><input type="hidden" name="revision" value="<?= (int)$row['revision'] ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-gnc-gold">Approve and apply</button></form>
 <?php endif;?>
 <?php if((int)$row['user_id']===$userId&&in_array($row['status'],['draft','rejected','failed'],true)):?>
 <?php if($row['kind']==='request'&&in_array($row['route'],['/admin/action/create-news.php','/admin/action/create-announcement.php'],true)):$kind=str_contains($row['route'],'create-news')?'news':'announcement';$post=$payload['post'];$url='/admin/'.$kind.'.php?'.http_build_query(['modal'=>($post['action']??'')==='update'?'edit':'create','id'=>$row['target_id'],'revise'=>$id]);?><a class="btn btn-outline-secondary" href="<?= $e($url) ?>">Revise submission</a><?php endif;?>
 <form method="post" data-review-confirm="Submit this saved proposal for Super Admin approval?"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="submission_id" value="<?= $id ?>"><input type="hidden" name="revision" value="<?= (int)$row['revision'] ?>"><input type="hidden" name="action" value="submit"><button class="btn btn-outline-secondary">Submit for approval</button></form>
 <?php endif;?>
 </div>
 <?php if($super&&$row['status']==='pending'):?>
 <details class="approval-reject"><summary>Reject with reason</summary><form method="post" data-review-confirm="Reject this submission and send your reason to the author?"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="submission_id" value="<?= $id ?>"><input type="hidden" name="revision" value="<?= (int)$row['revision'] ?>"><input type="hidden" name="action" value="reject"><label class="form-label" for="reason-<?= $id ?>">What should the author change?</label><textarea class="form-control mb-2" id="reason-<?= $id ?>" name="reason" maxlength="2000" placeholder="Explain what needs to be revised…" required></textarea><p class="small text-muted">This reason will be visible to the author so they can revise and resubmit.</p><button class="btn btn-outline-danger">Confirm rejection</button></form></details>
 <?php endif;?>
 <?php if(in_array($row['status'],['processing','uncertain'],true)):?><p class="text-warning mt-3">Execution needs verification. Check the actual content before submitting another request; this request cannot be executed twice.</p><?php endif;?>
</section>
<?php endforeach;?>
<div class="data-card approval-empty" id="approval-no-results" hidden><i class="bi bi-search" aria-hidden="true"></i><h2>No matching submissions</h2><p>Try another search or choose a different status.</p></div>
<?php if($super):?><div class="data-card p-4 mb-4"><h2 class="data-card-title">Existing page-text review queue</h2><p>Previously submitted page-text changes remain available in the Content Editor.</p><a class="btn btn-outline-secondary" href="/admin/content-edit.php?page=review">Review page text</a></div><?php endif;?>
</div>
<script>
(()=>{const search=document.getElementById('approval-search'),status=document.getElementById('approval-status');if(!search||!status)return;const cards=[...document.querySelectorAll('[data-approval-status]')];function filter(){const term=search.value.trim().toLocaleLowerCase();let count=0;cards.forEach(card=>{card.hidden=!(card.dataset.approvalSearch.toLocaleLowerCase().includes(term)&&(!status.value||card.dataset.approvalStatus===status.value));if(!card.hidden)count++;});document.getElementById('approval-results').textContent=count+' submission'+(count===1?'':'s')+' shown';document.getElementById('approval-no-results').hidden=count!==0;}search.addEventListener('input',filter);status.addEventListener('change',filter);})();
document.addEventListener('submit',async event=>{const form=event.target;if(!form.matches('[data-review-confirm]'))return;event.preventDefault();if(await AdminDialogs.confirm(form.dataset.reviewConfirm,{title:'Confirm review',confirmText:'Continue'})){form.querySelector('button').disabled=true;HTMLFormElement.prototype.submit.call(form);}});
</script>
<?php require __DIR__.'/../components/footer-admin.php';?>
