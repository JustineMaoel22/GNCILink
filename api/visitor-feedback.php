<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../includes/privacy.php';
require_once __DIR__.'/../includes/visitor-feedback.php';
require_once __DIR__.'/../includes/auth-security.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function feedbackReply(int $status, array $value): void { http_response_code($status); echo json_encode($value); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); feedbackReply(405, ['ok'=>false,'error'=>'Use POST.']); }
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$originHost = (string)parse_url($origin, PHP_URL_HOST);
$port = parse_url($origin, PHP_URL_PORT);
if ($port !== null && $port !== false) $originHost .= ':'.$port;
if ($origin === '' || strcasecmp($originHost, (string)($_SERVER['HTTP_HOST'] ?? '')) !== 0
    || parse_url($origin, PHP_URL_SCHEME) !== (authIsHttps() ? 'https' : 'http')
    || ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site' || ($_SERVER['HTTP_X_GNC_FEEDBACK'] ?? '') !== '1') {
    feedbackReply(403, ['ok'=>false,'error'=>'This request must come from the website.']);
}
if (!gncPrivacyAllows('personalization') || !gncPrivacyAllows('external')) feedbackReply(403, ['ok'=>false,'error'=>'Allow personalization and embedded services in Privacy Choices to rate.']);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) feedbackReply(413, ['ok'=>false,'error'=>'Request too large.']);
try {
    $raw = file_get_contents('php://input', false, null, 0, 4097);
    if (strlen($raw) > 4096) feedbackReply(413, ['ok'=>false,'error'=>'Request too large.']);
    $input = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($input) || !in_array($input['action'] ?? null, ['status', 'submit'], true)) throw new InvalidArgumentException('Invalid feedback action.');
    [$url, $secret] = gncFeedbackConfiguration();
    if (!authRateAllowed('feedback-ip', hash_hmac('sha256', authIp(), $secret), 300, 600)) {
        header('Retry-After: 600'); feedbackReply(429, ['ok'=>false,'error'=>'Too many requests. Please try again later.']);
    }
    $cookie = $_COOKIE[GNC_FEEDBACK_COOKIE] ?? null;
    $identity = gncFeedbackIdentity(is_string($cookie) ? $cookie : null, $secret);
    if ($identity === null) {
        if ($input['action'] !== 'status') feedbackReply(403, ['ok'=>false,'error'=>'Reopen the feedback popup before submitting.']);
        $identity = bin2hex(random_bytes(32));
        setcookie(GNC_FEEDBACK_COOKIE, $identity.'.'.hash_hmac('sha256', 'identity:'.$identity, $secret),
            ['expires'=>time()+GNC_FEEDBACK_IDENTITY_DAYS*86400,'path'=>'/','httponly'=>true,'samesite'=>'Lax',
                'secure'=>authIsHttps()]);
    }
    if ($input['action'] === 'submit' && (!is_string($input['csrf'] ?? null) || !hash_equals(gncFeedbackCsrf($identity, $secret), $input['csrf']))) {
        feedbackReply(403, ['ok'=>false,'error'=>'Reopen the feedback popup before submitting.']);
    }
    if (!authRateAllowed('feedback-browser', hash('sha256', $identity), 30, 60)) {
        header('Retry-After: 60'); feedbackReply(429, ['ok'=>false,'error'=>'Please wait a minute before checking again.']);
    }
    $request = ['action'=>$input['action'],'visitor'=>hash('sha256', $identity)];
    if ($input['action'] === 'submit') $request['answers'] = gncFeedbackAnswers($input['answers'] ?? null);
    $result = gncFeedbackRemote($url, $secret, $request);
    $result['csrf'] = gncFeedbackCsrf($identity, $secret);
    feedbackReply(200, $result);
} catch (InvalidArgumentException | JsonException $e) {
    feedbackReply(422, ['ok'=>false,'error'=>'Please answer all six questions with ratings from 1 to 5.']);
} catch (Throwable $e) {
    // Do not disclose Google URLs, credentials, payloads or exception traces.
    feedbackReply(503, ['ok'=>false,'error'=>'Feedback verification is unavailable. Please try again later.']);
}
