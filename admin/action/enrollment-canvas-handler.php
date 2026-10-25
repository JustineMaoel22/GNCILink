<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';
require_once __DIR__ . '/../../components/enrollment-procedures-content.php';
initSession();
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Use POST.']); exit; }
if (!isLoggedIn() || !hasPermission('manage_page_content')) { http_response_code(403); echo json_encode(['error'=>'You cannot publish enrollment content.']); exit; }
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0)>0) { http_response_code(413); echo json_encode(['error'=>'The upload exceeds the request limit. Choose fewer images.']); exit; }
if (!verifyCSRFToken((string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); echo json_encode(['error'=>'Refresh the editor and try again.']); exit; }
try {
    $changes=json_decode((string)($_POST['images'] ?? '{}'),true,16,JSON_THROW_ON_ERROR);
    if (!is_array($changes)) throw new InvalidArgumentException('Invalid illustration changes.');
    $steps = isset($_POST['steps']) ? json_decode((string)$_POST['steps'],true,16,JSON_THROW_ON_ERROR) : null;
    if (isset($_POST['steps']) && !is_array($steps)) throw new InvalidArgumentException('Invalid enrollment steps.');
    $result = publishEnrollmentCanvas($steps,(string)($_POST['revision'] ?? ''),$changes,$_FILES);
    echo json_encode(['success'=>true] + $result);
} catch (Throwable $e) {
    $expected=$e instanceof InvalidArgumentException || $e instanceof JsonException;
    if (!$expected) error_log('Enrollment canvas publishing failed: ' . $e->getMessage());
    http_response_code(422); echo json_encode(['error'=>$expected ? $e->getMessage() : 'Could not publish the enrollment content. Please try again.']);
}
