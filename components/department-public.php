<?php
/**
 * Public-side helpers for the College Department pages.
 *
 * Read by: pages/academics/college/college-departments.php (the departments
 *          listing) and pages/academics/college/college-department.php (the
 *          single dynamic page that renders EVERY college department).
 *
 * All data comes from the `departments`, `programs`, `announcements` and
 * `media_library` tables — nothing here is hardcoded per department, so a
 * department created / edited / archived / deleted in the Admin Panel
 * (Content Editor → College Departments) is reflected on the public site
 * immediately.
 */

if (!function_exists('getDB')) {
    require_once __DIR__ . '/../config/config.php';
}
require_once __DIR__ . '/../admin/admin-functions.php';

/**
 * Every department keyed by slug.
 *
 * Archived departments are hidden from the public site by default (they read
 * as "not found"), which is what the archive feature in the Admin Panel
 * promises ("hidden from website"). Pass $includeArchived = true when an
 * admin preview needs them.
 */
function publicDepartmentsBySlug(bool $includeArchived = false): array {
    static $maps = [];
    $key = $includeArchived ? 'all' : 'active';

    if (!array_key_exists($key, $maps)) {
        $maps[$key] = [];
        foreach (getDepartments() as $d) {
            if (empty($d['slug'])) {
                continue;
            }
            if (!$includeArchived && !empty($d['archived_at'])) {
                continue;
            }
            $maps[$key][$d['slug']] = $d;
        }
    }
    return $maps[$key];
}

/**
 * One department by its unique slug, or null (also null when the department
 * has been archived — archived means "hidden from the public website").
 */
function publicDepartment(string $slug, bool $includeArchived = false): ?array {
    return publicDepartmentsBySlug($includeArchived)[$slug] ?? null;
}

/**
 * The dean / "Head of Office" slice of a department row, with empty-string
 * defaults so templates never have to null-check every field.
 */
function publicDepartmentDean(?array $dept): array {
    return [
        'photo_url' => $dept['dean_photo_url'] ?? null,
        'name'      => $dept['dean_name']   ?? '',
        'title'     => $dept['dean_title']  ?? '',
        'bio'       => $dept['dean_bio']    ?? '',
        'phone'     => $dept['dean_phone']  ?? '',
        'email'     => $dept['dean_email']  ?? '',
        'office'    => $dept['dean_office'] ?? '',
        'hours'     => $dept['dean_hours']  ?? '',
    ];
}

/**
 * True once sql/department-type-migration.sql has been run (the `departments`
 * table has a `department_type` column). Checked once per request. Until then
 * every department is treated as a college department, which matches how the
 * old static College Departments page behaved.
 */
function publicDepartmentTypesSupported(): bool {
    static $supported = null;
    if ($supported === null) {
        try {
            $supported = (bool) getDB()->query("SHOW COLUMNS FROM departments LIKE 'department_type'")->fetch();
        } catch (Exception $e) {
            error_log('Department type check error: ' . $e->getMessage());
            $supported = false;
        }
    }
    return $supported;
}

/**
 * College departments only — the ones that belong on the public College
 * Departments page. Basic-education departments (CASA/GS Montessori, HS
 * Montessori, JHS Regular, SHS Regular) have their own page and are excluded
 * once the migration has been run. Archived departments are always excluded.
 */
function publicDepartmentIsCollege(array $dept): bool {
    if (($dept['slug'] ?? '') === 'graduate-school') return false;
    if (!publicDepartmentTypesSupported()) {
        return true;
    }
    return ($dept['department_type'] ?? 'college') !== 'basic-education';
}

/**
 * All live college departments in display order (dept_id = creation order,
 * the same order the old static page used).
 */
function publicCollegeDepartments(): array {
    $out = [];
    foreach (getDepartments() as $d) {
        if (!empty($d['archived_at'])) {
            continue;
        }
        if (!publicDepartmentIsCollege($d)) {
            continue;
        }
        $out[] = $d;
    }
    return $out;
}

/**
 * Every program that belongs to a department (programs.dept_id), hydrated
 * with program_name (full display name) and the pdf_* fields, exactly like
 * getPrograms() does.
 */
