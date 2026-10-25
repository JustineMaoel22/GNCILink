<?php
/**
 * Shared department detail layout for College, Basic Education and Graduate School.
 * Public entry points keep their URLs and supply the department slug.
 * Section text, office details and media are edited through the Visual Editor.
 */
require_once __DIR__ . '/department-public.php';

// Content Editor preview (?ce_preview=1, signed in as someone who may manage page content): the page also prints
// editing markers and the current stored values. Visitors never get either.
$cePreview = cePreviewCanManage();

$slug = trim((string)($_GET['slug'] ?? ''));
$dept = $slug !== '' ? publicDepartment($slug, $cePreview) : null; // null when missing OR archived (editors in the preview may open a hidden one to restore it)

if (!$dept) {
    http_response_code(404);
}

$dean       = $dept ? publicDepartmentDean($dept) : ['photo_url' => null, 'name' => '', 'title' => '', 'bio' => '', 'phone' => '', 'email' => '', 'office' => '', 'hours' => ''];
$programs   = $dept ? publicDepartmentPrograms((int)$dept['dept_id']) : [];
$fbEmbedUrl = $dept ? publicDepartmentFacebookEmbedUrl((string)($dept['facebook_url'] ?? '')) : null;
$logoUrl    = $dept['logo_url'] ?? null;
$pageTitle  = $dept ? (string)$dept['dept_name'] : 'Department Not Found';
$isGraduateSchool = ($dept['slug'] ?? '') === 'graduate-school';
$isBasicEducation = ($dept['department_type'] ?? '') === 'basic-education';
$collegeBanner = $dept && !$isBasicEducation && !$isGraduateSchool ? departmentBanner($dept) : null;
$isCollegeHeader = $dept && !$isBasicEducation && !$isGraduateSchool;
$pageContent = $dept ? departmentPageContent($dept) : [];
$logoUrl = $logoUrl ?: ($isGraduateSchool ? '/assets/images/logos/gnc-logo-v1.svg' : null);
$dean['photo_url'] = $dean['photo_url'] ?: ($isGraduateSchool && is_file($_SERVER['DOCUMENT_ROOT'] . '/assets/images/maam-nazal.png') ? '/assets/images/maam-nazal.png' : null);
$departmentIndexTitle = $isBasicEducation ? 'Basic Education Departments' : ($isGraduateSchool ? 'Graduate School' : 'College Departments');
$departmentIndexUrl = $isBasicEducation ? '/pages/academics/basic-edu/basic-edu-dept.php' : ($isGraduateSchool ? '/pages/academics/graduate-school/graduate-school.php' : '/pages/academics/college/college-dept.php');

/* ---------- tiny render helpers (output escaping + overview formatting) ---------- */

function cd_h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Render the admin's plain-text overview EXACTLY the way it was typed:
 *   - a blank line starts a new paragraph
 *   - a single line break inside a paragraph is kept (<br>)
 *   - a line ending with ":" becomes a bold heading, and the lines right under
 *     it (until the next blank line) become a bullet list — dashes optional
 *   - lines starting with "- " / "• " / "* " are bullet items anywhere
 * If the stored value already contains HTML tags it is passed through as-is.
 */
function cd_overview_html(?string $text): string {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (preg_match('/<[a-z][\s\S]*>/i', $text)) return richTextSanitize($text);

    $text   = str_replace(["\r\n", "\r"], "\n", $text);
    $blocks = preg_split('/\n[ \t]*\n/', $text);
    $html   = '';

    foreach ($blocks as $block) {
        $para = [];
        $items = [];
        $listMode = false;

        $flushPara = function () use (&$para, &$html) {
            if ($para) { $html .= '<p>' . implode('<br>', $para) . '</p>'; $para = []; }
        };
        $flushList = function () use (&$items, &$html) {
            if ($items) {
                $html .= '<ul class="dept-offers-list">';
                foreach ($items as $it) { $html .= '<li>' . $it . '</li>'; }
                $html .= '</ul>';
                $items = [];
            }
        };

        foreach (explode("\n", $block) as $line) {
            $line = trim($line);
            if ($line === '') continue;

            if (preg_match('/^[-•*]\s+(.*)$/u', $line, $m)) {          // explicit bullet
                $flushPara();
                $items[] = cd_h($m[1]);
                $listMode = true;
            } elseif (substr($line, -1) === ':') {                      // heading for a list
                $flushPara();
                $flushList();
                $html .= '<p class="dept-offers-label">' . cd_h($line) . '</p>';
                $listMode = true;
            } elseif ($listMode) {                                      // plain line under a heading
                $items[] = cd_h($line);
            } else {
                $para[] = cd_h($line);
            }
        }
        $flushPara();
        $flushList();
    }
    return $html;
}

