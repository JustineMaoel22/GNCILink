<?php
/**
 * Shared navbar partial.
 *
 * HOW ACTIVE-SECTION HIGHLIGHTING WORKS:
 * Before including this file, each page should declare which section it
 * belongs to, e.g.:
 *
 *     <?php $activeSection = 'academics'; ?>
 *     <?php include __DIR__ . '/../components/index-nav.php'; ?>
 *
 * Valid values: 'home', 'about', 'academics', 'admissions', 'student-life',
 * 'news-and-announcement', 'contact-us'.
 * If a page doesn't declare $activeSection, we fall back to guessing from
 * the URL path (kept only for pages that haven't been updated yet) — but
 * the explicit variable is what you should rely on, since guessing from
 * folder names breaks the moment a page doesn't live in a matching folder.
 */

$activeSection = $activeSection ?? null;

if ($activeSection === null) {
    // Fallback guess (legacy behavior) — only used if a page forgot to set $activeSection.
    $currentPath = strtok($_SERVER['REQUEST_URI'], '?');
    if ($currentPath === '/' || $currentPath === '/index.php') {
        $activeSection = 'home';
    } elseif (strpos($currentPath, '/about/') !== false) {
        $activeSection = 'about';
    } elseif (strpos($currentPath, '/academics/') !== false || strpos($currentPath, 'college-programs') !== false) {
        $activeSection = 'academics';
    } elseif (strpos($currentPath, '/admissions/') !== false) {
        $activeSection = 'admissions';
    } elseif (strpos($currentPath, '/student-life/') !== false) {
        $activeSection = 'student-life';
    } elseif (strpos($currentPath, 'news-and-announcement') !== false) {
        $activeSection = 'news-and-announcement';
    } elseif (strpos($currentPath, 'contact-us') !== false) {
        $activeSection = 'contact-us';
    }
}

$isHome        = $activeSection === 'home';
$isAbout       = $activeSection === 'about';
$isAcademics   = $activeSection === 'academics';
$isAdmissions  = $activeSection === 'admissions';
$isStudentLife = $activeSection === 'student-life';
$isNewsAndAnnouncement = $activeSection === 'news-and-announcement';
$isContactUs = $activeSection === 'contact-us';

/* Academic categories share one data source between desktop and mobile.
   College departments/programs are attached from the existing database below. */
$academicsMenu = [
    [
        'label' => 'Basic Education Department',
        'href' => '/pages/academics/basic-edu/basic-edu-dept.php',
        'children' => [
            ['label' => 'CASA and Grade School Montessori', 'href' => '/pages/academics/basic-edu/basic-edu-department/casa-gradeschool.php'],
            ['label' => 'High School Montessori', 'href' => '/pages/academics/basic-edu/basic-edu-department/hs-monte.php'],
            ['label' => 'Junior High School Regular', 'href' => '/pages/academics/basic-edu/basic-edu-department/jhs-regular.php'],
            ['label' => 'Senior High School Regular', 'href' => '/pages/academics/basic-edu/basic-edu-department/shs-regular.php'],
        ],
    ],
    [
        'label' => 'College Department',
        'href' => '/pages/academics/college/college-dept.php',
        // The existing database loader attaches the college departments here.
    ],
    [
        'label' => 'Graduate School Department',
        'href' => '/pages/academics/graduate-school/graduate-school.php',
        'children' => [], // Published graduate programs are attached from the database below.
    ],
];

/* =============================================================
   DYNAMIC COLLEGE MENU
   -------------------------------------------------------------
   Academics > College is built from the database:
     departments (not archived, type 'college')  ->  dept pages
     programs    (published, linked by dept_id)  ->  program pages
   Every department and program links to the generic page that reads
   its slug, so anything added or renamed in the Content Editor shows
   up here with a working link.

   There is no hardcoded College list anymore. If the database can't be
   reached or has no college departments, "College" shows as a plain link
   with no submenu (see the gnc-nav HTML comment for the reason).
   ============================================================= */
$gncDeptPage    = '/pages/academics/college/college-department.php';
$gncProgramPage = '/pages/academics/college/program';

