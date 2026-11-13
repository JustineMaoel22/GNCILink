<?php
/** Isolated model selection checks; no credentials, database or provider calls. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$settings = [];
function env(string $key, mixed $default = null): mixed {
    global $settings;
    return isset($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : $default;
}
require_once __DIR__.'/../inc/gemini.php';
$cases = [
    [[], 'gemini-3.1-flash-lite'],
    [['GEMINI_MODEL'=>'gemini-3.6-flash'], 'gemini-3.6-flash'],
    [['GEMINI_MODEL'=>'gemini-3.6-flash','CHATBOT_MODEL'=>'gemini-3.1-flash-lite'], 'gemini-3.1-flash-lite'],
    [['GEMINI_MODEL'=>'gemini-3.6-flash','CHATBOT_MODEL'=>''], 'gemini-3.6-flash'],
    [['CHATBOT_MODEL'=>'../../invalid?key=value'], 'gemini-3.1-flash-lite'],
];
foreach ($cases as [$settings, $expected]) {
    if (gncbot_gemini_model() !== $expected) throw new RuntimeException('Incorrect chatbot model selection.');
}
echo "PASS: chatbot override, legacy compatibility, empty settings and invalid model validation.\n";