/**
 * One-line summary for the page banner when the admin left "Line under the
 * page title" empty: the first paragraph of the short description, trimmed
 * to a sentence so it never repeats the whole About text.
 */
function cd_banner_summary(?string $text, int $max = 220): string {
    $text = trim((string)$text);
    if ($text === '') return '';
    $text  = str_replace(["\r\n", "\r"], "\n", $text);
    $first = preg_split('/\n[ \t]*\n/', $text)[0];
    $first = trim((string)preg_replace('/\s+/u', ' ', $first));
    if (mb_strlen($first) <= $max) return $first;
    $cut = mb_substr($first, 0, $max);
    $dot = mb_strrpos($cut, '. ');
    if ($dot !== false && $dot > 60) return mb_substr($cut, 0, $dot + 1);
    return rtrim(mb_substr($cut, 0, (int)mb_strrpos($cut, ' ')), " ,;:-") . '…';
}

// "About this department" box; if it was left empty, fall back to the short description.
$aboutSource = $dept ? (trim((string)($dept['about_text'] ?? '')) !== '' ? $dept['about_text'] : ($dept['description'] ?? '')) : '';
$aboutHtml   = cd_overview_html($aboutSource);
$deanTitle   = trim((string)$dean['title']);
// Banner line = "Line under the page title" (hero_text); if empty, a one-sentence summary.
$heroTitle = $isBasicEducation ? $pageContent['hero_title']['value'] : $pageTitle;
$heroTitleHtml = cd_h($heroTitle);
$heroThemeStyle = '';
if ($isCollegeHeader) {
    require_once __DIR__ . '/program-page-public.php';
    $heroThemeStyle = programPageThemeStyle(programPageNormalizeHex($dept['theme_color'] ?? '') ?? '#1F5E2C');
    if (preg_match('/^(College\s+of)\s+(.+)$/iu', $heroTitle, $titleParts)) {
        $heroTitleHtml = '<span class="dept-hero-prefix">' . cd_h($titleParts[1]) . ' </span><span class="dept-hero-subject">' . cd_h($titleParts[2]) . '</span>';
    }
}
$heroBlurb = $dept
    ? (trim((string)($dept['hero_text'] ?? '')) !== ''
        ? trim((string)$dept['hero_text'])
        : cd_banner_summary($dept['description'] ?? ''))
    : '';
$hasContact = trim((string)$dean['phone']) !== '' || trim((string)$dean['email']) !== ''
    || trim((string)$dean['office']) !== '' || trim((string)$dean['hours']) !== ''
    || $cePreview;   // the editor always shows every contact line, so an empty one can be filled in
