<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/admin-functions.php';
require_once __DIR__ . '/../components/enrollment-procedures-content.php';
initSession();
requireLogin();
if (!hasPermission('manage_page_content')) { http_response_code(403); exit('You cannot manage enrollment steps.'); }
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) { http_response_code(413); echo json_encode(['error'=>'The upload exceeds the request limit. Choose fewer images and retry.']); exit; }
    if (!verifyCSRFToken((string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); echo json_encode(['error'=>'Refresh the page and try again.']); exit; }
    try {
        $steps = json_decode((string)($_POST['steps'] ?? ''), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($steps)) throw new InvalidArgumentException('Invalid steps.');
        publishBasicEnrollmentSteps($steps, (string)($_POST['revision'] ?? ''), $_FILES);
        echo json_encode(['success'=>true]);
    } catch (Throwable $e) {
        $expected = $e instanceof InvalidArgumentException || $e instanceof JsonException;
        if (!$expected) error_log('Enrollment steps publish failed: ' . $e->getMessage());
        http_response_code(422);
        echo json_encode(['error'=>$expected ? $e->getMessage() : 'Could not publish the steps. Check the illustration files and try again.']);
    }
    exit;
}
$steps = basicEnrollmentSteps();
$pageTitle = 'Basic Education Enrollment Steps';
require __DIR__ . '/../components/header-admin.php';
?>
<div class="page-header">
    <div><h1>Basic Education Enrollment Steps</h1><p>Add the approved instructions in order. Changes stay here until you publish.</p></div>
    <a class="btn btn-outline-secondary" href="/admin/content-edit.php?page=enrollment-procedures">Back to Content Editor</a>
</div>
<section class="data-card p-4">
    <p class="text-muted small">Use an existing illustration or upload SVG, PNG, JPG, or WEBP. Images are optimized to a maximum of 5 MB. For <a href="https://storyset.com/" target="_blank" rel="noopener noreferrer">Storyset</a>, choose <strong>Download SVG</strong>. The public page includes attribution.</p>
    <form id="enrollment-steps-form">
        <div id="enrollment-step-list" class="d-grid gap-3"></div>
        <p id="enrollment-empty" class="text-muted">No steps yet. Add the approved enrollment steps when they are available.</p>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <button type="button" id="enrollment-add-step" class="btn btn-outline-secondary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add step</button>
            <button type="submit" id="enrollment-publish" class="btn-gnc-primary" disabled>Publish steps</button>
            <a class="btn btn-outline-secondary" href="/pages/admission/enrollment-procedures.php#basic-education-pane" target="_blank" rel="noopener">View live</a>
        </div>
        <p class="small text-muted mt-2" id="enrollment-save-status" role="status">Everything here is live.</p>
    </form>
</section>
<template id="enrollment-step-template">
    <fieldset class="border rounded p-3 enrollment-edit-step">
        <legend class="float-none w-auto px-2 fs-6 fw-semibold">Step <span data-number></span></legend>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-up>Move up</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-down>Move down</button>
            <button type="button" class="btn btn-outline-danger btn-sm" data-remove>Remove step</button>
        </div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label d-block">Office / step title<input class="form-control" data-title maxlength="150" required></label></div>
            <div class="col-md-6"><label class="form-label d-block">Location (optional)<input class="form-control" data-location maxlength="150"></label></div>
            <div class="col-12"><label class="form-label d-block">Instructions<textarea class="form-control" data-instruction maxlength="2000" rows="3" required></textarea></label></div>
            <div class="col-md-6"><label class="form-label d-block">Existing illustration<select class="form-select" data-illustration></select></label><label class="form-label d-block mt-2">Change illustration<input type="file" class="form-control" data-upload accept=".svg,.png,.jpg,.jpeg,.webp"></label><p class="form-text">Choose a file to replace the illustration. Maximum saved size: 5 MB.</p></div>
            <div class="col-md-6"><img data-preview alt="Illustration preview" width="120" height="120" style="object-fit:contain" hidden></div>
        </div>
    </fieldset>
</template>
<script id="enrollment-steps-config" type="application/json"><?= json_encode(['steps'=>$steps, 'revision'=>basicEnrollmentRevision($steps), 'illustrations'=>basicEnrollmentIllustrations(), 'csrf'=>$csrfToken], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<script src="/assets/js/enrollment-steps-editor.js?v=<?= filemtime(__DIR__ . '/../assets/js/enrollment-steps-editor.js') ?>"></script>
<?php require __DIR__ . '/../components/footer-admin.php'; ?>
