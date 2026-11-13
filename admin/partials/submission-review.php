<?php
if(!isset($row,$payload,$e,$super,$filters,$reviewUrl)){http_response_code(404);exit;}
$id=(int)$row['submission_id'];$state=$row['status'];$statuses=approvalStatuses();
$hiddenFields=static function(string $action)use($row,$csrfToken,$e,$filters){ ?>
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="submission_id" value="<?= (int)$row['submission_id'] ?>"><input type="hidden" name="revision" value="<?= (int)$row['revision'] ?>"><input type="hidden" name="action" value="<?= $e($action) ?>">
<?php foreach($filters as $key=>$value): ?><input type="hidden" name="return_<?= $e($key) ?>" value="<?= $e($value) ?>"><?php endforeach; ?>
<noscript><label class="approval-note"><input type="checkbox" required> I confirm this action. Approval applies the proposal to the website; other review decisions leave published content unchanged.</label></noscript>
<?php };
?>
<section class="data-card approval-review" id="submission-review" aria-labelledby="review-title" tabindex="-1">
<header class="approval-review-heading"><div><span class="approval-type"><?= $e(rbacSubmissionLabel($row,$payload)) ?></span><h2 id="review-title"><?= $e($row['title']) ?></h2></div><a class="btn btn-outline-secondary approval-close" href="<?= $e($reviewUrl(['id'=>null])) ?>" data-review-close aria-label="Close review"><i class="bi bi-x-lg" aria-hidden="true"></i></a></header>
<div class="approval-review-meta"><span class="approval-status approval-status-<?= $e($state) ?>"><?= $e($statuses[$state]??ucfirst($state)) ?></span><span><?= $e(approvalAuthor($row)) ?></span><time title="<?= $e($row['created_at']) ?>"><?= $e(approvalDate($row['created_at'])) ?></time><span>Revision <?= (int)$row['revision'] ?></span></div>
<?php if($row['reason']): ?><aside class="approval-feedback"><h3><?= $state==='needs_revision'?'Requested revisions':($state==='rejected'?'Rejection feedback':'Review feedback') ?></h3><p><?= nl2br($e($row['reason'])) ?></p></aside><?php endif; ?>
<?php if(in_array($state,['processing','uncertain'],true)): ?><p class="alert alert-warning">Check the actual website content before taking another action. Completion has not been confirmed; this request cannot be approved again from this screen.</p><?php endif; ?>
<?php if($state==='pending'&&!$super): ?><p class="approval-note">Waiting for Super Admin approval. Visitors still see the published content.</p><?php endif; ?>
<div class="approval-review-content">
<?php if($row['kind']==='snapshot'):
    $route=$payload['route']??'';$page=$payload['page']??'';
    if(is_string($page)&&is_string($route)&&rbacSafePreviewRoute($route)):
    $editorUrl='/admin/content-edit.php?'.http_build_query(['page'=>$page,'review_submission'=>$id,'route'=>$route]); ?>
<h3>Proposed page changes</h3><p>Page: <?= $e(ucwords(str_replace('-',' ',$page))) ?></p><p class="approval-note">Open the saved proposal in the Content Editor to inspect the full page and its changes. Opening it does not publish anything.</p>
<div class="approval-preview-links"><a class="btn btn-gnc" href="<?= $e($editorUrl) ?>">Review proposed page</a><a class="btn btn-outline-secondary" href="<?= $e($route) ?>" target="_blank" rel="noopener">View current website <span class="visually-hidden">(opens in a new tab)</span></a></div>
<?php if($super&&$state==='pending'): ?><p class="approval-note">Use Approve and publish in the Content Editor after reviewing the proposal.</p><?php endif; ?>
<?php else: ?><p role="alert">The saved page preview is unavailable. Do not approve until it has been checked.</p><?php endif; ?>
<?php else:
    $post=$payload['post']??[];$proposed=approvalFields(is_array($post)?$post:[]);$current=approvalCurrent(getDB(),$row);
    $operation=$post['action']??$post['ajax_action']??'';
    $compare=$current!==null&&in_array($operation,['update','edit'],true);
?>
<h3><?= $compare?'Review changes':'Proposed content' ?></h3>
<?php if($compare): ?><p class="approval-note">Compare the proposal with the current stored content. The original version at submission time was not saved.</p><?php endif; ?>
<?php if(in_array($operation,['delete','archive','restore','unarchive'],true)): ?><p class="approval-operation">Proposed action: <strong><?= $e(ucfirst($operation)) ?></strong>. Approval applies this action through the existing publishing workflow.</p><?php if($current): $proposed=approvalFields(array_intersect_key($current,array_flip(['title','content','description','start_date','end_date','location']))); endif; endif; ?>
<?php if(!$proposed): ?><p class="approval-note">No text fields are included. Review the page preview or attachments below.</p><?php endif; ?>
<?php foreach($proposed as $field=>$value):
    $hasBefore=$compare&&array_key_exists($field,$current);
    $before=$hasBefore?(approvalFields([$field=>$current[$field]])[$field]??''):'';
    $changed=$hasBefore&&$before!==$value;
    $change=$changed?($before===''?'Added':($value===''?'Removed':'Modified')):'';
