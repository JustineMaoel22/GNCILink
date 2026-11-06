<?php
if(!isset($row,$e,$super,$filters,$reviewUrl)){http_response_code(404);exit;}
$state=$row['review_status']==='pending'?'pending':($row['review_status']==='rejected'?'needs_revision':'approved');
$statuses=approvalStatuses();
$sectionTitle=$row['page_title'].' — '.($row['title']?:$row['section_key']);
$hiddenFields=static function(string $action)use($row,$csrfToken,$e,$filters){ ?>
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="record_source" value="section"><input type="hidden" name="submission_id" value="<?= (int)$row['section_id'] ?>"><input type="hidden" name="section_version" value="<?= $e(approvalSectionVersion($row)) ?>"><input type="hidden" name="action" value="<?= $e($action) ?>">
<?php foreach($filters as $key=>$value): ?><input type="hidden" name="return_<?= $e($key) ?>" value="<?= $e($value) ?>"><?php endforeach; ?>
<?php };
?>
<section class="data-card approval-review" id="submission-review" aria-labelledby="review-title" tabindex="-1">
<header class="approval-review-heading"><div><span class="approval-type">Page content</span><h2 id="review-title"><?= $e($sectionTitle) ?></h2></div><a class="btn btn-outline-secondary approval-close" href="<?= $e($reviewUrl()) ?>" data-review-close aria-label="Close review"><i class="bi bi-x-lg" aria-hidden="true"></i></a></header>
<div class="approval-review-meta"><span class="approval-status approval-status-<?= $e($state) ?>"><?= $e($statuses[$state]) ?></span><span><?= $e(approvalAuthor($row)) ?></span><time><?= $e(approvalDate($row['submitted_at'])) ?></time></div>
<?php if($row['review_note']): ?><aside class="approval-feedback"><h3>Review feedback</h3><p><?= $e($row['review_note']) ?></p></aside><?php endif; ?>
<div class="approval-review-content"><h3>Review page text</h3><p class="approval-note">Compare the proposed text with the current published version. Approval publishes only this section.</p>
<div class="approval-comparison"><div><h5>Current published text</h5><div class="cms-rich"><?= richTextSanitize(sectionToEditorHtml($row['content']??'')) ?></div></div><div><h5><?= $state==='approved'?'Approved text':'Proposed text' ?></h5><div class="cms-rich"><?= richTextSanitize(sectionToEditorHtml($row['draft_content']??$row['content']??'')) ?></div></div></div></div>
<footer class="approval-review-actions">
<?php if($super&&$state==='pending'): ?>
<form method="post" action="/admin/submissions.php" data-review-confirm="Approve and publish this page section?" data-review-label="Approve and publish"><?php $hiddenFields('approve'); ?><button class="btn btn-gnc" type="submit">Approve and publish</button></form>
<details class="approval-decision"><summary class="btn btn-outline-secondary">Request changes</summary><form method="post" action="/admin/submissions.php" data-review-confirm="Send this section back with your feedback? Published text will stay unchanged." data-review-label="Request changes"><?php $hiddenFields('request_revision'); ?><label for="section-feedback">What needs to change?</label><textarea class="form-control" id="section-feedback" name="reason" required maxlength="500"></textarea><button class="btn btn-outline-secondary" type="submit">Send revision request</button></form></details>
<?php endif; ?>
<a class="btn btn-outline-secondary" href="<?= $e('/admin/content-edit.php?'.http_build_query(['page'=>$row['page_slug']])) ?>">Open page in Content Editor</a>
</footer></section>
