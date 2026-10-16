<?php
/**
 * GNC Admin Panel - Photo check (AI validation for people photos)
 *
 * Asks Google's Gemini vision model two questions about an uploaded photo:
 *   1. Is it a photo of a real person?   (not a logo, building, object, drawing...)
 *   2. Does it look AI-generated?
 *
 * Used in two places:
 *   - admin/action/photo-check-handler.php  -> instant popup when a file is chosen
 *   - storeDepartmentUpload() in admin-functions.php -> server-side backstop,
 *     so the check can't be skipped by bypassing the browser.
 *
 * Setup: none needed if config.php already defines GEMINI_API_KEY (it reads it from
 * .env, as the chatbot does). The model defaults to gemini-3.5-flash-lite; override PHOTO_CHECK_MODEL in .env.
 * Optional (config.php):
 *     define('PHOTO_CHECK_MODEL', 'gemini-3.5-flash-lite');  // use a different model just for this check
 *     define('PHOTO_CHECK_BLOCK_AI', true);              // false = AI photos are not blocked
 *
 * If no key is set or the service cannot be reached, uploads are refused by
 * default. PHOTO_CHECK_FAIL_CLOSED can explicitly disable that policy.
 *
 * Note: AI-image detection is an estimate, not proof. Only photos the model
 * is fairly confident about are blocked.
 */

// Reuses the GEMINI_API_KEY / GEMINI_MODEL / GEMINI_TIMEOUT already defined in config.php (from .env).
if (!defined('PHOTO_CHECK_MODEL')) define('PHOTO_CHECK_MODEL', function_exists('env') ? env('PHOTO_CHECK_MODEL', 'gemini-3.5-flash-lite') : 'gemini-3.5-flash-lite');
if (!defined('PHOTO_CHECK_BLOCK_AI')) define('PHOTO_CHECK_BLOCK_AI', true);
// Backup models tried (in order) when the main model is overloaded (HTTP 503/429/500) or times out.
// Order matters: the first one is tried right after the main model, and so on.
// Change the list here, or set PHOTO_CHECK_FALLBACK_MODELS=model-a,model-b in .env.
if (!defined('PHOTO_CHECK_FALLBACK_MODELS')) {
    $__fb = function_exists('env') ? env('PHOTO_CHECK_FALLBACK_MODELS', '') : (getenv('PHOTO_CHECK_FALLBACK_MODELS') ?: '');
    $__fb = array_values(array_filter(array_map('trim', explode(',', (string)$__fb)), function ($m) {
        return $m !== '' && preg_match('/^[a-z0-9.\-]{3,60}$/i', $m);
    }));
    define('PHOTO_CHECK_FALLBACK_MODELS', $__fb ?: ['gemini-3.6-flash']);
    unset($__fb);
}
// true = REFUSE the upload when the check cannot run at all (Gemini down). false = let it through.
if (!defined('PHOTO_CHECK_FAIL_CLOSED')) define('PHOTO_CHECK_FAIL_CLOSED', true);

const PHOTO_CHECK_AI_THRESHOLD = 0.6;    // blocked at or above this "looks AI-generated" score
const PHOTO_CHECK_MAX_EDGE     = 1024;   // image is shrunk to this before it is sent (cheaper, faster)

const PHOTO_CHECK_MSG_NOT_PERSON = 'This image is not a person. Please upload a photo of the person.';
const PHOTO_CHECK_MSG_AI         = 'This image appears to be AI-generated. Please upload a camera photo of the person.';

function photoCheckConfigured(): bool {
    return defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY);
}

/**
 * Shrink the image and return [base64, media_type], or null if it can't be read.
 */
function photoCheckPrepare(string $path): ?array {
    $info = @getimagesize($path);
    if (!$info) return null;
    $types = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_WEBP => 'image/webp'];
    if (!isset($types[$info[2]])) return null;

    $media = $types[$info[2]];
    $data  = null;

    if (max($info[0], $info[1]) > PHOTO_CHECK_MAX_EDGE && function_exists('imagecreatetruecolor')) {
        $img = $info[2] === IMAGETYPE_JPEG ? @imagecreatefromjpeg($path)
             : ($info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($path)
             : (function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false));
        if ($img) {
            $scaled = $info[0] >= $info[1]
                ? imagescale($img, PHOTO_CHECK_MAX_EDGE, -1, IMG_BICUBIC)
                : imagescale($img, (int)round($info[0] * PHOTO_CHECK_MAX_EDGE / $info[1]), PHOTO_CHECK_MAX_EDGE, IMG_BICUBIC);
            imagedestroy($img);
            if ($scaled) {
                ob_start();
                imagejpeg($scaled, null, 85);
                $data  = ob_get_clean();
                $media = 'image/jpeg';
                imagedestroy($scaled);
            }
        }
    }
    if ($data === null || $data === false || $data === '') {
        $data = @file_get_contents($path);
        if ($data === false) return null;
    }
    // Inline images must stay small (the request limit is ~20 MB; we stay far below).
    if (strlen($data) > 5 * 1024 * 1024) return null;
    return [base64_encode($data), $media];
}

