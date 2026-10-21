<?php
require_once __DIR__ . '/../admin-functions.php';
initSession();
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Use POST.']); exit; }
if (!isLoggedIn() || !hasPermission('manage_page_content')) { http_response_code(403); echo json_encode(['error'=>'You cannot publish admission categories.']); exit; }
if (!verifyCSRFToken((string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); echo json_encode(['error'=>'Refresh the editor and try again.']); exit; }
try {
    if (($_POST['action'] ?? '') === 'publish_page') {
        require_once __DIR__ . '/../../components/admission-page-manager.php';
        $changes = json_decode((string)($_POST['sections'] ?? '[]'),true,32,JSON_THROW_ON_ERROR);
        $orders = json_decode((string)($_POST['orders'] ?? '{}'),true,32,JSON_THROW_ON_ERROR);
        if (!is_array($changes) || !array_is_list($changes) || !is_array($orders)) throw new InvalidArgumentException('Invalid admission changes.');
        $updated = publishAdmissionPage((string)($_POST['page'] ?? ''),$changes,$orders);
        echo json_encode(['success'=>true,'sections'=>$updated]);
        exit;
    }
    $page = (string)($_POST['page'] ?? '');
    $key = (string)($_POST['section_key'] ?? '');
    $pattern = match ($page) {
        'college-admission-requirements' => '/^custom_[a-f0-9]{16}$/D',
        'basic-education-admission-requirements' => '/^(casa|montessori|jhs-regular|shs-regular)_custom_[a-f0-9]{16}$/D',
        default => throw new InvalidArgumentException('Choose an admission requirements page.'),
    };
    if (!preg_match($pattern, $key)) throw new InvalidArgumentException('Invalid admission category.');
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '' || mb_strlen($title) > 150) throw new InvalidArgumentException('Enter a category name of up to 150 characters.');
    $raw = (string)($_POST['content'] ?? '');
    if (strlen($raw) > 100000) throw new InvalidArgumentException('The category content is too long.');
    $content = richTextToStorage($raw);
    $db = getDB();
    $db->beginTransaction();
    try {
        $query = $db->prepare("SELECT page_id FROM pages WHERE slug = ? AND status = 'active' FOR UPDATE");
        $query->execute([$page]);
        $id = $query->fetchColumn();
        if (!$id) throw new InvalidArgumentException('Admission page not found.');
        $query = $db->prepare('SELECT section_id FROM page_sections WHERE page_id = ? AND section_key = ?');
        $query->execute([$id, $key]);
        $existing = $query->fetchColumn();
        if ($existing) {
            // A retry with the same generated key updates the category instead of duplicating it.
            $db->prepare("UPDATE page_sections SET title=?,content=?,draft_content=NULL,status='published',updated_by=? WHERE section_id=?")
                ->execute([$title, $content, $_SESSION['user_id'], $existing]);
        } else {
            $query = $db->prepare('SELECT COALESCE(MAX(display_order),0)+1 FROM page_sections WHERE page_id=?');
            $query->execute([$id]);
            $order = (int)$query->fetchColumn();
            $db->prepare("INSERT INTO page_sections (page_id,section_key,title,content,status,display_order,updated_by) VALUES (?,?,?,?,'published',?,?)")
                ->execute([$id,$key,$title,$content,$order,$_SESSION['user_id']]);
        }
        logActivity($_SESSION['user_id'],'PUBLISH','page_sections',null,'Published admission category: '.$page.'/'.$key);
        $db->commit();
    } catch (Throwable $error) { $db->rollBack(); throw $error; }
    echo json_encode(['success'=>true,'has_draft'=>false,'review_status'=>'none','editor_html'=>sectionToEditorHtml($content)]);
} catch (Throwable $error) {
    if (!$error instanceof InvalidArgumentException) error_log('Admission category: '.$error->getMessage());
    http_response_code(422);
    echo json_encode(['error'=>$error instanceof InvalidArgumentException ? $error->getMessage() : 'Could not publish this category. Please try again.']);
}