?>
<section class="approval-field<?= $changed?' approval-field-changed':'' ?>"><h4><?= $e(ucwords(str_replace('_',' ',$field))) ?><?php if($change): ?><span class="approval-change-tag"><?= $e($change) ?></span><?php endif; ?></h4>
<?php if($hasBefore): ?><div class="approval-comparison"><div><h5>Current content</h5><p><?= $e($before!==''?$before:'Empty') ?></p></div><div><h5>Proposed content</h5><p><?= $e($value!==''?$value:'Empty — this value will be cleared') ?></p></div></div><?php else: ?><p><?= $e($value!==''?$value:'Empty') ?></p><?php endif; ?></section>
<?php endforeach; ?>
<?php if($row['route']==='/admin/events.php'&&($post['ajax_action']??'')==='import_confirm')include __DIR__.'/calendar-import-review.php'; ?>
<?php foreach($payload['files']??[] as $field=>$file): $media='/admin/submissions.php?'.http_build_query(['media'=>$id,'field'=>$field,'view'=>$state==='draft'?'drafts':'submissions']); ?>
<div class="approval-attachment"><?php if(str_starts_with($file['mime'],'image/')): ?><img src="<?= $e($media) ?>" alt="Submitted image" loading="lazy"><?php else: ?><i class="bi bi-paperclip" aria-hidden="true"></i><?php endif; ?><a href="<?= $e($media) ?>" target="_blank" rel="noopener"><?= $e($file['name']) ?><span class="visually-hidden"> (opens in a new tab)</span></a></div>
<?php endforeach; endif; ?>
</div>
<details class="approval-history"><summary>Submission history & feedback</summary><ol>
<li><strong>Created</strong><time><?= $e(approvalDate($row['created_at'])) ?></time></li>
<?php foreach($payload['_review_history']??[] as $event): if(!is_array($event))continue; ?>
<li><strong><?= $e($statuses[$event['status']??'']??'Review feedback') ?> · Revision <?= (int)($event['revision']??1) ?></strong><time><?= $e(approvalDate($event['at']??null)) ?></time><p><?= $e($event['feedback']??'') ?></p></li>
<?php endforeach; ?>
<?php if((int)$row['revision']>1): ?><li><strong>Latest revision saved · Revision <?= (int)$row['revision'] ?></strong><time><?= $e(approvalDate($row['updated_at'])) ?></time></li><?php endif; ?>
<li><strong>Current status: <?= $e($statuses[$state]??$state) ?></strong><time><?= $e(approvalDate($row['reviewed_at']??$row['updated_at'])) ?></time></li>
</ol><p class="approval-note">Earlier full versions and feedback not retained by the original workflow are unavailable.</p></details>
<footer class="approval-review-actions">
<?php if($super&&$state==='pending'&&$row['kind']==='request'): ?>
<form method="post" action="/admin/submissions.php" data-review-confirm="Approve this proposal and apply its changes to the website?" data-review-label="Approve and apply"><?php $hiddenFields('approve'); ?><button class="btn btn-gnc" type="submit">Approve and apply</button></form>
<?php endif; ?>
<?php if($super&&$state==='pending'): ?>
<details class="approval-decision"><summary class="btn btn-outline-secondary">Request revision</summary><form method="post" action="/admin/submissions.php" data-review-confirm="Return this proposal to the author with your feedback? Published content stays unchanged." data-review-label="Request revision"><?php $hiddenFields('request_revision'); ?><label for="revision-feedback">What needs to change?</label><textarea class="form-control" name="reason" id="revision-feedback" required maxlength="2000" placeholder="Explain the changes needed before approval."></textarea><button class="btn btn-gnc" type="submit">Send revision request</button></form></details>
<details class="approval-decision"><summary class="btn btn-outline-danger">Reject submission</summary><form method="post" action="/admin/submissions.php" data-review-confirm="Reject this proposal? The author will receive your feedback. No published content will be changed." data-review-label="Reject submission"><?php $hiddenFields('reject'); ?><label for="reject-feedback">Why is this proposal being declined?</label><textarea class="form-control" name="reason" id="reject-feedback" required maxlength="2000" placeholder="Explain why the proposal cannot be accepted."></textarea><button class="btn btn-outline-danger" type="submit">Confirm rejection</button></form></details>
<?php endif; ?>
<?php if((int)$row['user_id']===$userId&&in_array($state,['draft','rejected','needs_revision','failed'],true)): ?>
<?php if($row['kind']==='request'&&in_array($row['route'],['/admin/action/create-news.php','/admin/action/create-announcement.php'],true)):
    $kind=str_contains($row['route'],'create-news')?'news':'announcement';$post=$payload['post'];$editUrl='/admin/'.$kind.'.php?'.http_build_query(['modal'=>($post['action']??'')==='update'?'edit':'create','id'=>$row['target_id'],'revise'=>$id]); ?>
<a class="btn btn-outline-secondary" href="<?= $e($editUrl) ?>"><?= $state==='draft'?'Edit draft':'Revise submission' ?></a><?php endif; ?>
<form method="post" action="/admin/submissions.php" data-review-confirm="Submit this saved proposal for Super Admin approval?" data-review-label="Submit for approval"><?php $hiddenFields('submit'); ?><button class="btn btn-gnc" type="submit">Submit for approval</button></form>
<?php endif; ?>
<?php if(((int)$row['user_id']===$userId||$super)&&$state!=='processing'): ?>
<details class="approval-delete"><summary>Delete submission…</summary><p>This removes the saved proposal from the queue. Published website content is preserved.</p><form method="post" action="/admin/submissions.php" data-review-confirm="Permanently delete this saved submission? Published website content will not be affected." data-review-label="Delete submission"><?php $hiddenFields('delete'); ?><button class="btn btn-outline-danger" type="submit">Delete submission</button></form></details>
<?php endif; ?>
</footer>
</section>
