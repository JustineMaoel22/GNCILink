<?php
/**
 * GNC Admin Panel - Page content: save / submit / approve / publish (JSON)
 *
 * Used by the redesigned Content Editor (admin/content-edit.php).
 * Place at: admin/action/page-review-handler.php
 *
 * POST fields: action, csrf_token, page, section_key, [content], [note]
 *
 * Actions and who may use them:
 *   save_draft   Super Admin, Content Admin, Content Editor   save text as a private draft
 *   submit       Super Admin, Content Admin, Content Editor   save + send the draft for approval
 *   withdraw     Super Admin, Content Admin, Content Editor   take a submission back (draft is kept)
 *   discard      Super Admin, Content Admin, Content Editor   throw the draft away
 *   publish      Super Admin, Content Admin                   save + put it live right now
 *   approve      roles with approve_content                   publish a submitted draft
 *   reject       roles with reject_content                    send a submitted draft back with a note
 *
 * Content Editors can never publish: the server checks this, not just the buttons.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';

initSession();
header('Content-Type: application/json; charset=utf-8');

function pr_respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function pr_fail(string $message, int $status = 400): void {
    pr_respond(['success' => false, 'error' => $message], $status);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') pr_fail('Invalid request.', 405);
if (!isLoggedIn()) pr_fail('Your session has ended. Please log in again.', 401);
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) pr_fail('Your session has expired. Please reload the page and try again.', 403);

$action     = (string)($_POST['action'] ?? '');
$pageSlug   = trim((string)($_POST['page'] ?? ''));
$sectionKey = trim((string)($_POST['section_key'] ?? ''));

$canPublish = hasPermission('manage_page_content');
$canDraft   = $canPublish || hasPermission('edit_page_content');

if (!in_array($action, ['save_draft', 'submit', 'withdraw', 'discard', 'publish', 'approve', 'reject'], true)) {
    pr_fail('Unknown action.');
}
if ($pageSlug === '' || $sectionKey === '') pr_fail('Missing page or section.');
if (in_array($pageSlug, ['college-departments', 'programs', 'administration', 'home'], true)) {
    pr_fail('This page is edited with its own tools, not here.');
}

// ── Permissions (server side) ──
switch ($action) {
    case 'publish':
        if (!$canPublish) pr_fail('Only an administrator can publish directly. Use "Submit for approval" instead.', 403);
        break;
    case 'approve':
        if (!hasPermission('approve_content')) pr_fail('You do not have permission to approve content.', 403);
        break;
    case 'reject':
        if (!hasPermission('reject_content')) pr_fail('You do not have permission to send content back.', 403);
        break;
    default:
        if (!$canDraft) pr_fail('You do not have permission to edit page content.', 403);
}

$section = getSectionDetailed($pageSlug, $sectionKey);
if (!$section) pr_fail('That section was not found.', 404);

$ready = pageReviewReady();
$userId = (int)($_SESSION['user_id'] ?? 0);

/** Current state, sent back after every action so the screen can update itself. */
function pr_state(string $pageSlug, string $sectionKey): array {
    $s = getSectionDetailed($pageSlug, $sectionKey);
    $trim = fn($v) => trim((string)$v);
    return [
        'has_draft'         => $s && $s['draft_content'] !== null,
        'review_status'     => $s['review_status'] ?? 'none',
        'review_note'       => $s['review_note'] ?? null,
        'submitted_by_name' => $trim($s['submitted_by_name'] ?? ''),
        'submitted_at'      => $s['submitted_at'] ?? null,
        'updated_at'        => $s['updated_at'] ?? null,
        'updated_by_name'   => $trim($s['updated_by_name'] ?? ''),
        'editor_html'       => sectionToEditorHtml($s && $s['draft_content'] !== null ? $s['draft_content'] : ($s['content'] ?? '')),
        'live_html'         => sectionToEditorHtml($s['content'] ?? ''),
        'pending_count'     => count(getPendingSections()),
    ];
}