// Reason the dynamic menu fell back (printed in the HTML comment below).
$gncFallbackReason = '';
$gncGraduateItems = [];

$gncLoadCollegeMenu = function () use ($gncDeptPage, $gncProgramPage, &$gncFallbackReason, &$gncGraduateItems) {
    // Use the app's DB helper if the page already loaded it; otherwise try to load it.
    if (!function_exists('getDB')) {
        foreach ([
            __DIR__ . '/../config/config.php',
            __DIR__ . '/../config/database.php',
            __DIR__ . '/../config/db.php',
        ] as $f) {
            if (is_file($f)) { require_once $f; break; }
        }
    }
    if (!function_exists('getDB')) {
        $gncFallbackReason = 'getDB() not found (check config path)';
        return null;
    }

    try {
        $db = getDB();
        $depts = $db->query(
            "SELECT dept_id, dept_name, slug FROM departments
             WHERE archived_at IS NULL AND department_type = 'college'
             ORDER BY dept_id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $programsByDept = [];
        try {
            $rows = $db->query(
                "SELECT dept_id, program_code, program_name, slug FROM programs
                 WHERE dept_id IS NOT NULL AND is_published = 1
                 ORDER BY program_id ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $programsByDept[(int) $r['dept_id']][] = $r;
            }
        } catch (Throwable $ex) { /* programs table optional */ }

        $items = [];
        foreach ($depts as $d) {
            $deptSlug = trim((string) ($d['slug'] ?? ''));
            if ($deptSlug === '') continue;

            // Graduate School has its own permanent bottom category in Academics.
            // A legacy or admin-created Graduate School record may be tagged 'college';
            // do not render that record among the dynamic college departments.
            $deptName = trim((string) ($d['dept_name'] ?? ''));
            $normalizedName = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $deptName));
            $normalizedSlug = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $deptSlug));
            $isGraduateSchool = preg_match('/\bgraduate\s+school\b/', $normalizedName) ||
                preg_match('/\bgraduate\s+school\b/', $normalizedSlug);

            $item = [
                'label' => (string) $d['dept_name'],
                'href'  => $gncDeptPage . '?slug=' . rawurlencode($deptSlug),
            ];

            $kids = [];
            foreach ($programsByDept[(int) $d['dept_id']] ?? [] as $p) {
                $code  = trim((string) ($p['program_code'] ?? ''));
                $pslug = trim((string) ($p['slug'] ?? ''));
                if ($pslug === '') $pslug = strtolower($code);
                if ($pslug === '') continue;
                $name = trim((string) ($p['program_name'] ?? ''));
                $kids[] = [
                    'label' => $name !== '' ? $name : $code,
                    'href'  => $gncProgramPage . '/' . rawurlencode($pslug),
                ];
            }
            if ($isGraduateSchool) {
                $gncGraduateItems = array_merge($gncGraduateItems, $kids);
                continue;
            }
            if ($kids) $item['children'] = $kids;
            $items[] = $item;
        }
        if (!$items) {
            $gncFallbackReason = 'no college departments with a slug (check department_type, archived_at, slug)';
            return null;
        }
        return $items;
    } catch (Throwable $ex) {
        $gncFallbackReason = 'query error (see PHP error log)';
        error_log('Navbar college menu error: ' . $ex->getMessage());
        return null;
    }
};

$gncCollegeItems = $gncLoadCollegeMenu();
$academicsMenu[2]['children'] = $gncGraduateItems;

echo '<!-- gnc-nav: college menu from '
    . ($gncCollegeItems
        ? 'database (' . count($gncCollegeItems) . ' departments)'
        : 'NOTHING (no college submenu): ' . $gncFallbackReason)
    . ' -->' . "\n";

if ($gncCollegeItems) {
    foreach ($academicsMenu as &$topItem) {
        if (($topItem['label'] ?? '') === 'College Department') {
            $topItem['children'] = $gncCollegeItems;
        }
    }
    unset($topItem);
}

/* Display groups match the requested menu layout. For newly requested links
   without confirmed standalone routes, reuse an existing page or placeholder. */
