<?php
/**
 * The ONE page that shows every academic program.
 *
 *   /pages/academics/college/program.php?slug=bsmls
 *
 * Everything on it comes from the database and is edited in
 * Admin Panel -> Content Editor -> Programs. The only part that is NOT
 * editable there is the Bulletin Board: it fills itself with the
 * announcements and events posted for this program.
 *
 * A section with nothing in it simply doesn't appear (the Program Curriculum
 * only shows once a curriculum PDF is uploaded). Core Values only
 * appears for programs that switched it on.
 */
require_once __DIR__ . '/../../../components/program-page-public.php';

/**
 * Content Editor preview (?ce_preview=1, signed in as someone who may manage page content).
 * Only then does the Facilities section show its "Add slides / Edit slides" buttons, so the editor can
 * add pictures right on the page. Visitors never get this: the check costs nothing without ce_preview=1.
 */
$cePreview = false;
if (($_GET['ce_preview'] ?? '') === '1') {
    try {
        if (!function_exists('cePreviewActive')) {
            require_once __DIR__ . '/../../../admin/admin-functions.php';
        }
        $cePreview = function_exists('cePreviewActive') && cePreviewActive() && hasPermission('manage_page_content');
    } catch (Throwable $t) {
        $cePreview = false;
    }
}

$slug    = isset($_GET['slug']) ? (string)$_GET['slug'] : '';
$program = publicProgramPage($slug);
if ($program === null && $cePreview) {
    // The editor may open a program that is hidden from visitors (so it can be shown again). Visitors still get "not found".
    $slugKey = strtolower(trim($slug));
    if ($slugKey !== '' && preg_match('/^[a-z0-9-]+$/', $slugKey)) $program = getProgramPageBySlug($slugKey);
}

// Basic Education programs are informational cards, without separate detail pages.
if ($program !== null) {
    $programDepartment = getDepartmentById((int)$program['dept_id']);
    if (($programDepartment['department_type'] ?? '') === 'basic-education') {
        $program = null;
    }
}

if ($program === null) {
    // A renamed program keeps its old link working.
    $moved = publicProgramSlugRedirect(strtolower(trim($slug)));
    if ($moved !== null && $moved !== strtolower(trim($slug))) {
        header('Location: /pages/academics/college/program.php?slug=' . rawurlencode($moved), true, 301);
        exit;
    }
    http_response_code(404);
}

if ($program !== null) {
    // The bulletin board reads this to show only this program's posts.
    define('CURRENT_PROGRAM', $program['program_code']);
    require_once __DIR__ . '/../../../components/bulletin-board.php';
    require_once __DIR__ . '/../../../components/pdf-viewer.php';
}

/**
 * Carousel photo -> smaller cached copy.
 * Admins upload multi-MB photos; the carousel only shows them ~700px wide, so decoding the
 * originals is what made it lag. This makes (once) a ~1200px JPEG in a "_thumbs" folder next
 * to the original and returns its URL. The lightbox still opens the full-size original.
 * Any problem (remote URL, missing file, no GD, folder not writable...) -> the original URL.
 */
