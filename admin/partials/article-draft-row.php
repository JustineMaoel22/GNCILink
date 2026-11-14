<?php
if(!isset($draft,$draftKind,$csrfToken)){http_response_code(404);exit;}
$de=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$draftId=(int)$draft['_draft_id'];$module='/admin/'.$draftKind.'.php';
$editUrl=$module.'?'.http_build_query(['revise'=>$draftId]);
$categoryLabel=$draftKind==='news'?($newsCategories[$draft['category']]??$draft['category']):implode(', ',array_column(array_filter($categories,static fn($c)=>in_array((string)$c['category_id'],array_map('strval',$draft['_draft_categories']),true)),'category_name'));
?>
<tr class="ann-row" data-draft-id="<?= $draftId ?>" data-draft-revision="<?= (int)$draft['revision'] ?>">
<td class="ann-img-cell"><div class="bulk-select-media"><?php if($draft['image_path']): ?><img class="ann-thumb" src="<?= $de($draft['image_path']) ?>" alt="" loading="lazy"><?php else: ?><div class="ann-thumb ann-thumb-empty"><i class="bi bi-file-earmark-text" aria-hidden="true"></i></div><?php endif; ?></div></td>
<td class="ann-title-cell"><a class="ann-title text-reset text-decoration-none" data-draft-edit href="<?= $de($editUrl) ?>"><?= $de($draft['title']) ?></a><div class="ann-sub">Private draft &middot; Revision <?= (int)$draft['revision'] ?></div></td>
<td data-label="Category"><span class="program-badge"><?= $de($categoryLabel?:'Not set') ?></span></td>
<?php if($draftKind==='announcement'): ?><td data-label="For"><?= $de(contentAudienceDescription($draft['_draft_audience'])) ?></td><?php endif; ?>
<td data-label="Status"><span class="status-badge draft">Draft</span></td>
<td class="ann-actions-cell" data-label="Actions">
<a class="btn btn-sm btn-outline-secondary" href="<?= $de($editUrl) ?>" title="Edit draft" aria-label="Edit draft"><i class="bi bi-pencil" aria-hidden="true"></i><span>Edit draft</span></a>
<?php foreach(['submit'=>'Submit for approval','delete'=>'Delete draft'] as $draftAction=>$draftLabel): ?>
<form method="post" action="/admin/submissions.php" data-draft-action="<?= $draftAction ?>" class="ann-inline-form">
<input type="hidden" name="return_query" value="<?= $de(http_build_query($listFilters??[])) ?>">
<input type="hidden" name="csrf_token" value="<?= $de($csrfToken) ?>"><input type="hidden" name="submission_id" value="<?= $draftId ?>"><input type="hidden" name="revision" value="<?= (int)$draft['revision'] ?>"><input type="hidden" name="action" value="<?= $draftAction ?>"><input type="hidden" name="return_view" value="drafts"><input type="hidden" name="return_module" value="<?= $draftKind ?>">
<button type="submit" class="btn btn-sm <?= $draftAction==='delete'?'btn-outline-danger':'btn-outline-secondary' ?>" title="<?= $draftLabel ?>" aria-label="<?= $draftLabel ?>"><i class="bi <?= $draftAction==='delete'?'bi-trash3':'bi-send' ?>" aria-hidden="true"></i><span><?= $draftLabel ?></span></button>
</form>
<?php endforeach; ?>
</td>
</tr>
