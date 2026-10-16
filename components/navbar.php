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

/* =============================================================
   ACADEMICS MENU DATA (single source of truth)
   -------------------------------------------------------------
   Used to render BOTH the desktop dropdown and the mobile sidebar,
   so the structure only has to be edited here.

   - 'href'     : where clicking the label goes. Parent items keep
                  their own link; the submenu is opened separately
                  with the chevron button.
   - 'children' : optional nested items (any depth).
   - href '#'   : placeholder for pages that don't exist yet. For a
                  parent item, clicking a '#' label just toggles its
                  submenu instead of jumping to the top of the page.
                  Replace '#' with the real route when the page exists.
   ============================================================= */
$academicsMenu = [
    [
        'label'    => 'Basic Education',
        'href'     => '/pages/academics/basic-edu/basic-edu-dept.php',
        'children' => [
            ['label' => 'Junior High School Regular',                        'href' => '/pages/academics/basic-edu/basic-edu-department/jhs-regular.php'],
            ['label' => 'CASA and Grade School Montessori',   'href' => '/pages/academics/basic-edu/basic-edu-department/casa-gradeschool.php'],
            ['label' => 'Senior High School Regular',                        'href' => '/pages/academics/basic-edu/basic-edu-department/shs-regular.php'],
            ['label' => 'High School Montessori',                      'href' => '/pages/academics/basic-edu/basic-edu-department/hs-monte.php'],
        ],
    ],
    [
        'label'    => 'College',
        'href'     => '/pages/academics/college/college-dept.php',
        // 'children' are filled in from the database below ($gncCollegeItems).
    ],
    [
        'label' => 'Graduate School',
        'href'  => '/pages/academics/graduate-school/graduate-school.php',
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
$gncProgramPage = '/pages/academics/college/program.php';

// Reason the dynamic menu fell back (printed in the HTML comment below).
$gncFallbackReason = '';

$gncLoadCollegeMenu = function () use ($gncDeptPage, $gncProgramPage, &$gncFallbackReason) {
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
                    'href'  => $gncProgramPage . '?slug=' . rawurlencode($pslug),
                ];
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

echo '<!-- gnc-nav: college menu from '
    . ($gncCollegeItems
        ? 'database (' . count($gncCollegeItems) . ' departments)'
        : 'NOTHING (no college submenu): ' . $gncFallbackReason)
    . ' -->' . "\n";

if ($gncCollegeItems) {
    foreach ($academicsMenu as &$topItem) {
        if (($topItem['label'] ?? '') === 'College') {
            $topItem['children'] = $gncCollegeItems;
        }
    }
    unset($topItem);
}

$e = function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

/* Desktop renderer (recursive): link + separate chevron button for parents */
$renderDesktopMenu = function (array $items) use (&$renderDesktopMenu, $e) {
    foreach ($items as $item) {
        if (empty($item['children'])) {
            echo '<li><a class="dropdown-item" href="' . $e($item['href']) . '">' . $e($item['label']) . '</a></li>' . "\n";
            continue;
        }
        echo '<li class="gnc-dropdown-submenu">' . "\n";
        echo '  <div class="gnc-submenu-row">' . "\n";
        echo '    <a class="dropdown-item" href="' . $e($item['href']) . '">' . $e($item['label']) . '</a>' . "\n";
        echo '    <button type="button" class="gnc-submenu-toggle" aria-haspopup="true" aria-expanded="false" aria-label="Toggle ' . $e($item['label']) . ' submenu">';
        echo '<i class="bi bi-chevron-right" aria-hidden="true"></i></button>' . "\n";
        echo '  </div>' . "\n";
        echo '  <ul class="gnc-dropdown-menu gnc-nested-menu">' . "\n";
        $renderDesktopMenu($item['children']);
        echo '  </ul>' . "\n";
        echo '</li>' . "\n";
    }
};

/* Mobile renderer (recursive): link + separate chevron button targeting a Bootstrap collapse */
$mobileSubmenuCount = 0;
$renderMobileMenu = function (array $items, $depth) use (&$renderMobileMenu, $e, &$mobileSubmenuCount) {
    echo '<ul class="list-unstyled mb-0">' . "\n";
    foreach ($items as $item) {
        if (empty($item['children'])) {
            echo '<li class="gnc-mobile-depth-' . (int) $depth . '"><a href="' . $e($item['href']) . '">' . $e($item['label']) . '</a></li>' . "\n";
            continue;
        }
        $mobileSubmenuCount++;
        $id = 'mAcademicsSub' . $mobileSubmenuCount;
        echo '<li class="gnc-mobile-depth-' . (int) $depth . '">' . "\n";
        echo '  <div class="gnc-mobile-subrow">' . "\n";
        echo '    <a href="' . $e($item['href']) . '">' . $e($item['label']) . '</a>' . "\n";
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
?>

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
                        <li><a class="dropdown-item" href="/pages/about/gnc-history.php">History of GNC</a></li>
                        <li><a class="dropdown-item" href="/pages/about/vision-mission.php">Vision and Mission</a></li>
                        <li><a class="dropdown-item" href="/pages/about/core-values.php">Core Values</a></li>
                        <li><a class="dropdown-item" href="/pages/about/logo-meaning.php">Institutional Logo and Meaning</a></li>
                        <li><a class="dropdown-item" href="/pages/about/administration.php">Administration</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown <?= $isAcademics ? 'active' : '' ?>">
                    <a class="nav-link dropdown-toggle <?= $isAcademics ? 'active' : '' ?>" href="#" id="academicsDropdown" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">Academics</a>
                    <ul class="dropdown-menu gnc-dropdown-menu" aria-labelledby="academicsDropdown">
                        <?php $renderDesktopMenu($academicsMenu); ?>
                    </ul>
                </li>

                <li class="nav-item dropdown <?= $isAdmissions ? 'active' : '' ?>">
                    <a class="nav-link dropdown-toggle <?= $isAdmissions ? 'active' : '' ?>" href="#" id="admissionsDropdown" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">Admissions</a>
                    <ul class="dropdown-menu gnc-dropdown-menu" aria-labelledby="admissionsDropdown">
                        <li><a class="dropdown-item" href="/pages/admission/admission-req.php">Admission Requirements</a></li>
                        <li><a class="dropdown-item" href="/pages/admission/enrollment-procedures.php">Enrollment Procedures</a></li>
                        <li><a class="dropdown-item" href="/admissions/tuition-fees.php">Tuition and Fees</a></li>
                        <li><a class="dropdown-item" href="/pages/admission/scholarship.php">Scholarship</a></li>
                        <li><a class="dropdown-item" href="/admissions/faqs.php">FAQs</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown <?= $isStudentLife ? 'active' : '' ?>">
                    <a class="nav-link dropdown-toggle <?= $isStudentLife ? 'active' : '' ?>" href="#" id="studentLifeDropdown" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">Student Life</a>
                    <ul class="dropdown-menu gnc-dropdown-menu" aria-labelledby="studentLifeDropdown">
                        <li><a class="dropdown-item" href="/student-life/organizations.php">Student Organization</a></li>
                        <li><a class="dropdown-item" href="/student-life/leadership-programs.php">Student Leadership Programs</a></li>
                        <li><a class="dropdown-item" href="/student-life/campus-events.php">Campus Events</a></li>
                        <li><a class="dropdown-item" href="/student-life/community-extension.php">Community Extension Activities</a></li>
                        <li><a class="dropdown-item" href="/student-life/athletics-sports.php">Athletics and Sports</a></li>
                        <li><a class="dropdown-item" href="/student-life/achievements.php">Student Achievements</a></li>
                    </ul>
                </li>

                <li class="nav-item <?= $isNewsAndAnnouncement ? 'active' : '' ?>">
                    <a class="nav-link <?= $isNewsAndAnnouncement ? 'active' : '' ?>" href="/pages/news-and-announcement.php">News & Announcements</a>
                </li>
                <li class="nav-item <?= $isContactUs ? 'active' : '' ?>">
                    <a class="nav-link <?= $isContactUs ? 'active' : '' ?>" href="/pages/contact-us.php">Contact Us</a>
                </li>

                <li class="nav-item d-flex align-items-center">
                    <button id="search-toggle" class="btn-search" type="button" aria-label="Search">
                        <i class="bi bi-search"></i>
                    </button>
                </li>
                <li class="nav-item" id="search-box-item" style="display:none;">
                    <form class="d-flex" action="#" onsubmit="return false;">
                        <input id="search-input" class="form-control form-control-sm"
                            type="search" placeholder="Search…" aria-label="Search"
                            style="min-width:160px;">
                    </form>
                </li>

                <li class="nav-item ms-2">
                    <a class="btn-portal" href="/auth/login.php" target="_blank">
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
                    <ul class="list-unstyled mb-0">
                        <li><a href="/pages/about/gnc-history.php">History of GNC</a></li>
                        <li><a href="/pages/about/vision-mission.php">Vision and Mission</a></li>
                        <li><a href="/pages/about/core-values.php">Core Values</a></li>
                        <li><a href="/pages/about/logo-meaning.php">Institutional Logo and Meaning</a></li>
                        <li><a href="/pages/about/administration.php">Administration</a></li>
                    </ul>
                </div>
            </li>

            <li class="gnc-mobile-item <?= $isAcademics ? 'active' : '' ?>">
                <a href="#" class="gnc-mobile-link" data-bs-toggle="collapse" data-bs-target="#mAcademics" aria-expanded="<?= $isAcademics ? 'true' : 'false' ?>">
                    <i class="bi bi-mortarboard-fill"></i>
                    <span>Academics</span>
                    <i class="bi bi-chevron-down gnc-chevron ms-auto"></i>
                </a>
                <div class="collapse gnc-submenu <?= $isAcademics ? 'show' : '' ?>" id="mAcademics">
                    <?php $renderMobileMenu($academicsMenu, 1); ?>
                </div>
            </li>

            <li class="gnc-mobile-item <?= $isAdmissions ? 'active' : '' ?>">
                <a href="#" class="gnc-mobile-link" data-bs-toggle="collapse" data-bs-target="#mAdmissions" aria-expanded="<?= $isAdmissions ? 'true' : 'false' ?>">
                    <i class="bi bi-file-earmark-text-fill"></i>
                    <span>Admissions</span>
                    <i class="bi bi-chevron-down gnc-chevron ms-auto"></i>
                </a>
                <div class="collapse gnc-submenu <?= $isAdmissions ? 'show' : '' ?>" id="mAdmissions">
                    <ul class="list-unstyled mb-0">
                        <li><a href="/pages/admission/admission-req.php">Admission Requirements</a></li>
                        <li><a href="/pages/admission/enrollment-procedures.php">Enrollment Procedures</a></li>
                        <li><a href="/admissions/tuition-fees.php">Tuition and Fees</a></li>
                        <li><a href="/pages/admission/scholarship.php">Scholarship</a></li>
                        <li><a href="/admissions/faqs.php">FAQs</a></li>
                    </ul>
                </div>
            </li>

            <li class="gnc-mobile-item <?= $isStudentLife ? 'active' : '' ?>">
                <a href="#" class="gnc-mobile-link" data-bs-toggle="collapse" data-bs-target="#mStudentLife" aria-expanded="<?= $isStudentLife ? 'true' : 'false' ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>Student Life</span>
                    <i class="bi bi-chevron-down gnc-chevron ms-auto"></i>
                </a>
                <div class="collapse gnc-submenu <?= $isStudentLife ? 'show' : '' ?>" id="mStudentLife">
                    <ul class="list-unstyled mb-0">
                        <li><a href="/student-life/organizations.php">Student Organization</a></li>
                        <li><a href="/student-life/leadership-programs.php">Student Leadership Programs</a></li>
                        <li><a href="/student-life/campus-events.php">Campus Events</a></li>
                        <li><a href="/student-life/community-extension.php">Community Extension Activities</a></li>
                        <li><a href="/student-life/athletics-sports.php">Athletics and Sports</a></li>
                        <li><a href="/student-life/achievements.php">Student Achievements</a></li>
                    </ul>
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

        <div class="gnc-mobile-search">
            <i class="bi bi-search"></i>
            <input type="search" class="gnc-mobile-search-input" placeholder="Search" aria-label="Search">
        </div>

        <div class="gnc-mobile-portal">
            <a class="btn-portal w-100 justify-content-center" href="/auth/login.php" target="_blank">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-fill" viewBox="0 0 16 16">
                    <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                </svg>
                Student Portal
            </a>
        </div>
    </div>
</div>

<!-- Nested Academics submenus (desktop flyouts + mobile placeholder-link handling) -->
<script>
(function () {
    'use strict';

    /* ---------- Desktop: Academics flyouts ---------- */
    var academicsToggle = document.getElementById('academicsDropdown');
    var academicsItem = academicsToggle ? academicsToggle.closest('.nav-item') : null;

    if (academicsItem) {
        var canHover = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

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
            window.requestAnimationFrame(function () {
                menu.classList.remove('open-left');
                if (li.parentElement.classList.contains('open-left')) {
                    menu.classList.add('open-left');
                    return;
                }
                var r = menu.getBoundingClientRect();
                if (r.width && r.right > window.innerWidth - 8) {
                    menu.classList.add('open-left');
                }
            });
        };

        var setOpen = function (li, open) {
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

            // Mouse users: hover opens (CSS); make sure click-opened siblings close and flyouts fit.
            li.addEventListener('mouseenter', function () {
                if (!canHover) return;
                closeSiblings(li);
                position(li);
            });
        });

        academicsItem.addEventListener('hidden.bs.dropdown', resetAll);
        academicsItem.addEventListener('mouseleave', function () {
            if (canHover) resetAll();
        });
    }

    /* ---------- Mobile: placeholder ('#') parent labels toggle their submenu ---------- */
    document.querySelectorAll('#mAcademics .gnc-mobile-subrow > a').forEach(function (a) {
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