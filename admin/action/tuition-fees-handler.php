<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';
require_once __DIR__ . '/../../components/tuition-fee-documents.php';
initSession();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Use POST.']); exit; }
if (!isLoggedIn() || !hasPermission('manage_page_content')) { http_response_code(403); echo json_encode(['error'=>'You cannot publish tuition information.']); exit; }
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) { http_response_code(413); echo json_encode(['error'=>'The upload exceeds the server request limit. Try smaller PDFs.']); exit; }
if (!verifyCSRFToken((string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); echo json_encode(['error'=>'Refresh the editor and try again.']); exit; }
$db = getDB(); $newFiles = []; $oldFiles = [];
try {
    $texts = json_decode((string)($_POST['texts'] ?? '{}'), true, 32, JSON_THROW_ON_ERROR);
    $changes = json_decode((string)($_POST['documents'] ?? '[]'), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($texts) || !is_array($changes) || count($changes)>50) throw new InvalidArgumentException('Invalid tuition changes.');
    if (($texts || $changes) && !function_exists('mb_strlen')) {
        throw new InvalidArgumentException('Enable the PHP mbstring extension in your hosting PHP settings, then publish again.');
    }
    $fields = tuitionFeeFields(); $categories = tuitionFeeCategories();
    foreach ($texts as $key => &$value) {
        if (!isset($fields[$key]) || !is_string($value)) throw new InvalidArgumentException('Unknown tuition text field.');
        if (($fields[$key]['type'] ?? '') === 'icon') {
            $value = trim($value);
            if (!isset(tuitionFeeIconChoices()[$value])) throw new InvalidArgumentException('Choose an icon from the library.');
            continue;
        }
        $value = $fields[$key]['max'] > 255 ? richTextNormalize($value) : trim(strip_tags($value));
        if (mb_strlen($value)>$fields[$key]['max']) throw new InvalidArgumentException('The tuition text is too long.');
    } unset($value);
    $db->beginTransaction();
    foreach ($texts as $key => $value) $db->prepare('INSERT INTO tuition_fee_content (field_key,content) VALUES (?,?) ON DUPLICATE KEY UPDATE content=VALUES(content)')->execute([$key,$value]);
    $seen = [];
    foreach ($changes as $index => $change) {
        if (!is_array($change) || !isset($categories[$change['category'] ?? ''])) throw new InvalidArgumentException('Invalid fee category.');
        $id = filter_var($change['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($id === false || $id<0 || ($id && isset($seen[$id]))) throw new InvalidArgumentException('Invalid document.');
        $seen[$id] = true;
        $old = null;
        if ($id) {
            $query=$db->prepare('SELECT * FROM tuition_fee_documents WHERE document_id=? FOR UPDATE');$query->execute([$id]);$old=$query->fetch(PDO::FETCH_ASSOC);
            if (!$old || $old['category_key']!==$change['category']) throw new InvalidArgumentException('The document changed. Reload the editor.');
        }
        if (!empty($change['remove'])) {
            if ($old) { $db->prepare('DELETE FROM tuition_fee_documents WHERE document_id=?')->execute([$id]); $oldFiles[]=$old['file_path']; }
            continue;
        }
        $title=$change['title'] ?? '';
        if (!is_string($title) || (trim($title)==='' && !contentPublishAllowsIncomplete()) || mb_strlen($title)>255) throw new InvalidArgumentException('Enter a document title of up to 255 characters.');
        $path=$old['file_path'] ?? null; $size=$old['file_size'] ?? 0;
        $upload=$_FILES['pdf_'.$index] ?? null;
        if ($upload) {
            if (!class_exists('finfo')) throw new InvalidArgumentException('PDF uploads require the PHP Fileinfo extension. Enable fileinfo in your hosting PHP settings, then publish again.');
            if (is_array($upload['error']) || $upload['error']!==UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) throw new InvalidArgumentException('The PDF upload failed. Try again.');
            $size=filesize($upload['tmp_name']);
            if ($size>20*1024*1024 || !$size || strtolower(pathinfo($upload['name'],PATHINFO_EXTENSION))!=='pdf'
                || (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name'])!=='application/pdf'
                || file_get_contents($upload['tmp_name'],false,null,0,5)!=='%PDF-') throw new InvalidArgumentException('Upload a valid PDF of up to 20 MB.');
            $folder=dirname(__DIR__,2).'/uploads/tuition-fees/'.$change['category'];
            if (!is_dir($folder) && !@mkdir($folder,0755,true) && !is_dir($folder)) throw new InvalidArgumentException('The tuition PDF folder could not be created. Ask your hosting provider to make uploads/tuition-fees writable by PHP.');
            if (!is_writable($folder)) throw new InvalidArgumentException('The tuition PDF folder is not writable. Ask your hosting provider to check ownership and permissions for uploads/tuition-fees and its category folders.');
            $path='/uploads/tuition-fees/'.$change['category'].'/'.bin2hex(random_bytes(16)).'.pdf';
            $target=dirname(__DIR__,2).$path;
            if (!@move_uploaded_file($upload['tmp_name'],$target)) throw new InvalidArgumentException('The server could not store the PDF. Ask your hosting provider to check the tuition upload folder permissions and available disk space.');
            $newFiles[]=$target;
            if ($old) $oldFiles[]=$old['file_path'];
        }
        if (!$path) throw new InvalidArgumentException('Choose a PDF for the new document.');
        if ($old) $db->prepare('UPDATE tuition_fee_documents SET title=?,file_path=?,file_size=? WHERE document_id=?')->execute([trim($title),$path,$size,$id]);
        else $db->prepare('INSERT INTO tuition_fee_documents (category_key,title,file_path,file_size) VALUES (?,?,?,?)')->execute([$change['category'],trim($title),$path,$size]);
    }
    logActivity((int)$_SESSION['user_id'],'UPDATE','tuition_fee_content',null,'Published tuition information');
    $db->commit();
    foreach ($oldFiles as $path) {
        try {
            $check=$db->prepare('SELECT 1 FROM tuition_fee_documents WHERE file_path=?');$check->execute([$path]);
            if (!$check->fetchColumn() && preg_match('#^/uploads/tuition-fees/[a-z-]+/[a-f0-9]{32}\.pdf$#D',$path)) @unlink(dirname(__DIR__,2).$path);
        } catch (Throwable $cleanupError) { error_log('Tuition old PDF cleanup failed.'); }
    }
    echo json_encode(['success'=>true]);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    foreach ($newFiles as $file) @unlink($file);
    $expected=$e instanceof InvalidArgumentException || $e instanceof JsonException;
    if (!$expected) error_log('Tuition publishing: '.$e->getMessage());
    http_response_code(422);echo json_encode(['error'=>$expected?$e->getMessage():'Could not publish tuition changes. Please try again.']);
}
