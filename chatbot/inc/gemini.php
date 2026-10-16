<?php
/**
 * Minimal Gemini (Google AI Studio) REST client.
 * - The API key is read server-side and sent in a header (never in the URL),
 *   so it cannot leak through logs, cURL error strings or the browser.
 * - Returns only coarse error codes; raw API errors are logged without secrets.
 */
declare(strict_types=1);

/**
 * @param array $contents  Gemini "contents" array (role user|model, parts[text])
 * @return array{ok:bool,text?:string,error?:string}
 *   error: not_configured | rate_limited | invalid_key | timeout | unavailable | blocked | bad_response
 */
function gncbot_gemini_generate(string $systemInstruction, array $contents): array
{
    $key = function_exists('env') ? env('GEMINI_API_KEY') : (getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? null));
    if (!is_string($key) || trim($key) === '') {
        error_log('[gncbot] GEMINI_API_KEY is not set');
        return ['ok' => false, 'error' => 'not_configured'];
    }
    $key   = trim($key);
    $model = function_exists('env') ? (string)env('GEMINI_MODEL', 'gemini-2.5-flash') : 'gemini-3.6-flash';
    if (!preg_match('/^[a-z0-9.\-]{3,60}$/i', $model)) { $model = 'gemini-3.6-flash'; }
    $base    = rtrim((string)(function_exists('env') ? env('GEMINI_API_BASE', 'https://generativelanguage.googleapis.com/v1beta') : 'https://generativelanguage.googleapis.com/v1beta'), '/');
    $timeout = (int)(function_exists('env') ? env('GEMINI_TIMEOUT', 20) : 20);

    // Output budget is generous because "thinking" tokens (if any) count against it.
    $gen = ['temperature' => 0.2, 'topP' => 0.9, 'maxOutputTokens' => 1500];

    // This is a lookup/summarise task, so keep reasoning minimal (faster, cheaper).
    //   gemini-2.5-*  -> thinkingBudget (0 = off)      gemini-3*  -> thinkingLevel (minimal|low|medium|high)
    // Override with GEMINI_THINKING_BUDGET or GEMINI_THINKING_LEVEL in .env; set either to "off" to send nothing.
    $thinkCfg = null;
    $envBudget = function_exists('env') ? env('GEMINI_THINKING_BUDGET', null) : null;
    $envLevel  = function_exists('env') ? env('GEMINI_THINKING_LEVEL', null) : null;
    if ($envBudget === 'off' || $envLevel === 'off') { $thinkCfg = null; }
    elseif ($envLevel !== null && $envLevel !== '')   { $thinkCfg = ['thinkingLevel' => strtolower((string)$envLevel)]; }
    elseif ($envBudget !== null && $envBudget !== '') { $thinkCfg = ['thinkingBudget' => (int)$envBudget]; }
    elseif (stripos($model, 'gemini-3') === 0)        { $thinkCfg = ['thinkingLevel' => 'low']; }
    elseif (stripos($model, 'gemini-2.5-flash') === 0) { $thinkCfg = ['thinkingBudget' => 0]; }

    $build = function (bool $withThink) use ($systemInstruction, $contents, $gen, $thinkCfg) {
        $g = $gen;
        if ($withThink && $thinkCfg) { $g['thinkingConfig'] = $thinkCfg; }
        return json_encode([
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'contents'          => $contents,
            'generationConfig'  => $g,
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    };
    $withThink = $thinkCfg !== null;
    $payload = $build($withThink);
    if ($payload === false) { return ['ok' => false, 'error' => 'bad_response']; }

    $url = $base . '/models/' . $model . ':generateContent';

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => max(5, $timeout),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
        ]);
        $raw  = curl_exec($ch);
        $errn = curl_errno($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            error_log('[gncbot] gemini curl error #' . $errn);
            if ($attempt === 0 && in_array($errn, [CURLE_COULDNT_CONNECT, CURLE_RECV_ERROR], true)) { usleep(400000); continue; }
            return ['ok' => false, 'error' => $errn === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'unavailable'];
        }

        $data = json_decode((string)$raw, true);

        if ($code >= 200 && $code < 300 && is_array($data)) {
            $block = $data['promptFeedback']['blockReason'] ?? null;
            if ($block) { error_log('[gncbot] gemini blocked prompt: ' . $block); return ['ok' => false, 'error' => 'blocked']; }
            $cand = $data['candidates'][0] ?? null;
            $text = '';
            foreach (($cand['content']['parts'] ?? []) as $p) { if (isset($p['text']) && is_string($p['text'])) { $text .= $p['text']; } }
            $text = trim($text);
            if ($text === '') {
                error_log('[gncbot] gemini empty response, finishReason=' . ($cand['finishReason'] ?? 'none'));
                return ['ok' => false, 'error' => (($cand['finishReason'] ?? '') === 'SAFETY') ? 'blocked' : 'bad_response'];
            }
            return ['ok' => true, 'text' => $text];
        }

        // Log status + Google's short reason only (never the request, never the key).
        $reason = is_array($data) ? (string)($data['error']['status'] ?? '') : '';
        error_log('[gncbot] gemini http ' . $code . ' ' . $reason . ' model=' . $model);

        // Some models reject a thinking setting they don't support: retry once without it.
        if ($code === 400 && $withThink && !str_contains((string)$raw, 'API_KEY')) {
            $withThink = false; $payload = $build(false); continue;
        }

        if (($code === 503 || $code === 500) && $attempt === 0) { usleep(600000); continue; }
        if ($code === 429) { return ['ok' => false, 'error' => 'rate_limited']; }
        if ($code === 401 || $code === 403 || ($code === 400 && str_contains((string)$raw, 'API_KEY'))) { return ['ok' => false, 'error' => 'invalid_key']; }
        return ['ok' => false, 'error' => $code >= 500 || $code === 0 ? 'unavailable' : 'bad_response'];
    }
    return ['ok' => false, 'error' => 'unavailable'];
}

/**
 * Keep only URLs that were actually supplied in the retrieved information.
 * Markdown links to unknown URLs are reduced to their label; bare unknown URLs are removed.
 */
function gncbot_sanitize_reply(string $text, array $allowedUrls): string
{
    $allowed = array_flip($allowedUrls);
    $isOk = function (string $u) use ($allowed): bool {
        $u = rtrim($u, '.,;:)');
        return isset($allowed[$u]) || isset($allowed[html_entity_decode($u)]);
    };
    $text = preg_replace_callback('/\[([^\]]{1,120})\]\(([^)\s]{1,300})\)/u', function ($m) use ($isOk) {
        return $isOk($m[2]) ? $m[0] : $m[1];
    }, $text) ?? $text;
    // bare URLs / root-relative paths that are not inside a markdown link
    $text = preg_replace_callback('~(?<![\(\[/\w])(https?://[^\s)\]]+|/[A-Za-z0-9_\-./]+\.(?:php|html)[^\s)\]]*|/\?program=[^\s)\]]+)~u', function ($m) use ($isOk) {
        return $isOk($m[1]) ? $m[1] : '';
    }, $text) ?? $text;
    $text = strip_tags($text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    return mb_strlen($text) > 2000 ? rtrim(mb_substr($text, 0, 2000)) . '...' : trim($text);
}