/** Clean + store the text as a draft. Returns false (and responds) on failure. */
function pr_save(string $pageSlug, string $sectionKey): string {
    $raw = (string)($_POST['content'] ?? '');
    $content = richTextToStorage($raw);
    if (!saveSectionDraft($pageSlug, $sectionKey, $content)) pr_fail('Could not save your changes. Please try again.', 500);
    return $content;
}

try {
    switch ($action) {
        case 'save_draft':
            // Editing a submitted/rejected draft means it needs to be re-submitted.
            pr_save($pageSlug, $sectionKey);
            resetSectionReview($pageSlug, $sectionKey);
            pr_respond(['success' => true, 'message' => 'Draft saved. Visitors cannot see it yet.'] + pr_state($pageSlug, $sectionKey));

        case 'submit':
            if (!$ready) pr_fail('The approval feature is not set up yet. Ask your developer to run sql/page-approval-migration.sql.', 409);
            $content = pr_save($pageSlug, $sectionKey);
            $live = (string)($section['content'] ?? '');
            if (trim($content) === trim($live)) {
                discardSectionDraft($pageSlug, $sectionKey);
                pr_fail('Nothing has changed compared to what is on the website, so there is nothing to submit.');
            }
            submitSectionForReview($pageSlug, $sectionKey);
            pr_respond(['success' => true, 'message' => 'Sent for approval. An administrator will review it.'] + pr_state($pageSlug, $sectionKey));

        case 'withdraw':
            if ($ready && ($section['review_status'] ?? 'none') !== 'pending') pr_fail('This change is not waiting for approval.');
            resetSectionReview($pageSlug, $sectionKey);
            logActivity($userId, 'UPDATE', 'page_sections', (int)$section['section_id'], "Withdrew {$pageSlug}/{$sectionKey} from approval");
            pr_respond(['success' => true, 'message' => 'Taken back. It is a private draft again.'] + pr_state($pageSlug, $sectionKey));

        case 'discard':
            if ($ready && ($section['review_status'] ?? 'none') === 'pending' && !$canPublish) {
                pr_fail('This change is waiting for approval. Take it back first if you want to discard it.');
            }
            if (!discardSectionDraft($pageSlug, $sectionKey)) pr_fail('Could not discard the draft.', 500);
            resetSectionReview($pageSlug, $sectionKey);
            pr_respond(['success' => true, 'message' => 'Draft discarded. This is what is on the website now.'] + pr_state($pageSlug, $sectionKey));

        case 'publish':
            pr_save($pageSlug, $sectionKey);
            if (!publishSection($pageSlug, $sectionKey)) pr_fail('Could not publish. Please try again.', 500);
            if ($ready) {
                $stmt = getDB()->prepare("UPDATE page_sections SET review_status = 'none', review_note = NULL, reviewed_by = ?, reviewed_at = ? WHERE section_id = ?");
                $stmt->execute([$userId ?: null, date('Y-m-d H:i:s'), (int)$section['section_id']]);
            }
            pr_respond(['success' => true, 'message' => 'Done! This is now live on the website.'] + pr_state($pageSlug, $sectionKey));

        case 'approve':
            approveSection($pageSlug, $sectionKey);
            pr_respond(['success' => true, 'message' => 'Approved and published. It is now live.'] + pr_state($pageSlug, $sectionKey));

        case 'reject':
            rejectSection($pageSlug, $sectionKey, (string)($_POST['note'] ?? ''));
            pr_respond(['success' => true, 'message' => 'Sent back to the editor with your note.'] + pr_state($pageSlug, $sectionKey));
    }
} catch (Throwable $e) {
    error_log('page-review-handler error: ' . $e->getMessage());
    // approveSection/rejectSection/submitSectionForReview throw plain Exceptions with user-safe text.
    if ($e instanceof PDOException || !($e instanceof Exception)) pr_fail('Something went wrong. Please try again.', 500);
    pr_fail($e->getMessage(), 400);
}