/**
 * Looks inside the file for the labels AI tools write into their images
 * (IPTC "trainedAlgorithmicMedia" digital-source-type, used by Google/Gemini, OpenAI,
 * Adobe and others, plus the names of common generators in the metadata).
 * Returns the marker that was found, or null. Needs no API call.
 *
 * Limit: screenshots and some re-saves strip metadata, so this catches the common
 * "downloaded straight from the AI tool" case, not every AI image.
 */
function photoCheckAiMetadata(string $path): ?string {
    $bytes = @file_get_contents($path, false, null, 0, 8 * 1024 * 1024);
    if ($bytes === false || $bytes === '') return null;
    $markers = ['trainedAlgorithmicMedia', 'made with google ai', 'midjourney', 'stable diffusion',
                'dall-e', "dall\xC2\xB7e", 'adobe firefly', 'generated by ai', 'ai-generated image'];
    foreach ($markers as $m) {
        if (stripos($bytes, $m) !== false) return $m;
    }
    return null;
}

/**
 * Check one image file.
 *
 * Returns:
 *   ['checked' => bool,          // false = the check was skipped (no key / service error)
 *    'ok'      => bool,          // true = fine to use
 *    'reason'  => null|'not_person'|'ai_generated',
 *    'message' => string]        // text for the popup / error (empty when ok)
 */
function photoCheckImage(string $path): array {
    $skip = function (string $why): array {
        return ['checked' => false, 'ok' => !PHOTO_CHECK_FAIL_CLOSED, 'reason' => 'unavailable',
                'message' => 'The photo check is unavailable. Please try again later or contact your administrator.', 'detail' => $why];
    };

    $info = @getimagesize($path);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return ['checked' => false, 'ok' => false, 'reason' => 'invalid_image',
                'message' => 'Please choose a valid JPG, PNG or WEBP photo.'];
    }

    if (is_file($path) && PHOTO_CHECK_BLOCK_AI && ($marker = photoCheckAiMetadata($path)) !== null) {
        return ['checked' => true, 'ok' => false, 'reason' => 'ai_generated', 'message' => PHOTO_CHECK_MSG_AI,
                'detail' => 'AI label found in the file metadata: ' . $marker];
    }

    if (!photoCheckConfigured()) return $skip('GEMINI_API_KEY is empty or not defined');
    if (!function_exists('curl_init')) return $skip('The PHP cURL extension is not installed');
    if (!empty($_SESSION['photo_check_down_until']) && time() < $_SESSION['photo_check_down_until']) {
        return $skip('skipped: the Gemini call failed a moment ago, retrying in ' . ($_SESSION['photo_check_down_until'] - time()) . 's (' . ($_SESSION['photo_check_down_why'] ?? '') . ')');
    }
    if (!is_file($path))         return $skip('uploaded file not found');

    // Same file checked twice (popup, then the real upload) = one API call.
    $hash = @hash_file('sha256', $path);
    if ($hash && isset($_SESSION['photo_check'][$hash])) {
        return $_SESSION['photo_check'][$hash];
    }

    $prepared = photoCheckPrepare($path);
    if (!$prepared) {
        return $skip('image could not be read/resized (is the PHP GD extension enabled?)');
    }
    [$b64, $media] = $prepared;

    $prompt = <<<TXT
You are validating a profile photo for a school website's staff and administration pages.
Look at the image and answer with ONLY a JSON object, no other text:
{"is_person_photo": true|false, "ai_generated_score": 0.0-1.0, "ai_signs": "max 12 words: what looks synthetic, or 'natural camera photo'"}