/** Text of a stored value: visitors get line breaks as <br>; the editor gets the raw text (it edits exactly what is stored). */
$cdText = function (?string $v) use ($cePreview): string {
    return $cePreview ? cd_h($v) : nl2br(cd_h($v));
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= cd_h($pageTitle) ?></title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined&amp;display=block">
    <link href="/assets/css/college-dept-style.css?v=<?= (int)filemtime(__DIR__ . '/../assets/css/college-dept-style.css') ?>" rel="stylesheet">
    <link href="/assets/css/basic-education-content.css?v=<?= (int)filemtime(__DIR__ . '/../assets/css/basic-education-content.css') ?>" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
    <style>
        /* Small additions for states the stylesheet doesn't cover
           (missing logo / photo / programs, 404, Facebook embed). */
        .dept-logo-empty,
        .dept-dean-empty {
            display: flex; align-items: center; justify-content: center;
            background: #f1ede6; color: var(--gnc-green-light, #1F5E2C);
        }
        .dept-logo-empty { border-radius: 8px; font-size: 4rem; }
        .dept-dean-empty { width: 100%; height: 100%; font-size: 5rem; }
        .dept-program-img .dept-program-avatar { width: 100%; height: 100%; background: #f1ede6; }
        .dept-program-empty {
            font-family: 'Inter', sans-serif; font-size: 14px;
            color: var(--gnc-text-gray, #6b6b66);
            background: #fff; border: 1px dashed var(--gnc-border, #e6e1d8);
            border-radius: 10px; padding: 32px 20px; text-align: center;
        }
        .dept-notfound .dept-about-inner { grid-template-columns: 1fr; text-align: center; }
        .dept-notfound i.dept-notfound-icon { font-size: 2.6rem; color: var(--gnc-green-light, #1F5E2C); }
        .dept-facebook { text-align: center; }
        .dept-facebook .dept-programs-inner h2 { margin-bottom: 12px; }
        .dept-fb-lead {
            font-family: 'Inter', sans-serif; font-size: 15px; line-height: 1.7;
            color: var(--gnc-text-gray, #6b6b66); 
            margin: 0 auto 24px;
            max-width: 100ch;
        }
        /* Compact Facebook page header, centered with the section text. */
        .dept-fb-box { 
            width: 100%;
            max-width: 500px;
            margin-inline: auto;
            transition: transform 0.3s cubic-bezier(0.22, 0.61, 0.36, 1), box-shadow 0.3s ease;
            border-radius: 8px;
        }
        .dept-fb-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.12);
        }
        .dept-fb-box iframe {
            display: block; 
            width: 100%; 
            height: 130px;
            border: none; /* Removed custom border to let native Facebook border show */
            background: transparent;
            border-radius: inherit;
        }
    </style>
    <script>document.documentElement.classList.add('js-anim');</script>
</head>
<body>
    <?php $activeSection = 'academics'; ?>
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main<?= ($dept && !empty($dept['archived_at'])) ? ceManageMark('data-ve-status="draft" style="position:relative"') : '' ?>>

        <!-- ═══ Hero (inside .dept-intro so the --gnc-* variables apply) ═══ -->
        <section class="dept-intro<?= $isCollegeHeader ? ' dept-intro-program' : '' ?>"<?= $heroThemeStyle !== '' ? ' style="' . cd_h($heroThemeStyle) . '"' : '' ?>>
            <div class="dept-hero">
                <?php if ($isCollegeHeader): ?>
                <div class="dept-header-banner" data-dept-banner-image<?= ceManageMark('data-ve-img="dept-banner"') ?> aria-hidden="true">
                    <img<?= $collegeBanner ? ' src="' . cd_h($collegeBanner['file_path']) . '"' : ' hidden' ?> alt="" fetchpriority="high">
                </div>
                <?php endif; ?>
                <div class="dept-hero-inner">
                    <nav class="dept-breadcrumb" aria-label="breadcrumb">
                        <span>Academics</span>
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        <a href="<?= cd_h($departmentIndexUrl) ?>"><?= cd_h($departmentIndexTitle) ?></a>
                        <?php if ($dept): ?>
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        <span class="dept-current" aria-current="page"><?= cd_h($pageTitle) ?></span>
                        <?php endif; ?>
                    </nav>
                    <div class="dept-hero-content">
                    <?php if ($isCollegeHeader && ($logoUrl || $cePreview)): ?>
                    <?= basicEducationMedia($logoUrl, $pageTitle . ' logo', 'dept-hero-logo', ' data-gnc-text="' . substr(hash('sha256', 'department-header-logo:' . $dept['dept_id']), 0, 24) . '" data-gnc-layout-media' . ceManageMark('data-ve-img="dept-logo"')) ?>
                    <?php endif; ?>
                    <div class="dept-hero-copy">
                    <h1 class="dept-hero-title"<?= $dept ? ($isBasicEducation ? departmentPageField($dept, 'hero_title') : ceField('dept-name', ['max' => 255, 'label' => 'Department name'])) : '' ?>><?= $heroTitleHtml ?></h1>
                    <?php if ((!$isCollegeHeader || strcasecmp(trim($heroBlurb), trim($heroTitle)) !== 0) && ($heroBlurb !== '' || ($cePreview && $dept))): ?>
                    <p class="dept-hero-blurb"<?= $dept ? ceField('dept-hero', ['max' => 255, 'label' => 'Line under the title', 'ph' => 'Add a line under the title']) : '' ?>><?= cd_h($heroBlurb) ?></p>
                    <?php endif; ?>
                    </div>
                    </div>
                </div>
                <?php if ($cePreview && $isCollegeHeader): ?>
                <div class="dept-banner-help"<?= ceManageMark('data-ve-img="dept-banner"') ?>>
                    <?= $collegeBanner ? 'Change header background' : 'Add header background' ?>
                    <small>JPG, PNG or WEBP, up to 5 MB. The title and logo stay editable above the picture.</small>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!$dept): ?>

        <section class="dept-about dept-notfound">
            <div class="dept-about-inner">
                <div class="dept-about-content" data-reveal style="--d: 120ms">
                    <i class="bi bi-diagram-3 dept-notfound-icon" aria-hidden="true"></i>
                    <h2>Department not found</h2>
                    <p>The department you're looking for doesn't exist or is no longer published.</p>
                    <a class="dept-program-link" href="<?= cd_h($departmentIndexUrl) ?>">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i> Back to <?= cd_h($departmentIndexTitle) ?>
                    </a>
                </div>
            </div>
        </section>

        <?php else: ?>

        <!-- ═══ 1 · About / Overview ═══ -->
        <section class="dept-about">
            <div class="dept-about-inner<?= !$logoUrl && !$cePreview ? ' dept-layout-text-only' : '' ?>">
                <?php if ($logoUrl || $cePreview): ?>
                <div class="dept-logo-wrap" data-reveal="scale">
                    <span class="dept-logo-square" aria-hidden="true"></span>
                    <?= basicEducationMedia($logoUrl, $pageTitle . ' logo', 'dept-logo', ceManageMark('data-ve-img="dept-logo"')) ?>
                    <span class="dept-dots" aria-hidden="true"></span>
                </div>
                <?php endif; ?>
                <div class="dept-about-content" data-reveal="right" style="--d: 150ms">
                    <span class="dept-eyebrow"<?= departmentPageField($dept, 'overview_label') ?>><?= departmentPageText($dept, 'overview_label') ?></span>
                    <h2<?= departmentPageField($dept, 'overview_title') ?>><?= departmentPageText($dept, 'overview_title') ?></h2>
                    <?php if ($cePreview): ?>
                        <?php $aboutPh = (trim((string)($dept['about_text'] ?? '')) === '' && trim((string)($dept['description'] ?? '')) !== '')
                            ? 'Empty: visitors currently see the short description here. Type to write the full text.' : 'Write about this department…'; ?>
                        <div class="dept-about-raw" data-ve-overview<?= ceField('dept-about', ['max' => 10000, 'multiline' => true, 'label' => 'About this department', 'ph' => $aboutPh]) ?>><?= $aboutHtml ?></div>
                    <?php elseif ($aboutHtml !== ''): ?>
                        <?= $aboutHtml ?>
                    <?php else: ?>
                        <p><?= departmentPageText($dept, 'overview_empty') ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if (!$isBasicEducation && !$isGraduateSchool): ?>
        <?php $departmentStatements = array_filter(['vision', 'mission'], static fn($key) => $cePreview || trim(strip_tags($pageContent[$key . '_text']['value'])) !== ''); ?>
        <?php if ($departmentStatements): ?>
        <section class="dept-programs" aria-label="Department vision and mission">
            <div class="dept-programs-inner">
                <div class="dept-statements">
                    <?php foreach ($departmentStatements as $statement): ?>
                    <article class="dept-statement-card">
                        <div class="dept-statement-head">
                            <span class="dept-statement-icon"<?= ceManageMark('data-ve-icon="department-statement" data-ve-key="' . $statement . '_icon" role="button" tabindex="0" aria-label="Change ' . $statement . ' icon"') ?>><?= departmentStatementIcon($dept, $statement . '_icon') ?></span>
                            <h2 class="dept-statement-title"<?= departmentPageField($dept, $statement . '_title') ?>><?= departmentPageText($dept, $statement . '_title') ?></h2>
                        </div>
                        <div class="dept-statement-text cms-rich"<?= departmentPageField($dept, $statement . '_text') ?>><?= departmentPageText($dept, $statement . '_text') ?></div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
        <?php endif; ?>

        <!-- ═══ 2 · Programs Offered (from `programs` where dept_id = this dept) ═══ -->
        <?php if (!$isBasicEducation || $cePreview || $programs): ?>
        <section class="dept-programs">
            <div class="dept-programs-inner">
                <span class="dept-eyebrow" data-reveal="right"<?= departmentPageField($dept, 'programs_label') ?>><?= departmentPageText($dept, 'programs_label') ?></span>
                <h2 data-reveal="right" style="--d: 80ms"<?= departmentPageField($dept, 'programs_title') ?>><?= departmentPageText($dept, 'programs_title') ?></h2>
                <?php if (empty($programs) && !$cePreview): ?>
                    <div class="dept-program-empty"<?= departmentPageField($dept, 'programs_empty') ?>><?= departmentPageText($dept, 'programs_empty') ?></div>
                <?php else: ?>
                <div class="dept-programs-grid"<?= ceManageMark('data-ve-program-grid') ?>>
                    <?php foreach ($programs as $i =>$p): ?>
                    <article class="dept-program-card<?= !$cePreview && !$isBasicEducation ? ' dept-program-card-clickable' : '' ?>" data-reveal="scale" style="--d: <?= ($i % 3) * 100 ?>ms"<?= ceManageMark('data-ve-program-card="' . (int)$p['program_id'] . '"' . (!$isBasicEducation && !empty($p['slug']) ? ' data-ve-goto="' . cd_h(publicProgramPageUrl($p)) . '"' : '') . ' data-ve-program-id="' . (int)$p['program_id'] . '" data-ve-label="' . cd_h($p['program_name']) . '"') ?>>
                        <div class="dept-program-img">
                            <?php if ($cePreview): ?>
                                <?= basicEducationMedia($p['image_url'], $p['program_name'], '', ceManageMark('data-ve-program-image="' . (int)$p['program_id'] . '"')) ?>
                            <?php elseif (!empty($p['image_url'])): ?>
                                <img src="<?= cd_h($p['image_url']) ?>" alt="<?= cd_h($p['program_name']) ?>" loading="lazy" decoding="async">
                            <?php else: ?>
                                <div class="dept-program-avatar" aria-hidden="true"></div>
                            <?php endif; ?>
                        </div>
                        <div class="dept-program-body">
                            <h3<?= ceField('dept-program-name', ['key' => (string)$p['program_id'], 'max' => 255, 'label' => 'Program heading', 'ph' => 'Add heading']) ?>><?= cd_h($p['program_name']) ?></h3>
                            <?php if (trim((string)($p['description'] ?? '')) !== '' || $cePreview): ?>
                                <div class="cms-rich"<?= ceField('dept-program-description', ['key' => (string)$p['program_id'], 'max' => 5000, 'multiline' => true, 'rich'=>true, 'label' => 'Program description', 'ph' => 'Add description']) ?>><?= renderSectionContent($p['description']) ?></div>
                            <?php endif; ?>
                            <?php if (!$isBasicEducation): ?>
                            <a class="dept-program-link"
                               href="<?= cd_h(publicProgramPageUrl($p)) ?>"
                               aria-label="View details for <?= cd_h($p['program_name']) ?>">
                                <span<?= departmentPageField($dept, 'program_link') ?>><?= departmentPageText($dept, 'program_link') ?></span> <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php endforeach; ?>
                    <?php if ($cePreview): ?>
                    <button type="button" class="ve-add-tile dept-program-add" data-ve-add="program" data-ve-dept-id="<?= (int)$dept['dept_id'] ?>"><span style="font-size:28px;line-height:1">+</span><span>Add a program</span></button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($cePreview): ?>
                <template id="veDepartmentProgramTemplate">
                    <article class="dept-program-card" data-ve-program-card="__KEY__">
                        <div class="dept-program-img"><?= basicEducationMedia('', '', '', ' data-ve-program-image="__KEY__"') ?></div>
                        <div class="dept-program-body">
                            <h3<?= ceField('dept-program-name', ['key' => '__KEY__', 'max' => 255, 'label' => 'Program heading', 'ph' => 'Add heading']) ?>></h3>
                            <div class="cms-rich"<?= ceField('dept-program-description', ['key' => '__KEY__', 'max' => 5000, 'multiline' => true, 'rich'=>true, 'label' => 'Program description', 'ph' => 'Add description']) ?>></div>
                        </div>
                    </article>
                </template>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ═══ 3 · Head of Office + Get Connected (from departments.dean_*) ═══ -->
        <section class="dept-dean">
            <div class="dept-dean-inner">

                <div class="dept-dean-intro" data-reveal="right">
                    <span class="dept-eyebrow"<?= departmentPageField($dept, 'leadership_label') ?>><?= departmentPageText($dept, 'leadership_label') ?></span>
                    <h2<?= departmentPageField($dept, 'leadership_title') ?>><?= departmentPageText($dept, 'leadership_title') ?></h2>
                    <div class="cms-rich"<?= departmentPageField($dept, 'leadership_intro') ?>><?= departmentPageText($dept, 'leadership_intro') ?></div>
                </div>

                <div class="dept-dean-grid<?= empty($dean['photo_url']) && !$cePreview ? ' dept-layout-text-only' : '' ?>">
                    <?php if (!empty($dean['photo_url']) || $cePreview): ?>
                    <div class="dept-dean-photo-wrap" data-reveal="scale">
                        <span class="dept-dean-square" aria-hidden="true"></span>
                        <span class="dept-dean-accent" aria-hidden="true"></span>
                        <div class="dept-dean-photo">
                            <?= basicEducationMedia($dean['photo_url'], $dean['name'], 'dept-dean-upload', ceManageMark('data-ve-img="dean-photo"')) ?>

                            <div class="dept-dean-wave" aria-hidden="true">
                                <svg viewBox="0 0 260 90" preserveAspectRatio="none" focusable="false">
                                    <path class="wave-gold"   d="M0 38 C50 8 100 62 160 34 S235 18 260 30 V90 H0 Z"/>
                                    <path class="wave-maroon" d="M0 54 C55 28 110 76 170 50 S238 38 260 48 V90 H0 Z"/>
                                    <path class="wave-green"  d="M0 70 C60 50 120 88 180 66 S240 60 260 66 V90 H0 Z"/>
                                </svg>
                            </div>

                            <div class="dept-dean-badge">
                                <span class="dept-dean-badge-title"<?= ceField('dean-title', ['max' => 255, 'label' => 'Position', 'ph' => 'Position']) ?>><?= cd_h($deanTitle) ?></span>
                                <span class="dept-dean-badge-sub"<?= departmentPageField($dept, 'profile_department') ?>><?= departmentPageText($dept, 'profile_department') ?></span>
                            </div>
                        </div>
                    </div>

                    <?php endif; ?>
                    <div class="dept-dean-content" data-reveal="left" style="--d: 150ms">
                        <span class="dept-eyebrow"<?= departmentPageField($dept, 'profile_label') ?>><?= departmentPageText($dept, 'profile_label') ?></span>
                        <h3<?= ceField('dean-name', ['max' => 255, 'label' => 'Name', 'ph' => 'Name of the head of office']) ?>><?= cd_h($dean['name']) ?></h3>
                        <div class="dept-dean-rule" aria-hidden="true"></div>
                        <?php if (trim((string)$dean['bio']) !== '' || $cePreview): ?>
                            <div class="cms-rich"<?= ceField('dean-bio', ['max' => 5000, 'multiline' => true, 'rich'=>true, 'label' => 'Short biography', 'ph' => 'Write a short introduction']) ?>><?= renderSectionContent($dean['bio']) ?></div>
                        <?php endif; ?>

                        <?php if ($hasContact): ?>
                        <hr class="dept-dean-divider">
                        <span class="dept-eyebrow"<?= departmentPageField($dept, 'contacts_label') ?>><?= departmentPageText($dept, 'contacts_label') ?></span>
                        <div class="dept-dean-contacts-grid">
                            <?php if (trim((string)$dean['phone']) !== '' || $cePreview): ?>
                            <div class="dept-dean-contact" data-reveal="scale">
                                <i class="bi bi-telephone" aria-hidden="true"></i>
                                <div>
                                    <span class="dept-contact-label"<?= departmentPageField($dept, 'phone_label') ?>><?= departmentPageText($dept, 'phone_label') ?></span>
                                    <span class="dept-contact-value"<?= ceField('dean-phone', ['max' => 255, 'label' => 'Phone', 'ph' => 'Add a phone number']) ?>><?= $cdText($dean['phone']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (trim((string)$dean['email']) !== '' || $cePreview): ?>
                            <div class="dept-dean-contact" data-reveal="scale">
                                <i class="bi bi-envelope" aria-hidden="true"></i>
                                <div>
                                    <span class="dept-contact-label"<?= departmentPageField($dept, 'email_label') ?>><?= departmentPageText($dept, 'email_label') ?></span>
                                    <span class="dept-contact-value"<?= ceField('dean-email', ['max' => 255, 'label' => 'Email', 'ph' => 'Add an email address']) ?>><?= $cdText($dean['email']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (trim((string)$dean['office']) !== '' || $cePreview): ?>
                            <?= departmentOfficeCard($dept, 'dept-dean-contact') ?>
                            <?php endif; ?>
                            <?php if (trim((string)$dean['hours']) !== '' || $cePreview): ?>
                            <div class="dept-dean-contact" data-reveal="scale">
                                <i class="bi bi-clock" aria-hidden="true"></i>
                                <div>
                                    <span class="dept-contact-label"<?= departmentPageField($dept, 'hours_label') ?>><?= departmentPageText($dept, 'hours_label') ?></span>
                                    <span class="dept-contact-value"<?= ceField('dean-hours', ['max' => 500, 'multiline' => true, 'label' => 'Operating hours', 'ph' => 'Add the operating hours']) ?>><?= $cdText($dean['hours']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </section>

        <!-- ═══ 4 · Connect with Us (from departments.facebook_url) ═══ -->
        <?php if ($fbEmbedUrl !== null || $cePreview): ?>
        <section class="dept-programs dept-facebook">
            <div class="dept-programs-inner">
                <span class="dept-eyebrow" data-reveal="right"<?= departmentPageField($dept, 'facebook_label') ?>><?= departmentPageText($dept, 'facebook_label') ?></span>
                <h2 data-reveal="right" style="--d: 80ms"<?= departmentPageField($dept, 'facebook_title') ?>><?= departmentPageText($dept, 'facebook_title') ?></h2>
                <div class="dept-fb-lead cms-rich" data-reveal="right" style="--d: 120ms"<?= departmentPageField($dept, 'facebook_intro') ?>><?= departmentPageText($dept, 'facebook_intro') ?></div>
                <?php if ($fbEmbedUrl !== null): ?>
                <div class="dept-fb-box" data-reveal="scale" style="--d: 200ms<?= $cePreview ? '; position: relative' : '' ?>">
                    <iframe src="<?= cd_h($fbEmbedUrl) ?>" width="500" height="130" scrolling="no" frameborder="0"
                            allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"
                            allowfullscreen loading="lazy"
                            title="<?= cd_h($pageTitle) ?> Facebook page"></iframe>
                    <?php if ($cePreview): ?>
                    <?php /* The Facebook plugin is another website's frame: clicks inside it never reach the editor, so a see-through cover takes the click. */ ?>
                    <div data-ve-pick="dept-facebook" data-ve-label="Facebook page" title="Click to change the Facebook page" style="position:absolute;inset:0;z-index:2;cursor:pointer"></div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="ve-add-tile" data-ve-pick="dept-facebook" data-ve-label="Facebook page"><span style="font-size:28px;line-height:1">+</span><span>Add the Facebook page link</span></div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php endif; ?>

    </main>

    <?= ceDeptEditorBlock($dept, $isBasicEducation ? 'basic' : ($isGraduateSchool ? 'graduate' : 'college')) ?>
    <?= $dept ? ceData('veDeptPageData', ['dept_id' => (int)$dept['dept_id'], 'fields' => $pageContent, 'icons' => array_filter(academicFeatureIconChoices(), static fn($choice) => !isset($choice['path']))]) : '' ?>
    <?= $dept ? ceData('veDeptProgramsData', array_map(fn($p) => ['program_id' => (int)$p['program_id'], 'program_name' => (string)$p['program_name'], 'description' => (string)($p['description'] ?? ''), 'image_url' => $p['image_url'] ?? null], $programs)) : '' ?>
    <?php include __DIR__ . '/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
    <script src="/assets/js/college-department.js"></script>
</body>
</html>
