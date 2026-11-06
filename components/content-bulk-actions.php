<?php
// Caller supplies kind/table/CSRF for the existing list. No records are hardcoded.
if(!hasPermission('delete_'.$bulkDeleteKind)&&!hasPermission('delete_own_'.$bulkDeleteKind))return;
$bulkEscape=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$bulkEndpoint=['news'=>'/admin/action/create-news.php','announcement'=>'/admin/action/create-announcement.php','event'=>'/admin/events.php'][$bulkDeleteKind];
?>
<link rel="stylesheet" href="/assets/css/content-bulk-actions.css?v=<?= filemtime(__DIR__.'/../assets/css/content-bulk-actions.css') ?>">
<div class="d-flex flex-wrap align-items-center gap-3 px-3 py-2 border-bottom" data-bulk-actions data-kind="<?= $bulkEscape($bulkDeleteKind) ?>" data-table="<?= $bulkEscape($bulkDeleteTable) ?>" data-queued="<?= rbacIsSuper()?'0':'1' ?>">
    <label class="d-inline-flex align-items-center gap-2 mb-0"><input type="checkbox" class="form-check-input m-0" data-bulk-all> Select all on this page</label>
    <span class="text-muted small" data-bulk-count role="status" aria-live="polite">0 selected</span>
    <button type="button" class="btn btn-sm btn-outline-danger" data-bulk-delete disabled><i class="bi bi-trash" aria-hidden="true"></i> Delete selected</button>
</div>
<dialog class="gnc-dialog gnc-dialog-danger" data-bulk-dialog aria-labelledby="bulk-delete-title" aria-describedby="bulk-delete-description">
    <div class="gnc-dialog-content"><h2 id="bulk-delete-title"><?= rbacIsSuper()?'Delete selected items?':'Request deletion of selected items?' ?></h2>
    <p id="bulk-delete-description" data-bulk-description></p>
    <form method="post" action="<?= $bulkEscape($bulkEndpoint) ?>" data-bulk-form>
        <input type="hidden" name="csrf_token" value="<?= $bulkEscape($bulkDeleteCsrf) ?>"><input type="hidden" name="<?= $bulkDeleteKind==='event'?'ajax_action':'action' ?>" value="bulk_delete">
        <?php if($bulkDeleteKind==='news'&&isset($returnQuery)): ?><input type="hidden" name="return_query" value="<?= $bulkEscape($returnQuery) ?>"><?php endif; ?>
        <div data-bulk-ids></div>
        <label class="form-label" for="bulk-delete-password">Your current password</label><input type="password" class="form-control mb-3" id="bulk-delete-password" name="current_password" maxlength="4096" autocomplete="current-password" required>
        <p class="text-danger" data-bulk-error role="alert"></p>
        <div class="gnc-dialog-actions"><button type="button" class="gnc-dialog-cancel" data-bulk-cancel>Cancel</button><button type="submit" class="gnc-dialog-accept"><?= rbacIsSuper()?'Delete selected':'Submit for approval' ?></button></div>
    </form></div>
</dialog>
<script src="/assets/js/content-bulk-actions.js?v=<?= filemtime(__DIR__.'/../assets/js/content-bulk-actions.js') ?>"></script>
