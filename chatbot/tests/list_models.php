<?php
/**
 * Lists the Gemini models your GEMINI_API_KEY can actually use for chat (generateContent).
 *   docker exec -it -w /var/www/html <container> php chatbot/tests/list_models.php
 * Put one of the printed names in .env as GEMINI_MODEL=...   The key is never printed.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../config/config.php';

$key  = trim((string)env('GEMINI_API_KEY', ''));
$base = rtrim((string)env('GEMINI_API_BASE', 'https://generativelanguage.googleapis.com/v1beta'), '/');
if ($key === '') { fwrite(STDERR, "GEMINI_API_KEY is not set in .env (or the container cannot see it).\n"); exit(1); }

$names = []; $token = '';
do {
    $ch = curl_init($base . '/models?pageSize=200' . ($token ? '&pageToken=' . urlencode($token) : ''));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => ['x-goog-api-key: ' . $key]]);
    $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($raw === false) { fwrite(STDERR, "Network error: $err\n(The server may be blocked from reaching generativelanguage.googleapis.com)\n"); exit(1); }
    $d = json_decode($raw, true);
    if ($code !== 200) { fwrite(STDERR, "HTTP $code: " . ($d['error']['status'] ?? 'error') . ' - ' . ($d['error']['message'] ?? '') . "\n"); exit(1); }
    foreach ($d['models'] ?? [] as $m) {
        if (in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true)) { $names[] = preg_replace('#^models/#', '', $m['name']); }
    }
    $token = $d['nextPageToken'] ?? '';
} while ($token);

// text chat models only (skip image/tts/live/embedding variants), newest-looking first
$chat = array_values(array_filter($names, fn($n) => str_starts_with($n, 'gemini-') && !preg_match('/image|tts|live|audio|embed|omni|robotics|computer-use|customtools/i', $n)));
natsort($chat); $chat = array_reverse($chat);
echo "Models available to your key for chat:\n";
foreach ($chat as $n) { echo "  $n\n"; }
echo "\nSuggested: GEMINI_MODEL=" . ($chat ? (preg_grep('/^gemini-[\d.]+-flash$/', $chat) ?: $chat)[array_key_first(preg_grep('/^gemini-[\d.]+-flash$/', $chat) ?: $chat)] : '(none found)') . "\n";
