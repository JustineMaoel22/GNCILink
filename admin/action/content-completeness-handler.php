<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';
require_once __DIR__ . '/../content-completeness.php';
initSession();
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['success' => false]); exit; }
if (!isLoggedIn()) {
    http_response_code(403); echo json_encode(['success' => false]); exit;
}
if (!verifyCSRFToken((string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); echo json_encode(['success' => false]); exit; }
try {
    $raw = (string)($_POST['fields'] ?? '');
    if (strlen($raw) > 2000000) throw new InvalidArgumentException('Content report is too large.');
    $fields = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($fields)) throw new InvalidArgumentException('Invalid content report.');
    echo json_encode(['success' => true, 'missing' => contentMissingInformation($fields)]);
} catch (Throwable $e) {
    http_response_code(422); echo json_encode(['success' => false, 'error' => 'Could not inspect the content report.']);
}
