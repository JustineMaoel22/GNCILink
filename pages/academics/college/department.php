<?php
/**
 * department.php: one template for every college / department page.
 * Location: /pages/academics/college/department.php
 * Usage:    department.php?slug=cba   (or the clean URL from .htaccess)
 *
 * Replaces the per-department pages (cba.php, coe.php, ...). Those files are
 * left in place; nothing here depends on or modifies them.
 */

require_once __DIR__ . '/../../../components/department-public.php';

/* ---------------------------------------------------------------------------
   DB helper: works whether getDB() returns PDO or mysqli.
   --------------------------------------------------------------------------- */
function deptFetchAll(string $sql, array $params = []): array {
    $db = getDB();
    if ($db instanceof PDO) {
        $st = $db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($db instanceof mysqli) {
        $st = $db->prepare($sql);
        if ($params) {
            $types = '';
            foreach ($params as $p) { $types .= is_int($p) ? 'i' : 's'; }
            $st->bind_param($types, ...$params);
        }
        $st->execute();
        $res = $st->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
    return [];
}

/* ---------------------------------------------------------------------------
   Real 404 (unknown slug, bad slug, or archived department).
   Must run before any output.
   --------------------------------------------------------------------------- */
function deptNotFound(): void {
    http_response_code(404);
    header('X-Robots-Tag: noindex');
    $custom = $_SERVER['DOCUMENT_ROOT'] . '/404.php';
    if (is_file($custom)) {
        include $custom;
    } else {
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Page not found</title></head>'
           . '<body><h1>404 - Page not found</h1><p><a href="/">Return to the homepage</a></p></body></html>';
    }
    exit;
}

/* ---------------------------------------------------------------------------
   Load the department
   --------------------------------------------------------------------------- */
$slug = (string)($_GET['slug'] ?? '');
if (!preg_match('/^[a-z0-9-]{1,50}$/', $slug)) {
    deptNotFound();
}

$dept = publicDepartment($slug);          // joined record: logo_url, pdf_url, dean_photo_url, ...
if (!$dept) {
    deptNotFound();
}

// Pull the text columns straight from the table so this page does not depend on
// which columns getDepartments() happens to select. Existing values win.
$row = deptFetchAll(
    'SELECT dept_id, dept_name, hero_text, about_text, facebook_url, office_label, theme_color, archived_at
       FROM departments WHERE slug = ? LIMIT 1',
    [$slug]
)[0] ?? null;

if (!$row || !empty($row['archived_at'])) {
    deptNotFound();
}
$dept += $row;

$deptId = (int)$dept['dept_id'];
$name   = trim((string)($dept['dept_name'] ?? '')) ?: 'College of Business Administration';

$logoUrl = $dept['logo_url'] ?? null;
$dean    = publicDepartmentDean($dept);

/* ---------------------------------------------------------------------------
   Content with the original cba.php placeholders as fallbacks
   --------------------------------------------------------------------------- */
$loremShort = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
$loremLong  = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.';

$heroText    = trim((string)($dept['hero_text']   ?? '')) ?: $loremShort;
$aboutText   = trim((string)($dept['about_text']  ?? '')) ?: $loremLong;
$officeLabel = trim((string)($dept['office_label'] ?? '')) ?: (strtoupper($slug) . ' Office');

// Facebook: the old page hardcoded GNC.CBA, so keep that only for cba.
// Other departments with no URL simply hide the section.
$facebookUrl = trim((string)($dept['facebook_url'] ?? ''));
if ($facebookUrl === '' && $slug === 'cba') {
    $facebookUrl = 'https://www.facebook.com/GNC.CBA';
}
if ($facebookUrl !== '' && !preg_match('#^https?://#i', $facebookUrl)) {
    $facebookUrl = '';
}

// Theme color: only accept #RRGGBB (column is varchar(7)).
$themeColor = trim((string)($dept['theme_color'] ?? ''));
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor)) {
    $themeColor = '';
}

/* ---------------------------------------------------------------------------
   Programs from the `programs` table by dept_id.
   The table currently has only program_code / slug / theme_color / dept_id /
   pdf_media_id, so name, blurb and image use these fallbacks until real columns
   exist. If you later add program_name / description / image_url columns they
   are picked up automatically (see below).
   --------------------------------------------------------------------------- */
$legacyPrograms = [
    'BSBA-FM' => ['Bachelor of Science in Business Administration major in Financial Management',
                  "The Bachelor of Science in Financial Management is a program that provides quality finance education and develops students' technical, analytical, and professional skills. It prepares..."],
    'BSCS'    => ['Bachelor of Science in Computer Science',
                  'The Bachelor of Science in Computer Science program prepares students to become skilled computing professionals and researchers. It develops strong foundations in...'],
    'BSIT'    => ['Bachelor of Science in Information Technology',
                  'The Bachelor of Science in Information Technology program prepares students to become competent IT professionals with practical knowledge and skills in software development...'],
    'BSHM'    => ['Bachelor of Science in Hospitality Management',
                  'The Bachelor of Science in Hospitality Management program prepares students with the knowledge, skills, and competencies needed to excel in food production...'],
    'BSTM'    => ['Bachelor of Science in Tourism Management',
                  'The Bachelor of Science in Tourism Management program prepares students with the knowledge and practical skills needed for careers in travel and tour operations, tourism...'],
];

