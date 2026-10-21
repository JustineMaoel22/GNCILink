<?php
/**
 * GNC Admin Panel - Content Editor
 *
 * One place to manage the website's content:
 *   - "All content" list: search, filter and sort everything you may edit
 *   - Review queue: approvers accept or send back what editors submitted
 *   - Homepage slideshow, section-based pages (rich-text editor + live preview),
 *     College Departments, Programs and Administration (existing managers)
 *
 * Roles:
 *   Super Admin / Content Admin  edit and publish directly, approve and reject
 *   Content Editor               edit page text, save drafts, submit for approval
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';

initSession();
requireLogin();

// CSRF token used by the forms and the JS config below (safe to call repeatedly).
$csrfToken = generateCSRFToken();

/**
 * Include an admin component without letting a crash inside it take the
 * whole Content Editor down. On failure the partial output is discarded,
 * the error is logged, and a visible message is shown in its place.
 */
function renderAdminComponent(string $file): void {
    // Included components run inside THIS function's scope, so they can't see
    // the page-level $csrfToken unless it is pulled in here. Without this the
    // department manager posted an empty token -> "Your session has expired".
    global $csrfToken;
    ob_start();
    try {
        include $file;
        ob_end_flush();
    } catch (Throwable $e) {
        ob_end_clean();
        error_log('Content editor component error (' . basename($file) . '): ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine());
        echo '<div class="alert alert-danger" style="margin-bottom:1rem"><strong>'
            . htmlspecialchars(basename($file)) . ' failed to load.</strong><br>'
            . htmlspecialchars($e->getMessage()) . '<br><small>'
            . htmlspecialchars(basename($e->getFile()) . ':' . $e->getLine()) . '</small></div>';
    }
}

/** Short helpers for the markup below. */
function ce_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function ce_when(?string $ts): string {
    if (!$ts) return '';
    $t = strtotime($ts);
    return $t ? date('M j, Y g:i A', $t) : '';
}

$currentUser = getCurrentUser();
$pageTitle   = 'Content Editor';
$slides      = getHeroSlides(false); // all slides, including drafts, for the admin list

// ── What may this person do? ──
$canManagePages = hasPermission('manage_page_content');                 // edit + publish directly
$canDraftPages  = $canManagePages || hasPermission('edit_page_content'); // edit + submit for approval
$canApprove     = hasPermission('approve_content') || hasPermission('reject_content');
$reviewReady    = pageReviewReady();   // false until sql/page-approval-migration.sql has been run

$roleName = (string)($currentUser['role'] ?? ($_SESSION['role'] ?? ''));

// Departments that belong to Basic Education (they have their own page templates). Every other department is a college
// department. Kept in ONE place so the pickers and the "All content" list always agree.
$basicEduSlugs = ['casa-gradeschool', 'jhs-regular', 'hs-monte', 'shs-regular'];

// Pages that are NOT edited with the text editor (they have their own tools)
$customSlugs = ['home', 'college-departments', 'programs', 'administration', 'graduate-school', 'tuition-fees', 'online-payment-guide'];

// Where each page appears on the public website (shown as "View live page")
$publicUrls = [
    'basic-education-admission-requirements' => '/pages/admission/basic-education-admission-req.php',
    'college-admission-requirements' => '/pages/admission/admission-req.php',
    'home'                       => '/',
    'tuition-fees'               => '/pages/admission/tuition-fees.php',
    'online-payment-guide'       => '/pages/admission/online-payment-guide.php',
    'vision-mission'             => '/pages/about/vision-mission.php',
    'history'                    => '/pages/about/gnc-history.php',
    'administration'             => '/pages/about/administration.php',
    'college-departments'        => '/pages/academics/college/college-dept.php',
    'programs'                   => '/pages/academics/college/college-dept.php',
    'graduate-school'            => '/pages/academics/graduate-school/graduate-school.php',
    'basic-education-departments'=> '/pages/academics/basic-edu/basic-edu-dept.php',
];

// ── Pages ──
// Home keeps using the dedicated slideshow editor; every other page is powered
// by the generic Page -> Section -> Content structure (page-content functions in
// admin-functions.php), so new pages can be added without touching this file.
$editablePages = getEditablePages();
if (empty($editablePages)) {
    // Fallback so the editor still opens even before the page-content schema has been installed.
    $editablePages = [['slug' => 'home', 'title' => 'Home', 'page_type' => 'slideshow']];
}
$sectionPages = array_values(array_filter($editablePages, fn($p) => ($p['page_type'] ?? 'sections') === 'sections'));

// Which pages can this person open?
$textPages   = $canDraftPages ? array_values(array_filter($sectionPages, fn($p) => !in_array($p['slug'], $customSlugs, true))) : [];
$customPages = $canManagePages ? array_values(array_filter($sectionPages, fn($p) => in_array($p['slug'], $customSlugs, true))) : [];
$graduateDept = null;
if ($canManagePages && in_array((string)($_GET['page'] ?? ''), ['programs', 'graduate-school'], true)) {
    $graduateDept = ensureGraduateSchoolDepartment();
}
if ($canManagePages && !in_array('graduate-school', array_column($customPages, 'slug'), true)) {
    $customPages[] = ['slug' => 'graduate-school', 'title' => 'Graduate School', 'page_type' => 'sections'];
}
$titleOf = [];
foreach ($editablePages as $p) $titleOf[$p['slug']] = $p['title'];
// Pages of the Visual Editor that are not rows of the page tables (their content lives in the departments / posts tables)
$titleOf['basic-education'] = 'Basic Education';
$titleOf['graduate-school'] = 'Graduate School';
$titleOf['updates']         = 'News, announcements & events';
$titleOf['student-organizations'] = 'Student Organizations Management';
$titleOf['student-achievements'] = 'Student Achievements';
$titleOf['athletics-sports'] = 'Athletics and Sports';
$publicUrls['athletics-sports'] = '/pages/student%20life/athletics-sports.php';
$publicUrls['student-achievements'] = '/pages/student%20life/student-achievement.php';
$titleOf['tuition-fees'] = 'Tuition and Fees';
$titleOf['online-payment-guide'] = 'Online Payment Guide';
$publicUrls['student-organizations'] = studentOrganizationsUrl();

// News, announcements and events keep their own editors (and their approval workflow). The Visual Editor opens those very
// editors inside its side panel, so nothing is re-implemented and every permission check stays where it is.
$postModules = [];
foreach ([
    ['announcement', 'announcement.php', 'Announcements', 'bi-megaphone',      ['manage_announcements', 'create_announcement', 'edit_own_announcement', 'edit_announcement', 'publish_announcement'], 'create_announcement'],
    ['news',         'news.php',         'News',          'bi-newspaper',      ['manage_news', 'create_news', 'edit_own_news', 'edit_news', 'publish_news'],                                      'create_news'],
    ['event',        'events.php',       'Events',        'bi-calendar-event', ['manage_events', 'create_event', 'edit_own_event', 'edit_event', 'publish_event'],                                'create_event'],
] as [$pmKey, $pmFile, $pmLabel, $pmIcon, $pmPerms, $pmCreate]) {
    if (!is_file(__DIR__ . '/' . $pmFile)) continue;
    foreach ($pmPerms as $pm) {
        if (hasPermission($pm)) { $postModules[$pmKey] = ['key' => $pmKey, 'file' => $pmFile, 'label' => $pmLabel, 'icon' => $pmIcon, 'canCreate' => hasPermission($pmCreate)]; break; }
    }
}
/** URL (inside this editor) that opens one of those editors in the side panel. Only the whitelisted modules, only plain numbers. */
function ce_postUrl(string $key, array $q = []): string {
    $u = '?page=updates&open=' . rawurlencode($key);
    foreach (['modal' => '/^(edit|create)$/', 'id' => '/^\d{1,10}$/', 'month' => '/^\d{1,2}$/', 'year' => '/^\d{4}$/'] as $k => $re) {
        if (isset($q[$k]) && preg_match($re, (string)$q[$k])) $u .= '&' . $k . '=' . rawurlencode((string)$q[$k]);
    }
    return $u;
}

// Load sections (with who edited / submitted them) for the text pages
$sectionsBySlug = [];
if ($canDraftPages && (($_GET['page'] ?? '') === 'history')) ensureHistoryPageSections();
foreach ($textPages as $sp) {
    try {
        $sectionsBySlug[$sp['slug']] = getPageSectionsDetailed($sp['slug']);
        if (in_array($sp['slug'],['college-admission-requirements','basic-education-admission-requirements'],true)) {
            $sectionsBySlug[$sp['slug']] = array_values(array_filter($sectionsBySlug[$sp['slug']],static fn($section) => $section['status']==='published'));
        }
    }
    catch (Throwable $e) { error_log('getPageSectionsDetailed error: ' . $e->getMessage()); $sectionsBySlug[$sp['slug']] = []; }
}

// Approvals waiting
$pending = ($canApprove && $reviewReady) ? getPendingSections() : [];

// ── Which panel is open? ──
$validPanels = ['hub'];
if ($canApprove) $validPanels[] = 'review';
if ($canManagePages) { $validPanels[] = 'home'; $validPanels[] = 'basic-education'; $validPanels[] = 'student-organizations'; $validPanels[] = 'student-achievements'; $validPanels[] = 'athletics-sports'; $validPanels[] = 'tuition-fees'; $validPanels[] = 'online-payment-guide'; }
if ($postModules && $canManagePages) $validPanels[] = 'updates';
foreach ($textPages as $p)   $validPanels[] = $p['slug'];
foreach ($customPages as $p) $validPanels[] = $p['slug'];
$requestedPage = (string)($_GET['page'] ?? '');
if ($requestedPage === '') {
    // Opening the Content Editor lands on the website itself (the homepage, or the first page you may edit).
    $requestedPage = $canManagePages ? 'home' : (string)($textPages[0]['slug'] ?? 'hub');
}
$activeSlug    = in_array($requestedPage, $validPanels, true) ? $requestedPage : 'hub';

// "All content" and the review queue are lists. Every other page opens as the visual editor:
// the real website page, edited in place.
$editorMode = !in_array($activeSlug, ['hub', 'review'], true);

$VE = null;
if ($editorMode) {
    $veKind = $activeSlug === 'student-organizations' ? 'organizations' : ($activeSlug === 'home' ? 'home'
        : ($activeSlug === 'administration' ? 'admin'
        : ($activeSlug === 'updates' ? 'feed'
        : (in_array($activeSlug, ['college-departments', 'programs', 'basic-education', 'graduate-school'], true) ? 'entity' : 'sections'))));
    if ($activeSlug === 'tuition-fees') $veKind = 'tuition';
    if ($activeSlug === 'online-payment-guide') $veKind = 'payment-guide';
    if ($activeSlug === 'student-achievements') $veKind = 'achievements';
    if ($activeSlug === 'athletics-sports') $veKind = 'sports';
    $withPreview = function (string $url): string { return $url . (strpos($url, '?') !== false ? '&' : '?') . 'ce_preview=1'; };

    // The page list in the top bar
    $pickerPages = [];
    if ($canManagePages) $pickerPages[] = ['slug' => 'home', 'title' => 'Homepage', 'group' => 'Homepage', 'icon' => 'window-desktop'];
    foreach ($textPages as $p)   $pickerPages[] = ['slug' => $p['slug'], 'title' => $p['title'], 'group' => 'Pages', 'icon' => 'file-earmark-text'];
    foreach ($customPages as $p) $pickerPages[] = ['slug' => $p['slug'], 'title' => $p['title'], 'group' => 'Academics & people',
        'icon' => $p['slug'] === 'administration' ? 'people' : ($p['slug'] === 'programs' ? 'mortarboard' : 'building')];
    if ($canManagePages) $pickerPages[] = ['slug' => 'basic-education', 'title' => 'Basic Education', 'group' => 'Academics & people', 'icon' => 'backpack'];
    if ($canManagePages) $pickerPages[] = ['slug'=>'student-organizations','title'=>'Student Organizations Management','group'=>'Student life','icon'=>'people'];
    if ($canManagePages) $pickerPages[] = ['slug'=>'student-achievements','title'=>'Student Achievements','group'=>'Student life','icon'=>'trophy'];
    if ($canManagePages) $pickerPages[] = ['slug'=>'athletics-sports','title'=>'Athletics and Sports','group'=>'Student life','icon'=>'dribbble'];
    if ($canManagePages && !in_array('online-payment-guide',array_column($pickerPages,'slug'),true)) $pickerPages[] = ['slug'=>'online-payment-guide','title'=>'Online Payment Guide','group'=>'Admissions','icon'=>'credit-card'];
    if ($canManagePages && !in_array('tuition-fees',array_column($pickerPages,'slug'),true)) $pickerPages[] = ['slug'=>'tuition-fees','title'=>'Tuition and Fees','group'=>'Admissions','icon'=>'file-earmark-pdf'];
    if ($postModules && $canManagePages) $pickerPages[] = ['slug' => 'updates', 'title' => 'News, announcements & events', 'group' => 'Posts', 'icon' => 'megaphone'];

    $veTitle   = $activeSlug === 'home' ? 'Homepage' : ($titleOf[$activeSlug] ?? $activeSlug);
    $publicUrl = $publicUrls[$activeSlug] ?? '';
    $pageUrl   = $publicUrl !== '' ? $withPreview($publicUrl) : '';
    $subs = [];
    $wantSub   = trim((string)($_GET['sub'] ?? ''));   // deep link: ?page=programs&sub=<slug>
    $wantProg  = (int)($_GET['program_id'] ?? 0);      // deep link to a program that was just created
    $veSection = trim((string)($_GET['section'] ?? ''));

    if ($veKind === 'organizations') {
        $subs[] = ['label'=>'All organizations','url'=>$withPreview(studentOrganizationsUrl())];
        $publicUrl = studentOrganizationsUrl();
        foreach (studentOrganizations(true) as $organization) {
            $subs[] = ['key'=>$organization['slug'],'label'=>$organization['name'].($organization['status']==='draft'?' (Unpublished)':''),'url'=>$withPreview(studentOrganizationsUrl($organization['slug']))];
            if ($wantSub === $organization['slug']) $publicUrl=studentOrganizationsUrl($organization['slug']);
        }
        $pageUrl=$withPreview($publicUrl);
    } elseif ($veKind === 'admin') {
        $pageUrl = $withPreview('/pages/about/administration.php') . ($veSection !== '' ? '&section=' . rawurlencode($veSection) : '');
    } elseif ($activeSlug === 'college-departments') {
        $subs[] = ['label' => 'All departments', 'url' => $withPreview('/pages/academics/college/college-dept.php')];
        try {
            foreach (getDepartments() as $d) {
                if (!empty($d['archived_at']) || empty($d['slug']) || in_array((string)$d['slug'], $basicEduSlugs, true) || $d['slug'] === 'graduate-school') continue;
                $subs[] = ['key' => (string)$d['slug'], 'label' => (string)$d['dept_name'], 'url' => $withPreview('/pages/academics/college/college-department.php?slug=' . rawurlencode($d['slug']))];
            }
        } catch (Throwable $e) { error_log('visual editor departments: ' . $e->getMessage()); }
        $pageUrl = $subs[0]['url'];
    } elseif ($activeSlug === 'programs') {
        try {
            // Basic Education programs are cards on their department page;
            // they do not have the standalone detail pages edited here.
            $basicProgramDepartments = [];
            foreach (getDepartments() as $department) {
                if (($department['department_type'] ?? '') === 'basic-education'
                    || in_array((string)($department['slug'] ?? ''), $basicEduSlugs, true)) {
                    $basicProgramDepartments[(int)$department['dept_id']] = true;
                }
            }
            foreach (getPrograms() as $pr) {
                if (empty($pr['slug']) || isset($basicProgramDepartments[(int)($pr['dept_id'] ?? 0)])) continue;
                if ($wantProg > 0 && (int)$pr['program_id'] === $wantProg) $wantSub = (string)$pr['slug'];
                $subs[] = ['key' => (string)$pr['slug'], 'label' => trim(($pr['program_code'] ? $pr['program_code'] . ' – ' : '') . $pr['program_name']), 'url' => $withPreview('/pages/academics/college/program.php?slug=' . rawurlencode($pr['slug']))];
            }
        } catch (Throwable $e) { error_log('visual editor programs: ' . $e->getMessage()); }
        $pageUrl = $subs ? $subs[0]['url'] : $withPreview('/pages/academics/college/college-dept.php');
    } elseif ($activeSlug === 'graduate-school') {
        $subs[] = ['key' => 'graduate-school', 'label' => 'Graduate School', 'url' => $withPreview('/pages/academics/college/college-department.php?slug=graduate-school')];
        $pageUrl = $subs[0]['url'];
    } elseif ($activeSlug === 'basic-education') {
        $publicUrl = '/pages/academics/basic-edu/basic-edu-dept.php';
        $subs[] = ['key' => '', 'label' => 'All Basic Education departments', 'url' => $withPreview($publicUrl)];
        try {
            $beSlugs = $basicEduSlugs;
            foreach (getDepartments() as $d) {
                if (!in_array((string)($d['slug'] ?? ''), $beSlugs, true)) continue;
                $subs[] = ['key' => (string)$d['slug'], 'label' => (string)$d['dept_name'], 'url' => $withPreview('/pages/academics/basic-edu/basic-edu-department/' . rawurlencode($d['slug']) . '.php')];
            }
        } catch (Throwable $e) { error_log('visual editor basic education: ' . $e->getMessage()); }
        $pageUrl = $subs[0]['url'];
    } elseif ($veKind === 'feed') {
        $publicUrl = '/';
        $pageUrl   = $withPreview('/') . '#news';
    }
    if ($wantSub !== '') {
        foreach ($subs as $s) { if (($s['key'] ?? null) === $wantSub) { $pageUrl = $s['url']; break; } }
    }

    // What the in-page editor needs about the open page
    $veSections = [];
    if ($veKind === 'sections') {
        foreach ($sectionsBySlug[$activeSlug] ?? [] as $sec) {
            $hasDraft = $sec['draft_content'] !== null;
            $veSections[] = [
                'key' => $sec['section_key'], 'title' => $sec['title'],
                'html' => sectionToEditorHtml($hasDraft ? $sec['draft_content'] : ($sec['content'] ?? '')),
                'has_draft' => $hasDraft, 'review_status' => (string)($sec['review_status'] ?? 'none'),
                'review_note' => $sec['review_note'] ?? null, 'submitted_by' => trim((string)($sec['submitted_by_name'] ?? '')),
            ];
        }
    }
    $veHome = null;
    if ($veKind === 'home') {
        $pres = [];
        try { $pres = homepagePresidentAdminPayload(getHomepagePresident()); } catch (Throwable $e) { error_log('visual editor president: ' . $e->getMessage()); }
        $veHome = ['slides' => $slides, 'president' => $pres, 'content' => homeContentAdminPayload()];
    }
    $veAdmin = null;
    if ($veKind === 'admin') {
        try { $veAdmin = ['sections' => administrationAdminPayload()]; } catch (Throwable $e) { $veAdmin = ['sections' => []]; error_log('visual editor administration: ' . $e->getMessage()); }
    }
    // News, announcements and events open in the side panel (on the homepage, where they appear, and on their own page here)
    $veEmbed = null;
    if (in_array($veKind, ['home', 'feed'], true) && $postModules) {
        $openUrl = null;
        $openKey = (string)($_GET['open'] ?? '');
        if (isset($postModules[$openKey])) {
            $q = []; foreach (['modal', 'id', 'month', 'year'] as $k) if (isset($_GET[$k])) $q[$k] = (string)$_GET[$k];
            $isEvent = $openKey === 'event';
            $ok = ['modal' => preg_match('/^(edit|create)$/', $q['modal'] ?? '') ? $q['modal'] : '', 'id' => preg_match('/^\d{1,10}$/', $q['id'] ?? '') ? $q['id'] : '',
                   'month' => preg_match('/^\d{1,2}$/', $q['month'] ?? '') ? $q['month'] : '', 'year' => preg_match('/^\d{4}$/', $q['year'] ?? '') ? $q['year'] : ''];
            $qs = ['embed=1'];
            if (!$isEvent) { if ($ok['modal'] === 'edit' && $ok['id'] !== '') { $qs[] = 'modal=edit'; $qs[] = 'id=' . $ok['id']; } elseif ($ok['modal'] === 'create' && $postModules[$openKey]['canCreate']) { $qs[] = 'modal=create'; } }
            else { if ($ok['month'] !== '' && $ok['year'] !== '') { $qs[] = 'month=' . $ok['month']; $qs[] = 'year=' . $ok['year']; } }
            $openUrl = $postModules[$openKey]['file'] . '?' . implode('&', $qs);
        }
        $veEmbed = ['modules' => array_map(function ($m) {
            return ['key' => $m['key'], 'label' => $m['label'], 'icon' => $m['icon'], 'canCreate' => $m['canCreate'],
                    'list' => $m['file'] . '?embed=1', 'create' => ($m['canCreate'] && $m['key'] !== 'event') ? $m['file'] . '?embed=1&modal=create' : ($m['canCreate'] ? $m['file'] . '?embed=1' : null)];
        }, array_values($postModules)), 'open' => $openUrl];
    }
    // Program page content and settings are edited in the visual canvas and its
    // contextual options. Do not mount the legacy multi-section form drawer.
    $hasDrawer = in_array($veKind, ['home', 'admin', 'entity'], true) && !in_array($activeSlug, ['programs', 'graduate-school'], true);
    $hasPanel  = $hasDrawer || $veEmbed !== null;
    $drawerFile = $veKind === 'home' ? 'homepage-president-manager.php'
        : ($veKind === 'admin' ? 'administration-manager.php' : ($activeSlug === 'programs' ? 'program-page-manager.php' : 'department-media-manager.php'));
    if ($activeSlug === 'basic-education') $drawerFile = 'basic-edu-media-manager.php';
    $drawerTitle = $veKind === 'home' ? 'Message from the President (full form)'
        : ($veKind === 'admin' ? 'More settings: archived people, tabs and offices' : ($activeSlug === 'programs' ? 'Program details' : 'Department details'));
    if ($veKind === 'organizations') $drawerTitle = 'Student Organizations Management';

    $VE = [
        'csrf' => $csrfToken, 'contentHandler' => '/admin/action/page-content-handler.php', 'reviewHandler' => '/admin/action/page-review-handler.php',
        'canPublish' => $canManagePages, 'canApprove' => $canApprove, 'reviewReady' => $reviewReady, 'hasDrawer' => $hasDrawer,
        'focusSection' => (string)($_GET['section'] ?? '') !== '' && $veKind !== 'admin' ? (string)$_GET['section'] : null,
        'page' => ['slug' => $activeSlug, 'title' => $veTitle, 'kind' => $veKind, 'url' => $pageUrl, 'publicUrl' => $publicUrl, 'section' => $veSection],
        'embed' => $veEmbed,
        'sections' => $veSections, 'home' => $veHome, 'admin' => $veAdmin,
        'graduateDeptId' => (int)($graduateDept['dept_id'] ?? 0),
        'programDepartments' => array_values(array_map(static function ($d) {
            return ['id' => (int)$d['dept_id'], 'name' => (string)$d['dept_name'], 'slug' => (string)($d['slug'] ?? '')];
        }, array_filter(getDepartments(), static function ($d) use ($basicEduSlugs) {
            return empty($d['archived_at']);
        }))),
    ];
}

// ── The "All content" list ──
$rank = ['pending' => 0, 'rejected' => 1, 'draft' => 2, 'live' => 3, 'hidden' => 4];
$hub = [];
if ($canManagePages) {
    $homeStatus = 'live'; $homeTs = 0;
    foreach ($slides as $s) {
        if (($s['status'] ?? '') !== 'published') $homeStatus = 'draft';
        $homeTs = max($homeTs, (int)strtotime((string)($s['updated_at'] ?? $s['created_at'] ?? '')));
    }
    if (!$slides) $homeStatus = 'draft';
    $hub[] = ['title' => 'Homepage slideshow', 'type' => 'Homepage', 'status' => $homeStatus, 'ts' => $homeTs,
              'note' => count($slides) . ' slide' . (count($slides) === 1 ? '' : 's'), 'open' => 'home', 'href' => '?page=home', 'where' => '/'];
}
foreach (getContentHubPages() as $row) {
    $slug = $row['slug'];
    if (in_array($slug, $customSlugs, true)) continue;
    if (!in_array($slug, array_column($textPages, 'slug'), true)) continue;
    $st = ((int)$row['pending'] > 0) ? 'pending' : (((int)$row['rejected'] > 0) ? 'rejected' : (((int)$row['drafts'] > 0) ? 'draft' : 'live'));
    $ts = max((int)strtotime((string)$row['sections_updated']), (int)strtotime((string)$row['page_updated']));
    $n  = (int)$row['section_count'];
    $hub[] = ['title' => $row['title'], 'type' => 'Page', 'status' => $st, 'ts' => $ts,
              'note' => $n . ' section' . ($n === 1 ? '' : 's'), 'open' => $slug, 'href' => '?page=' . rawurlencode($slug),
              'where' => $publicUrls[$slug] ?? ''];
}
if ($canManagePages) {
    $hub[] = ['title'=>'Online Payment Guide','type'=>'Admissions','status'=>'live','ts'=>0,'note'=>'Payment instructions, account details, links and illustrations','open'=>'online-payment-guide','href'=>'?page=online-payment-guide','where'=>$publicUrls['online-payment-guide']];
    $hub[] = ['title'=>'Tuition and Fees','type'=>'Admissions','status'=>'live','ts'=>0,'note'=>'Tuition text, fee categories and PDF schedules','open'=>'tuition-fees','href'=>'?page=tuition-fees','where'=>$publicUrls['tuition-fees']];
    foreach ($customPages as $cp) {
        if ($cp['slug'] === 'home') continue;
        $type = $cp['slug'] === 'administration' ? 'Administration' : 'Academics';
        $hub[] = ['title' => $cp['title'], 'type' => $type, 'status' => 'live', 'ts' => 0,
                  'note' => $cp['slug'] === 'administration' ? 'People and offices' : 'Departments and programs',
                  'open' => $cp['slug'], 'href' => '?page=' . rawurlencode($cp['slug']), 'where' => $publicUrls[$cp['slug']] ?? ''];
    }
    $hub[] = ['title'=>'Student Organizations Management','type'=>'Student life','status'=>'live','ts'=>0,'note'=>'Organizations, categories, order and publication status','open'=>'student-organizations','href'=>'?page=student-organizations','where'=>studentOrganizationsUrl()];
    $hub[] = ['title'=>'Student Achievements','type'=>'Student life','status'=>'live','ts'=>0,'note'=>'Achievements, uploaded pictures and academic years','open'=>'student-achievements','href'=>'?page=student-achievements','where'=>'/pages/student%20life/student-achievement.php'];
    $hub[] = ['title'=>'Athletics and Sports','type'=>'Student life','status'=>'live','ts'=>0,'note'=>'Sports slideshow, program names and photos','open'=>'athletics-sports','href'=>'?page=athletics-sports','where'=>$publicUrls['athletics-sports']];
    foreach (studentOrganizations(true) as $organization) {
        $hub[] = ['title'=>$organization['name'],'type'=>'Organization','status'=>$organization['status']==='published'?'live':'draft','ts'=>strtotime($organization['updated_at']),'note'=>$organization['category_name']??'Uncategorized','open'=>'student-organizations','href'=>'?page=student-organizations&sub='.rawurlencode($organization['slug']),'where'=>studentOrganizationsUrl($organization['slug'])];
    }
    foreach (getContentHubDepartments() as $d) {
        $isBasic = !empty($d['slug']) && in_array((string)$d['slug'], $basicEduSlugs, true);
        $isGraduate = ($d['slug'] ?? '') === 'graduate-school';
        $pageKey = $isGraduate ? 'graduate-school' : ($isBasic ? 'basic-education' : 'college-departments');
        $hub[] = ['title' => $d['dept_name'], 'type' => 'Department', 'status' => $d['archived_at'] ? 'hidden' : 'live', 'ts' => 0,
                  'note' => $isGraduate ? 'Graduate School' : ($isBasic ? 'Basic Education department' : 'College department'), 'open' => $pageKey, 'href' => '?page=' . $pageKey . (!empty($d['slug']) ? '&sub=' . rawurlencode($d['slug']) : ''),
                  'where' => empty($d['slug']) ? '' : ($isGraduate ? '/pages/academics/graduate-school/graduate-school.php' : ($isBasic ? '/pages/academics/basic-edu/basic-edu-department/' . rawurlencode($d['slug']) . '.php' : '/pages/academics/college/college-department.php?slug=' . rawurlencode($d['slug'])))];
    }
    foreach (getContentHubPrograms() as $pr) {
        $hub[] = ['title' => ($pr['program_code'] ? $pr['program_code'] . ' – ' : '') . $pr['program_name'], 'type' => 'Program',
                  'status' => ((int)$pr['is_published'] === 1) ? 'live' : 'hidden', 'ts' => 0,
                  'note' => (string)($pr['dept_name'] ?? ''), 'open' => 'programs',
                  'href' => '?page=programs' . (!empty($pr['slug']) ? '&sub=' . rawurlencode($pr['slug']) : '') . '#program-' . (int)$pr['program_id'], 'where' => ''];
    }
}
// News, announcements and events: listed here so everything is searchable in one place.
// Each row links to that module's own editor (which keeps its approval workflow).
$statusMap = ['published' => 'live', 'pending' => 'pending', 'draft' => 'draft', 'archived' => 'hidden'];
$uid = (int)($_SESSION['user_id'] ?? 0);
$mayEdit = function (string $manage, string $kind, $ownerId) use ($uid): bool {
    return hasPermission($manage) || hasPermission("edit_{$kind}") || hasPermission("publish_{$kind}")
        || (hasPermission("edit_own_{$kind}") && (int)$ownerId === $uid);
};
if (is_file(__DIR__ . '/announcement.php')) {
    try {
        foreach (getAnnouncements([], 200, 0) as $a) {
            if (!$mayEdit('manage_announcements', 'announcement', $a['user_id'] ?? 0)) continue;
            $by = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
            $hub[] = ['title' => $a['title'], 'type' => 'Announcement', 'status' => $statusMap[$a['status']] ?? 'draft',
                      'ts' => (int)strtotime((string)($a['updated_at'] ?? $a['created_at'] ?? '')),
                      'note' => $by !== '' ? 'By ' . $by : '', 'open' => '', 'href' => $canManagePages ? ce_postUrl('announcement', ['modal' => 'edit', 'id' => (int)$a['announcement_id']]) : 'announcement.php?modal=edit&id=' . (int)$a['announcement_id'], 'where' => ''];
        }
    } catch (Throwable $e) { error_log('hub announcements: ' . $e->getMessage()); }
}
if (is_file(__DIR__ . '/news.php')) {
    try {
        foreach (getNews([], 200, 0) as $n) {
            if (!$mayEdit('manage_news', 'news', $n['user_id'] ?? 0)) continue;
            $by = trim((string)($n['author'] ?? '')) ?: trim(($n['first_name'] ?? '') . ' ' . ($n['last_name'] ?? ''));
            $raw = (string)($n['updated_at'] ?? $n['created_at'] ?? '');
            $hub[] = ['title' => $n['title'], 'type' => 'News', 'status' => $statusMap[$n['status']] ?? 'draft',
                      'ts' => (int)strtotime($raw . ' UTC'),   // news dates are stored in UTC
                      'note' => $by !== '' ? 'By ' . $by : '', 'open' => '', 'href' => $canManagePages ? ce_postUrl('news', ['modal' => 'edit', 'id' => (int)$n['news_id']]) : 'news.php?modal=edit&id=' . (int)$n['news_id'], 'where' => ''];
        }
    } catch (Throwable $e) { error_log('hub news: ' . $e->getMessage()); }
}
if (is_file(__DIR__ . '/events.php')) {
    try {
        foreach (getContentHubEvents() as $ev) {
            if (!$mayEdit('manage_events', 'event', $ev['user_id'] ?? 0)) continue;
            $hub[] = ['title' => $ev['title'], 'type' => 'Event', 'status' => $statusMap[$ev['status']] ?? 'draft',
                      'ts' => (int)strtotime((string)$ev['start_date']),
                      'note' => 'Starts ' . date('M j, Y', (int)strtotime((string)$ev['start_date'])), 'open' => '', 'href' => ($canManagePages ? ce_postUrl('event', ['month' => (int)date('n', (int)strtotime((string)$ev['start_date'])), 'year' => (int)date('Y', (int)strtotime((string)$ev['start_date']))]) : 'events.php?month=' . (int)date('n', (int)strtotime((string)$ev['start_date'])) . '&year=' . (int)date('Y', (int)strtotime((string)$ev['start_date']))), 'where' => ''];
        }
    } catch (Throwable $e) { error_log('hub events: ' . $e->getMessage()); }
}
$hubTypes = array_values(array_unique(array_column($hub, 'type')));
$hubCount = ['all' => count($hub), 'live' => 0, 'draft' => 0, 'pending' => 0, 'rejected' => 0, 'hidden' => 0];
foreach ($hub as $h) $hubCount[$h['status']]++;

$statusLabel = ['live' => 'Live', 'draft' => 'Draft', 'pending' => 'Waiting for approval', 'rejected' => 'Changes requested', 'hidden' => 'Hidden'];

// Other content modules (real files in /admin). Linked only when the person may use them.
$otherModules = [];
foreach ([
    ['announcement.php', 'Announcements', 'bi-megaphone', ['manage_announcements', 'create_announcement', 'edit_own_announcement']],
    ['news.php',         'News',          'bi-newspaper', ['manage_news', 'create_news', 'edit_own_news']],
    ['events.php',       'Events',        'bi-calendar-event', ['manage_events', 'create_event', 'edit_own_event']],
] as [$file, $label, $icon, $perms]) {
    if (!is_file(__DIR__ . '/' . $file)) continue;
    foreach ($perms as $pm) { if (hasPermission($pm)) { $otherModules[] = ['href' => ($canManagePages && isset($postModules[$pmk = ['announcement.php' => 'announcement', 'news.php' => 'news', 'events.php' => 'event'][$file]])) ? ce_postUrl($pmk) : $file, 'label' => $label, 'icon' => $icon]; break; } }
}
$createLinks = [];
if (hasPermission('create_announcement') && is_file(__DIR__ . '/announcement.php')) $createLinks[] = [$canManagePages ? ce_postUrl('announcement', ['modal' => 'create']) : 'announcement.php?modal=create', 'Announcement', 'bi-megaphone'];
if (hasPermission('create_news') && is_file(__DIR__ . '/news.php'))                 $createLinks[] = [$canManagePages ? ce_postUrl('news', ['modal' => 'create']) : 'news.php?modal=create', 'News article', 'bi-newspaper'];
if (hasPermission('create_event') && is_file(__DIR__ . '/events.php'))              $createLinks[] = [$canManagePages ? ce_postUrl('event') : 'events.php', 'Event', 'bi-calendar-event'];

// Nothing at all to show or do on this screen?
$noAccess = !$canDraftPages && !$canApprove && !$hub && !$createLinks;

include __DIR__ . '/../components/header-admin.php';
?>

<link rel="stylesheet" href="/assets/css/content-editor-style.css?v=<?= (int)@filemtime(__DIR__ . '/../assets/css/content-editor-style.css') ?: 1 ?>">

<div class="ce-toasts" id="ceToasts" aria-live="polite" aria-atomic="false"></div>

<?php if ($editorMode): ?>
<?php /* ══════════════════ Visual editor: the real website page, edited in place ══════════════════ */ ?>
<div class="ve-app" id="veApp" data-device="desktop">

    <header class="ve-top">
        <a class="ve-back" href="?page=hub" data-ve-nav title="Back to the list of all content"><i class="bi bi-grid-1x2"></i><span>All content</span></a>

        <details class="ve-menu" id="vePageMenu">
            <summary title="Choose which page to edit">
                <i class="bi bi-file-earmark-richtext"></i>
                <span class="ve-menu-name"><?= ce_e($VE['page']['title']) ?></span>
                <i class="bi bi-chevron-down ve-caret"></i>
            </summary>
            <div class="ve-menu-list" role="menu">
                <?php $lastGroup = null; foreach ($pickerPages as $pp):
                    if ($pp['group'] !== $lastGroup) { echo '<div class="ve-menu-group">' . ce_e($pp['group']) . '</div>'; $lastGroup = $pp['group']; } ?>
                <a role="menuitem" data-ve-nav class="ve-menu-item<?= $pp['slug'] === $activeSlug ? ' is-current' : '' ?>" href="<?= ce_e($pp['href'] ?? ('?page=' . rawurlencode($pp['slug']))) ?>">
                    <i class="bi bi-<?= ce_e($pp['icon']) ?>"></i><span><?= ce_e($pp['title']) ?></span>
                </a>
                <?php endforeach; ?>
                <div class="ve-menu-group">More</div>
                <a role="menuitem" data-ve-nav class="ve-menu-item" href="?page=hub"><i class="bi bi-grid-1x2"></i><span>All content (list)</span></a>
                <?php if ($canApprove): ?>
                <a role="menuitem" data-ve-nav class="ve-menu-item" href="?page=review"><i class="bi bi-inboxes"></i><span>Review queue</span>
                    <?php if ($pending): ?><em class="ve-badge"><?= count($pending) ?></em><?php endif; ?></a>
                <?php endif; ?>
            </div>
        </details>

        <?php if ($subs): ?>
        <select class="ve-sub" id="veSub" aria-label="Choose what to preview">
            <?php foreach ($subs as $s): ?><option value="<?= ce_e($s['url']) ?>"<?= $s['url'] === $pageUrl ? ' selected' : '' ?>><?= ce_e($s['label']) ?></option><?php endforeach; ?>
        </select>
        <?php endif; ?>

        <button type="button" class="ve-tool" id="veOutlineBtn" hidden title="Jump to a section of this page"><i class="bi bi-list-nested"></i><span>Sections</span></button>

        <div class="ve-devices" role="group" aria-label="Preview size">
            <button type="button" class="active" data-device="desktop" aria-pressed="true" title="Computer"><i class="bi bi-display"></i></button>
            <button type="button" data-device="tablet" aria-pressed="false" title="Tablet"><i class="bi bi-tablet"></i></button>
            <button type="button" data-device="mobile" aria-pressed="false" title="Phone"><i class="bi bi-phone"></i></button>
        </div>

        <span class="ve-status ve-status-ok" id="veStatus" role="status" aria-live="polite"></span>

        <div class="ve-actions">
            <?php if ($activeSlug === 'programs' && $canManagePages): ?>
            <button type="button" class="ve-tool" id="veAddProgramBtn" title="Create a college or Graduate School program"><i class="bi bi-plus-circle"></i><span>Add Program</span></button>
            <?php endif; ?>
            <?php if ($VE['page']['publicUrl'] !== ''): ?>
            <a class="ve-tool" href="<?= ce_e($VE['page']['publicUrl']) ?>" target="_blank" rel="noopener" title="Open the live page in a new tab"><i class="bi bi-box-arrow-up-right"></i><span>View live</span></a>
            <?php endif; ?>
            <?php if ($hasDrawer): ?>
            <button type="button" class="ve-tool" id="veSettingsBtn" title="<?= ce_e($drawerTitle) ?>"><i class="bi bi-sliders"></i><span>More settings</span></button>
            <?php endif; ?>
            <button type="button" class="ve-tool" id="vePageOpts" hidden title="Visibility, colour and other options for this page"><i class="bi bi-gear"></i><span>Page options</span></button>
            <?php if ($veEmbed): ?>
            <button type="button" class="ve-tool" id="vePostsBtn" title="Open the list of news, announcements and events here"><i class="bi bi-megaphone"></i><span>Posts</span></button>
            <?php endif; ?>
            <button type="button" class="ve-tool" id="vePreviewBtn" title="See the page as visitors see it (no editing outlines)"><i class="bi bi-eye"></i><span>Preview</span></button>
            <button type="button" class="ve-tool ve-tool-danger" id="veCancel" hidden title="Throw away the changes that are not saved yet"><i class="bi bi-x-circle"></i><span>Cancel</span></button>
            <button type="button" class="btn btn-outline-secondary ve-btn" id="veSecondary" hidden></button>
            <button type="button" class="btn-gnc ve-btn" id="vePrimary" hidden></button>
        </div>
    </header>

    <main class="ve-canvas" id="veCanvas">
        <div class="ve-stage" id="veStage" data-device="desktop">
            <iframe id="veFrame" class="ve-frame" title="Website preview. Click anything to edit it."></iframe>
        </div>
        <div class="ve-loader" id="veLoader"><div class="ve-spin"></div><span>Opening the page…</span></div>
        <div class="ve-empty-state" id="veEmpty" hidden>
            <i class="bi bi-collection-play"></i>
            <strong>The homepage has no slides yet</strong>
            <span>Add a picture or video to start the slideshow.</span>
            <button type="button" class="btn-gnc" id="veAddFirst"><i class="bi bi-plus-lg"></i> Add the first slide</button>
        </div>
    </main>

    <div class="ve-dock" id="veDock" hidden></div>
    <div class="ve-tb" id="veTb" hidden role="toolbar" aria-label="Edit the selected item"></div>
    <div class="ve-pop" id="vePop" hidden></div>
    <input type="file" id="veFile" hidden>

    <?php if ($hasPanel): ?>
    <div class="ve-backdrop" data-ve-drawer-close></div>
    <aside class="ve-drawer" id="veDrawer" hidden aria-label="<?= ce_e($hasDrawer ? $drawerTitle : 'News, announcements and events') ?>">
        <div class="ve-drawer-head">
            <strong id="veDrawerTitle"><?= ce_e($hasDrawer ? $drawerTitle : 'News, announcements and events') ?></strong>
            <button type="button" class="ve-x" data-ve-drawer-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <?php if ($hasDrawer): ?>
        <div class="ve-drawer-body" id="veDrawerBody">
            <p class="ve-drawer-note"><i class="bi bi-info-circle"></i> Most things are edited by clicking them on the page. This panel holds the extra settings. Changes here are saved with its own buttons and go live when saved.</p>
            <?php renderAdminComponent(__DIR__ . '/../components/' . $drawerFile); ?>
        </div>
        <?php endif; ?>
        <?php if ($veEmbed): ?>
        <div class="ve-drawer-embed" id="veEmbedWrap" <?= $hasDrawer ? 'hidden' : '' ?>>
            <iframe id="veEmbed" title="News, announcements and events editor" data-src="about:blank"></iframe>
        </div>
        <?php endif; ?>
    </aside>
    <?php endif; ?>
</div>

<script>
    // legacy config the existing managers read
    window.SLIDE_EDITOR_CONFIG = { csrfToken: <?= json_encode($csrfToken) ?>, ajaxUrl: '/admin/action/page-content-handler.php' };
    window.CE_CONFIG = { csrf: <?= json_encode($csrfToken) ?>, handler: '/admin/action/page-review-handler.php',
        canPublish: <?= $canManagePages ? 'true' : 'false' ?>, canApprove: <?= $canApprove ? 'true' : 'false' ?>, reviewReady: <?= $reviewReady ? 'true' : 'false' ?> };
    window.VE_CONFIG = <?= json_encode($VE, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?>;
    document.body.classList.add('ve-mode');
<?php if ($activeSlug === 'programs'): ?>
    // Programs: "Edit details" on the Facilities carousel (in the preview) opens that program's Facilities tab here.
    // (Adding, renaming, reordering and deleting slides is done from the strip under the page: assets/js/content-edit.js)
    (function () {
        var frame = document.getElementById('veFrame');
        function openFacilities(slug) {
            var drawer = document.getElementById('veDrawer'), btn = document.getElementById('veSettingsBtn');
            if (drawer && drawer.hidden && btn) btn.click();
            var cards = Array.prototype.slice.call(document.querySelectorAll('.pgm-card'));
            var card = cards.filter(function (c) { return c.getAttribute('data-slug') === slug; })[0];
            if (!card) return;
            var head = card.querySelector('.pgm-head');
            if (head && head.getAttribute('aria-expanded') !== 'true') head.click();
            var tab = card.querySelector('.pgm-tab[data-tab="facility"]');
            if (tab) tab.click();
            try { card.scrollIntoView({ block: 'start' }); } catch (e) {}
        }
        window.addEventListener('message', function (ev) {
            if (ev.origin !== window.location.origin) return;
            if (!frame || ev.source !== frame.contentWindow) return;
            var d = ev.data;
            if (!d || d.source !== 'gnc-program-preview' || d.action !== 'facilities' || d.mode !== 'edit') return;
            openFacilities(String(d.slug || ''));
        });
    })();
<?php endif; ?>
    (function () {   // the page list closes when you pick something or click elsewhere
        var m = document.getElementById('vePageMenu');
        document.addEventListener('mousedown', function (e) { if (m && m.open && !m.contains(e.target)) m.removeAttribute('open'); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && m) m.removeAttribute('open'); });
    })();
</script>
<script src="/assets/js/editor-feedback.js?v=<?= substr(hash_file('sha256', __DIR__ . '/../assets/js/editor-feedback.js'),0,12) ?>"></script>
<script src="/assets/js/department-card-order.js?v=<?= substr(hash_file('sha256', __DIR__ . '/../assets/js/department-card-order.js'),0,12) ?>"></script>
<script src="/assets/js/content-edit.js?v=<?= substr(hash_file('sha256', __DIR__ . '/../assets/js/content-edit.js'),0,12) ?>"></script>

<?php else: ?>
<div class="ce-shell">

    <!-- ══════════════════ Navigation ══════════════════ -->
    <button type="button" class="ce-nav-toggle" id="ceNavToggle" aria-expanded="false" aria-controls="ceNav">
        <i class="bi bi-list"></i> <span>Menu: <strong id="ceNavCurrent">Content Editor</strong></span>
    </button>

    <nav class="ce-nav" id="ceNav" aria-label="Content Editor">
        <div class="ce-nav-group">
            <div class="ce-nav-label">Overview</div>
            <a class="ce-nav-link" href="?page=hub" data-panel="hub"><i class="bi bi-grid-1x2"></i><span>All content</span></a>
            <?php if ($canApprove): ?>
            <a class="ce-nav-link" href="?page=review" data-panel="review"><i class="bi bi-inboxes"></i><span>Review queue</span>
                <span class="ce-nav-badge" id="cePendingBadge" <?= $pending ? '' : 'hidden' ?>><?= count($pending) ?></span></a>
            <?php endif; ?>
        </div>

        <?php if ($canManagePages): ?>
        <div class="ce-nav-group">
            <div class="ce-nav-label">Homepage</div>
            <a class="ce-nav-link" href="?page=home" data-panel="home"><i class="bi bi-window-desktop"></i><span>Slideshow &amp; president</span></a>
        </div>
        <?php endif; ?>

        <?php if ($textPages): ?>
        <div class="ce-nav-group">
            <div class="ce-nav-label">Pages</div>
            <?php foreach ($textPages as $p): ?>
            <a class="ce-nav-link" href="?page=<?= ce_e(rawurlencode($p['slug'])) ?>" data-panel="<?= ce_e($p['slug']) ?>">
                <i class="bi bi-file-earmark-text"></i><span><?= ce_e($p['title']) ?></span>
                <span class="ce-nav-dot" data-dot-for="<?= ce_e($p['slug']) ?>" hidden title="Unsaved changes"></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($customPages): ?>
        <div class="ce-nav-group">
            <div class="ce-nav-label">Academics &amp; people</div>
            <?php foreach ($customPages as $p):
                $icon = $p['slug'] === 'administration' ? 'bi-people' : ($p['slug'] === 'programs' ? 'bi-mortarboard' : 'bi-building'); ?>
            <a class="ce-nav-link" href="?page=<?= ce_e(rawurlencode($p['slug'])) ?>" data-panel="<?= ce_e($p['slug']) ?>">
                <i class="bi <?= $icon ?>"></i><span><?= ce_e($p['title']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($canManagePages): ?>
        <div class="ce-nav-group">
            <div class="ce-nav-label">Student life</div>
            <a class="ce-nav-link" href="?page=student-organizations"><i class="bi bi-people"></i><span>Student Organizations Management</span></a>
            <a class="ce-nav-link" href="?page=student-achievements"><i class="bi bi-trophy"></i><span>Student Achievements</span></a>
            <a class="ce-nav-link" href="?page=athletics-sports"><i class="bi bi-dribbble"></i><span>Athletics and Sports</span></a>
        </div>
        <?php endif; ?>

        <?php if ($otherModules): ?>
        <div class="ce-nav-group">
            <div class="ce-nav-label">Other content</div>
            <?php foreach ($otherModules as $m): ?>
            <a class="ce-nav-link ce-nav-ext" href="<?= ce_e($m['href']) ?>"><i class="bi <?= ce_e($m['icon']) ?>"></i><span><?= ce_e($m['label']) ?></span></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="ce-nav-role">
            <strong><?= $canManagePages ? 'You can publish' : ($canDraftPages ? 'You can submit for approval' : 'Your own content') ?></strong>
            <span><?= $canManagePages
                ? 'Your changes go live when you press Publish.'
                : ($canDraftPages ? 'Your changes go live after an administrator approves them.' : 'Page text is edited by editors and administrators. You can manage your own posts.') ?></span>
        </div>
    </nav>

    <div class="ce-main" id="ceMain">

<?php if ($noAccess): ?>
        <div class="data-card">
            <div class="empty-state">
                <i class="bi bi-lock-fill"></i>
                <p>You don't have permission to edit website content. Ask a Super Admin if you need access.</p>
            </div>
        </div>
<?php else: ?>

<?php if (!$reviewReady && $canDraftPages): ?>
        <div class="alert alert-warning ce-setup" role="alert">
            <strong>Approval is not switched on yet.</strong>
            Your developer needs to run <code>sql/page-approval-migration.sql</code> once. Until then, editors cannot submit for approval; saving drafts and publishing still work.
        </div>
<?php endif; ?>

        <!-- ══════════════════ All content ══════════════════ -->
        <div class="page-panel" id="page-panel-hub" style="<?= $activeSlug === 'hub' ? '' : 'display:none' ?>">
            <div class="page-header">
                <div>
                    <h1>Content Editor</h1>
                    <p>Everything you can edit on the website, in one list. Search, filter, then press <strong>Edit</strong>.</p>
                </div>
                <?php if ($createLinks): ?>
                <div class="dropdown">
                    <button class="btn-gnc-gold dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-plus-lg"></i> Create new</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($createLinks as $c): ?><li><a class="dropdown-item" href="<?= ce_e($c[0]) ?>"><i class="bi <?= ce_e($c[2]) ?> me-2"></i><?= ce_e($c[1]) ?></a></li><?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>

            <div class="ce-stats" role="group" aria-label="Filter by status">
                <button type="button" class="ce-stat" data-status="" aria-pressed="true"><b id="ceStatAll"><?= $hubCount['all'] ?></b><span>All content</span></button>
                <button type="button" class="ce-stat ce-stat-live" data-status="live" aria-pressed="false"><b><?= $hubCount['live'] ?></b><span>Live</span></button>
                <button type="button" class="ce-stat ce-stat-draft" data-status="draft" aria-pressed="false"><b><?= $hubCount['draft'] ?></b><span>Drafts</span></button>
                <button type="button" class="ce-stat ce-stat-pending" data-status="pending" aria-pressed="false"><b><?= $hubCount['pending'] ?></b><span>Waiting for approval</span></button>
                <button type="button" class="ce-stat ce-stat-rejected" data-status="rejected" aria-pressed="false"><b><?= $hubCount['rejected'] ?></b><span>Changes requested</span></button>
            </div>

            <div class="data-card ce-list-card">
                <div class="ce-toolbar">
                    <div class="ce-search">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input type="search" id="ceSearch" class="form-control" placeholder="Search by title…" aria-label="Search content">
                    </div>
                    <select id="ceType" class="form-select" aria-label="Filter by type">
                        <option value="">All types</option>
                        <?php foreach ($hubTypes as $t): ?><option value="<?= ce_e($t) ?>"><?= ce_e($t) ?></option><?php endforeach; ?>
                    </select>
                    <select id="ceStatus" class="form-select" aria-label="Filter by status">
                        <option value="">Any status</option>
                        <?php foreach ($statusLabel as $k => $v): ?><option value="<?= $k ?>"><?= ce_e($v) ?></option><?php endforeach; ?>
                    </select>
                    <select id="ceSort" class="form-select" aria-label="Sort by">
                        <option value="attention">Needs attention first</option>
                        <option value="modified">Recently modified</option>
                        <option value="title">Title A–Z</option>
                        <option value="type">Type</option>
                    </select>
                </div>

                <div class="ce-table-wrap">
                    <table class="ce-table" id="ceTable">
                        <thead>
                            <tr>
                                <th scope="col"><button type="button" class="ce-th" data-sort="title">Title</button></th>
                                <th scope="col"><button type="button" class="ce-th" data-sort="type">Type</button></th>
                                <th scope="col"><button type="button" class="ce-th" data-sort="attention">Status</button></th>
                                <th scope="col"><button type="button" class="ce-th" data-sort="modified">Last modified</button></th>
                                <th scope="col" class="ce-th-act"><span class="visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($hub as $h): ?>
                            <tr class="ce-row" data-title="<?= ce_e(mb_strtolower($h['title'] . ' ' . $h['note'])) ?>"
                                data-name="<?= ce_e(mb_strtolower($h['title'])) ?>" data-type="<?= ce_e($h['type']) ?>"
                                data-status="<?= ce_e($h['status']) ?>" data-rank="<?= $rank[$h['status']] ?>" data-ts="<?= (int)$h['ts'] ?>">
                                <td data-label="Title">
                                    <a class="ce-row-title" href="<?= ce_e($h['href']) ?>" data-open="<?= ce_e($h['open']) ?>"><?= ce_e($h['title']) ?></a>
                                    <?php if ($h['note'] !== ''): ?><small><?= ce_e($h['note']) ?></small><?php endif; ?>
                                </td>
                                <td data-label="Type"><?= ce_e($h['type']) ?></td>
                                <td data-label="Status"><span class="ce-badge ce-badge-<?= ce_e($h['status']) ?>"><?= ce_e($statusLabel[$h['status']]) ?></span></td>
                                <td data-label="Last modified"><?= $h['ts'] ? ce_e(date('M j, Y', $h['ts'])) : '<span class="text-muted">—</span>' ?></td>
                                <td class="ce-row-act">
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= ce_e($h['href']) ?>" data-open="<?= ce_e($h['open']) ?>"><i class="bi bi-pencil"></i> Edit</a>
                                    <?php if ($h['where']): ?><a class="ce-ext" href="<?= ce_e($h['where']) ?>" target="_blank" rel="noopener" title="See it on the website" aria-label="View <?= ce_e($h['title']) ?> on the website"><i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="empty-state ce-none" id="ceNone" hidden>
                        <i class="bi bi-search"></i>
                        <p>Nothing matches. Try a different word, or <button type="button" class="btn btn-link p-0 align-baseline" id="ceReset">clear the filters</button>.</p>
                    </div>
                </div>
                <div class="ce-count-line" id="ceShowing" aria-live="polite"></div>
            </div>

            <?php if ($otherModules): ?>
            <div class="ce-others">
                <h2>Also manage</h2>
                <div class="ce-others-grid">
                    <?php foreach ($otherModules as $m): ?>
                    <a href="<?= ce_e($m['href']) ?>"><i class="bi <?= ce_e($m['icon']) ?>"></i><?= ce_e($m['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

<?php if ($canApprove): ?>
        <!-- ══════════════════ Review queue ══════════════════ -->
        <div class="page-panel" id="page-panel-review" style="<?= $activeSlug === 'review' ? '' : 'display:none' ?>">
            <div class="page-header">
                <div>
                    <h1>Review queue</h1>
                    <p>Changes sent in by editors. Nothing here is on the website yet. <strong>Approve</strong> to publish it, or <strong>Request changes</strong> to send it back with a note.</p>
                </div>
            </div>

            <?php if (!$reviewReady): ?>
                <div class="alert alert-warning">Approval is not switched on yet. Run <code>sql/page-approval-migration.sql</code> once.</div>
            <?php endif; ?>

            <?php if (hasPermission('manage_approvals')): ?>
            <div class="alert alert-light border ce-approvals-note"><i class="bi bi-info-circle"></i> This list is for <strong>page text</strong> only. News, announcements and events waiting for approval are on the <a href="/admin/approvals/">Approvals page</a>.</div>
            <?php endif; ?>

            <div id="ceQueue">
            <?php foreach ($pending as $q): ?>
                <article class="data-card ce-review" data-page="<?= ce_e($q['page_slug']) ?>" data-section="<?= ce_e($q['section_key']) ?>">
                    <header class="ce-review-head">
                        <div>
                            <h2><?= ce_e($q['page_title']) ?> <span>›</span> <?= ce_e($q['section_title']) ?></h2>
                            <small>Sent by <?= ce_e(trim((string)$q['submitted_by_name']) ?: 'an editor') ?> · <?= ce_e(ce_when($q['submitted_at'])) ?></small>
                        </div>
                        <a class="btn btn-sm btn-outline-secondary" href="?page=<?= ce_e(rawurlencode($q['page_slug'])) ?>&amp;section=<?= ce_e(rawurlencode($q['section_key'])) ?>" data-open="<?= ce_e($q['page_slug']) ?>" data-section-open="<?= ce_e($q['section_key']) ?>">Open in editor</a>
                    </header>
                    <div class="ce-compare">
                        <div>
                            <div class="ce-compare-label">On the website now</div>
                            <div class="ce-prose ce-prose-old"><?= sectionToEditorHtml($q['content']) ?: '<em class="text-muted">(empty)</em>' ?></div>
                        </div>
                        <div>
                            <div class="ce-compare-label ce-compare-new">Proposed change</div>
                            <div class="ce-prose" data-warning-field data-warning-label="<?= ce_e($q['section_title']) ?>" data-warning-value="<?= ce_e((string)$q['draft_content']) ?>"><?= sectionToEditorHtml($q['draft_content']) ?: '<em class="text-muted">(empty)</em>' ?></div>
                        </div>
                    </div>
                    <div class="ce-review-actions">
                        <label class="visually-hidden" for="note-<?= (int)$q['section_id'] ?>">Note to the editor</label>
                        <input type="text" id="note-<?= (int)$q['section_id'] ?>" class="form-control ce-review-note" maxlength="500" placeholder="Note to the editor (needed when you request changes)">
                        <button type="button" class="btn btn-outline-secondary" data-review="reject"><i class="bi bi-arrow-counterclockwise"></i> Request changes</button>
                        <button type="button" class="btn-gnc-gold" data-review="approve"><i class="bi bi-check2-circle"></i> Approve &amp; publish</button>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
            <div class="data-card" id="ceQueueEmpty" <?= $pending ? 'hidden' : '' ?>>
                <div class="empty-state"><i class="bi bi-check2-all"></i><p>All caught up. Nothing is waiting for approval.</p></div>
            </div>
        </div>
<?php endif; ?>

<?php endif; /* has any permission */ ?>

    </div><!-- /#ceMain -->
</div><!-- /.ce-shell -->

<script>
    window.CE_CONFIG = {
        csrf: <?= json_encode($csrfToken) ?>,
        handler: '/admin/action/page-review-handler.php',
        canPublish: <?= $canManagePages ? 'true' : 'false' ?>,
        canApprove: <?= $canApprove ? 'true' : 'false' ?>,
        reviewReady: <?= $reviewReady ? 'true' : 'false' ?>
    };
</script>

<script>
// ══════════════════ Content Editor: navigation, content list, rich-text editor, review ══════════════════
(function () {
    'use strict';
    var CFG = window.CE_CONFIG || {};
    var $  = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

    // ══════════════════ Notifications ══════════════════
    var toastBox = $('#ceToasts');
    function toast(message, type) {
        if (!toastBox) return;
        var t = document.createElement('div');
        t.className = 'ce-toast ce-toast-' + (type === 'error' ? 'error' : 'ok');
        t.setAttribute('role', type === 'error' ? 'alert' : 'status');
        var icon = document.createElement('i');
        icon.className = 'bi ' + (type === 'error' ? 'bi-exclamation-octagon-fill' : 'bi-check-circle-fill');
        var span = document.createElement('span');
        span.textContent = message;
        var x = document.createElement('button');
        x.type = 'button'; x.className = 'ce-toast-x'; x.setAttribute('aria-label', 'Dismiss'); x.textContent = '×';
        t.appendChild(icon); t.appendChild(span); t.appendChild(x);
        toastBox.appendChild(t);
        var kill = function () { if (t.parentNode) t.parentNode.removeChild(t); };
        x.addEventListener('click', kill);
        setTimeout(kill, type === 'error' ? 9000 : 4500);
    }

    // ══════════════════ Navigation between panels ══════════════════
    var main = $('#ceMain');
    var navLinks = $$('.ce-nav-link[data-panel]');
    var navEl = $('#ceNav'), navToggle = $('#ceNavToggle');
    function panelEl(slug) { return document.getElementById('page-panel-' + slug); }

    function showPanel(slug, opts) {
        opts = opts || {};
        if (!panelEl(slug)) return false;
        $$('.page-panel', main).forEach(function (p) {
            if (p.parentNode !== main) return;
            p.style.display = (p.id === 'page-panel-' + slug) ? '' : 'none';
        });
        var label = '';
        navLinks.forEach(function (a) {
            var on = a.getAttribute('data-panel') === slug;
            a.classList.toggle('active', on);
            if (on) { a.setAttribute('aria-current', 'page'); label = (a.querySelector('span') || a).textContent; }
            else a.removeAttribute('aria-current');
        });
        var cur = $('#ceNavCurrent'); if (cur && label) cur.textContent = label;
        if (navEl) navEl.classList.remove('open');
        if (navToggle) navToggle.setAttribute('aria-expanded', 'false');
        if (window.history && history.replaceState) {
            var u = new URL(window.location.href);
            u.searchParams.set('page', slug);
            u.hash = '';
            history.replaceState({}, '', u);
        }
        if (opts.scroll !== false) window.scrollTo({ top: 0 });
        return true;
    }

    function activateSection(panel, key) {
        if (!panel) return;
        $$('.ce-sec-tab', panel).forEach(function (t) {
            var on = t.getAttribute('data-section-tab') === key;
            t.classList.toggle('active', on); t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        $$('.ce-editor', panel).forEach(function (ed) { ed.hidden = ed.getAttribute('data-section') !== key; });
    }

    // open something from a link: switches panel without reloading (so unsaved text is never lost)
    function openFrom(a, ev) {
        var slug = a.getAttribute('data-panel') || a.getAttribute('data-open');
        if (!slug || !panelEl(slug)) return;
        ev.preventDefault();
        showPanel(slug);
        var sec = a.getAttribute('data-section-open');
        if (sec) activateSection(panelEl(slug), sec);
        var href = a.getAttribute('href') || '';
        var m = /#(program-\d+)$/.exec(href);
        if (m) {
            var card = document.getElementById(m[1]);
            if (card) {
                var head = card.querySelector('.pgm-head');
                if (head && head.getAttribute('aria-expanded') !== 'true') head.click();
                card.scrollIntoView({ block: 'start' });
            }
        }
    }
    document.addEventListener('click', function (ev) {
        var a = ev.target.closest ? ev.target.closest('a[data-panel], a[data-open]') : null;
        if (a && main && (main.contains(a) || (navEl && navEl.contains(a)))) openFrom(a, ev);
    });
    if (navToggle) navToggle.addEventListener('click', function () {
        var open = navEl.classList.toggle('open');
        navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    $$('.ce-sec-tab').forEach(function (t) {
        t.addEventListener('click', function () {
            activateSection(t.closest('.page-panel'), t.getAttribute('data-section-tab'));
        });
    });
    (function () {
        var cur = (new URL(window.location.href)).searchParams.get('page') || 'hub';
        if (!panelEl(cur)) cur = 'hub';
        showPanel(cur, { scroll: false });
    })();

    // ══════════════════ Content list: search / filter / sort ══════════════════
    (function () {
        var table = $('#ceTable'); if (!table) return;
        var tbody = table.tBodies[0];
        var rows = $$('.ce-row', tbody);
        var q = $('#ceSearch'), fType = $('#ceType'), fStatus = $('#ceStatus'), fSort = $('#ceSort');
        var none = $('#ceNone'), showing = $('#ceShowing');
        var stats = $$('.ce-stat');

        function cmpName(a, b) { return a.dataset.name < b.dataset.name ? -1 : a.dataset.name > b.dataset.name ? 1 : 0; }
        var sorters = {
            attention: function (a, b) { return (a.dataset.rank - b.dataset.rank) || (b.dataset.ts - a.dataset.ts) || cmpName(a, b); },
            modified:  function (a, b) { return (b.dataset.ts - a.dataset.ts) || cmpName(a, b); },
            title:     cmpName,
            type:      function (a, b) { return a.dataset.type < b.dataset.type ? -1 : a.dataset.type > b.dataset.type ? 1 : cmpName(a, b); }
        };
        function apply() {
            var term = (q.value || '').trim().toLowerCase();
            var shown = 0;
            rows.sort(sorters[fSort.value] || sorters.attention).forEach(function (r) {
                var ok = (!term || r.dataset.title.indexOf(term) !== -1) &&
                         (!fType.value || r.dataset.type === fType.value) &&
                         (!fStatus.value || r.dataset.status === fStatus.value);
                r.hidden = !ok;
                if (ok) shown++;
                tbody.appendChild(r);
            });
            table.hidden = shown === 0;
            if (none) none.hidden = shown !== 0;
            if (showing) showing.textContent = 'Showing ' + shown + ' of ' + rows.length;
            stats.forEach(function (s) { s.setAttribute('aria-pressed', (s.getAttribute('data-status') || '') === fStatus.value ? 'true' : 'false'); });
            $$('.ce-th', table).forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-sort') === fSort.value); });
        }
        [q, fType, fStatus, fSort].forEach(function (el) { el.addEventListener(el === q ? 'input' : 'change', apply); });
        stats.forEach(function (s) { s.addEventListener('click', function () { fStatus.value = s.getAttribute('data-status') || ''; apply(); }); });
        $$('.ce-th', table).forEach(function (b) { b.addEventListener('click', function () { fSort.value = b.getAttribute('data-sort'); apply(); }); });
        var reset = $('#ceReset');
        if (reset) reset.addEventListener('click', function () { q.value = ''; fType.value = ''; fStatus.value = ''; apply(); q.focus(); });
        apply();
    })();

    function post(action, fields) {
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('csrf_token', CFG.csrf || '');
        Object.keys(fields).forEach(function (k) { body.set(k, fields[k]); });
        return fetch(CFG.handler, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (res) {
            return res.json().catch(function () {
                return { success: false, error: 'The server sent an unexpected reply (code ' + res.status + '). Please reload the page and try again.' };
            });
        }, function () {
            return { success: false, error: 'We could not reach the server. Please check your internet connection and try again.' };
        });
    }
    // ══════════════════ Review queue ══════════════════
    function updatePending(n) {
        var b = $('#cePendingBadge');
        if (b) { b.textContent = n; b.hidden = n < 1; }
        var empty = $('#ceQueueEmpty'); if (empty) empty.hidden = n > 0 || !!$('.ce-review');
    }
    $$('.ce-review').forEach(function (card) {
        card.addEventListener('click', async function (e) {
            var b = e.target.closest('[data-review]'); if (!b || b.disabled) return;
            var what = b.getAttribute('data-review');
            var note = $('.ce-review-note', card).value.trim();
            if (what === 'reject' && !note) { toast('Please write a short note so the editor knows what to change.', 'error'); $('.ce-review-note', card).focus(); return; }
            if (what === 'approve' && !await window.AdminDialogs.confirm('Approve and publish this change?\n\nIt will go live on the website.')) return;
            $$('button', card).forEach(function (x) { x.disabled = true; });
            post(what, { page: card.getAttribute('data-page'), section_key: card.getAttribute('data-section'), note: note }).then(function (res) {
                if (res && res.success) {
                    toast(res.message || 'Done.', 'ok');
                    var ed = $('.ce-editor[data-page="' + card.getAttribute('data-page') + '"][data-section="' + card.getAttribute('data-section') + '"]');
                    if (ed && ed.__ce) ed.__ce.applyState(res, true);
                    card.parentNode.removeChild(card);
                    updatePending(typeof res.pending_count === 'number' ? res.pending_count : $$('.ce-review').length);
                } else {
                    $$('button', card).forEach(function (x) { x.disabled = false; });
                    toast((res && res.error) || 'Something went wrong. Please try again.', 'error');
                }
            });
        });
    });

})();
</script>
<?php endif; /* editor / list */ ?>

<script src="../assets/js/photo-check.js?v=<?= (int)filemtime(__DIR__ . '/../assets/js/photo-check.js') ?>"></script>
<?php include __DIR__ . '/../components/footer-admin.php'; ?>