$aboutGroups = [
    ['heading' => 'About GNCI', 'items' => [
        ['label' => 'History of GNC', 'href' => '/pages/about/gnc-history.php'],
        ['label' => 'Vision and Mission', 'href' => '/pages/about/vision-mission.php'],
        ['label' => 'Core Values', 'href' => '/pages/about/core-values.php'],
        ['label' => 'Institutional Logo and Meaning', 'href' => '/pages/about/logo-meaning.php'],
    ]],
    ['heading' => 'Administration', 'items' => [
        ['label' => 'Board of Trustees', 'href' => '/pages/about/administration.php?section=board-of-trustees'],
        ['label' => 'Corporate Officers', 'href' => '/pages/about/administration.php?section=corporate-officers'],
        ['label' => 'Board of Administration', 'href' => '/pages/about/administration.php?section=board-of-administration'],
        ['label' => 'Heads of Student Services', 'href' => '/pages/about/administration.php?section=heads-of-student-services'],
    ], 'overview' => '/pages/about/administration.php'],
    ['heading' => 'GNCI Developers Corner', 'items' => [
        ['label' => 'Meet the Developers', 'href' => '/pages/about/gnci-developers-corner-2/the-devs.php'],
    ], 'overview' => '/pages/about/gnci-developers-corner-2/the-devs.php'],
];
$academicsGroups = [
    ['heading' => 'Basic Education Department', 'items' => $academicsMenu[0]['children'], 'overview' => $academicsMenu[0]['href']],
    ['heading' => 'College Department', 'items' => ($academicsMenu[1]['children'] ?? []), 'overview' => $academicsMenu[1]['href']],
    ['heading' => 'Graduate School', 'items' => $academicsMenu[2]['children'], 'overview' => $academicsMenu[2]['href']],
];
$admissionsGroups = [
    ['heading' => 'Admissions', 'items' => [
        ['label' => 'Basic Education Admission Requirements', 'href' => '/pages/admission/basic-education-admission-req.php'],
        ['label' => 'College Admission Requirements', 'href' => '/pages/admission/admission-req.php'],
    ]],
    ['heading' => 'Enrollment', 'items' => [
        ['label' => 'Enrollment Procedures', 'href' => '/pages/admission/enrollment-procedures.php'],
    ]],
    ['heading' => 'Finance', 'items' => [
        ['label' => 'Tuition and Fees', 'href' => '/pages/admission/tuition-fees.php'],
        ['label' => 'Scholarship Opportunities', 'href' => '/pages/admission/scholarship.php'],
        ['label' => 'Online Payment Guide', 'href' => '/pages/admission/online-payment-guide.php'],
    ]],
];
$studentLifeGroups = [
    ['heading' => 'Student Affairs', 'items' => [
        ['label' => 'Student Organizations and Leadership Programs', 'href' => '/pages/student%20life/student%20organization%20and%20leadership%20program/student-org.php'],
    ]],
    ['heading' => 'Campus Life & Activities', 'items' => [
        ['label' => 'Campus Events', 'href' => '/pages/student%20life/campus-event.php'],
        ['label' => 'Community Extension Activities', 'href' => '/pages/student%20life/community-activity.php'],
        ['label' => 'Athletics and Sports', 'href' => '/pages/student%20life/athletics-sports.php'],
    ]],
    ['heading' => 'Student Resources', 'items' => [
        ['label' => 'Access Student Email', 'href' => 'https://login.microsoftonline.com/', 'target' => '_blank'],
        ['label' => 'OSA Student Grievance', 'href' => 'https://osa.gncipampanga.com/', 'target' => '_blank'],
        ['label' => 'GNCI Library', 'href' => 'http://library.gncipampanga.com/', 'target' => '_blank'],
        ['label' => 'Frequently Asked Questions', 'href' => '/pages/faq.php'],
    ]],
    ['heading' => 'Our Community', 'items' => [
        ['label' => 'Student Achievements', 'href' => '/pages/student%20life/student-achievement.php'],
        ['label' => 'Alumni', 'href' => '/coming-soon.php'],
    ]],
];