$programs = [];
foreach (deptFetchAll('SELECT * FROM programs WHERE dept_id = ? ORDER BY program_id', [$deptId]) as $p) {
    $code   = (string)$p['program_code'];
    $legacy = $legacyPrograms[$code] ?? null;
    $pSlug  = trim((string)($p['slug'] ?? '')) ?: strtolower($code);   // BSBA-FM -> bsba-fm

    $programs[] = [
        'name' => trim((string)($p['program_name'] ?? $p['name'] ?? '')) ?: ($legacy[0] ?? $code),
        'desc' => trim((string)($p['description'] ?? '')) ?: ($legacy[1] ?? ''),
        'img'  => trim((string)($p['image_url'] ?? '')) ?: '/assets/images/test-image.png',
        'url'  => '/pages/academics/college/programs/' . rawurlencode($pSlug) . '.php',
    ];
}

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $h($name) ?></title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/college-dept-style.css" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
<?php if ($themeColor): ?>
    <style>
        /* Department accent color. The dean wave (.wave-gold/.wave-maroon/.wave-green)
           is intentionally NOT touched. */
        .dept-intro, .dept-about, .dept-programs, .dept-dean { --dept-color: <?= $themeColor ?>; }
        .dept-eyebrow,
        .dept-breadcrumb,
        .dept-breadcrumb a,
        .dept-breadcrumb i,
        .dept-breadcrumb .dept-current,
        .dept-program-link { color: var(--dept-color); }
        .dept-program-link:hover { color: var(--gnc-green-light); }
        .dept-logo-square,
        .dept-dean-square,
        .dept-dean-accent,
        .dept-dean-rule { background: var(--dept-color); }
    </style>
<?php endif; ?>
</head>
<body>
    <?php $activeSection = 'academics'; ?>
    <?php include_once __DIR__ . '/../../../components/navbar.php'; ?>

    <section class="dept-intro">
        <div class="dept-hero skeleton-hero" data-bg="/assets/images/Section Header.png">
            <div class="dept-hero-inner">
                <nav class="dept-breadcrumb" aria-label="breadcrumb">
                    <span>Academics</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/college-departments.php">College Departments</a>
                    <i class="bi bi-chevron-right"></i>
                    <span class="dept-current"><?= $h($name) ?></span>
                </nav>
                <h1><?= $h($name) ?></h1>
                <p><?= $h($heroText) ?></p>
            </div>
        </div>
    </section>

    <section class="dept-about">
        <div class="dept-about-inner">

            <div class="dept-logo-wrap">
                <div class="dept-logo-square"></div>
                <?php if ($logoUrl): ?>
                <div class="dept-logo-skeleton skeleton-wrap">
                    <img src="<?= $h($logoUrl) ?>" alt="<?= $h($name) ?> seal" class="dept-logo" loading="lazy">
                </div>
                <?php endif; ?>
                <div class="dept-dots" aria-hidden="true"></div>
            </div>

            <div class="dept-about-content">
                <span class="dept-eyebrow">Overview</span>
                <h2>About the <?= $h($name) ?></h2>
                <p><?= nl2br($h($aboutText)) ?></p>
            </div>

        </div>
    </section>

<?php if ($programs): ?>
    <section class="dept-programs">
        <div class="dept-programs-inner">
            <span class="dept-eyebrow">Our Programs</span>
            <h2>Programs Offered</h2>

            <div class="dept-programs-grid">
<?php foreach ($programs as $prog): ?>

                <div class="dept-program-card">
                    <div class="dept-program-img skeleton-wrap">
                        <img src="<?= $h($prog['img']) ?>" alt="<?= $h($prog['name']) ?>" loading="lazy">
                    </div>
                    <div class="dept-program-body">
                        <h3><?= $h($prog['name']) ?></h3>
<?php if ($prog['desc'] !== ''): ?>
                        <p><?= $h($prog['desc']) ?></p>
<?php endif; ?>
                        <a href="<?= $h($prog['url']) ?>" class="dept-program-link">
                            VIEW DETAILS <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
<?php endforeach; ?>

            </div>
        </div>
    </section>
