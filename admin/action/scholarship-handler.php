<?php
require_once __DIR__ . '/../../components/scholarship-content.php';
require_once __DIR__ . '/../../includes/image-upload.php';
initSession();
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Use POST.']); exit; }
if (!isLoggedIn() || !hasPermission('manage_page_content')) { http_response_code(403); echo json_encode(['error'=>'You cannot publish scholarships.']); exit; }
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) { http_response_code(413); echo json_encode(['error'=>'The upload exceeds the server request limit. Try a smaller PDF.']); exit; }
if (!verifyCSRFToken((string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); echo json_encode(['error'=>'Refresh the editor and try again.']); exit; }
$db = getDB();
$newFiles = [];
try {
    $texts = json_decode((string)($_POST['texts'] ?? '{}'), true, 16, JSON_THROW_ON_ERROR);
    // Obsolete photo payloads from old drafts are ignored; only PDFs are accepted.
    if (!is_array($texts)) throw new InvalidArgumentException('Invalid scholarship changes.');
    $texts = scholarshipValidateTexts($texts);
    $categories=scholarshipCategories($db);
    $categoryPayload=null;
    if(isset($_POST['categories'])) {
        $categoryPayload=json_decode((string)$_POST['categories'],true,16,JSON_THROW_ON_ERROR);
        if(!is_array($categoryPayload))throw new InvalidArgumentException('Invalid scholarship categories.');
        $categories=scholarshipValidateCategories($categoryPayload);
        if(!isset($_POST['entries']))throw new InvalidArgumentException('Include the scholarship opportunities with category changes.');
    }
    $entries = null;
    if (isset($_POST['entries'])) {
        $entries = json_decode((string)$_POST['entries'], true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($entries)) throw new InvalidArgumentException('Invalid scholarship entries.');
        $entries = scholarshipValidateEntries($entries,$categories);
    }
    $db->beginTransaction();
    $save = $db->prepare('INSERT INTO tuition_fee_content (field_key,content) VALUES (?,?) ON DUPLICATE KEY UPDATE content=VALUES(content)');
    foreach ($texts as $key => $value) $save->execute(['scholarship_text_' . $key, $value]);
    if ($entries !== null) {
        $existing = [];
        foreach (scholarshipCatalog($db) as $entry) $existing[$entry['id']] = $entry;
        foreach ($entries as &$entry) {
            $old = $existing[$entry['id']] ?? null;
            if (($entry['legacy']??null)!==($old['legacy']??null)) throw new InvalidArgumentException('The scholarship information reference cannot be changed.');
            $upload = $_FILES['pdf_' . $entry['id']] ?? null;
            if ($upload) {
                if (!is_int($upload['error']) || $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) throw new InvalidArgumentException('The PDF upload failed. Try again.');
                $size = filesize($upload['tmp_name']);
                if (!$size || $size > 20 * 1024 * 1024 || strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)) !== 'pdf' || !uploadPdfSignatureValid($upload['tmp_name']) || imageUploadMime($upload['tmp_name']) !== 'application/pdf') throw new InvalidArgumentException('Upload a valid PDF of up to 20 MB.');
                $folder = dirname(__DIR__, 2) . '/uploads/scholarships';
                if (!is_dir($folder) && !mkdir($folder,0755,true) && !is_dir($folder)) throw new RuntimeException('Could not create scholarship upload folder.');
                $entry['pdf'] = '/uploads/scholarships/' . bin2hex(random_bytes(16)) . '.pdf';
                $target = dirname(__DIR__, 2) . $entry['pdf'];
                if (!move_uploaded_file($upload['tmp_name'], $target)) throw new RuntimeException('Could not store scholarship PDF.');
                $newFiles[] = $target;
            } else {
                if (($entry['pdf'] ?? '') !== ($old['pdf'] ?? '')) throw new InvalidArgumentException('Upload the PDF instead of entering its path.');
            }
        }
        unset($entry);
        $entries = scholarshipValidateEntries($entries,$categories);
        $save->execute(['scholarship_entries', json_encode($entries, JSON_THROW_ON_ERROR)]);
        if($categoryPayload!==null)$save->execute(['scholarship_catalog_version','1']);
    }
    if($categoryPayload!==null)scholarshipSaveCategories($db,$categories);
    if ($texts || $entries !== null) logActivity((int)$_SESSION['user_id'], 'UPDATE', 'tuition_fee_content', null, 'Published scholarship opportunities');
    $db->commit();
    echo json_encode(['success'=>true]);
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    foreach ($newFiles as $file) @unlink($file);
    $expected = $error instanceof InvalidArgumentException || $error instanceof JsonException;
    if (!$expected) error_log('Scholarship publishing: ' . $error->getMessage());
    http_response_code(422);
    echo json_encode(['error'=>$expected ? $error->getMessage() : 'Could not publish scholarships. Check the content table and upload folder, then try again.']);
}
