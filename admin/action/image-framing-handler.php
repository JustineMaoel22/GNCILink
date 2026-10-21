<?php
require_once __DIR__ . '/../../includes/image-framing.php';
require_once __DIR__ . '/../admin-functions.php';
initSession();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Use POST.']); exit; }
if (!isLoggedIn()) { http_response_code(403); echo json_encode(['error' => 'Sign in to adjust image display settings.']); exit; }
if (!verifyCSRFToken((string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); echo json_encode(['error' => 'Refresh the page and try again.']); exit; }
try {
    $path = (string)($_POST['path'] ?? '');
    if (!imageFramingPath($path)) throw new InvalidArgumentException('Choose an image stored on this website.');
    $allowed = hasPermission('manage_page_content');
    if (!$allowed && str_starts_with($path, '/uploads/')) {
        $query = getDB()->prepare('SELECT created_by FROM media_library WHERE file_path=? LIMIT 1'); $query->execute([$path]);
        $owner = $query->fetchColumn();
        $allowed = $owner !== false && (int)$owner === (int)$_SESSION['user_id'] && (hasPermission('edit_own_announcement') || hasPermission('edit_own_news') || hasPermission('edit_page_content'));
    }
    if (!$allowed) { http_response_code(403); echo json_encode(['error' => 'You cannot change this image.']); exit; }
    $value = json_decode((string)($_POST['framing'] ?? '{}'), true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($value)) throw new InvalidArgumentException('Invalid image display settings.');
    $value = imageFramingNormalize($value);
    $db = getDB(); $db->beginTransaction();
    try {
        imageFramingSave($path, $value, (int)$_SESSION['user_id']);
        logActivity((int)$_SESSION['user_id'], 'UPDATE', 'image_display_settings', null, 'Adjusted image display: ' . $path);
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
    echo json_encode(['success' => true, 'path' => $path, 'framing' => $value]);
} catch (Throwable $e) {
    $expected = $e instanceof InvalidArgumentException || $e instanceof JsonException;
    if (!$expected) error_log('Image framing: ' . $e->getMessage());
    http_response_code(422); echo json_encode(['error' => $expected ? $e->getMessage() : 'Could not save image display settings. Please try again.']);
}