<?php endif; ?>

    <?php if (!empty($dept['pdf_url'])): ?>
    <!-- Department PDF (uploaded in Content Editor → College Departments) -->
    <section class="dept-programs">
        <div class="dept-programs-inner">
            <span class="dept-eyebrow">Resources</span>
            <h2>Department Document</h2>
            <?php renderDepartmentPdfViewer($dept, ['title' => '']); ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="dept-dean">
        <div class="dept-dean-inner">

            <div class="dept-dean-intro">
                <span class="dept-eyebrow">Leadership &amp; Organization</span>
                <h2>Head of Office</h2>
                <p>Meet the visionary leader guiding the <?= $h($name) ?> in advancing academic excellence, innovation, and service.</p>
            </div>

            <div class="dept-dean-grid">

                <div class="dept-dean-photo-wrap">
                    <div class="dept-dean-square"></div>

                    <div class="dept-dean-photo skeleton-wrap">
                        <img src="<?= $h($dean['photo_url'] ?: '/assets/images/maam-sampang.png') ?>" alt="<?= $h($dean['name'] ?: 'Rowena R. Sampang') ?>, <?= $h($dean['title'] ?: 'Dean') ?> of the <?= $h($name) ?>" loading="lazy">

                        <!-- Layered Wave Pattern: Gold -> Maroon -> Light Green -->
                        <div class="dept-dean-wave" aria-hidden="true">
                            <svg viewBox="0 0 500 150" preserveAspectRatio="none">
                                <path class="wave-gold" d="M0.00,30.98 C150.00,120.00 350.00,-20.00 500.00,30.98 L500.00,150.00 L0.00,150.00 Z"></path>
                                <path class="wave-maroon" d="M0.00,49.98 C149.99,150.00 349.20,-49.98 500.00,49.98 L500.00,150.00 L0.00,150.00 Z"></path>
                                <path class="wave-green" d="M0.00,79.98 C149.99,170.00 349.20,-20.98 500.00,79.98 L500.00,150.00 L0.00,150.00 Z"></path>
                            </svg>
                        </div>

                        <!-- Glassmorphism Nameplate Badge -->
                        <div class="dept-dean-badge">
                            <span class="dept-dean-badge-title"><?= $h($dean['title'] ?: 'Dean') ?></span>
                            <span class="dept-dean-badge-sub"><?= $h($name) ?></span>
                        </div>
                    </div>

                    <div class="dept-dean-accent" aria-hidden="true"></div>
                </div>

                <div class="dept-dean-content">
                    <span class="dept-eyebrow">Meet the <?= $h($dean['title'] ?: 'Dean') ?></span>
                    <h3><?= $h($dean['name'] ?: 'Rowena R. Sampang, LPT, MBA') ?></h3>
                    <div class="dept-dean-rule"></div>
                    <p><?= nl2br($h($dean['bio'] ?: $loremLong)) ?></p>

                    <hr class="dept-dean-divider">

                    <span class="dept-eyebrow">Get Connected</span>

                    <div class="dept-dean-contacts-grid">
                        <div class="dept-dean-contact">
                            <i class="bi bi-telephone"></i>
                            <div>
                                <span class="dept-contact-label">Contact Information</span>
                                <span class="dept-contact-value"><?= $h($dean['phone'] ?: '(045) 900-4473 Loc. 106') ?></span>
                            </div>
                        </div>

                        <div class="dept-dean-contact">
                            <i class="bi bi-geo-alt"></i>
                            <div>
                                <span class="dept-contact-label"><?= $h($officeLabel) ?></span>
                                <span class="dept-contact-value"><?= $h($dean['office'] ?: 'Limlingan Hall, Floor 2, Room 203') ?></span>
                            </div>
                        </div>

                        <div class="dept-dean-contact">
                            <i class="bi bi-envelope"></i>
                            <div>
                                <span class="dept-contact-label">Email Address</span>
                                <span class="dept-contact-value"><?= $h($dean['email'] ?: 'info@gnc.edu.ph') ?></span>
                            </div>
                        </div>

                        <div class="dept-dean-contact">
                            <i class="bi bi-clock"></i>
                            <div>
                                <span class="dept-contact-label">Operating Hours</span>
                                <span class="dept-contact-value"><?= nl2br($h($dean['hours'] ?: "Monday - Friday\n8:00AM - 4:00PM")) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

<?php if ($facebookUrl !== ''): ?>
    <section class="dept-dean dept-facebook" style="padding-top:0;">
        <div class="dept-dean-inner">

            <div class="dept-dean-intro">
                <span class="dept-eyebrow">Stay Updated</span>
                <h2>Follow Us on Facebook</h2>
                <p>Get the latest announcements, events, and news from the <?= $h($name) ?>.</p>
            </div>

            <div style="max-width:500px;">
                <iframe
                    src="https://www.facebook.com/plugins/page.php?href=<?= $h(rawurlencode($facebookUrl)) ?>&amp;tabs=&amp;width=500&amp;height=150&amp;small_header=false&amp;adapt_container_width=true&amp;hide_cover=false&amp;show_facepile=false"
                    title="<?= $h($name) ?> Facebook Page"
                    width="500" height="150"
                    style="border:none; overflow:hidden; width:100%; max-width:500px; border-radius:8px;"
                    scrolling="no" frameborder="0" allowfullscreen="true"
                    allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"
                    loading="lazy"></iframe>
            </div>

        </div>
    </section>
<?php endif; ?>

    <?php include __DIR__ . '/../../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
    <script src="/assets/js/skeleton-loader.js"></script>

</body>
</html>