is_person_photo: true only if the main subject is a real human being (a portrait or photo of a person).
Set it to false for logos, seals, buildings, objects, animals, documents, screenshots, cartoons, drawings or illustrations, and images with no clear person.
ai_generated_score: how likely the image is AI-generated or synthetic (diffusion/GAN-style faces, unnatural skin or hair, malformed hands or ears, garbled text or backgrounds, over-smooth rendering). 0 = clearly a real camera photo, 1 = clearly AI-generated. Be conservative: ordinary retouched or filtered photos are NOT AI-generated.
TXT;

    $payload = json_encode([
        'contents' => [[
            'parts' => [
                ['inline_data' => ['mime_type' => $media, 'data' => $b64]],
                ['text' => $prompt],
            ],
        ]],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'responseSchema'   => [
                'type'       => 'OBJECT',
                'properties' => [
                    'is_person_photo'    => ['type' => 'BOOLEAN'],
                    'ai_generated_score' => ['type' => 'NUMBER'],
                    'ai_signs'           => ['type' => 'STRING'],
                ],
                'required'   => ['is_person_photo', 'ai_generated_score', 'ai_signs'],
            ],
            'temperature'     => 0,
            // Gemini 3.x "thinks" before answering and thinking tokens count against this limit.
            'maxOutputTokens' => 8192,
        ],
    ]);

    // Try the main model, then the backup models. Overload/timeouts get one quick retry each.
    $models   = array_values(array_unique(array_merge([PHOTO_CHECK_MODEL], (array)PHOTO_CHECK_FALLBACK_MODELS)));
    $started  = time();
    $status   = 0; $body = false; $err = ''; $usedModel = PHOTO_CHECK_MODEL; $tried = [];
    foreach ($models as $model) {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $remaining = 45 - (time() - $started);
            if ($remaining <= 0) break 2;
            $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent');
            // Windows PHP may have no bundled CA file; use the OS trust store.
            if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
                curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
            }
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT        => min($remaining, max(5, defined('GEMINI_TIMEOUT') ? (int)GEMINI_TIMEOUT : 20)),
                CURLOPT_HTTPHEADER     => [
                    'content-type: application/json',
                    'x-goog-api-key: ' . GEMINI_API_KEY,
                ],
            ]);
            $body   = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err    = curl_error($ch);
            unset($ch);
            $usedModel = $model;
            if ($body !== false && $status === 200) break 2;                     // got an answer
            $tried[] = $model . ' → HTTP ' . $status . ($err !== '' ? ' (' . $err . ')' : '');
            // 429 = this model's per-minute quota is used up: retrying it only wastes time, go to the next model now.
            if ($status === 429) break;
            $retryable = in_array($status, [0, 500, 502, 503, 504], true);
            if (!$retryable) break;                                              // 400/403/404: try the next model
            if ($attempt === 1) usleep(800000);                                  // brief pause, then retry once
        }
    }

    if ($body === false || $status !== 200) {
        $why = implode('; ', $tried) . ' | ' . ($err !== '' ? $err : substr((string)$body, 0, 200));
        error_log('Photo check unavailable: ' . $why);
        $_SESSION['photo_check_down_until'] = time() + 20;
        $_SESSION['photo_check_down_why']   = substr($why, 0, 160);
        $res = $skip('Gemini request failed on all models: ' . $why);
        if (PHOTO_CHECK_FAIL_CLOSED) {
            $res['ok']      = false;
            $res['reason']  = 'unavailable';
            $res['message'] = 'The photo check is unavailable right now. Please try again in a minute.';
        }
        return $res;
    }

    $resp   = json_decode((string)$body, true);
    $text = '';
    foreach (($resp['candidates'][0]['content']['parts'] ?? []) as $part) {
        if (empty($part['thought'])) $text .= (string)($part['text'] ?? '');
    }
    $finish = (string)($resp['candidates'][0]['finishReason'] ?? '');
    $v = null;
    if (preg_match('/\{.*\}/s', $text, $m)) {
        $v = json_decode($m[0], true);
    }
    if ($finish !== 'STOP' || !is_array($v) || !is_bool($v['is_person_photo'] ?? null)
        || !is_numeric($v['ai_generated_score'] ?? null)
        || $v['ai_generated_score'] < 0 || $v['ai_generated_score'] > 1) {
        return $skip('Gemini returned an incomplete or invalid verdict (finishReason ' . $finish . ')');
    }
    $detail = 'model=' . $usedModel . ', person=' . (!empty($v['is_person_photo']) ? 'yes' : 'no')
            . ', ai_score=' . ($v['ai_generated_score'] ?? 'n/a')
            . ', signs=' . ($v['ai_signs'] ?? 'n/a')
            . ($finish && $finish !== 'STOP' ? ', finishReason=' . $finish : '');

    $result = ['checked' => true, 'ok' => true, 'reason' => null, 'message' => '', 'detail' => $detail];
    if (empty($v['is_person_photo'])) {
        $result = ['checked' => true, 'ok' => false, 'reason' => 'not_person', 'message' => PHOTO_CHECK_MSG_NOT_PERSON, 'detail' => $detail];
    } elseif (PHOTO_CHECK_BLOCK_AI && (float)($v['ai_generated_score'] ?? 0) >= PHOTO_CHECK_AI_THRESHOLD) {
        $result = ['checked' => true, 'ok' => false, 'reason' => 'ai_generated', 'message' => PHOTO_CHECK_MSG_AI, 'detail' => $detail];
    }

    if ($hash) {
        $_SESSION['photo_check'][$hash] = $result;
        // keep the session small
        if (count($_SESSION['photo_check']) > 30) {
            $_SESSION['photo_check'] = array_slice($_SESSION['photo_check'], -30, null, true);
        }
    }
    return $result;
}

/**
 * Throws an Exception (user-safe message) when the photo is not acceptable.
 * Used by storeDepartmentUpload() for people photos.
 */
function photoCheckEnforce(string $path): void {
    $r = photoCheckImage($path);
    if (!$r['ok']) {
        throw new Exception($r['message']);
    }
}
