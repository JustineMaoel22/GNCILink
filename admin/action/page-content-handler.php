<?php
/**
 * GNC Admin Panel – Page Content CMS AJAX Handler
 * Powers the "Select Page" section editor in content-edit.php
 * (Vision & Mission, About Us, History, etc.). The homepage
 * slideshow keeps using hero-slides-handler.php.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';

initSession();
requireLogin();
requirePermission('manage_page_content');

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        // ── List a page's sections (with draft + published values) ──
        case 'get_sections':
            $slug = trim($_GET['page'] ?? '');
            $page = $slug !== '' ? getPageBySlug($slug) : null;
            if (!$page) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Unknown page.']);
                break;
            }
            $sections = getPageSections($slug);
            echo json_encode([
                'success'  => true,
                'page'     => $page,
                'sections' => array_map(function ($s) {
                    return [
                        'section_key'   => $s['section_key'],
                        'section_label' => $s['title'],
                        'content'       => $s['content'],
                        'draft_content' => $s['draft_content'],
                        'has_draft'     => $s['draft_content'] !== null,
                        'updated_at'    => $s['updated_at'],
                    ];
                }, $sections),
            ]);
            break;

        // ── Save edits as a draft (does not touch the live site) ──
        case 'save_draft':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(419);
                echo json_encode(['success' => false, 'error' => 'Your session has expired. Please refresh and try again.']);
                break;
            }
            $slug       = trim($_POST['page'] ?? '');
            $sectionKey = trim($_POST['section_key'] ?? '');
            $content    = $_POST['content'] ?? '';

            if ($slug === '' || $sectionKey === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing page or section.']);
                break;
            }

            $ok = saveSectionDraft($slug, $sectionKey, $content);
            echo json_encode($ok
                ? ['success' => true, 'message' => 'Draft saved.']
                : ['success' => false, 'error' => 'Could not save the draft. Please try again.']);
            break;

        // ── Publish a section's draft to the live public site ──
        case 'publish':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(419);
                echo json_encode(['success' => false, 'error' => 'Your session has expired. Please refresh and try again.']);
                break;
            }
            $slug       = trim($_POST['page'] ?? '');
            $sectionKey = trim($_POST['section_key'] ?? '');

            if ($slug === '' || $sectionKey === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing page or section.']);
                break;
            }

            $ok = publishSection($slug, $sectionKey);
            echo json_encode($ok
                ? ['success' => true, 'message' => 'Published.']
                : ['success' => false, 'error' => 'Could not publish. Please try again.']);
            break;

        // ── Discard the pending draft, revert to published text ──
        case 'discard_draft':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(419);
                echo json_encode(['success' => false, 'error' => 'Your session has expired. Please refresh and try again.']);
                break;
            }
            $slug       = trim($_POST['page'] ?? '');
            $sectionKey = trim($_POST['section_key'] ?? '');
            $ok = discardSectionDraft($slug, $sectionKey);
            echo json_encode(['success' => $ok]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    }
} catch (Exception $e) {
    error_log('Page content handler error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Something went wrong. Please try again.']);
}