$e = function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

/* Desktop renderer (recursive): link + separate chevron button for parents */
$renderDesktopMenu = function (array $items) use (&$renderDesktopMenu, $e) {
    foreach ($items as $item) {
        $attrs = '';
        if (!empty($item['target'])) {
            $attrs .= ' target="' . $e($item['target']) . '"';
            if ($item['target'] === '_blank') {
                $attrs .= ' rel="noopener noreferrer"';
            }
        }
        if (empty($item['children'])) {
            echo '<li><a class="dropdown-item" href="' . $e($item['href']) . '"' . $attrs . '>' . $e($item['label']) . '</a></li>' . "\n";
            continue;
        }
        echo '<li class="gnc-dropdown-submenu">' . "\n";
        echo '  <div class="gnc-submenu-row">' . "\n";
        echo '    <a class="dropdown-item" href="' . $e($item['href']) . '"' . $attrs . '>' . $e($item['label']) . '</a>' . "\n";
        echo '    <button type="button" class="gnc-submenu-toggle" aria-haspopup="true" aria-expanded="false" aria-label="Toggle ' . $e($item['label']) . ' submenu">';
        echo '<i class="bi bi-chevron-right" aria-hidden="true"></i></button>' . "\n";
        echo '  </div>' . "\n";
        echo '  <ul class="gnc-dropdown-menu gnc-nested-menu">' . "\n";
        $renderDesktopMenu($item['children']);
        echo '  </ul>' . "\n";
        echo '</li>' . "\n";
    }
};

/* Categorized desktop dropdown: heading, links and thin gold separators. */
$renderDesktopGroups = function (array $groups) use ($renderDesktopMenu, $e) {
    foreach ($groups as $i => $group) {
        if ($i > 0) echo '<li class="gnc-menu-separator" role="separator"></li>' . "\n";
        echo '<li class="gnc-menu-heading" role="presentation">';
        if (!empty($group['overview'])) echo '<a href="' . $e($group['overview']) . '">' . $e($group['heading']) . '</a>';
        else echo $e($group['heading']);
        echo '</li>' . "\n";
        $renderDesktopMenu($group['items']);
    }
};

/* Mobile renderer (recursive): link + separate chevron button targeting a Bootstrap collapse */
$mobileSubmenuCount = 0;
$renderMobileMenu = function (array $items, $depth) use (&$renderMobileMenu, $e, &$mobileSubmenuCount) {
    echo '<ul class="list-unstyled mb-0">' . "\n";
    foreach ($items as $item) {
        $attrs = '';
        if (!empty($item['target'])) {
            $attrs .= ' target="' . $e($item['target']) . '"';
            if ($item['target'] === '_blank') {
                $attrs .= ' rel="noopener noreferrer"';
            }
        }
        if (empty($item['children'])) {
            echo '<li class="gnc-mobile-depth-' . (int) $depth . '"><a href="' . $e($item['href']) . '"' . $attrs . '>' . $e($item['label']) . '</a></li>' . "\n";
            continue;
        }
        $mobileSubmenuCount++;
        $id = 'mNavSub' . $mobileSubmenuCount;
        echo '<li class="gnc-mobile-depth-' . (int) $depth . '">' . "\n";
        echo '  <div class="gnc-mobile-subrow">' . "\n";
        echo '    <a href="' . $e($item['href']) . '"' . $attrs . '>' . $e($item['label']) . '</a>' . "\n";
        echo '    <button type="button" class="gnc-mobile-subtoggle" data-bs-toggle="collapse" data-bs-target="#' . $id . '" aria-expanded="false" aria-controls="' . $id . '" aria-label="Toggle ' . $e($item['label']) . ' submenu">';
        echo '<i class="bi bi-chevron-down gnc-chevron" aria-hidden="true"></i></button>' . "\n";
        echo '  </div>' . "\n";
        echo '  <div class="collapse gnc-subsubmenu" id="' . $id . '">' . "\n";
        $renderMobileMenu($item['children'], $depth + 1);
        echo '  </div>' . "\n";
        echo '</li>' . "\n";
    }
    echo '</ul>' . "\n";
};
/* Equivalent category structure in the existing mobile offcanvas. */
$renderMobileGroups = function (array $groups) use ($renderMobileMenu, $e) {
    foreach ($groups as $i => $group) {
        echo '<div class="gnc-mobile-group">';
        echo '<div class="gnc-mobile-group-title">';
        if (!empty($group['overview'])) echo '<a href="' . $e($group['overview']) . '">' . $e($group['heading']) . '</a>';
        else echo $e($group['heading']);
        echo '</div>';
        $renderMobileMenu($group['items'], 1);
        echo '</div>';
    }
};
?>

