<?php
if (!isset($payload, $row, $e)) { http_response_code(404); exit; }
// The enclosing submission has already been restricted to its owner or a Super Admin.
$calendarReviewQuery=getDB()->prepare('SELECT approved_json,series_id,calendar_action,filename FROM calendar_import_batches WHERE token=? AND user_id=?');
$calendarReviewQuery->execute([$payload['post']['import_token']??'', $row['user_id']]);
$calendarReviewBatch=$calendarReviewQuery->fetch(PDO::FETCH_ASSOC);
$calendarReviewRows=$calendarReviewBatch?json_decode($calendarReviewBatch['approved_json']??'[]',true):[];
$calendarReviewCategories=getDB()->query('SELECT category_id,category_name FROM categories')->fetchAll(PDO::FETCH_KEY_PAIR);
$calendarReviewDepartments=getDB()->query('SELECT dept_id,dept_name FROM departments')->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<?php if (!empty($calendarReviewBatch['calendar_action'])): ?>
<p class="alert alert-<?= $calendarReviewBatch['calendar_action']==='delete'?'danger':'warning' ?>"><?= $e($calendarReviewBatch['filename']) ?>: approving this request <?= $calendarReviewBatch['calendar_action']==='delete'?'permanently deletes':'archives and hides' ?> the imported calendar and all <?= count($calendarReviewRows) ?> events listed below. Manually created events are preserved.</p>
<?php else: ?>
<p class="text-muted">Only the activities below were approved in the import preview. Approving this submission publishes additions and updates, and hides any explicitly selected removals.</p>
<?php endif; ?>
<div class="table-responsive"><table class="table"><thead><tr><th>Action</th><th>Event</th><th>Date &amp; time</th><th>Location</th><th>Category</th><th>Description</th></tr></thead><tbody>
<?php foreach($calendarReviewRows as $calendarReviewEvent): ?>
<tr><td><?= $e(ucfirst($calendarReviewEvent['action'])) ?><?= $calendarReviewEvent['event_id']?' #'.(int)$calendarReviewEvent['event_id']:'' ?></td>
<td><?= $e($calendarReviewEvent['title']??'Previously imported event') ?></td>
<td><?= $e($calendarReviewEvent['start_date']??'') ?><?= !empty($calendarReviewEvent['end_date'])?' – '.$e($calendarReviewEvent['end_date']):'' ?></td>
<td><?= $e($calendarReviewEvent['location']??'') ?></td>
<td><?= $e($calendarReviewCategories[$calendarReviewEvent['category_id']??0]??'—') ?><div class="small text-muted"><?= $e(['basic-education'=>'All Basic Education','college'=>'All College'][$calendarReviewEvent['audience_group']??'all']??($calendarReviewDepartments[$calendarReviewEvent['dept_id']??0]??'All departments')) ?> · <?= $e($calendarReviewEvent['program']??'ALL') ?></div></td>
<td style="white-space:pre-wrap;overflow-wrap:anywhere"><?= $e($calendarReviewEvent['description']??'') ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if(!$calendarReviewRows && empty($calendarReviewBatch['calendar_action'])): ?><p class="text-warning">This import has no reviewable activities. Do not approve it; request a fresh upload.</p><?php endif; ?>
