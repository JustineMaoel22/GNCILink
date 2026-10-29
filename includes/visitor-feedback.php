<?php
/** Anonymous Google Forms gateway. No answers or Google credentials enter browser storage. */
const GNC_FEEDBACK_COOKIE = 'gnci_feedback_identity';
const GNC_FEEDBACK_IDENTITY_DAYS = 180;

function gncFeedbackConfiguration(): array {
    $url = trim((string)env('FEEDBACK_SCRIPT_URL', ''));
    $secret = (string)env('FEEDBACK_SCRIPT_SECRET', '');
    if (!preg_match('~^https://script\.google\.com/macros/s/[A-Za-z0-9_-]+/exec$~D', $url) || strlen($secret) < 32) {
        throw new RuntimeException('Feedback verification is not configured.');
    }
    return [$url, $secret];
}

function gncFeedbackIdentity(?string $cookie, string $secret): ?string {
    if ($cookie === null || !preg_match('/^([a-f0-9]{64})\.([a-f0-9]{64})$/D', $cookie, $match)) return null;
    return hash_equals(hash_hmac('sha256', 'identity:'.$match[1], $secret), $match[2]) ? $match[1] : null;
}

function gncFeedbackCsrf(string $identity, string $secret): string {
    return hash_hmac('sha256', 'csrf:'.$identity, $secret);
}

function gncFeedbackAnswers($answers): array {
    if (!is_array($answers) || count($answers) !== 6) throw new InvalidArgumentException('Please answer all six rating questions.');
    foreach ($answers as $id => $rating) {
        if (!preg_match('/^[0-9]{1,15}$/D', (string)$id) || !is_int($rating) || $rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Choose a rating from 1 to 5 for every question.');
        }
    }
    return $answers;
}

function gncFeedbackRemote(string $url, string $secret, array $request): array {
    if (!function_exists('curl_init')) throw new RuntimeException('Feedback transport is unavailable.');
    $payload = json_encode($request + ['at' => time()], JSON_THROW_ON_ERROR);
    $body = json_encode(['payload' => $payload, 'signature' => hash_hmac('sha256', $payload, $secret)], JSON_THROW_ON_ERROR);
    $post = true;
    for ($redirects = 0; $redirects < 4; $redirects++) {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || !in_array($parts['host'] ?? '', ['script.google.com', 'script.googleusercontent.com'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) throw new RuntimeException('Invalid feedback redirect.');
        $response = '';
        $handle = curl_init($url);
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$response): int {
                if (strlen($response) + strlen($chunk) > 65536) return 0;
                $response .= $chunk; return strlen($chunk);
            }]);
        if ($post) curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body]);
        $ok = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $next = (string)curl_getinfo($handle, CURLINFO_REDIRECT_URL);
        curl_close($handle);
        if ($ok === false) throw new RuntimeException('Feedback service could not be reached.');
        if (in_array($status, [301, 302, 303], true) && $next !== '') { $url = $next; $post = false; continue; }
        if ($status !== 200) throw new RuntimeException('Feedback service is unavailable.');
        try { $result = json_decode($response, true, 32, JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { throw new RuntimeException('Feedback service returned an invalid response.'); }
        return gncFeedbackResult($result, $request['visitor']);
    }
    throw new RuntimeException('Feedback service redirected too many times.');
}

function gncFeedbackResult($result, string $visitor): array {
    if (!is_array($result) || ($result['ok'] ?? false) !== true || ($result['visitor'] ?? '') !== $visitor
        || !is_bool($result['submitted'] ?? null)) throw new RuntimeException('Feedback confirmation was not received.');
    if ($result['submitted']) {
        if (!is_string($result['receipt'] ?? null) || $result['receipt'] === '') throw new RuntimeException('Feedback receipt is missing.');
        return ['ok' => true, 'submitted' => true];
    }
    if (($result['pending'] ?? false) === true) return ['ok' => true, 'submitted' => false, 'pending' => true];
    $questions = $result['questions'] ?? null;
    if (!is_array($questions) || count($questions) !== 6) throw new RuntimeException('Feedback questions are unavailable.');
    $ids = [];
    foreach ($questions as &$question) {
        if (!is_array($question) || !preg_match('/^[0-9]{1,15}$/D', (string)($question['id'] ?? ''))
            || !is_string($question['title'] ?? null) || trim($question['title']) === '' || strlen($question['title']) > 2000
            || (isset($question['section']) && (!is_string($question['section']) || strlen($question['section']) > 2000))
            || isset($ids[(string)$question['id']])) throw new RuntimeException('Invalid feedback questions.');
        $ids[(string)$question['id']] = true;
        $question = ['id' => (string)$question['id'], 'title' => $question['title'], 'section' => (string)($question['section'] ?? '')];
    }
    unset($question);
    return ['ok' => true, 'submitted' => false, 'questions' => $questions];
}