<?php include_once __DIR__ . '/image-framing-assets.php'; ?>
<link rel="stylesheet" href="/assets/css/page-entrance.css?v=<?= filemtime(__DIR__ . '/../assets/css/page-entrance.css') ?>">
<div id="gncPageEntrance" aria-hidden="true"<?= $isHome ? ' data-opening-title="Guagua National Colleges"' : '' ?>>
    <i style="--n:0"></i><i style="--n:1"></i><i style="--n:2"></i><i style="--n:3"></i><i style="--n:4"></i><i style="--n:5"></i>
    <div class="gnc-page-entrance-label"><img src="/assets/images/logos/gnc-logo-v1.svg" alt=""><span></span></div>
</div>
<script src="/assets/js/page-entrance.js?v=<?= filemtime(__DIR__ . '/../assets/js/page-entrance.js') ?>"></script>
<script src="/assets/js/page-skeletons.js?v=<?= filemtime(__DIR__ . '/../assets/js/page-skeletons.js') ?>"></script>
<nav id="mainNavbar" class="navbar gnc-navbar <?= $isHome ? 'fixed-top is-home' : 'sticky-top' ?> navbar-expand-xl shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 me-3" href="/">
            <img src="/assets/images/logos/gnc-logo-v1.svg" alt="GNC Logo" width="46" height="46">
            <div class="brand-text">
                <span class="brand-name d-block">Guagua National Colleges, Inc.</span>
                <span class="brand-tagline d-block">Fides, Scientia et. Patria</span>
            </div>
        </a>

        <!-- Mobile: opens offcanvas instead of collapse -->
        <button class="navbar-toggler border-0 ms-auto me-2" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#mobileNav"
                aria-controls="mobileNav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Desktop nav (hidden below xl) -->
        <div class="collapse navbar-collapse d-none d-xl-flex" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">

                <li class="nav-item <?= $isHome ? 'active' : '' ?>">
                    <a class="nav-link <?= $isHome ? 'active' : '' ?>" href="/">Home</a>
                </li>

                <li class="nav-item dropdown <?= $isAbout ? 'active' : '' ?>">
                    <a class="nav-link dropdown-toggle <?= $isAbout ? 'active' : '' ?>" href="#" id="aboutDropdown" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">About</a>
                    <ul class="dropdown-menu gnc-dropdown-menu" aria-labelledby="aboutDropdown">
                        <?php $renderDesktopGroups($aboutGroups); ?>
                    </ul>
                </li>

                <li class="nav-item dropdown <?= $isAcademics ? 'active' : '' ?>">
                    <a class="nav-link dropdown-toggle <?= $isAcademics ? 'active' : '' ?>" href="#" id="academicsDropdown" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">Academics</a>
                    <ul class="dropdown-menu gnc-dropdown-menu" aria-labelledby="academicsDropdown">
                        <?php $renderDesktopGroups($academicsGroups); ?>
                    </ul>
                </li>

                <li class="nav-item dropdown <?= $isAdmissions ? 'active' : '' ?>">
                    <a class="nav-link dropdown-toggle <?= $isAdmissions ? 'active' : '' ?>" href="#" id="admissionsDropdown" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">Admissions</a>
                    <ul class="dropdown-menu gnc-dropdown-menu" aria-labelledby="admissionsDropdown">
                        <?php $renderDesktopGroups($admissionsGroups); ?>
                    </ul>
                </li>

                <li class="nav-item dropdown <?= $isStudentLife ? 'active' : '' ?>">
                    <a class="nav-link dropdown-toggle <?= $isStudentLife ? 'active' : '' ?>" href="#" id="studentLifeDropdown" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">Student Life</a>
                    <ul class="dropdown-menu gnc-dropdown-menu" aria-labelledby="studentLifeDropdown">
                        <?php $renderDesktopGroups($studentLifeGroups); ?>
                    </ul>
                </li>

                <li class="nav-item <?= $isNewsAndAnnouncement ? 'active' : '' ?>">
                    <a class="nav-link <?= $isNewsAndAnnouncement ? 'active' : '' ?>" href="/pages/news-and-announcement.php">News & Announcements</a>
                </li>
                <li class="nav-item <?= $isContactUs ? 'active' : '' ?>">
                    <a class="nav-link <?= $isContactUs ? 'active' : '' ?>" href="/pages/contact-us.php">Contact Us</a>
                </li>

                <li class="nav-item ms-2">
                    <a class="btn-portal" href="/coming-soon.php" target="_blank">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-fill" viewBox="0 0 16 16">
                            <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                        </svg>
                        Student Portal
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- ================= MOBILE OFFCANVAS SIDEBAR ================= -->
<div class="offcanvas offcanvas-end gnc-offcanvas" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
    <div class="offcanvas-header">
        <span id="mobileNavLabel" class="visually-hidden">Main Menu</span>
        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column p-0">
        <ul class="gnc-mobile-nav list-unstyled mb-0">

            <li class="gnc-mobile-item <?= $isHome ? 'active' : '' ?>">
                <a href="/" class="gnc-mobile-link">
                    <i class="bi bi-house-door-fill"></i>
                    <span>Home</span>
                </a>
            </li>

            <li class="gnc-mobile-item <?= $isAbout ? 'active' : '' ?>">
                <a href="#" class="gnc-mobile-link" data-bs-toggle="collapse" data-bs-target="#mAbout" aria-expanded="<?= $isAbout ? 'true' : 'false' ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>About</span>
                    <i class="bi bi-chevron-down gnc-chevron ms-auto"></i>
                </a>
                <div class="collapse gnc-submenu <?= $isAbout ? 'show' : '' ?>" id="mAbout">
                    <?php $renderMobileGroups($aboutGroups); ?>
                </div>
            </li>

            <li class="gnc-mobile-item <?= $isAcademics ? 'active' : '' ?>">
                <a href="#" class="gnc-mobile-link" data-bs-toggle="collapse" data-bs-target="#mAcademics" aria-expanded="<?= $isAcademics ? 'true' : 'false' ?>">
                    <i class="bi bi-mortarboard-fill"></i>
                    <span>Academics</span>
                    <i class="bi bi-chevron-down gnc-chevron ms-auto"></i>
                </a>
                <div class="collapse gnc-submenu <?= $isAcademics ? 'show' : '' ?>" id="mAcademics">
                    <?php $renderMobileGroups($academicsGroups); ?>
                </div>
            </li>

            <li class="gnc-mobile-item <?= $isAdmissions ? 'active' : '' ?>">
                <a href="#" class="gnc-mobile-link" data-bs-toggle="collapse" data-bs-target="#mAdmissions" aria-expanded="<?= $isAdmissions ? 'true' : 'false' ?>">
                    <i class="bi bi-file-earmark-text-fill"></i>
                    <span>Admissions</span>
                    <i class="bi bi-chevron-down gnc-chevron ms-auto"></i>
                </a>
                <div class="collapse gnc-submenu <?= $isAdmissions ? 'show' : '' ?>" id="mAdmissions">
                    <?php $renderMobileGroups($admissionsGroups); ?>
                </div>
            </li>

            <li class="gnc-mobile-item <?= $isStudentLife ? 'active' : '' ?>">
                <a href="#" class="gnc-mobile-link" data-bs-toggle="collapse" data-bs-target="#mStudentLife" aria-expanded="<?= $isStudentLife ? 'true' : 'false' ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>Student Life</span>
                    <i class="bi bi-chevron-down gnc-chevron ms-auto"></i>
                </a>
                <div class="collapse gnc-submenu <?= $isStudentLife ? 'show' : '' ?>" id="mStudentLife">
                    <?php $renderMobileGroups($studentLifeGroups); ?>
                </div>
            </li>

            <li class="gnc-mobile-item <?= $isNewsAndAnnouncement ? 'active' : '' ?>">
                <a href="/pages/news-and-announcement.php" class="gnc-mobile-link">
                    <i class="bi bi-calendar-event-fill"></i>
                    <span>News & Announcements</span>
                </a>
            </li>

            <li class="gnc-mobile-item <?= $isContactUs ? 'active' : '' ?>">
                <a href="/pages/contact-us.php" class="gnc-mobile-link">
                    <i class="bi bi-envelope-fill"></i>
                    <span>Contact Us</span>
                </a>
            </li>
        </ul>

        <div class="gnc-mobile-portal d-grid gap-2">
            <a class="btn-portal w-100 justify-content-center" href="/coming-soon.php" target="_blank">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-fill" viewBox="0 0 16 16">
                    <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                </svg>
                Student Portal
            </a>
        </div>
    </div>
