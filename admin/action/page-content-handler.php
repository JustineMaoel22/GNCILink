<?php
/**
 * GNC Admin Panel – Page Content CMS AJAX Handler
 * Powers the "Select Page" section editor in content-edit.php
 * (Vision & Mission, About Us, History, etc.) AND the homepage
 * Slideshow editor (hero-slides-handler.php was merged in here —
 * actions 'list' / 'get' / 'save' / 'delete' / 'reorder' below).
 *
 * Also handles College Departments name changes (save_department_name), logo
 * changes (save_department_media) and
 * College Programs curriculum PDFs (save_program_pdf), which arrive as
 * multipart form data because they carry file uploads.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';

initSession();
requireLogin();
requirePermission('manage_page_content');

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// A file bigger than post_max_size makes PHP discard $_POST and $_FILES
// entirely, so the action name is lost too. Detect that up front and give
// a clear message instead of "Unknown action" / "session expired".
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    http_response_code(413);
    echo json_encode(['success' => false, 'error' => 'That upload is larger than the server allows (limit: ' . ini_get('post_max_size') . '). Try a smaller file.']);
    exit;
}

// Hero Slideshow actions (formerly hero-slides-handler.php) use their own
// CSRF gate, kept exactly as it was there, so the merge doesn't change how
// the existing Slideshow editor JS is expected to behave.
$heroStateChanging = ['save', 'delete', 'reorder'];
if (in_array($action, $heroStateChanging, true)) {
    $heroToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($heroToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid or expired session token. Please refresh the page and try again.']);
        exit;
    }
}

try {
    switch ($action) {
        case 'save_department_order':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRFToken((string)($_POST['csrf_token'] ?? ''))) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Please refresh the editor and try again.']);
                break;
            }
            require_once __DIR__ . '/../../components/department-public.php';
            try {
                $order = json_decode((string)($_POST['order'] ?? ''), true);
                if (!is_array($order)) throw new InvalidArgumentException('Invalid department order.');
                saveDepartmentOrder((string)($_POST['scope'] ?? ''), $order);
                echo json_encode(['success' => true]);
            } catch (InvalidArgumentException $e) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
        case 'save_department_page_text':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRFToken((string)($_POST['csrf_token'] ?? ''))) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Please refresh the editor and try again.']);
                break;
            }
            $changes = json_decode((string)($_POST['texts'] ?? ''), true);
            if (!is_array($changes)) throw new InvalidArgumentException('Invalid department text changes.');
            $fields = saveDepartmentPageContent((int)($_POST['dept_id'] ?? 0), $changes);
            echo json_encode(['success' => true, 'fields' => $fields]);
            break;
        case 'save_department_office_location':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRFToken((string)($_POST['csrf_token'] ?? '')) || ($_SESSION['user_role'] ?? '') !== 'Super Admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Only Super Admin with a valid session can update office locations.']);
                break;
            }
            try {
                $input = json_decode((string)($_POST['location'] ?? ''), true);
                if (!is_array($input)) throw new InvalidArgumentException('Invalid office location.');
                $payload = saveDepartmentOfficeLocation((int)($_POST['dept_id'] ?? 0), $input);
                echo json_encode(['success' => true, 'department' => $payload]);
            } catch (InvalidArgumentException $e) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            } catch (Throwable $e) {
                error_log('Office location save: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Could not save the office location. Check that the office location database migration has been applied.']);
            }
            break;
        case 'save_basic_education_content':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRFToken((string)($_POST['csrf_token'] ?? ''))) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Please refresh the page and try again.']);
                break;
            }
            $texts = json_decode((string)($_POST['texts'] ?? '{}'), true);
            if (!is_array($texts)) throw new Exception('Invalid text changes.');
            saveBasicEducationContent((string)($_POST['page'] ?? ''), $texts, $_FILES);
            echo json_encode(['success' => true]);
            break;

        // ══════════════════ Hero Slideshow (merged from hero-slides-handler.php) ══════════════════

        // ── LIST all slides (used to rebuild the left-hand slide order list) ──
        case 'list': {
            $slides = getHeroSlides(false);
            echo json_encode(['success' => true, 'slides' => $slides]);
            break;
        }

        // ── GET a single slide's full details ──
        case 'get': {
            $id = (int)($_GET['id'] ?? 0);
            $slide = getHeroSlideById($id);
            if (!$slide) {
                http_response_code(404);
                echo json_encode(['error' => 'Slide not found.']);
                break;
            }
            echo json_encode(['success' => true, 'slide' => $slide]);
            break;
        }

        // ── SAVE (create when no id, update when id is present) ──
        case 'save': {
            $id = (int)($_POST['slide_id'] ?? 0);

            $data = [
                'title'          => trim($_POST['title'] ?? ''),
                'subtitle'       => richTextNormalize((string)($_POST['subtitle'] ?? '')),
                'btn1_text'      => trim($_POST['btn1_text'] ?? ''),
                'btn1_link'      => trim($_POST['btn1_link'] ?? ''),
                'btn2_text'      => trim($_POST['btn2_text'] ?? ''),
                'btn2_link'      => trim($_POST['btn2_link'] ?? ''),
                'status'         => in_array($_POST['status'] ?? '', ['published', 'draft'], true) ? $_POST['status'] : 'published',
                'show_on_mobile' => isset($_POST['show_on_mobile']) && $_POST['show_on_mobile'] === '1',
                'show_gradient'  => isset($_POST['show_gradient']) && $_POST['show_gradient'] === '1',
            ];

            // Basic length guards to match the reference UI's character limits
            if (mb_strlen($data['title']) > 100)    $data['title']    = mb_substr($data['title'], 0, 100);
            if (mb_strlen($data['subtitle']) > 150) throw new Exception('The slide description is too long (maximum 150 characters including formatting).');
            if (mb_strlen($data['btn1_text']) > 30) $data['btn1_text'] = mb_substr($data['btn1_text'], 0, 30);
            if (mb_strlen($data['btn2_text']) > 30) $data['btn2_text'] = mb_substr($data['btn2_text'], 0, 30);

            // Desktop background — image or video, unchanged behavior.
            $media = isset($_FILES['media']) ? uploadHeroMedia($_FILES['media']) : null;

            // Mobile background — completely independent of the desktop upload above.
            // Restricted to images server-side (imageOnly = true), matching the admin UI.
            $mobileMedia = isset($_FILES['media_mobile']) ? uploadHeroMedia($_FILES['media_mobile'], true) : null;
            $removeMobileMedia = isset($_POST['remove_mobile_media']) && $_POST['remove_mobile_media'] === '1';

            if ($id > 0) {
                // Updating an existing slide
                if ($media === null && empty(getHeroSlideById($id)['media_path'] ?? null)) {
                    echo json_encode(['error' => 'A background image or video is required.']);
                    break;
                }
                $ok = updateHeroSlide($id, $data, $media, $mobileMedia, $removeMobileMedia);
                if (!$ok) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Could not update the slide.']);
                    break;
                }
                echo json_encode(['success' => true, 'slide_id' => $id]);
            } else {
                // Creating a new slide — desktop media is required, mobile image is optional
                if ($media === null) {
                    echo json_encode(['error' => 'Please upload a background image or video for the new slide.']);
                    break;
                }
                $newId = createHeroSlide($data, $media, $mobileMedia);
                if ($newId === false) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Could not create the slide.']);
                    break;
                }
                echo json_encode(['success' => true, 'slide_id' => $newId]);
            }
            break;
        }

        // ── DELETE a slide ──
        case 'delete': {
            $id = (int)($_POST['slide_id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['error' => 'Missing slide id.']);
                break;
            }
            $ok = deleteHeroSlide($id);
            if (!$ok) {
                http_response_code(400);
                echo json_encode(['error' => 'Could not delete the slide.']);
                break;
            }
            echo json_encode(['success' => true]);
            break;
        }

        // ── REORDER slides (drag & drop) ──
        case 'reorder': {
            $orderJson = $_POST['order'] ?? '[]';
            $orderedIds = json_decode($orderJson, true);
            if (!is_array($orderedIds) || empty($orderedIds)) {
                echo json_encode(['error' => 'Invalid order data.']);
                break;
            }
            $ok = reorderHeroSlides($orderedIds);
            if (!$ok) {
                http_response_code(400);
                echo json_encode(['error' => 'Could not save the new order.']);
                break;
            }
            echo json_encode(['success' => true]);
            break;
        }

        // ══════════════════ Page Content CMS ══════════════════

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
        case 'save_section_image':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(419);
                echo json_encode(['success' => false, 'error' => 'Your session has expired. Please refresh and try again.']);
                break;
            }
            if (($_POST['page'] ?? '') !== 'history') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Images can only be changed for History timeline entries here.']);
                break;
            }
            try {
                $path = saveHistorySectionImage(trim((string)($_POST['section_key'] ?? '')), $_FILES['image'] ?? null, ($_POST['remove_image'] ?? '') === '1');
                echo json_encode(['success' => true, 'image_url' => $path]);
            } catch (Throwable $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
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

        // ── College Departments: save logo changes for one department ──
        // Multipart POST: dept_id, optional logo file, optional remove_logo
        // flag ('1').
        case 'save_department_media':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(419);
                echo json_encode(['success' => false, 'error' => 'Your session has expired. Please refresh and try again.']);
                break;
            }
            $deptId = (int)($_POST['dept_id'] ?? 0);
            if ($deptId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing department.']);
                break;
            }

            try {
                $dept = saveDepartmentMedia(
                    $deptId,
                    $_FILES['logo'] ?? null,
                    ($_POST['remove_logo'] ?? '') === '1'
                );
                echo json_encode([
                    'success'    => true,
                    'message'    => 'Department saved.',
                    'department' => departmentAdminPayload($dept),
                ]);
            } catch (Exception $e) {
                // saveDepartmentMedia() only throws user-safe messages
                // (bad file type, too large, not found, ...).
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        // ── College Departments: rename a department ──
        // POST: dept_id, name. Only the display name changes; the slug (page
        // filename / links) stays the same.
        case 'save_department_name':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(419);
                echo json_encode(['success' => false, 'error' => 'Your session has expired. Please refresh and try again.']);
                break;
            }
            $deptId = (int)($_POST['dept_id'] ?? 0);
            if ($deptId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing department.']);
                break;
            }

            try {
                $dept = saveDepartmentName($deptId, $_POST['name'] ?? '');
                echo json_encode([
                    'success'    => true,
                    'message'    => 'Department name saved.',
                    'department' => departmentAdminPayload($dept),
                ]);
            } catch (Exception $e) {
                // saveDepartmentName() only throws user-safe messages
                // (missing name, too long, duplicate, not found, ...).
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        // ── College Programs: upload / replace / remove one program's curriculum PDF ──
        // Multipart POST: program_id, optional pdf file, optional remove_pdf
        // flag ('1').
        case 'save_program_pdf':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(419);
                echo json_encode(['success' => false, 'error' => 'Your session has expired. Please refresh and try again.']);
                break;
            }
            $programId = (int)($_POST['program_id'] ?? 0);
            if ($programId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing program.']);
                break;
            }

            try {
                $program = saveProgramPdf(
                    $programId,
                    $_FILES['pdf'] ?? null,
                    ($_POST['remove_pdf'] ?? '') === '1'
                );
                echo json_encode([
                    'success' => true,
                    'message' => 'Curriculum PDF saved.',
                    'program' => programAdminPayload($program),
                ]);
            } catch (Exception $e) {
                // saveProgramPdf() only throws user-safe messages
                // (bad file type, too large, not found, ...).
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        // ── College Departments: save "Head of Office" (Dean) info ──
        // Multipart POST: dept_id, dean_name, dean_title, dean_bio, dean_phone,
        // dean_email, dean_office, dean_hours, optional photo file, optional
        // remove_photo flag ('1'). Everything is applied at once.
        case 'save_department_dean':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(419);
                echo json_encode(['success' => false, 'error' => 'Your session has expired. Please refresh and try again.']);
                break;
            }
            $deptId = (int)($_POST['dept_id'] ?? 0);
            if ($deptId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing department.']);
                break;
            }

            try {
                $dept = saveDepartmentDean(
                    $deptId,
                    $_POST['dean_name']   ?? '',
                    $_POST['dean_title']  ?? '',
                    $_POST['dean_bio']    ?? '',
                    $_POST['dean_phone']  ?? '',
                    $_POST['dean_email']  ?? '',
                    $_POST['dean_office'] ?? '',
                    $_POST['dean_hours']  ?? '',
                    $_FILES['dean_photo'] ?? null,
                    ($_POST['remove_photo'] ?? '') === '1'
                );
                echo json_encode([
                    'success'    => true,
                    'message'    => 'Head of Office saved.',
                    'department' => departmentAdminPayload($dept),
                ]);
            } catch (Exception $e) {
                // saveDepartmentDean() only throws user-safe messages
                // (bad file type, too large, not found, ...).
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        // ── Homepage: save "Message from the President" section ──
        // Multipart POST: name, title, org, eyebrow, heading, greeting,
        // message, optional photo file, optional remove_photo flag ('1').
        case 'save_homepage_president':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(419);
                echo json_encode(['success' => false, 'error' => 'Your session has expired. Please refresh and try again.']);
                break;
            }

            try {
                $row = saveHomepagePresident(
                    $_POST['name']     ?? '',
                    $_POST['title']    ?? '',
                    $_POST['org']      ?? '',
                    $_POST['eyebrow']  ?? '',
                    $_POST['heading']  ?? '',
                    $_POST['greeting'] ?? '',
                    $_POST['message']  ?? '',
                    $_FILES['photo']   ?? null,
                    ($_POST['remove_photo'] ?? '') === '1'
                );
                echo json_encode([
                    'success'   => true,
                    'message'   => 'President section saved.',
                    'president' => homepagePresidentAdminPayload($row),
                ]);
            } catch (Exception $e) {
                // saveHomepagePresident() only throws user-safe messages
                // (bad file type, too large, ...).
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        // ── Homepage: save edited headings / intro lines / program cards ──
        // Multipart POST: texts = JSON {key: new text} (only what changed); optional picture files named
        // img_<key>; reset_images = JSON list of keys whose picture goes back to the original.
        case 'save_home_content':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(419);
                echo json_encode(['success' => false, 'error' => 'Your session has expired. Please refresh and try again.']);
                break;
            }
            try {
                $texts  = json_decode((string)($_POST['texts'] ?? '{}'), true);
                $resets = json_decode((string)($_POST['reset_images'] ?? '[]'), true);
                if (!is_array($texts) || !is_array($resets)) throw new Exception('The changes could not be read. Please try again.');
                $images = [];
                foreach ($_FILES as $field => $file) {
                    if (strpos($field, 'img_') === 0 && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $images[substr($field, 4)] = $file;
                }
                foreach ($resets as $k) { if (is_string($k) && !isset($images[$k])) $images[$k] = 'reset'; }
                $content = saveHomeContent($texts, $images);
                echo json_encode(['success' => true, 'message' => 'Homepage saved.', 'content' => $content]);
            } catch (Exception $e) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        // ══════════════════ Administration page (Content Editor → Administration) ══════════════════
        // Every admin_* action below needs a valid CSRF token, and each one
        // answers with the refreshed list of sections so the editor can
        // simply re-render. Multipart POSTs carry the optional photo.

        // ── Edit a tab's title + description ──
        case 'admin_save_section':
        // ── Add / edit one person card (Trustees, Officers, Administration) ──
        case 'admin_save_person':
        // ── Add / edit one Heads of Student Services office ──
        case 'admin_save_office':
        // ── Delete a person card / an office ──
        case 'admin_delete_person':
        case 'admin_delete_office':
        // ── Archive (hide from the website, keep) or restore a person card / an office ──
        case 'admin_archive_person':
        case 'admin_archive_office':
        // ── Drag-and-drop order for the cards of one section ──
        case 'admin_reorder':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(419);
                echo json_encode(['success' => false, 'error' => 'Your session has expired. Please refresh and try again.']);
                break;
            }

            try {
                $message = 'Saved.';
                if ($action === 'admin_save_section') {
                    saveAdministrationSection(
                        trim($_POST['section_key'] ?? ''),
                        $_POST['title'] ?? '',
                        $_POST['description'] ?? ''
                    );
                    $message = 'Section saved.';
                } elseif ($action === 'admin_save_person') {
                    saveAdministrationPerson(
                        (int)($_POST['person_id'] ?? 0),
                        trim($_POST['section_key'] ?? ''),
                        $_POST['name'] ?? '',
                        $_POST['role'] ?? '',
                        $_FILES['photo'] ?? null,
                        ($_POST['remove_photo'] ?? '') === '1'
                    );
                    $message = 'Saved.';
                } elseif ($action === 'admin_save_office') {
                    saveAdministrationOffice(
                        (int)($_POST['office_id'] ?? 0),
                        trim($_POST['section_key'] ?? ''),
                        $_POST['office_name'] ?? '',
                        $_POST['head_name'] ?? '',
                        $_POST['head_role'] ?? '',
                        $_POST['phone'] ?? '',
                        $_POST['email'] ?? '',
                        $_POST['location'] ?? '',
                        $_POST['hours'] ?? '',
                        $_POST['services'] ?? '',
                        $_FILES['photo'] ?? null,
                        ($_POST['remove_photo'] ?? '') === '1'
                    );
                    $message = 'Office saved.';
                } elseif ($action === 'admin_delete_person') {
                    deleteAdministrationPerson((int)($_POST['person_id'] ?? 0));
                    $message = 'Deleted.';
                } elseif ($action === 'admin_delete_office') {
                    deleteAdministrationOffice((int)($_POST['office_id'] ?? 0));
                    $message = 'Office deleted.';
                } elseif ($action === 'admin_archive_person' || $action === 'admin_archive_office') {
                    // archived = '1' archives (default), '0' restores
                    $archive = ($_POST['archived'] ?? '1') !== '0';
                    if ($action === 'admin_archive_person') {
                        archiveAdministrationPerson((int)($_POST['person_id'] ?? 0), $archive);
                    } else {
                        archiveAdministrationOffice((int)($_POST['office_id'] ?? 0), $archive);
                    }
                    $message = $archive
                        ? 'Archived. It is hidden from the website; restore it any time from the Archived list.'
                        : 'Restored. It is showing on the website again.';
                } else { // admin_reorder
                    $ids = json_decode($_POST['order'] ?? '[]', true);
                    if (!is_array($ids) || empty($ids)) {
                        throw new Exception('Invalid order data.');
                    }
                    reorderAdministration(trim($_POST['section_key'] ?? ''), $ids);
                    $message = 'Order saved.';
                }

                echo json_encode([
                    'success'  => true,
                    'message'  => $message,
                    'sections' => administrationAdminPayload(),
                ]);
            } catch (Exception $e) {
                // The Administration functions only throw user-safe messages
                // (validation, bad file type, too large, not found, ...).
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        // ── College Departments: create a department, or update one's details ──
        // POST: dept_id (0 = create), dept_name, description, hero_text,
        // about_text, facebook_url, office_label, theme_color.
        case 'save_department_details':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(419);
                echo json_encode(['success' => false, 'error' => 'Your session has expired. Please refresh and try again.']);
                break;
            }

            try {
                $isNew = (int)($_POST['dept_id'] ?? 0) <= 0;
                $dept  = saveDepartmentDetails([
                    'dept_id'      => (int)($_POST['dept_id'] ?? 0),
                    'dept_name'    => $_POST['dept_name']    ?? '',
                    'description'  => $_POST['description']  ?? '',
                    'hero_text'    => $_POST['hero_text']    ?? '',
                    'about_text'   => $_POST['about_text']   ?? '',
                    'facebook_url' => $_POST['facebook_url'] ?? '',
                    'office_label' => $_POST['office_label'] ?? '',
                    'theme_color'  => $_POST['theme_color']  ?? '',
                ]);
                echo json_encode([
                    'success'    => true,
                    'message'    => $isNew
                        ? 'Department created. It is now live on the College Departments page — add its logo and page text below.'
                        : 'Department details saved.',
                    'department' => departmentAdminPayload($dept),
                ]);
            } catch (Exception $e) {
                // saveDepartmentDetails() only throws user-safe messages.
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        // ── College Departments: permanently delete one ──
        // Programs/announcements that belong to it are detached (their
        // dept_id becomes NULL), not deleted.
        case 'delete_department':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(419);
                echo json_encode(['success' => false, 'error' => 'Your session has expired. Please refresh and try again.']);
                break;
            }
            $deptId = (int)($_POST['dept_id'] ?? 0);
            if ($deptId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing department.']);
                break;
            }

            try {
                deleteDepartment($deptId);
                echo json_encode([
                    'success' => true,
                    'message' => 'Department deleted. It no longer appears on the website.',
                ]);
            } catch (Exception $e) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        // ── College Departments: hide from / restore to the public website ──
        // archived = '1' archives (default), '0' restores.
        case 'archive_department':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(419);
                echo json_encode(['success' => false, 'error' => 'Your session has expired. Please refresh and try again.']);
                break;
            }
            $deptId = (int)($_POST['dept_id'] ?? 0);
            if ($deptId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing department.']);
                break;
            }

            try {
                $archive = ($_POST['archived'] ?? '1') !== '0';
                $dept    = setDepartmentArchived($deptId, $archive);
                echo json_encode([
                    'success'    => true,
                    'message'    => $archive
                        ? 'Department hidden from the website. Restore it any time.'
                        : 'Department restored. It is showing on the website again.',
                    'department' => departmentAdminPayload($dept),
                ]);
            } catch (Exception $e) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        // ── College Programs: create / update a program (code, department, description, card image) ──
        // Multipart POST: program_id (0 = create), program_code, dept_id, description,
        // optional image file, optional remove_image flag ('1').
        case 'save_program':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(419);
                echo json_encode(['success' => false, 'error' => 'Your session has expired. Please refresh and try again.']);
                break;
            }

            try {
                $isNew   = (int)($_POST['program_id'] ?? 0) <= 0;
                $program = saveProgramFull([
                    'program_id'   => (int)($_POST['program_id'] ?? 0),
                    'program_code' => $_POST['program_code'] ?? '',
                    'dept_id'      => (int)($_POST['dept_id'] ?? 0),
                    'description'  => $_POST['description'] ?? '',
                ], $_FILES['image'] ?? null, ($_POST['remove_image'] ?? '') === '1');

                echo json_encode([
                    'success' => true,
                    'message' => $isNew ? 'Program added.' : 'Program saved.',
                    'program' => programFullAdminPayload($program),
                ]);
            } catch (Exception $e) {
                // saveProgramFull() only throws user-safe messages.
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        // ── College Programs: permanently delete one ──
        case 'delete_program':
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                http_response_code(419);
                echo json_encode(['success' => false, 'error' => 'Your session has expired. Please refresh and try again.']);
                break;
            }
            $programId = (int)($_POST['program_id'] ?? 0);
            if ($programId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing program.']);
                break;
            }

            try {
                deleteProgramFull($programId);
                echo json_encode(['success' => true, 'message' => 'Program deleted.']);
            } catch (Exception $e) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
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
