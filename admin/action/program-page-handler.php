<?php
/**
 * AJAX endpoint for Content Editor -> Programs (components/program-page-manager.php).
 *
 * POST fields: csrf_token, action, ...
 *   create_program        program_code, program_name, dept_id, theme, theme_custom, core_values_enabled
 *   save_program_details  program_id + every field of the Details tab (+ hero / seal pictures)
 *   save_program_items    program_id, item_type, items (JSON list), file_<n> pictures
 *   save_program_pdf      program_id, pdf (file) or remove_pdf=1
 *   delete_program        program_id
 *
 * Always answers JSON: {success: true, ...} or {success: false, error: "..."}.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';
require_once __DIR__ . '/../../components/program-page-public.php';

// Resume the logged-in admin session (a no-op if config.php already started it).
initSession();

header('Content-Type: application/json; charset=utf-8');

function programHandlerFail(string $message, int $status = 200): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    programHandlerFail('Invalid request.', 405);
}
if (!isLoggedIn()) {
    programHandlerFail('Your session has expired. Please log in again.', 401);
}
if (!hasPermission('manage_page_content')) {
    programHandlerFail('You do not have permission to edit program pages.', 403);
}
if (!verifyCSRFToken((string)($_POST['csrf_token'] ?? ''))) {
    programHandlerFail('Your session has expired. Please refresh the page and try again.', 403);
}

/** The slice of a program the editor needs back after a save (JSON-safe). */
function programHandlerPayload(array $p): array {
    return [
        'program_id'   => (int)$p['program_id'],
        'program_code' => $p['program_code'],
        'program_name' => $p['program_name'],
        'slug'         => $p['slug'],
        'url'          => '/pages/academics/college/program.php?slug=' . rawurlencode((string)$p['slug']),
        'published'    => (bool)$p['published'],
        'core_enabled' => (bool)$p['core_enabled'],
        'theme_accent' => $p['theme_accent'],
        'hero_url'     => $p['hero_url'],
        'hero_upload'  => (bool)$p['has_hero_upload'],
        'seal_url'     => $p['seal_url'],
        'seal_upload'  => (bool)$p['has_seal_upload'],
        'pdf_url'      => $p['pdf_url'] ?? null,
        'pdf_name'     => $p['pdf_name'] ?? null,
        'pdf_size'     => isset($p['pdf_size']) ? (int)$p['pdf_size'] : null,
        'dept_name'    => $p['dept_name'] ?? null,
        'content'      => $p['content'],
    ];
}

/** Items of one list as the editor wants them. */
function programHandlerItems(array $items): array {
    return array_map(function (array $i): array {
        return [
            'item_id'   => (int)$i['item_id'],
            'title'     => (string)$i['title'],
            'subtitle'  => (string)($i['subtitle'] ?? ''),
            'body'      => (string)($i['body'] ?? ''),
            'icon'      => (string)($i['icon'] ?? ''),
            'letter'    => (string)($i['letter'] ?? ''),
            'image_url' => $i['image_url'] ?? null,
            'has_upload'=> (bool)($i['has_upload'] ?? false),
        ];
    }, $items);
}

$action    = (string)($_POST['action'] ?? '');
$programId = (int)($_POST['program_id'] ?? 0);

try {
    switch ($action) {

        case 'create_program':
            $id = createProgramPage($_POST);
            echo json_encode(['success' => true, 'program_id' => $id]);
            break;

        case 'save_program_details':
            $program = saveProgramPageDetails(
                $programId,
                $_POST,
                ['hero' => $_FILES['hero'] ?? null, 'seal' => $_FILES['seal'] ?? null],
                ['hero' => !empty($_POST['remove_hero']), 'seal' => !empty($_POST['remove_seal'])]
            );
            echo json_encode(['success' => true, 'program' => programHandlerPayload($program)]);
            break;

        case 'save_program_items':
            $type = (string)($_POST['item_type'] ?? '');
            $list = json_decode((string)($_POST['items'] ?? '[]'), true);
            if (!is_array($list)) {
                programHandlerFail('The list could not be read. Please refresh the page and try again.');
            }
            $rows = [];
            foreach (array_values($list) as $n => $row) {
                if (!is_array($row)) continue;
                $row['file'] = $_FILES['file_' . $n] ?? null;
                $rows[] = $row;
            }
            $items = saveProgramPageItems($programId, $type, $rows);
            echo json_encode(['success' => true, 'items' => programHandlerItems($items)]);
            break;

        case 'save_program_pdf':
            $program = saveProgramPdf($programId, $_FILES['pdf'] ?? null, !empty($_POST['remove_pdf']));
            $fresh   = getProgramPageById($programId) ?? $program;
            echo json_encode(['success' => true, 'program' => programHandlerPayload($fresh)]);
            break;

        case 'delete_program':
            deleteProgramPage($programId);
            echo json_encode(['success' => true]);
            break;

        default:
            programHandlerFail('Unknown action.');
    }
} catch (Throwable $e) {
    // Exceptions thrown by the helpers carry user-safe messages.
    if (!($e instanceof Exception)) {
        error_log('Program page handler error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        programHandlerFail('Something went wrong on the server. Please try again.');
    }
    programHandlerFail($e->getMessage());
}