</div>

<!-- Shared navigation requires Offcanvas, Collapse and Dropdown (including Popper). -->
<script src="/assets/js/bootstrap.bundle.min.js"></script>

<!-- Nested Academics submenus (desktop flyouts + mobile placeholder-link handling) -->
<script>
(function () {
    'use strict';

    /* ---------- Desktop: Academics flyouts ---------- */
    document.querySelectorAll('#mainNavbar .nav-item.dropdown').forEach(function (academicsItem) {
        var canHover = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        var rootCloseDelay = 80;
        var hoverCloseDelay = 350;
        var rootCloseTimer;
        var submenuCloseTimers = new WeakMap();
        var hoverEnabled = function () {
            return canHover && window.innerWidth >= 1200 && !document.documentElement.classList.contains('gnc-editor-preview');
        };
        var rootMenu = academicsItem.querySelector(':scope > .gnc-dropdown-menu');
        var fitRootMenu = function () {
            if (!rootMenu) return;
            var top = Math.max(0, academicsItem.getBoundingClientRect().bottom);
            rootMenu.style.setProperty('--gnc-menu-top', top + 'px');
        };
        fitRootMenu();
        academicsItem.addEventListener('mouseenter', fitRootMenu);
        academicsItem.addEventListener('show.bs.dropdown', fitRootMenu);
        window.addEventListener('resize', function () {
            clearTimeout(rootCloseTimer);
            academicsItem.classList.remove('show');
            fitRootMenu();
            resetAll();
        });

        var toggleOf = function (li) {
            return li.querySelector(':scope > .gnc-submenu-row > .gnc-submenu-toggle');
        };
        var menuOf = function (li) {
            return li.querySelector(':scope > .gnc-nested-menu');
        };

        // Flip a flyout to the left if it would run off the right edge of the screen.
        var position = function (li) {
            var menu = menuOf(li);
            if (!menu) return;
            if (window.innerWidth < 1200) return;
            var row = li.getBoundingClientRect();
            var width = menu.getBoundingClientRect().width;
            var left = row.right;
            if (li.parentElement.classList.contains('open-left') || left + width > window.innerWidth - 8) left = row.left - width;
            menu.classList.toggle('open-left', left < row.left);
            var top = Math.max(8, Math.min(row.top, window.innerHeight - Math.min(menu.scrollHeight, 200) - 12));
            menu.style.left = Math.max(8, Math.min(left, window.innerWidth - width - 8)) + 'px';
            menu.style.right = 'auto';
            menu.style.top = top + 'px';
            menu.style.setProperty('--gnc-menu-top', top + 'px');
        };

        var setOpen = function (li, open) {
            clearTimeout(submenuCloseTimers.get(li));
            li.classList.toggle('show', open);
            var btn = toggleOf(li);
            if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                position(li);
            } else {
                li.querySelectorAll('.gnc-dropdown-submenu.show').forEach(function (d) {
                    setOpen(d, false);
                });
            }
        };

        var closeSiblings = function (li) {
            Array.prototype.forEach.call(li.parentElement.children, function (sib) {
                if (sib !== li && sib.classList.contains('show')) setOpen(sib, false);
            });
        };

        var resetAll = function () {
            academicsItem.querySelectorAll('.gnc-dropdown-submenu.show').forEach(function (li) {
                setOpen(li, false);
            });
        };

        academicsItem.querySelectorAll('.gnc-dropdown-menu').forEach(function (menu) {
            menu.addEventListener('scroll', function () {
                menu.querySelectorAll('.gnc-dropdown-submenu.show').forEach(function (li) { setOpen(li, false); });
            }, { passive: true });
        });

        academicsItem.querySelectorAll('.gnc-dropdown-submenu').forEach(function (li) {
            var btn = toggleOf(li);
            var link = li.querySelector(':scope > .gnc-submenu-row > a');

            // Chevron button opens/closes the submenu without touching the link.
            if (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation(); // keep Bootstrap from closing the whole Academics dropdown
                    var willOpen = !li.classList.contains('show');
                    if (willOpen) closeSiblings(li);
                    setOpen(li, willOpen);
                });
            }

            // Parent labels with no real page yet ('#') just toggle their submenu.
            if (link && link.getAttribute('href') === '#' && btn) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    btn.click();
                });
            }

            // Keep flyouts open while crossing small gaps or moving diagonally into them.
            li.addEventListener('mouseenter', function () {
                if (!hoverEnabled()) return;
                closeSiblings(li);
                setOpen(li, true);
            });
            li.addEventListener('mouseleave', function () {
                if (!hoverEnabled()) return;
                submenuCloseTimers.set(li, setTimeout(function () {
                    setOpen(li, false);
                }, hoverCloseDelay));
            });
        });

        academicsItem.addEventListener('hidden.bs.dropdown', function () {
            clearTimeout(rootCloseTimer);
            academicsItem.classList.remove('show');
            resetAll();
        });
        academicsItem.addEventListener('mouseenter', function () {
            if (!hoverEnabled()) return;
            clearTimeout(rootCloseTimer);
            academicsItem.classList.add('show');
        });
        academicsItem.addEventListener('mouseleave', function () {
            if (!hoverEnabled()) return;
            // Close ordinary dropdowns promptly; preserve the grace period for deeper flyouts.
            var delay = academicsItem.querySelector('.gnc-dropdown-submenu.show') ? hoverCloseDelay : rootCloseDelay;
            rootCloseTimer = setTimeout(function () {
                academicsItem.classList.remove('show');
                resetAll();
            }, delay);
        });
    });

    /* ---------- Mobile: placeholder ('#') parent labels toggle their submenu ---------- */
    document.querySelectorAll('#mobileNav .gnc-mobile-subrow > a').forEach(function (a) {
        if (a.getAttribute('href') !== '#') return;
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var btn = a.parentElement.querySelector('.gnc-mobile-subtoggle');
            if (btn) btn.click();
        });
    });

    /* ---------- Homepage Navbar Transparency ---------- */
    var mainNavbar = document.getElementById('mainNavbar');
    // Only run this logic if we are on the homepage
    if (mainNavbar && mainNavbar.classList.contains('is-home')) {
        var handleScroll = function () {
            // If at the very top (within 10px), make it transparent
            if (window.scrollY <= 10) {
                mainNavbar.classList.add('gnc-navbar-transparent');
                mainNavbar.classList.remove('shadow-sm'); // Remove shadow when transparent
            } else {
                mainNavbar.classList.remove('gnc-navbar-transparent');
                mainNavbar.classList.add('shadow-sm'); // Restore shadow when scrolling down
            }
        };
        
        window.addEventListener('scroll', handleScroll);
        // Initialize immediately in case the page is reloaded halfway down
        handleScroll(); 
    }
})();
</script>