if (!function_exists('facilityCarouselThumb')) {
    function facilityCarouselThumb(string $url, int $maxW = 1200, int $quality = 78): string {
        try {
            if (!function_exists('imagecreatetruecolor')) return $url;
            $path = parse_url($url, PHP_URL_PATH);
            $host = parse_url($url, PHP_URL_HOST);
            if (!$path || ($host && strcasecmp($host, (string)($_SERVER['HTTP_HOST'] ?? '')) !== 0)) return $url;
            $root = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
            $src  = $root ? realpath($root . rawurldecode($path)) : false;
            if (!$src || strpos($src, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($src)) return $url;

            $dir = dirname($src) . DIRECTORY_SEPARATOR . '_thumbs';
            $name = md5($src . '|' . filemtime($src) . '|' . $maxW) . '.jpg';
            $dst = $dir . DIRECTORY_SEPARATOR . $name;
            $out = rtrim(str_replace('\\', '/', dirname($path)), '/') . '/_thumbs/' . $name;
            if (is_file($dst)) return $out;

            $info = @getimagesize($src);
            if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return $url;
            if ($info[0] <= $maxW && filesize($src) < 400000) return $url;   // already small
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return $url;
            if (!is_writable($dir)) return $url;

            @ini_set('memory_limit', '512M');
            @set_time_limit(30);
            switch ($info[2]) {
                case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg($src); break;
                case IMAGETYPE_PNG:  $im = @imagecreatefrompng($src);  break;
                default:             $im = @imagecreatefromwebp($src);
            }
            if (!$im) return $url;

            // phone photos: honour the EXIF rotation, as browsers do for the original
            if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $exif = @exif_read_data($src);
                $o = (int)($exif['Orientation'] ?? 1);
                $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
                if ($deg) { $r = imagerotate($im, $deg, 0); if ($r) { imagedestroy($im); $im = $r; } }
            }

            $w = imagesx($im); $h = imagesy($im);
            $nw = min($w, $maxW); $nh = (int)round($h * $nw / $w);
            $new = imagecreatetruecolor($nw, $nh);
            imagefill($new, 0, 0, imagecolorallocate($new, 255, 255, 255));   // PNG transparency -> white
            imagecopyresampled($new, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($im);
            imageinterlace($new, true);

            $tmp = $dst . '.' . getmypid() . '.tmp';
            $ok = imagejpeg($new, $tmp, $quality);
            imagedestroy($new);
            if (!$ok || !@rename($tmp, $dst)) { @unlink($tmp); return $url; }
            return $out;
        } catch (\Throwable $t) {
            return $url;
        }
    }
}

/**
 * Text that spins around the program seal.
 * It is the program's department name in capitals (College of Engineering -> "COLLEGE OF ENGINEERING"),
 * repeated with a bullet between copies so the ring is always full, whatever the name's length.
 * No department -> the standard wording, as before.
 */
if (!function_exists('programSealRingText')) {
    function programSealRingText(string $deptName): string {
        $label = trim(preg_replace('/\s+/u', ' ', $deptName));
        $label = $label !== '' ? mb_strtoupper($label, 'UTF-8') : 'COLLEGE OF ARTS, SCIENCE, AND EDUCATION';
        $targetChars = 82;                                   // about what the ring holds at a comfortable spacing
        $reps = max(1, (int)round($targetChars / (mb_strlen($label, 'UTF-8') + 3)));
        return implode(' • ', array_fill(0, $reps, $label)) . ' •';
    }
}

$e = function ($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$para = function ($v) use ($e): string { return nl2br($e($v)); };
// Long text: visitors get line breaks as <br>; the editor gets the raw stored text (it edits exactly what is saved).
$pt = function ($v): string { return renderSectionContent((string)$v); };
/** Editing marker for one stored text of this program (empty for visitors). */
$pf = function (string $key, string $label, bool $multi = false, string $ph = '') use ($cePreview): string {
    $max = (int)(programPageContentFields()[$key]['max'] ?? 0);
    return ceField('prog-text', ['key' => $key, 'max' => $max, 'label' => $label, 'multiline' => $multi || (programPageContentFields()[$key]['type'] ?? '') === 'long', 'rich'=>(programPageContentFields()[$key]['type'] ?? '') === 'long', 'ph' => $ph !== '' ? $ph : $label,
        'optional' => empty(programPageContentFields()[$key]['warning_required'])]);
};
/** Editing marker for one card of a list (badge, core value, why, career). */
$pi = function (string $type, int $index, string $prop, string $label, bool $multi = false) use ($cePreview): string {
    global $items;
    $id = (int)($items[$type][$index]['item_id'] ?? 0);
    $mark = ceField('prog-item', ['key' => $type . ':' . $index . ':' . $prop, 'label' => $label, 'multiline' => $multi, 'rich'=>$prop==='body', 'ph' => $label]);
    return preg_replace('/data-gnc-text="[^"]*"/', 'data-gnc-text="'.substr(hash('sha256','program-item:'.$id.':'.$prop),0,24).'"', $mark);
};
$pcard = function (string $type, int $index) use ($cePreview): string {
    return $cePreview ? ' data-ve-item="' . $type . ':' . $index . '"' : '';
};
$padd = function (string $type, string $label) use ($cePreview): string {
    return $cePreview ? '<div class="ve-add-tile" data-ve-add="item" data-ve-type="' . $type . '"><span style="font-size:26px;line-height:1">+</span><span>' . htmlspecialchars($label) . '</span></div>' : '';
};

if ($program !== null) {
    $c        = $program['content'];
    $defaults = programPageContentDefaults();
    // Empty heading/label -> the standard wording.
    $text = function (string $key) use ($c, $defaults): string {
        return trim($c[$key] ?? '') !== '' ? trim($c[$key]) : ($defaults[$key] ?? '');
    };
    $items = $program['items'];

    // Facilities: only entries that have a picture. No pictures = no section at all.
    $facilities = array_values(array_filter($items['facility'] ?? [], function ($f) {
        return !empty($f['image_url']);
    }));

    // Top-banner slideshow (pictures and videos). No slides = the single banner picture below, as before.
    $banner = array_values(array_filter($items['hero_slide'] ?? [], function ($b) {
        return !empty($b['image_url']);
    }));

    // "Bachelor of Science in Nursing" -> "Nursing" for the big title.
    $fullName   = (string)$program['program_name'];
    $shortName  = preg_replace('/^Bachelor of (?:Science|Arts)?\s*(?:in\s+)?/i', '', $fullName);
    $shortName  = $shortName !== '' ? $shortName : $fullName;
    $heroDesc   = trim($c['hero_desc']);
    $deptName   = (string)($program['dept_name'] ?? '');
    $isGraduateProgram = ($program['dept_slug'] ?? '') === 'graduate-school';
    $programDepartment = !empty($program['dept_id']) ? getDepartmentById((int)$program['dept_id']) : null;
    // Programs without a custom seal inherit their department's current logo.
    $sealUrl = $program['seal_url'] ?: ($programDepartment['logo_url'] ?? null);
    $sealAlt = $program['seal_url'] ? $fullName . ' seal' : $deptName . ' logo';
    $isBasicProgram = ($programDepartment['department_type'] ?? '') === 'basic-education';
    $departmentIndexTitle = $isBasicProgram ? 'Basic Education Departments' : ($isGraduateProgram ? 'Graduate School' : 'College Departments');
    $departmentIndexUrl = $isBasicProgram ? '/pages/academics/basic-edu/basic-edu-dept.php' : ($isGraduateProgram ? '/pages/academics/graduate-school/graduate-school.php' : '/pages/academics/college/college-dept.php');
    $deptUrl    = !empty($program['dept_slug'])
        ? '/pages/academics/college/college-department.php?slug=' . rawurlencode($program['dept_slug'])
        : '/pages/academics/college/college-dept.php';
    if ($isBasicProgram && in_array($program['dept_slug'] ?? '', ['casa-gradeschool', 'hs-monte', 'jhs-regular', 'shs-regular'], true)) {
        $deptUrl = '/pages/academics/basic-edu/basic-edu-department/' . rawurlencode($program['dept_slug']) . '.php';
    }

    // The spinning text around the seal follows the program's department.
    $sealRingText = programSealRingText($deptName);

    $showVision  = trim($c['vision_text'])  !== '' || $cePreview;
    $showMission = trim($c['mission_text']) !== '' || $cePreview;
    $showAbout   = $sealUrl !== null || trim($c['about_text']) !== '' || $showVision || $showMission;
    $showCore    = $program['core_enabled'] && (!empty($items['core_value']) || $cePreview);

    $ctaLink = trim($c['cta_link']) !== '' ? trim($c['cta_link']) : $defaults['cta_link'];
    if (!preg_match('#^(/|https?://)#i', $ctaLink)) $ctaLink = $defaults['cta_link'];

    $pageTitle = $fullName;
    $themeStyle = programPageThemeStyle($program['theme_accent']);
} else {
    $pageTitle = 'Program not found';
    $themeStyle = programPageThemeStyle(null);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $e($pageTitle) ?></title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,100..700,0,0&display=block">
    <link href="/assets/css/program-page-style.css" rel="stylesheet">
    <script src="/assets/js/facility-coverflow.js?v=<?= filemtime(__DIR__.'/../../../assets/js/facility-coverflow.js') ?>"></script>
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
    <style>:root{<?= $themeStyle ?>}</style>
    <?php if (!empty($banner)): ?>
    <style>
        /* Top banner slideshow (program.php: Banner slides in the Content Editor) */
        .template-hero.has-banner { overflow: hidden; }
        .hero-slides { position: absolute; inset: 0; z-index: 0; overflow: hidden; pointer-events: none; }
        .hero-slide { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; background-size: cover; background-position: center; background-repeat: no-repeat; opacity: 0; transition: opacity .9s ease; }
        .hero-slide.is-active { opacity: 1; }
        .hero-dots { position: absolute; left: 50%; bottom: 18px; transform: translateX(-50%); display: flex; gap: .55rem; z-index: 3; pointer-events: auto; }
        .hero-dot { width: 10px; height: 10px; padding: 0; border-radius: 50%; border: 1.5px solid var(--gnc-template, #307056); background: rgba(255, 255, 255, .85); cursor: pointer; transition: background-color .25s ease, width .3s ease; }
        .hero-dot.is-active { width: 26px; border-radius: 999px; background: var(--gnc-gold, #EABA3B); border-color: var(--gnc-gold, #EABA3B); }
        .hero-dot:focus-visible { outline: 3px solid var(--gnc-gold, #EABA3B); outline-offset: 3px; }
        @media (prefers-reduced-motion: reduce) { .hero-slide, .hero-dot { transition: none; } }
    </style>
    <?php endif; ?>
    <?php if ($cePreview): ?>
    <style>
        /* Content Editor preview only: slide controls on the Facilities carousel */
        .career-rail > .ve-add-tile:only-child { margin-inline: auto; }
        .ve-hero-hint { position: absolute; left: 50%; bottom: 14px; transform: translateX(-50%); z-index: 3; display: inline-flex; align-items: center; gap: .5rem; padding: .55rem 1rem; border: 1.5px dashed var(--gnc-template, #307056); border-radius: 999px; background: rgba(255, 255, 255, .92); color: var(--gnc-template-text, #307056); font: 500 .85rem/1.2 'Inter', sans-serif; white-space: nowrap; }
        @media (max-width: 767px) { .ve-hero-hint { white-space: normal; text-align: center; width: calc(100% - 2rem); border-radius: 14px; } }
        .ve-fac-bar { display: flex; justify-content: center; align-items: center; gap: .6rem; flex-wrap: wrap; margin: .35rem 0 0; }
        .ve-fac-btn {
            display: inline-flex; align-items: center; gap: .45rem;
            border: 1.5px dashed var(--gnc-template, #307056); border-radius: 999px;
            background: #fff; color: var(--gnc-template-text, #307056);
            font: 600 .88rem/1 'Inter', sans-serif; padding: .6rem 1.1rem; cursor: pointer;
            transition: background-color .2s ease, color .2s ease, border-color .2s ease;
        }
        .ve-fac-btn:hover, .ve-fac-btn:focus-visible { background: var(--gnc-template, #307056); color: #fff; border-style: solid; outline: none; }
        .ve-fac-btn--solid { border-style: solid; background: var(--gnc-gold, #EABA3B); border-color: var(--gnc-gold, #EABA3B); color: #14201a; }
        .ve-fac-btn--solid:hover, .ve-fac-btn--solid:focus-visible { background: var(--gnc-template, #307056); border-color: var(--gnc-template, #307056); color: #fff; }
        .ve-fac-count { font-size: .8rem; color: #5f6b65; }
        .ve-fac-empty {
            display: flex; flex-direction: column; align-items: center; gap: .55rem; text-align: center;
            margin-top: 1.5rem; padding: 2.5rem 1.25rem;
            border: 2px dashed color-mix(in srgb, var(--gnc-template, #307056) 45%, #fff); border-radius: 16px;
            background: color-mix(in srgb, var(--gnc-template, #307056) 5%, #fff); color: #14201a;
        }
        .ve-fac-empty i { font-size: 2rem; color: var(--gnc-template-text, #307056); }
        .ve-fac-empty span { color: #5f6b65; max-width: 34rem; font-size: .92rem; line-height: 1.55; }
    </style>
    <?php endif; ?>
    <script>document.documentElement.classList.add('js-anim');</script>
</head>
<body>
    <?php include_once __DIR__ . '/../../../components/navbar.php'; ?>

<?php if ($program === null): ?>

    <section class="template-about py-5" style="min-height:60vh">
        <div class="container text-center py-5">
            <h1 class="template-section-title" style="display:inline-block">Program not found</h1>
            <p class="template-section-text mt-3">We couldn't find that program. It may have been moved or is not available right now.</p>
            <a href="<?= $e($departmentIndexUrl ?? '/pages/academics/college/college-dept.php') ?>" class="template-cta-btn mt-2">Browse <?= $e($departmentIndexTitle ?? 'College Departments') ?></a>
        </div>
    </section>

<?php else: ?>

    <?php /* The single fixed banner picture is gone on purpose: the top banner is only what is added under "Banner slides" in the Content Editor. */ ?>
    <section class="hero-page template-hero<?= !empty($banner) ? ' has-banner' : '' ?>" style="background-image: none !important;"<?= empty($program['published']) ? ceManageMark('data-ve-status="draft"') : '' ?>>
        <?php if (!empty($banner)): ?>
        <div class="hero-slides" id="heroSlides" aria-roledescription="carousel" aria-label="<?= $e($fullName) ?> banner">
            <?php foreach ($banner as $bi => $b):
                $bTitle = trim((string)($b['title'] ?? '')) !== '' ? trim((string)$b['title']) : $fullName;
            ?>
                <?php if (($b['media_type'] ?? 'image') === 'video'): ?>
                <video class="hero-slide<?= $bi === 0 ? ' is-active' : '' ?>" src="<?= $e($b['image_url']) ?>" muted playsinline<?= count($banner) === 1 ? ' loop' : '' ?> preload="<?= $bi === 0 ? 'auto' : 'metadata' ?>" aria-label="<?= $e($bTitle) ?>"></video>
                <?php else: ?>
                <div class="hero-slide<?= $bi === 0 ? ' is-active' : '' ?>" style="background-image:url('<?= $e($b['image_url']) ?>')" role="img" aria-label="<?= $e($bTitle) ?>"></div>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (count($banner) > 1): ?>
            <div class="hero-dots" role="tablist" aria-label="Choose banner slide">
                <?php foreach ($banner as $bi => $b): ?>
                <button type="button" class="hero-dot<?= $bi === 0 ? ' is-active' : '' ?>" role="tab" data-index="<?= (int)$bi ?>" aria-label="Show banner slide <?= (int)$bi + 1 ?>"></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($cePreview && empty($banner)): ?>
        <div class="ve-hero-hint"><i class="bi bi-collection-play"></i> No banner slides yet. Use <strong>Add slides</strong> in the strip below to add photos or videos.</div>
        <?php endif; ?>
        <div class="container h-100">
        <div class="row align-items-center g-0">
            <div class="col-lg-6">
                <nav class="template-breadcrumb" aria-label="breadcrumb">
                    <span>ACADEMICS</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="<?= $e($departmentIndexUrl) ?>"><?= $e(mb_strtoupper($departmentIndexTitle)) ?></a>
                    <?php if ($deptName !== ''): ?>
                    <i class="bi bi-chevron-right"></i>
                    <a href="<?= $e($deptUrl) ?>"><?= $e(mb_strtoupper($deptName)) ?></a>
                    <?php endif; ?>
                    <i class="bi bi-chevron-right"></i>
                    <span><?= $e(mb_strtoupper($fullName)) ?></span>
                </nav>

                <div class="template-hero-content">
                    <h1 class="template-title"<?= ceManageMark('data-ve-pick="prog-name" data-ve-field="prog-name" data-ve-key="short-name" data-ve-label="Program heading" data-ve-max="255" style="text-transform:uppercase"') ?>><?= $e($cePreview ? $shortName : mb_strtoupper($shortName)) ?></h1>
                    <?php if (trim($c['subtitle']) !== '' || $cePreview): ?>
                    <p class="template-subtitle"<?= $pf('subtitle', 'Subtitle') ?>><?= $e($c['subtitle']) ?></p>
                    <?php endif; ?>
                    <?php if ($heroDesc !== '' || $cePreview): ?>
                    <div class="template-desc cms-rich"<?= $pf('hero_desc', 'Introduction', true, 'Write a short introduction') ?>><?= $pt($heroDesc) ?></div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($items['badge']) || $cePreview): ?>
                <div class="template-badges">
                    <?php foreach ($items['badge'] as $bi => $badge): ?>
                    <div class="template-badge"<?= $pcard('badge', $bi) ?>>
                        <?= programPageIcon($badge['icon'] ?? '', 'template-badge-icon') ?>
                        <div>
                            <strong<?= $pi('badge', $bi, 'title', 'Badge title') ?>><?= $e($badge['title']) ?></strong>
                            <span<?= $pi('badge', $bi, 'subtitle', 'Badge subtitle') ?>><?= $e($badge['subtitle'] ?? '') ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?= $padd('badge', 'Add a badge') ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        </div><!-- /.container -->
    </section>

    <?php if ($showAbout || $showCore): ?>
    <section class="template-about py-5">
        <div class="container">

            <?php if ($showAbout): ?>
            <?php if ($sealUrl !== null || trim($c['about_text']) !== '' || $cePreview): ?>
            <div class="about-feature" id="about-program">
                <?php if ($sealUrl): ?>
                <div class="about-feature-seal">
                    <div class="about-feature-seal-wrap">
                        <img src="<?= $e($sealUrl) ?>" alt="<?= $e($sealAlt) ?>" loading="lazy">
                        <span class="about-seal-ring" aria-hidden="true">
                            <svg viewBox="0 0 500 500" focusable="false">
                                <defs><path id="sealRingPath" d="M250,250 m-212,0 a212,212 0 1,1 424,0 a212,212 0 1,1 -424,0"/></defs>
                                <text><textPath href="#sealRingPath" textLength="1330" lengthAdjust="spacing"><?= $e($sealRingText) ?></textPath></text>
                            </svg>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                <div class="about-feature-text">
                    <span class="about-eyebrow"<?= $pf('about_title', 'Small heading above the name') ?><?= $cePreview ? ' style="text-transform:uppercase"' : '' ?>><?= $e($cePreview ? $text('about_title') : mb_strtoupper($text('about_title'))) ?></span>
                    <h2 class="about-heading"<?= ceManageMark('data-ve-pick="prog-name" data-ve-field="prog-name" data-ve-key="full-name" data-ve-label="Full program name" data-ve-max="255"') ?>><?= $e($fullName) ?></h2>
                    <?php if ($cePreview): ?>
                    <small class="text-muted">Department card description</small>
                    <p class="text-muted" data-ve-field="prog-description" data-ve-key="description" data-ve-label="Department card description" data-ve-max="5000" data-ve-multiline data-ve-ph="Write a short description for department cards"><?= $e((string)($program['description'] ?? '')) ?></p>
                    <?php endif; ?>
                    <?php if (trim($c['about_text']) !== '' || $cePreview): ?>
                    <div class="about-body cms-rich"<?= $pf('about_text', 'About this program', true, 'Write about this program') ?>><?= $pt($c['about_text']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($showVision || $showMission): ?>
            <span id="vision-mission"></span>
            <div class="template-vm-stack mt-5">
                <?php if ($showVision): ?>
                <div class="template-vm-card">
                    <div class="template-vm-head">
                        <div class="template-vm-icon"<?= ceManageMark('data-ve-icon="program-statement" data-ve-key="vision_icon" data-ve-value="' . $e($text('vision_icon')) . '" role="button" tabindex="0" aria-label="Change vision icon"') ?>><?= programPageIcon($text('vision_icon'), 'template-vm-icon-glyph') ?></div>
                        <h5 class="template-vm-card-title"<?= $pf('vision_title', 'Vision heading') ?>><?= $e($text('vision_title')) ?></h5>
                    </div>
                    <div class="template-vm-card-text cms-rich"<?= $pf('vision_text', 'Vision', true, 'Write the vision') ?>><?= $pt($c['vision_text']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($showMission): ?>
                <div class="template-vm-card">
                    <div class="template-vm-head">
                        <div class="template-vm-icon"<?= ceManageMark('data-ve-icon="program-statement" data-ve-key="mission_icon" data-ve-value="' . $e($text('mission_icon')) . '" role="button" tabindex="0" aria-label="Change mission icon"') ?>><?= programPageIcon($text('mission_icon'), 'template-vm-icon-glyph') ?></div>
                        <h5 class="template-vm-card-title"<?= $pf('mission_title', 'Mission heading') ?>><?= $e($text('mission_title')) ?></h5>
                    </div>
                    <div class="template-vm-card-text cms-rich"<?= $pf('mission_text', 'Mission', true, 'Write the mission') ?>><?= $pt($c['mission_text']) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($showCore): ?>
            <div class="row g-4">
                <div class="col-12">
                    <h3 class="template-section-title template-section-title--gold mt-4" id="core-values"<?= $pf('core_title', 'Core values heading') ?>><?= $e($text('core_title')) ?></h3>
                    <?php if (trim($c['core_intro']) !== '' || $cePreview): ?>
                    <div class="corevalue-subtitle cms-rich"<?= $pf('core_intro', 'Core values introduction') ?>><?= $pt($c['core_intro']) ?></div>
                    <?php endif; ?>

                    <div class="corevalue-stack">
                        <?php foreach ($items['core_value'] as $cvi => $value):
                            $cvTitle  = (string)$value['title'];
                            $cvLetter = trim((string)($value['letter'] ?? '')) !== '' ? (string)$value['letter'] : mb_substr($cvTitle, 0, 1);
                        ?>
                        <div class="corevalue-card"<?= $pcard('core_value', $cvi) ?>>
                            <div class="corevalue-icon"><?= programPageIcon($value['icon'] ?? '', 'corevalue-icon-glyph') ?></div>
                            <div class="corevalue-titlegroup">
                                <span class="corevalue-letter"><?= $e($cvLetter) ?></span>
                                <h5 class="corevalue-title"<?= $pi('core_value', $cvi, 'title', 'Core value') ?>><?= $e($cvTitle) ?></h5>
                            </div>
                            <div class="corevalue-text cms-rich"<?= $pi('core_value', $cvi, 'body', 'Description', true) ?>><?= renderSectionContent($value['body'] ?? '') ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?= $padd('core_value', 'Add a core value') ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($items['why']) || $cePreview): ?>
    <section class="template-why" id="why-section">
        <div class="container">
            <div class="why-layout">
                <div class="why-side">
                    <div class="why-side-inner">
                        <h2 class="why-heading" id="why-choose"><span<?= $pf('why_title', 'Heading') ?>><?= $e($text('why_title')) ?></span><span class="why-dot">.</span></h2>
                        <?php if (trim($c['why_intro']) !== '' || $cePreview): ?>
                        <div class="why-intro cms-rich"<?= $pf('why_intro', 'Introduction', true) ?>><?= $pt($c['why_intro']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <ol class="why-list">
                    <?php foreach ($items['why'] as $i => $card): ?>
                    <li class="why-item"<?= $pcard('why', $i) ?>>
                        <span class="why-num" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span>
                        <div class="why-body">
                            <h3 class="why-title"<?= $pi('why', $i, 'title', 'Title') ?>><?= $e($card['title']) ?></h3>
                            <div class="why-desc cms-rich"<?= $pi('why', $i, 'body', 'Description', true) ?>><?= renderSectionContent($card['body'] ?? '') ?></div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                    <?php if ($cePreview): ?><li style="list-style:none"><?= $padd('why', 'Add a reason') ?></li><?php endif; ?>
                </ol>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php /* Program Curriculum: only when the admin has uploaded a PDF for THIS program
             (pdf_url is empty when no PDF is attached or the file is missing). */ ?>
    <?php if (!empty($program['pdf_url']) || $cePreview): ?>
    <section class="template-curriculum py-5" id="curriculum"<?= ceManageMark('data-warning-field data-warning-label="Curriculum PDF" data-warning-value="' . $e($program['pdf_url'] ?? '') . '"') ?>>
        <div class="container">
            <h2 class="template-section-title template-section-title--gold"<?= $pf('curriculum_title', 'Curriculum heading') ?>><?= $e($text('curriculum_title')) ?></h2>
            <?php if (!empty($program['pdf_url'])): ?>
            <?php renderPdfViewer($program['pdf_url'], ['title' => $text('curriculum_title'), 'download_name' => $program['pdf_name']]); ?>
            <?php endif; ?>
            <?php if ($cePreview): ?>
            <div class="text-center mt-3">
                <button type="button" class="ve-fac-btn" data-ve-program-section="curriculum"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> <?= !empty($program['pdf_url']) ? 'Manage curriculum PDF' : 'Upload curriculum PDF' ?></button>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php /* Facilities: shown only when the admin uploaded at least one facility picture for THIS program. */ ?>
    <?php if (!empty($facilities) || $cePreview): ?>
    <section class="template-facilities py-5" id="facilities">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold"<?= $pf('facility_title', 'Facilities heading') ?>><?= $e($text('facility_title')) ?></h2>

            <?php if ($cePreview && !empty($facilities)): ?>
            <div class="ve-fac-bar">
                <button type="button" class="ve-fac-btn ve-fac-btn--solid" data-ve-fac="add"><i class="bi bi-plus-lg"></i> Add slides</button>
                <span class="ve-fac-count"><?= count($facilities) ?> slide<?= count($facilities) === 1 ? '' : 's' ?></span>
            </div>
            <?php endif; ?>

            <?php if (empty($facilities)): ?>
            <div class="ve-fac-empty">
                <i class="bi bi-images"></i>
                <strong>No facility slides yet</strong>
                <span>Add pictures to show the Facilities slideshow on this page. Visitors won't see this section until there is at least one.</span>
                <button type="button" class="ve-fac-btn ve-fac-btn--solid" data-ve-fac="add"><i class="bi bi-plus-lg"></i> Add the first slide</button>
            </div>
            <?php else: ?>
            <div class="fcar" id="facilityCarousel" aria-roledescription="carousel" aria-label="Facilities">
                <div class="fcar-stage" id="fcarStage" tabindex="0">
                    <?php foreach ($facilities as $i => $fac):
                        $facName = trim((string)$fac['title']);
                    ?>
                    <button type="button" class="fcar-slide" data-index="<?= (int)$i ?>" aria-label="<?= $e($facName) ?>">
                        <img src="<?= $e(facilityCarouselThumb((string)$fac['image_url'])) ?>" alt="<?= $e($facName) ?>" loading="<?= $i < 3 ? 'eager' : 'lazy' ?>" decoding="async" draggable="false">
                        <span class="fcar-caption"><?= $e($facName) ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>

                <?php if (count($facilities) > 1): ?>
                <button type="button" class="fcar-arrow fcar-prev" id="fcarPrev" aria-label="Previous facility"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="fcar-arrow fcar-next" id="fcarNext" aria-label="Next facility"><i class="bi bi-chevron-right"></i></button>
                <div class="fcar-dots" id="fcarDots" role="tablist" aria-label="Choose facility">
                    <?php foreach ($facilities as $i => $fac): ?>
                    <button type="button" class="fcar-dot" role="tab" data-index="<?= (int)$i ?>" aria-label="Show <?= $e(trim((string)$fac['title'])) ?>"></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; /* empty($facilities) ? placeholder : carousel */ ?>
        </div>
    </section>
    <?php endif; /* section shown */ ?>

    <?php if (!empty($facilities)): ?>
    <!-- Facility Lightbox -->
    <div class="facility-lightbox" id="facilityLightbox" role="dialog" aria-modal="true" aria-label="Facility photo" hidden>
        <button type="button" class="facility-lb-close" id="facilityLbClose" aria-label="Close">&times;</button>
        <button type="button" class="facility-lb-nav facility-lb-prev" id="facilityLbPrev" aria-label="Previous photo"><i class="bi bi-chevron-left"></i></button>
        <figure class="facility-lb-figure">
            <img id="facilityLbImg" src="" alt="">
            <figcaption>
                <strong id="facilityLbName"></strong>
                <span id="facilityLbDesc"></span>
            </figcaption>
        </figure>
        <button type="button" class="facility-lb-nav facility-lb-next" id="facilityLbNext" aria-label="Next photo"><i class="bi bi-chevron-right"></i></button>
    </div>
    <?php endif; ?>

    <?php $uniforms = array_values(array_filter($items['uniform'], function ($u) { return !empty($u['image_url']); })); ?>
    <?php if (!empty($uniforms) || $cePreview): ?>
    <section class="template-uniform py-5" id="template-uniform"<?= ceManageMark('data-warning-field data-warning-label="Uniform photos" data-warning-value="' . ($uniforms ? 'uploaded' : '') . '"') ?>>
        <div class="container">
            <h2 class="template-section-title template-section-title--gold"<?= $pf('uniform_title', 'Uniform heading') ?>><?= $e($text('uniform_title')) ?></h2>
            <?php if ($cePreview): ?>
            <div class="text-center my-3">
                <button type="button" class="ve-fac-btn" data-ve-program-section="uniform"><i class="bi bi-person-badge" aria-hidden="true"></i> <?= $uniforms ? 'Manage uniform photos' : 'Add uniform photos' ?></button>
            </div>
            <?php endif; ?>
            <?php if (trim($c['uniform_text']) !== '' || $cePreview): ?>
            <div class="cms-rich text-center"<?= $pf('uniform_text', 'Uniform text', true) ?>><?= $pt($c['uniform_text']) ?></div>
            <?php endif; ?>

            <div class="row g-4 justify-content-center mt-2">
                <?php foreach ($uniforms as $u): ?>
                <div class="col-lg-5 col-md-6">
                    <div class="template-uniform-card">
                        <img src="<?= $e($u['image_url']) ?>" alt="<?= $e($u['title'] !== '' ? $u['title'] : 'Uniform') ?>" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($items['career']) || $cePreview): ?>
    <section class="template-career py-5">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold" id="career-opportunities"<?= $pf('career_title', 'Careers heading') ?>><?= $e($text('career_title')) ?></h2>

            <div class="career-rail" id="careerRail">
                <?php foreach ($items['career'] as $ci => $career):
                    $cImg = !empty($career['image_url']) ? ' style="background-image:url(\'' . $e($career['image_url']) . '\')"' : '';
                ?>
                <article class="career-panel<?= $ci === 0 ? ' is-open' : '' ?><?= $cImg === '' ? ' career-panel--plain' : '' ?>" tabindex="0"<?= $pcard('career', $ci) ?>>
                    <div class="career-panel-bg"<?= $cImg ?>></div>
                    <span class="career-index"><?= sprintf('%02d', $ci + 1) ?></span>
                    <span class="career-vlabel"><?= $e($career['title']) ?></span>
                    <div class="career-content">
                        <h3 class="career-title"<?= $pi('career', $ci, 'title', 'Career title') ?>><?= $e($career['title']) ?></h3>
                        <div class="career-desc cms-rich"<?= $pi('career', $ci, 'body', 'Description', true) ?>><?= renderSectionContent($career['body'] ?? '') ?></div>
                    </div>
                </article>
                <?php endforeach; ?>
                <?php if ($cePreview): ?>
                <div class="ve-add-tile" data-ve-add="item" data-ve-type="career" role="button" tabindex="0" aria-label="Add career opportunity">
                    <span style="font-size:26px;line-height:1">+</span><span>Add career opportunity</span>
                </div>
                <?php endif; ?>
            </div>
            <script>
                (function () {
                    var rail = document.getElementById('careerRail');
                    if (!rail) return;
                    var panels = rail.querySelectorAll('.career-panel');
                    function open(p) {
                        panels.forEach(function (x) { x.classList.toggle('is-open', x === p); });
                    }
                    panels.forEach(function (p) {
                        p.addEventListener('mouseenter', function () { open(p); });
                        p.addEventListener('focus', function () { open(p); });
                        p.addEventListener('click', function () { open(p); });
                    });
                })();
            </script>
        </div>
    </section>
    <?php endif; ?>

    <?php render_bulletin_board(); /* automatic: not editable */ ?>

    <section class="template-cta py-4">
        <div class="container">
            <div class="template-cta-card">
                <div class="template-cta-icon"><?= programPageIcon($text('cta_icon'), 'template-cta-glyph') ?></div>
                <div class="template-cta-text">
                    <h5 class="template-cta-title"<?= $pf('cta_title', 'Heading') ?>><?= $e($text('cta_title')) ?></h5>
                    <div class="template-cta-desc cms-rich"<?= $pf('cta_text', 'Text') ?>><?= $pt($text('cta_text')) ?></div>
                </div>
                <div class="template-cta-action">
                    <a href="<?= $e($ctaLink) ?>" class="template-cta-btn"<?= $pf('cta_button', 'Button text') ?>><?= $e($text('cta_button')) ?></a>
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($uniforms)): ?>
    <!-- Uniform Lightbox -->
    <div class="uniform-lightbox" id="uniformLightbox" onclick="closeUniformLightbox()">
        <span class="uniform-lightbox-close" onclick="closeUniformLightbox()">&times;</span>
        <img class="uniform-lightbox-img" id="uniformLightboxImg" src="" alt="">
        <p class="uniform-lightbox-caption" id="uniformLightboxCaption"></p>
    </div>
    <?php endif; ?>

<?php endif; ?>

    <?php include_once __DIR__ . '/../../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>

    <script>
        /* Scroll-reveal animations (styles: "ANIMATIONS" block at the end of program-page-style.css) */
        (function () {
            var root = document.documentElement;
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduce || !('IntersectionObserver' in window)) { root.classList.remove('js-anim'); return; }

            /* [selector, direction, stagger the siblings?] */
            var groups = [
                ['.template-about .template-section-title', 'up', false],
                ['.corevalue-subtitle', 'up', false],
                ['.about-feature-seal', 'zoom', false],
                ['.about-feature-text', 'right', false],
                ['.template-vm-card', 'up', true],
                ['.corevalue-card', 'left', true],
                ['.template-curriculum .template-section-title', 'up', false],
                ['.template-curriculum .gnc-pdf-viewer', 'up', false],
                ['.template-facilities .template-section-title', 'up', false],
                ['.fcar', 'up', false],
                ['.template-uniform .template-section-title', 'up', false],
                ['.template-uniform-card', 'zoom', true],
                ['.template-career .template-section-title', 'up', false],
                ['.career-panel', 'up', true],
                ['.bulletin-heading', 'up', false],
                ['.bulletin-subtext', 'up', false],
                ['.bulletin-panel', 'up', true],
                ['.template-cta-card', 'up', false]
            ];

            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (!en.isIntersecting) return;
                    var el = en.target;
                    io.unobserve(el);
                    el.classList.add('in-view');
                    /* hand control back to the normal hover transitions once the reveal is done */
                    var wait = 900 + (parseFloat(el.style.getPropertyValue('--anim-delay')) || 0) * 1000;
                    setTimeout(function () {
                        el.removeAttribute('data-anim');
                        el.style.removeProperty('--anim-delay');
                    }, wait);
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });

            groups.forEach(function (g) {
                var seen = new Map();
                document.querySelectorAll(g[0]).forEach(function (el) {
                    var n = seen.get(el.parentNode) || 0;
                    seen.set(el.parentNode, n + 1);
                    if (g[2]) el.style.setProperty('--anim-delay', (Math.min(n, 5) * 0.09).toFixed(2) + 's');
                    el.setAttribute('data-anim', g[1]);
                    io.observe(el);
                });
            });
        })();
    </script>
    <script>
        function openUniformLightbox(src, caption) {
            var lightbox = document.getElementById('uniformLightbox');
            if (!lightbox) return;
            document.getElementById('uniformLightboxImg').src = src;
            document.getElementById('uniformLightboxCaption').textContent = caption;
            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeUniformLightbox() {
            var lightbox = document.getElementById('uniformLightbox');
            if (!lightbox) return;
            lightbox.classList.remove('active');
            document.body.style.overflow = '';
        }
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') closeUniformLightbox();
        });
    </script>
    <?php if (!empty($banner)): ?>
    <script>
        /* Top banner slideshow: pictures fade, videos play to the end, then the next slide shows */
        (function () {
            var box = document.getElementById('heroSlides');
            if (!box) return;
            var slides = Array.prototype.slice.call(box.querySelectorAll('.hero-slide'));
            var dots = Array.prototype.slice.call(box.querySelectorAll('.hero-dot'));
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var at = 0, timer = null;

            function playVideo(v) { try { v.currentTime = 0; } catch (e) {} var p = v.play(); if (p && p.catch) p.catch(function () {}); }
            function schedule() {
                clearTimeout(timer);
                if (slides.length < 2 || reduce || document.hidden) return;
                var cur = slides[at];
                /* a video moves on when it ends (the 'ended' event below); this is only a safety net */
                timer = setTimeout(function () { show(at + 1); }, cur.tagName === 'VIDEO' ? 45000 : 6000);
            }
            function show(i) {
                at = (i + slides.length) % slides.length;
                slides.forEach(function (s, k) {
                    var on = k === at;
                    s.classList.toggle('is-active', on);
                    if (s.tagName === 'VIDEO') { if (on) { if (!reduce) playVideo(s); } else s.pause(); }
                });
                dots.forEach(function (d, k) { d.classList.toggle('is-active', k === at); d.setAttribute('aria-selected', k === at ? 'true' : 'false'); });
                schedule();
            }
            slides.forEach(function (s, k) {
                if (s.tagName === 'VIDEO') s.addEventListener('ended', function () { if (k === at && slides.length > 1) show(at + 1); });
            });
            dots.forEach(function (d) { d.addEventListener('click', function () { show(parseInt(d.getAttribute('data-index'), 10) || 0); }); });
            document.addEventListener('visibilitychange', function () {
                var cur = slides[at];
                if (document.hidden) { clearTimeout(timer); if (cur.tagName === 'VIDEO') cur.pause(); }
                else { if (cur.tagName === 'VIDEO' && !reduce) { var p = cur.play(); if (p && p.catch) p.catch(function () {}); } schedule(); }
            });
            if (slides[0].tagName === 'VIDEO' && !reduce) playVideo(slides[0]);
            schedule();
        })();
    </script>
    <?php endif; ?>
    <?php if ($cePreview && $program !== null): ?>
    <?php /* The Content Editor reads this to edit this program in place. saveProgramPageDetails() replaces the WHOLE record,
             so the editor needs every current value (the picture paths are kept by the server). */
        $veThemeKey = 'custom';
        foreach (programPageThemePresets() as $tk => $tp) { if (strcasecmp($tp['accent'], (string)$program['theme_accent']) === 0) { $veThemeKey = $tk; break; } }
        $veItems = [];
        foreach (['badge', 'core_value', 'why', 'career', 'uniform'] as $vt) {
            $veItems[$vt] = array_map(function ($i) {
                return [
                    'item_id'   => (int)($i['item_id'] ?? 0),
                    'title'     => (string)($i['title'] ?? ''),
                    'subtitle'  => (string)($i['subtitle'] ?? ''),
                    'body'      => (string)($i['body'] ?? ''),
                    'icon'      => (string)($i['icon'] ?? ''),
                    'letter'    => (string)($i['letter'] ?? ''),
                    'image_url' => !empty($i['image_url']) ? (string)$i['image_url'] : null,
                ];
            }, $items[$vt] ?? []);
        }
    ?>
    <script type="application/json" id="veProgData"><?= json_encode([
        'program_id'  => (int)$program['program_id'],
        'slug'        => (string)($program['slug'] ?? ''),
        'name'        => (string)$program['program_name'],
        'hero_upload' => !empty($program['has_hero_upload']),
        'seal_upload' => !empty($program['has_seal_upload']),
        'pdf_url'     => !empty($program['pdf_url']) ? (string)$program['pdf_url'] : null,
        'pdf_name'    => !empty($program['pdf_name']) ? (string)$program['pdf_name'] : null,
        'description' => (string)($program['description'] ?? ''),
        'dept_id'     => (int)($program['dept_id'] ?? 0),
        'theme'       => $veThemeKey,
        'theme_custom'=> (string)$program['theme_accent'],
        'presets'     => array_map(function ($p) { return ['label' => $p['label'], 'accent' => $p['accent']]; }, programPageThemePresets()),
        'core_enabled'=> !empty($program['core_enabled']),
        'published'   => !empty($program['published']),
        'content'     => $c,
        'fields'      => array_map(function ($f) { return ['type' => $f['type'], 'max' => $f['max']]; }, programPageContentFields()),
        'items'       => $veItems,
        'limits'      => programPageItemLimits(),
        'icons'       => array_filter(academicFeatureIconChoices(), static fn($choice) => !isset($choice['path'])),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR) ?></script>
    <?php endif; ?>
    <?php if ($cePreview && $program !== null): ?>
    <?php /* The Content Editor reads this to show the Banner slides in its strip under the page. */ ?>
    <script type="application/json" id="veBannerData"><?= json_encode([
        'program_id' => (int)$program['program_id'],
        'limit'      => function_exists('programPageItemLimits') ? (int)(programPageItemLimits()['hero_slide'] ?? 8) : 8,
        'slides'     => array_map(function ($b) {
            $isVideo = (($b['media_type'] ?? 'image') === 'video');
            return [
                'item_id'    => (int)($b['item_id'] ?? 0),
                'title'      => (string)($b['title'] ?? ''),
                'body'       => (string)($b['body'] ?? ''),
                'media_type' => $isVideo ? 'video' : 'image',
                'image_url'  => !empty($b['image_url']) ? ($isVideo ? $b['image_url'] : facilityCarouselThumb((string)$b['image_url'], 400, 70)) : null,
            ];
        }, $items['hero_slide'] ?? []),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <?php endif; ?>
    <?php if ($cePreview && $program !== null): ?>
    <?php /* The Content Editor reads this to show the Facilities slides in its strip under the page (add, rename, reorder, delete). */ ?>
    <script type="application/json" id="veFacData"><?= json_encode([
        'program_id' => (int)$program['program_id'],
        'slug'       => (string)($program['slug'] ?? $slug),
        'limit'      => function_exists('programPageItemLimits') ? (int)(programPageItemLimits()['facility'] ?? 24) : 24,
        'slides'     => array_map(function ($f) {
            return [
                'item_id'   => (int)($f['item_id'] ?? 0),
                'title'     => (string)($f['title'] ?? ''),
                'body'      => (string)($f['body'] ?? ''),
                'image_url' => !empty($f['image_url']) ? facilityCarouselThumb((string)$f['image_url'], 400, 70) : null,
            ];
        }, $items['facility'] ?? []),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <script>
        /* Content Editor preview: "Add slides" starts the picture chooser in the parent editor. */
        (function () {
            var slug = <?= json_encode((string)($program['slug'] ?? $slug), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            document.addEventListener('click', function (ev) {
                var b = ev.target.closest ? ev.target.closest('[data-ve-fac]') : null;
                if (!b) return;
                ev.preventDefault(); ev.stopPropagation();
                try {
                    window.parent.postMessage({ source: 'gnc-program-preview', action: 'facilities', mode: b.getAttribute('data-ve-fac'), slug: slug }, window.location.origin);
                } catch (err) {}
            }, true);
        })();
    </script>
    <?php endif; ?>
    <?php if (!empty($facilities)): ?>
    <script>
        (function () {
            var data = <?= json_encode(array_map(function ($f) {
                return ['src' => $f['image_url'], 'name' => (string)$f['title'], 'desc' => trim((string)($f['body'] ?? ''))];
            }, $facilities), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            var box = document.getElementById('facilityLightbox');
            if (!box) return;
            var img = document.getElementById('facilityLbImg'), nm = document.getElementById('facilityLbName'), ds = document.getElementById('facilityLbDesc');
            var prev = document.getElementById('facilityLbPrev'), next = document.getElementById('facilityLbNext');
            var cur = 0, lastFocus = null;

            function show(i) {
                cur = (i + data.length) % data.length;
                img.src = data[cur].src; img.alt = data[cur].name;
                nm.textContent = data[cur].name;
                ds.textContent = data[cur].desc; ds.hidden = !data[cur].desc;
                var single = data.length < 2; prev.hidden = single; next.hidden = single;
            }
            function open(i) {
                lastFocus = document.activeElement;
                show(i); box.hidden = false; document.body.style.overflow = 'hidden';
                document.getElementById('facilityLbClose').focus();
            }
            function close() {
                box.hidden = true; document.body.style.overflow = '';
                if (lastFocus && lastFocus.focus) lastFocus.focus();
            }
            /* ---------- Carousel (coverflow) ---------- */
            var stage  = document.getElementById('fcarStage');
            var slides = stage ? Array.prototype.slice.call(stage.querySelectorAll('.fcar-slide')) : [];
            var dots   = Array.prototype.slice.call(document.querySelectorAll('.fcar-dot'));
            var n = slides.length, at = 0, timer = null;

            function layout() { window.GNCFacilityCoverflow.layout(slides, dots, at); }
            function go(i) { at = (i + n) % n; layout(); }
            function auto() {
                if (timer) clearInterval(timer);
                if (n < 2 || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) return;
                timer = setInterval(function () { go(at + 1); }, 5000);
            }
            function pause() { if (timer) { clearInterval(timer); timer = null; } }

            if (n) {
                slides.forEach(function (s, i) {
                    s.addEventListener('click', function () {
                        if (dragMoved) return;
                        if (i === at) open(i); else { go(i); auto(); }
                    });
                });
                dots.forEach(function (dt) {
                    dt.addEventListener('click', function () { go(parseInt(dt.getAttribute('data-index'), 10) || 0); auto(); });
                });
                var pv = document.getElementById('fcarPrev'), nx = document.getElementById('fcarNext');
                if (pv) pv.addEventListener('click', function () { go(at - 1); auto(); });
                if (nx) nx.addEventListener('click', function () { go(at + 1); auto(); });

                /* swipe / drag: fires as soon as the pointer travels far enough (no wait for release) */
                var startX = null, dragMoved = false;
                stage.addEventListener('pointerdown', function (ev) { startX = ev.clientX; dragMoved = false; pause(); });
                stage.addEventListener('pointermove', function (ev) {
                    if (startX === null) return;
                    var dx = ev.clientX - startX;
                    if (Math.abs(dx) > 40) { dragMoved = true; startX = null; go(at + (dx < 0 ? 1 : -1)); }
                });
                stage.addEventListener('pointerup', function () { startX = null; auto(); });
                stage.addEventListener('pointercancel', function () { startX = null; auto(); });

                stage.addEventListener('keydown', function (ev) {
                    if (ev.key === 'ArrowLeft')  { go(at - 1); auto(); ev.preventDefault(); }
                    if (ev.key === 'ArrowRight') { go(at + 1); auto(); ev.preventDefault(); }
                });
                var wrap = document.getElementById('facilityCarousel');
                wrap.addEventListener('mouseenter', pause);
                wrap.addEventListener('mouseleave', auto);
                wrap.addEventListener('focusin', pause);
                wrap.addEventListener('focusout', auto);

                /* decode every photo once, up front and one at a time, so no slide hitches the first time it appears */
                function predecode() {
                    var imgs = slides.map(function (s) { return s.firstElementChild; }), k = 0;
                    (function next() {
                        if (k >= imgs.length) return;
                        var im = imgs[k++];
                        if (im && im.tagName === 'IMG') im.loading = 'eager';
                        var p = (im && im.decode) ? im.decode() : Promise.resolve();
                        p.catch(function () {}).then(function () { setTimeout(next, 60); });
                    })();
                }
                var idle = window.requestIdleCallback || function (f) { return setTimeout(f, 800); };
                if (document.readyState === 'complete') idle(predecode);
                else window.addEventListener('load', function () { idle(predecode); });

                /* autoplay only while the carousel is on screen / tab is visible */
                var onScreen = true;
                if ('IntersectionObserver' in window) {
                    new IntersectionObserver(function (es) {
                        onScreen = es[0].isIntersecting;
                        if (onScreen && !document.hidden) auto(); else pause();
                    }, { threshold: 0.25 }).observe(wrap);
                }
                document.addEventListener('visibilitychange', function () { if (document.hidden || !onScreen) pause(); else auto(); });

                layout();
                auto();
            }

            document.getElementById('facilityLbClose').addEventListener('click', close);
            prev.addEventListener('click', function () { show(cur - 1); });
            next.addEventListener('click', function () { show(cur + 1); });
            box.addEventListener('click', function (ev) { if (ev.target === box) close(); });
            document.addEventListener('keydown', function (ev) {
                if (box.hidden) return;
                if (ev.key === 'Escape') close();
                else if (ev.key === 'ArrowLeft') show(cur - 1);
                else if (ev.key === 'ArrowRight') show(cur + 1);
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>
