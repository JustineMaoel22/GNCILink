<?php
/**
 * Public-side helpers for the dynamic program page
 * (pages/academics/college/program.php?slug=...).
 *
 * Every program on the website is rendered from the database by ONE page.
 * Nothing here is hardcoded per program: text, pictures, colours, badges,
 * core values, careers ... are all edited in
 * Admin Panel -> Content Editor -> Programs (components/program-page-manager.php).
 *
 * Needs sql/program-page-migration.sql to have been run.
 */

if (!function_exists('getDB')) {
    require_once __DIR__ . '/../config/config.php';
}
require_once __DIR__ . '/../admin/admin-functions.php';

/* ─────────────────────────────────────────────────────────────────────────
 * Colours
 * ───────────────────────────────────────────────────────────────────────── */

/**
 * The colour themes an admin can pick from (the same eight themes the old
 * per-program stylesheets had). 'light' is the soft background tint.
 */
function programPageThemePresets(): array {
    return [
        'green'  => ['label' => 'Green',  'accent' => '#307056', 'light' => '#E3EFE9'],
        'blue'   => ['label' => 'Blue',   'accent' => '#135E95', 'light' => '#A9C6EB'],
        'gray'   => ['label' => 'Gray',   'accent' => '#6F716E', 'light' => '#EEEEEE'],
        'orange' => ['label' => 'Orange', 'accent' => '#FD541B', 'light' => '#FFEEE9'],
        'yellow' => ['label' => 'Yellow', 'accent' => '#EABA3B', 'light' => '#FDF9EC'],
        'pink'   => ['label' => 'Pink',   'accent' => '#E86698', 'light' => '#FDF0F5'],
        'peach'  => ['label' => 'Peach',  'accent' => '#E15221', 'light' => '#FFBDA7'],
        'purple' => ['label' => 'Purple', 'accent' => '#5C198B', 'light' => '#EFE8F4'],
    ];
}

/** "#abc" / "#aabbcc" -> "#AABBCC", or null when it isn't a colour. */
function programPageNormalizeHex($value): ?string {
    $v = strtoupper(trim((string)$value));
    if (preg_match('/^#([0-9A-F])([0-9A-F])([0-9A-F])$/', $v, $m)) {
        return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
    }
    return preg_match('/^#[0-9A-F]{6}$/', $v) ? $v : null;
}

function programPageHexToRgb(string $hex): array {
    return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
}

