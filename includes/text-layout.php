<?php
require_once __DIR__ . '/../config/config.php';
/** Display metadata only; content continues through the existing CMS handlers. */
function textLayoutFonts(): array {
    return [
        'inter'=>['label'=>'Inter','fallback'=>'sans-serif'],
        'lora'=>['label'=>'Lora','fallback'=>'serif'],
        'montserrat'=>['label'=>'Montserrat','fallback'=>'sans-serif'],
        'noto-serif'=>['label'=>'Noto Serif','fallback'=>'serif'],
        'open-sans'=>['label'=>'Open Sans','fallback'=>'sans-serif'],
        'poppins'=>['label'=>'Poppins','fallback'=>'sans-serif'],
        'roboto'=>['label'=>'Roboto','fallback'=>'sans-serif'],
        'rubik'=>['label'=>'Rubik','fallback'=>'sans-serif'],
    ];
}
function textLayoutRoute(string $route): string {
    if (strlen($route)>1000) throw new InvalidArgumentException('Invalid public page.');
    $parts = parse_url($route);
    $path = rawurldecode($parts['path'] ?? '');
    $programSlug = null;
    if (preg_match('~^/pages/academics/college/(program|college-department)/([a-z0-9-]+)/?$~D', $path, $programMatch)) {
        $programSlug = $programMatch[2];
        $path = '/pages/academics/college/' . $programMatch[1] . '.php';
    }
    // Clean public URLs share the existing CMS placement key with their PHP page.
    if ($path === '/index') $path = '/index.php';
    if (str_starts_with($path, '/pages/') && !str_ends_with($path, '/') && pathinfo($path, PATHINFO_EXTENSION) === '') $path .= '.php';
    if (!is_array($parts) || isset($parts['host']) || isset($parts['scheme']) || !preg_match('~\A/(?:index\.php|pages/[a-zA-Z0-9_/ .-]+\.php)?\z~D', $path) || str_contains($path, '..')) throw new InvalidArgumentException('Invalid public page.');
    if ($path === '/index.php') $path = '/';
    parse_str($parts['query'] ?? '', $query);
    if ($programSlug !== null) $query['slug'] = $programSlug;
    foreach (['ce_preview','_','_ve','section','add_org'] as $key) unset($query[$key]);
    foreach ($query as $key => $value) if (!is_string($value) || strlen($key) > 80 || strlen($value) > 200) throw new InvalidArgumentException('Invalid page parameters.');
    ksort($query);
    return $path . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
}
function textLayoutNormalize(array $entries): array {
    if (count($entries) > 400) throw new InvalidArgumentException('Too many text placements.');
    $out = [];
    foreach ($entries as $key => $devices) {
        if (!is_string($key) || !preg_match('/\A[a-f0-9]{24}(?:-[0-9]{1,4})?\z/D', $key) || !is_array($devices)) throw new InvalidArgumentException('Invalid text element.');
        $out[$key] = [];
        foreach ($devices as $device => $box) {
            if (!in_array($device, ['desktop','tablet','mobile'], true)) throw new InvalidArgumentException('Invalid device.');
            if ($box === null) { $out[$key][$device] = null; continue; }
            if (!is_array($box) || !$box || array_diff(array_keys($box), ['x','y','w','h','fontSize','fontFamily','centerX'])) throw new InvalidArgumentException('Invalid text box.');
            $positioned = (bool)array_intersect(array_keys($box), ['x','y','w','h']);
            if (!$positioned && !array_key_exists('fontFamily', $box)) throw new InvalidArgumentException('Invalid text box.');
            foreach ($positioned ? ['x'=>[-100,100], 'y'=>[-100,100], 'w'=>[1,100], 'h'=>[0,3000]] : [] as $prop => [$min,$max]) {
                $n = $box[$prop] ?? null;
                if ((!is_int($n) && !is_float($n)) || !is_finite((float)$n) || $n < $min || $n > $max) throw new InvalidArgumentException('Text position or size is out of range.');
                $out[$key][$device][$prop] = round((float)$n, 4);
            }
            if (array_key_exists('fontFamily', $box)) {
                if (!is_string($box['fontFamily']) || !isset(textLayoutFonts()[$box['fontFamily']])) throw new InvalidArgumentException('Choose a font from the Google Fonts list.');
                $out[$key][$device]['fontFamily'] = $box['fontFamily'];
            }
            if (array_key_exists('centerX', $box)) {
                if (!is_bool($box['centerX']) || !$positioned) throw new InvalidArgumentException('Invalid centering option.');
                $out[$key][$device]['centerX'] = $box['centerX'];
            }
            if (array_key_exists('fontSize', $box)) {
                $size = $box['fontSize'];
                if ((!is_int($size) && !is_float($size)) || !is_finite((float)$size) || $size < 8 || $size > 200) throw new InvalidArgumentException('Text size must be between 8 and 200 pixels.');
                $out[$key][$device]['fontSize'] = round((float)$size, 2);
            }
        }
    }
    return $out;
}
function textLayoutLoad(PDO $db, string $route): array {
    try {
        $stmt = $db->prepare('SELECT settings FROM text_layout_settings WHERE page_key=?');
        $stmt->execute([hash('sha256', textLayoutRoute($route))]);
        $raw = $stmt->fetchColumn();
        return $raw ? textLayoutNormalize(json_decode($raw, true, 16, JSON_THROW_ON_ERROR)) : [];
    } catch (PDOException $e) { if ($e->getCode() === '42S02') return []; throw $e; }
}
function textLayoutSave(PDO $db, string $route, array $changes, int $userId): void {
    $route = textLayoutRoute($route); $changes = textLayoutNormalize($changes);
    $db->beginTransaction();
    try {
        $key = hash('sha256', $route);
        $db->prepare('INSERT IGNORE INTO text_layout_settings (page_key,settings,updated_by) VALUES (?, ?, ?)')->execute([$key,'{}',$userId]);
        $stmt=$db->prepare('SELECT settings FROM text_layout_settings WHERE page_key=? FOR UPDATE');$stmt->execute([$key]);
        $all=json_decode($stmt->fetchColumn(),true,16,JSON_THROW_ON_ERROR);
        foreach ($changes as $id=>$devices) foreach ($devices as $device=>$box) {
            if ($box===null) unset($all[$id][$device]); else $all[$id][$device]=$box;
            if (empty($all[$id])) unset($all[$id]);
        }
        $all=textLayoutNormalize($all);
        $db->prepare('UPDATE text_layout_settings SET settings=?,updated_by=? WHERE page_key=?')->execute([json_encode($all,JSON_THROW_ON_ERROR),$userId,$key]);
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
}