function publicDepartmentPrograms(int $deptId): array {
    if ($deptId <= 0) {
        return [];
    }
    try {
        // LEFT JOIN the card image (programs.image_media_id — added by
        // sql/college-department-page-migration.sql). hydrateProgram() adds
        // program_name + pdf_*; the map below adds image_url/image_name.
        $stmt = getDB()->prepare("
            SELECT p.*, im.file_path AS image_path, im.file_name AS image_name
            FROM programs p
            LEFT JOIN media_library im ON im.media_id = p.image_media_id
            WHERE p.dept_id = ?
            ORDER BY p.program_id ASC
        ");
        $stmt->execute([$deptId]);
        // Programs hidden in Content Editor -> Programs (is_published = 0) don't get a card.
        $rows = array_filter($stmt->fetchAll(PDO::FETCH_ASSOC), function (array $row): bool {
            return !isset($row['is_published']) || (int)$row['is_published'] === 1;
        });
        return array_map(function (array $row): array {
            $row = hydrateProgram($row);
            $row['image_url'] = departmentFileExists($row['image_path'] ?? null) ? $row['image_path'] : null;
            if ($row['image_url'] === null) {
                $row['image_name'] = null;
            }
            return $row;
        }, array_values($rows));
    } catch (Exception $e) {
        error_log('Get department programs error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Public URL of a program's page.
 *
 * Programs keep their own page files for now, named after the program code
 * (beed.php, bsba-fm.php, bsce.php, ...). The `slug` column on `programs`
 * (plus the slug_history table) is the planned dynamic-programs upgrade: as
 * soon as a program has a slug, this links to the dynamic program page
 * instead, so no template has to change when that lands.
 */
function publicProgramPageUrl(array $program): string {
    if (!empty($program['slug'])) {
        return '/pages/academics/college/program.php?slug=' . urlencode($program['slug']);
    }
    $code = preg_replace('/[^a-z0-9-]/', '', strtolower((string)($program['program_code'] ?? '')));
    return '/pages/academics/college/programs/' . ($code !== '' ? $code : 'index') . '.php';
}

/**
 * Published announcements posted for one department (announcements.dept_id),
 * newest first, with the featured image path when there is one.
 */
function publicDepartmentAnnouncements(int $deptId, int $limit = 6): array {
    if ($deptId <= 0) {
        return [];
    }
    try {
        $stmt = getDB()->prepare("
            SELECT a.announcement_id, a.title, a.slug, a.content,
                   a.created_at, a.published_at,
                   m.file_path AS image_path
            FROM announcements a
            LEFT JOIN media_library m ON m.media_id = a.featured_image
            WHERE a.dept_id = ? AND a.status = 'published'
            ORDER BY a.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $deptId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Get department announcements error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Plain-text excerpt of an announcement's HTML content, for cards/lists.
 */
function publicAnnouncementExcerpt(string $html, int $max = 140): string {
    $text = trim((string)preg_replace('/\s+/u', ' ', strip_tags($html)));
    return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
}

/**
 * Short text for a department's card on the College Departments list.
 *
 * Always describes the department itself: the start of "About this
 * department", falling back to the "Short description" only when no About
 * text has been written. It never uses the "Line under the page title"
 * (hero_text).
 *
 * The text is flattened to one paragraph, limited to the first few sentences,
 * and cut at a word boundary with "..." when it doesn't fit in $max characters.
 */
function publicDepartmentCardText(array $dept, int $max = 160, int $maxSentences = 2): string {
    $source = trim((string)($dept['about_text'] ?? ''));
    if ($source === '') {
        $source = trim((string)($dept['description'] ?? ''));
    }
    if ($source === '') {
        return '';
    }

    // One flat paragraph: drop tags, turn line breaks / extra spaces into single spaces.
    $text = trim((string)preg_replace('/\s+/u', ' ', strip_tags($source)));
    if ($text === '') {
        return '';
    }

    // Keep only the first few sentences.
    $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $trimmed   = false;
    if (is_array($sentences) && count($sentences) > $maxSentences) {
        $text    = implode(' ', array_slice($sentences, 0, $maxSentences));
        $trimmed = true;
    }

    // Then make sure it fits.
    if (mb_strlen($text) > $max) {
        $cut   = mb_substr($text, 0, $max);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > $max * 0.6) {
            $cut = mb_substr($cut, 0, $space);
        }
        return rtrim($cut, " \t\n\r\0\x0B.,;:-") . '...';
    }

    return $trimmed ? rtrim($text, '.') . '...' : $text;
}

/**
 * Facebook Page Plugin URL for a department's facebook_url, or null when the
 * stored URL is not a facebook.com link (the section is then hidden).
 */
function publicDepartmentFacebookEmbedUrl(string $facebookUrl): ?string {
    $facebookUrl = trim($facebookUrl);
    if ($facebookUrl === '' || !preg_match('#^https?://(www\.)?facebook\.com/#i', $facebookUrl)) {
        return null;
    }
    return 'https://www.facebook.com/plugins/page.php?href=' . rawurlencode($facebookUrl)
        . '&tabs=&width=500&height=150&small_header=false&adapt_container_width=true'
        . '&hide_cover=false&show_facepile=false';
}

/**
 * What the Content Editor needs to edit one department page in place: the department's current stored values
 * (so a save always sends the whole record, never a half-empty one). Printed only for managers, only in the
 * editor's preview; visitors get an empty string. $kind is 'college' or 'basic' (Basic Education).
 */
function ceDeptEditorBlock(?array $dept, string $kind = 'college'): string {
    if (!$dept || !cePreviewCanManage()) return '';
    return ceData('veDeptData', departmentAdminPayload($dept) + ['kind' => $kind]);
}