function programPageLuminance(array $rgb): float {
    $c = array_map(function ($v) {
        $v /= 255;
        return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    }, $rgb);
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

function programPageRgbToHex(array $rgb): string {
    return sprintf('#%02X%02X%02X', max(0, min(255, (int)round($rgb[0]))), max(0, min(255, (int)round($rgb[1]))), max(0, min(255, (int)round($rgb[2]))));
}

/**
 * Inline CSS variables for one accent colour. Light colours (yellow, ...)
 * would be unreadable as text on the cream background, so the TEXT version
 * of the accent is darkened until it has enough contrast; the original
 * colour is still used for bars, borders and buttons.
 */
function programPageThemeStyle(?string $accent): string {
    $accent = programPageNormalizeHex($accent) ?? programPageThemePresets()['green']['accent'];
    $rgb    = programPageHexToRgb($accent);

    $cream = programPageLuminance([249, 246, 241]);
    $text  = $rgb;
    for ($i = 0; $i < 14; $i++) {
        $ratio = ($cream + 0.05) / (programPageLuminance($text) + 0.05);
        if ($ratio >= 3.0) break;
        $text = [$text[0] * 0.93, $text[1] * 0.93, $text[2] * 0.93];
    }
    $dark = [$rgb[0] * 0.8, $rgb[1] * 0.8, $rgb[2] * 0.8];

    return sprintf(
        '--gnc-template:%s;--gnc-template-text:%s;--gnc-template-tint:rgba(%d,%d,%d,0.10);--gnc-template-dark:%s;',
        $accent, programPageRgbToHex($text), $rgb[0], $rgb[1], $rgb[2], programPageRgbToHex($dark)
    );
}

/* ─────────────────────────────────────────────────────────────────────────
 * Icons: "material:school" / "bi:mortarboard"
 * ───────────────────────────────────────────────────────────────────────── */

function programPageIcon(?string $spec, string $class): string {
    $parts = explode(':', (string)$spec, 2);
    $set   = $parts[0] ?? '';
    $name  = $parts[1] ?? '';
    if ($set === 'bi' && preg_match('/^[a-z0-9-]+$/i', $name)) {
        return '<i class="bi bi-' . $name . ' ' . $class . '" aria-hidden="true"></i>';
    }
    if ($set === 'material' && preg_match('/^[a-z0-9_]+$/i', $name)) {
        return '<span class="material-symbols-outlined ' . $class . '" aria-hidden="true">' . $name . '</span>';
    }
    return '<span class="material-symbols-outlined ' . $class . '" aria-hidden="true">star</span>';
}

/** Keep only a valid icon spec; anything else becomes $fallback. */
function programPageCleanIcon($spec, string $fallback = ''): string {
    $spec = trim((string)$spec);
    return preg_match('/^(material:[a-z0-9_]+|bi:[a-z0-9-]+)$/i', $spec) ? $spec : $fallback;
}

/* ─────────────────────────────────────────────────────────────────────────
 * Images
 * ───────────────────────────────────────────────────────────────────────── */

/**
 * Web path of a picture, or null when it's missing. Accepts uploads and the
 * pictures that ship with the site (/assets/images/...).
 */
function programPageFileUrl(?string $webPath): ?string {
    if (!$webPath || strpos($webPath, '..') !== false) {
        return null;
    }
    if (strpos($webPath, '/uploads/') !== 0 && strpos($webPath, '/assets/images/') !== 0) {
        return null;
    }
    return is_file(__DIR__ . '/..' . $webPath) ? $webPath : null;
}

/* ─────────────────────────────────────────────────────────────────────────
 * Page content (programs.page_content JSON) — the allowed text fields
 * ───────────────────────────────────────────────────────────────────────── */

/**
 * Every editable text field of the page, with its limit. The admin form, the
 * save handler and the public page all read this one list.
 *   type 'text' = single line, 'long' = paragraph, 'icon' = icon spec, 'path' = static picture path
 */
function programPageContentFields(): array {
    return [
        'subtitle'     => ['type' => 'text', 'max' => 255],
        'hero_desc'    => ['type' => 'long', 'max' => 2000],
        'hero_image_path' => ['type' => 'path', 'max' => 255],
        'seal_path'    => ['type' => 'path', 'max' => 255],
        'about_title'  => ['type' => 'text', 'max' => 120],
        'about_text'   => ['type' => 'long', 'max' => 6000],
        'vision_title' => ['type' => 'text', 'max' => 60],
        'vision_text'  => ['type' => 'long', 'max' => 3000],
        'vision_icon'  => ['type' => 'icon', 'max' => 60],
        'mission_title'=> ['type' => 'text', 'max' => 60],
        'mission_text' => ['type' => 'long', 'max' => 3000],
        'mission_icon' => ['type' => 'icon', 'max' => 60],
        'core_title'   => ['type' => 'text', 'max' => 120],
        'core_intro'   => ['type' => 'text', 'max' => 400],
        'why_title'    => ['type' => 'text', 'max' => 160],
        'why_intro'    => ['type' => 'long', 'max' => 2000],
        'curriculum_title' => ['type' => 'text', 'max' => 120],
        'curriculum_intro' => ['type' => 'long', 'max' => 1000],
        'uniform_title'=> ['type' => 'text', 'max' => 120],
        'uniform_text' => ['type' => 'long', 'max' => 2000],
        'career_title' => ['type' => 'text', 'max' => 120],
        'facility_title' => ['type' => 'text', 'max' => 120],
        'facility_title' => ['type' => 'text', 'max' => 120],
        'cta_title'    => ['type' => 'text', 'max' => 200],
        'cta_text'     => ['type' => 'text', 'max' => 400],
        'cta_button'   => ['type' => 'text', 'max' => 40],
        'cta_link'     => ['type' => 'text', 'max' => 255],
        'cta_icon'     => ['type' => 'icon', 'max' => 60],
    ];
}

/** Text shown when an admin leaves a heading/label empty. */
function programPageContentDefaults(): array {
    return [
        'about_title'   => 'About the Program',
        'vision_title'  => 'Vision',
        'mission_title' => 'Mission',
        'core_title'    => 'Core Values',
        'why_title'     => 'Why Choose This Program at GNCI?',
        'curriculum_title' => 'Program Curriculum',
        'curriculum_intro' => 'Curriculum details coming soon.',
        'uniform_title' => 'Uniform',
        'career_title'  => 'Career Opportunities',
        'facility_title' => 'Facilities',
        'cta_title'     => 'Make a difference. Begin Your Journey with GNCI.',
        'cta_text'      => 'Join a community driven by purpose, dedicated to excellence, and built to make an impact.',
        'cta_button'    => 'Apply Now',
        'cta_link'      => '/admissions/apply.php',
        'vision_icon'   => 'material:visibility',
        'mission_icon'  => 'material:shield_with_heart',
        'cta_icon'      => 'material:volunteer_activism',
    ];
}

/* ─────────────────────────────────────────────────────────────────────────
 * Loading a program
 * ───────────────────────────────────────────────────────────────────────── */

const PROGRAM_PAGE_SELECT_SQL = "
    SELECT p.*,
           d.dept_name, d.slug AS dept_slug,
           pm.file_path AS pdf_path, pm.file_name AS pdf_name, pm.file_size AS pdf_size,
           im.file_path AS image_path, im.file_name AS image_name,
           hm.file_path AS hero_path, hm.file_name AS hero_name,
           sm.file_path AS seal_path_upload, sm.file_name AS seal_name
    FROM programs p
    LEFT JOIN departments d  ON d.dept_id = p.dept_id
    LEFT JOIN media_library pm ON pm.media_id = p.pdf_media_id
    LEFT JOIN media_library im ON im.media_id = p.image_media_id
    LEFT JOIN media_library hm ON hm.media_id = p.hero_media_id
    LEFT JOIN media_library sm ON sm.media_id = p.seal_media_id
";

/**
 * Turn a joined programs row into everything the page and the admin form
 * need: program_name, merged content, image URLs, items grouped by type.
 */
function hydrateProgramPage(array $row): array {
    $row = hydrateProgramFull($row);   // program_name, pdf_url, image_url

    $stored = [];
    if (!empty($row['page_content'])) {
        $decoded = json_decode((string)$row['page_content'], true);
        if (is_array($decoded)) $stored = $decoded;
    }
    $content = [];
    foreach (array_keys(programPageContentFields()) as $key) {
        $content[$key] = isset($stored[$key]) ? (string)$stored[$key] : '';
    }
    $row['content'] = $content;

    $row['hero_url'] = programPageFileUrl($row['hero_path'] ?? null) ?? programPageFileUrl($content['hero_image_path']);
    $row['seal_url'] = programPageFileUrl($row['seal_path_upload'] ?? null) ?? programPageFileUrl($content['seal_path']);
    $row['has_hero_upload'] = programPageFileUrl($row['hero_path'] ?? null) !== null;
    $row['has_seal_upload'] = programPageFileUrl($row['seal_path_upload'] ?? null) !== null;

    $row['theme_accent']  = programPageNormalizeHex($row['theme_color'] ?? '') ?? programPageThemePresets()['green']['accent'];
    $row['core_enabled']  = !empty($row['core_values_enabled']);
    $row['published']     = !isset($row['is_published']) || (int)$row['is_published'] === 1;
    return $row;
}

/** Items of one program grouped by type, each with a ready image_url. */
function programPageItems(int $programId): array {
    $groups = ['badge' => [], 'core_value' => [], 'why' => [], 'career' => [], 'uniform' => [], 'facility' => [], 'hero_slide' => []];
    try {
        $stmt = getDB()->prepare("
            SELECT i.*, m.file_path AS upload_path
            FROM program_items i
            LEFT JOIN media_library m ON m.media_id = i.image_media_id
            WHERE i.program_id = ?
            ORDER BY i.display_order ASC, i.item_id ASC
        ");
        $stmt->execute([$programId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $it) {
            if (!isset($groups[$it['item_type']])) continue;
            $upload = programPageFileUrl($it['upload_path'] ?? null);
            $it['image_url']    = $upload ?? programPageFileUrl($it['image_path'] ?? null);
            $it['has_upload']   = $upload !== null;
            // banner slides can be a picture or a video: tell the page which one by the file's extension
            $it['media_type']   = preg_match('/\.(mp4|webm|mov)$/i', (string)$it['image_url']) ? 'video' : 'image';
            $groups[$it['item_type']][] = $it;
        }
    } catch (Exception $e) {
        error_log('Program items error: ' . $e->getMessage());
    }
    return $groups;
}

function getProgramPageBySlug(string $slug): ?array {
    try {
        $stmt = getDB()->prepare(PROGRAM_PAGE_SELECT_SQL . " WHERE p.slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $program = hydrateProgramPage($row);
        $program['items'] = programPageItems((int)$program['program_id']);
        return $program;
    } catch (Exception $e) {
        error_log('Get program page error: ' . $e->getMessage());
        return null;
    }
}

function getProgramPageById(int $programId): ?array {
    try {
        $stmt = getDB()->prepare(PROGRAM_PAGE_SELECT_SQL . " WHERE p.program_id = ? LIMIT 1");
        $stmt->execute([$programId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $program = hydrateProgramPage($row);
        $program['items'] = programPageItems((int)$program['program_id']);
        return $program;
    } catch (Exception $e) {
        error_log('Get program page by id error: ' . $e->getMessage());
        return null;
    }
}

/** Public page for a slug; unpublished programs read as "not found". */
function publicProgramPage(string $slug): ?array {
    $slug = strtolower(trim($slug));
    if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) return null;
    $program = getProgramPageBySlug($slug);
    return ($program && $program['published']) ? $program : null;
}

/**
 * Where an old slug now lives (slug_history), or null. Lets a renamed
 * program keep its old links. Silent when the table has another shape.
 */
function publicProgramSlugRedirect(string $slug): ?string {
    try {
        $stmt = getDB()->prepare("
            SELECT p.slug FROM slug_history h
            JOIN programs p ON p.program_id = h.target_id
            WHERE h.type = 'program' AND h.old_slug = ? LIMIT 1
        ");
        $stmt->execute([$slug]);
        $new = $stmt->fetchColumn();
        return $new ? (string)$new : null;
    } catch (Throwable $e) {
        return null;
    }
}