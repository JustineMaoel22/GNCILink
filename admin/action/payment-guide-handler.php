<?php
require_once __DIR__ . '/../../components/payment-guide-content.php';
initSession();
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Use POST.']); exit; }
if (!isLoggedIn() || !hasPermission('manage_page_content')) { http_response_code(403); echo json_encode(['error' => 'You cannot publish the payment guide.']); exit; }
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) { http_response_code(413); echo json_encode(['error' => 'The upload exceeds the server request limit. Try smaller images.']); exit; }
if (!verifyCSRFToken((string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); echo json_encode(['error' => 'Refresh the editor and try again.']); exit; }
$db = getDB(); $newFiles = []; $oldFiles = [];
try {
    $changes = json_decode((string)($_POST['images'] ?? '{}'), true, 16, JSON_THROW_ON_ERROR);
    $texts = json_decode((string)($_POST['texts'] ?? '{}'), true, 16, JSON_THROW_ON_ERROR);
    if (!class_exists('DOMDocument') || !method_exists('DOMDocument', 'loadXML') || !method_exists('DOMDocument', 'loadHTML')) {
        throw new InvalidArgumentException('Publishing the payment guide requires the PHP DOM/XML extension on your host. Enable it in your hosting PHP settings and try again.');
    }
    $fields = paymentGuideFieldDefaults();
    if (!is_array($texts) || count($texts) > count($fields)) throw new InvalidArgumentException('Invalid payment guide text changes.');
    foreach ($texts as $key => &$value) {
        if (!isset($fields[$key]) || !is_string($value)) throw new InvalidArgumentException('Unknown payment guide text field.');
        $field = $fields[$key];
        if ($field['type'] === 'url') {
            $value = trim($value);
            if ($value !== '' && !paymentGuideValidUrl($value)) throw new InvalidArgumentException('Enter an HTTP or HTTPS URL, or a local path starting with /.');
        } else {
            $value = $field['type'] === 'rich' ? richTextNormalize($value) : trim(strip_tags($value));
        }
        if (mb_strlen($value) > $field['max']) throw new InvalidArgumentException($field['label'] . ' is too long.');
    }
    unset($value);
    $defaults = paymentGuideImageDefaults();
    if (!is_array($changes) || count($changes) > count($defaults)) throw new InvalidArgumentException('Invalid illustration changes.');
    $db->beginTransaction();
    foreach ($texts as $key => $value) $db->prepare('INSERT INTO tuition_fee_content (field_key,content) VALUES (?,?) ON DUPLICATE KEY UPDATE content=VALUES(content)')->execute(['payment_text_' . $key, $value]);
    foreach ($changes as $key => $change) {
        if (!isset($defaults[$key]) || !is_array($change) || !isset($change['reset']) || !is_bool($change['reset'])) throw new InvalidArgumentException('Unknown illustration or invalid change.');
        $field = 'payment_image_' . $key;
        $query = $db->prepare('SELECT content FROM tuition_fee_content WHERE field_key=? FOR UPDATE');
        $query->execute([$field]); $oldFiles[] = $query->fetchColumn();
        if ($change['reset']) {
            $db->prepare('DELETE FROM tuition_fee_content WHERE field_key=?')->execute([$field]);
            continue;
        }
        $file = $_FILES['image_' . $key] ?? null;
        if (!$file || is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('Choose an illustration to upload.');
        $size = filesize($file['tmp_name']);
        if (!$size) throw new InvalidArgumentException('Choose an image of up to 5 MB.');
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'svg') {
            if ($size > IMAGE_UPLOAD_MAX_BYTES) throw new InvalidArgumentException('Choose an image of up to 5 MB.');
            if (!sanitizeSvgFile($file['tmp_name'])) throw new InvalidArgumentException('Invalid SVG. Export the illustration again and retry.');
            $extension = 'svg';
        } else {
            $file = imageUploadPrepare($file);
            if ($file['size'] > IMAGE_UPLOAD_MAX_BYTES) throw new InvalidArgumentException('Choose an image of up to 5 MB.');
            $types = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
            $detected = @getimagesize($file['tmp_name']);
            $mime = $detected['mime'] ?? '';
            if (!isset($types[$mime])) throw new InvalidArgumentException('Upload an SVG, PNG, JPG, or WEBP illustration.');
            $extension = $types[$mime];
        }
        $folder = dirname(__DIR__, 2) . '/uploads/payment-guide';
        if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) throw new RuntimeException('Could not create upload folder.');
        $url = '/uploads/payment-guide/' . bin2hex(random_bytes(16)) . '.' . $extension;
        $target = dirname(__DIR__, 2) . $url;
        if (!move_uploaded_file($file['tmp_name'], $target)) throw new RuntimeException('Could not store illustration.');
        $newFiles[] = $target;
        imageFramingSaveUpload($url, $target);
        $db->prepare('INSERT INTO tuition_fee_content (field_key,content) VALUES (?,?) ON DUPLICATE KEY UPDATE content=VALUES(content)')->execute([$field, $url]);
    }
    if ($changes || $texts) logActivity((int)$_SESSION['user_id'], 'UPDATE', 'tuition_fee_content', null, 'Published payment guide content');
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    foreach ($newFiles as $file) @unlink($file);
    $expected = $e instanceof InvalidArgumentException || $e instanceof JsonException;
    if (!$expected) error_log('Payment guide publishing: ' . $e->getMessage());
    http_response_code(422); echo json_encode(['error' => $expected ? $e->getMessage() : 'Could not publish the payment guide. Please try again.']); exit;
}
foreach ($oldFiles as $url) {
    if (!is_string($url) || !preg_match('#^/uploads/payment-guide/[a-f0-9]{32}\.(svg|png|jpg|webp)$#D', $url)) continue;
    try {
        $query = $db->prepare('SELECT 1 FROM tuition_fee_content WHERE content=?'); $query->execute([$url]);
        if (!$query->fetchColumn()) @unlink(dirname(__DIR__, 2) . $url);
    } catch (Throwable $e) { error_log('Payment guide old image cleanup failed.'); }
}
echo json_encode(['success' => true]);
