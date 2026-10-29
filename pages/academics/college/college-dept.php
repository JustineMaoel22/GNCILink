<?php
/**
 * college-departments.php — public "College Departments" page.
 *
 * FULLY DYNAMIC: every card on this page comes from the `departments`
 * table (managed in the admin panel under Content Editor → College
 * Departments). Nothing is hardcoded — creating, editing, archiving or
 * deleting a department in the admin updates this page automatically.
 */
require_once __DIR__ . '/../../../components/department-public.php';

// Content Editor preview (?ce_preview=1, a signed-in manager): hidden departments are listed too (dimmed, so they can be restored)
// and an "Add a department" tile appears. Visitors never see either.
$cePreview = cePreviewCanManage();

/**
 * Where a department card links to.
 *
 * ONE reusable dynamic page for every department — including departments
 * that don't exist yet. No per-department .php files, no hardcoded slugs.
 */
function collegeDeptPublicUrl(array $dept): string {
    $slug = trim((string)($dept['slug'] ?? ''));
    if ($slug === '') {
        return '/pages/academics/college/college-departments.php';
    }
    return '/pages/academics/college/college-department/' . rawurlencode($slug);
}

// Only college departments are shown, including in the editor preview.
// Archived departments are hidden from visitors; rows without a slug are
// always left out. Unsorted new departments follow the saved listing order.
$publicDepartments = array_values(array_filter(getDepartments(), function ($d) use ($cePreview) {
    return publicDepartmentIsCollege($d)
        && ($cePreview || empty($d['archived_at']))
        && trim((string)($d['slug'] ?? '')) !== '';
}));
$publicDepartments = departmentApplyOrder($publicDepartments, departmentStoredOrder('college'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College Departments</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&amp;display=block">
    <link href="/assets/css/col-dept-style.css" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
    <style>
        /* Optional per-department accent. The stylesheet drives the button
           background, hover and focus outline from --accent, so the card just
           overrides that one variable (set inline from the admin's card color).
           The title, text and the gold underline are deliberately NOT touched,
           so changing a card's color never affects them. */

        /* Shown only when the admin has not created any departments yet. */
        .cd-grid-empty {
            max-width: 640px;
            margin: 0 auto;
            padding: 64px 24px;
            text-align: center;
            color: var(--gnc-text-gray, #6b6b66);
        }
        .cd-grid-empty i { font-size: 2.4rem; color: var(--gnc-green-light, #1F5E2C); }
        .cd-grid-empty h3 {
            font-family: 'Noto Serif', serif;
            color: var(--gnc-green-light, #1F5E2C);
            margin: 12px 0 6px;
            font-size: 1.25rem;
        }
        .cd-grid-empty p { margin: 0; font-size: .9rem; }
    </style>
    <script>document.documentElement.classList.add('js-anim');</script>
</head>
<body>
    <?php $activeSection = 'academics'; ?>
    <?php include_once __DIR__ . '/../../../components/navbar.php'; ?>

    <section class="gnc-college-programs">

        <!-- Hero / Page Header -->
        <div class="cp-hero">
            <div class="cp-hero-inner">
                <nav class="cp-breadcrumb" aria-label="breadcrumb" data-reveal>
                    <a class="gnc-breadcrumb-home" href="/">Home</a><i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>Academics</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="cp-current" aria-current="page">College Departments</span>
                </nav>
                <h1 data-reveal style="--d: 100ms"<?= basicEducationMark('college-index', 'text_01') ?>><?= basicEducationText('college-index', 'text_01') ?></h1>
                <div class="cms-rich" data-reveal style="--d: 200ms"<?= basicEducationMark('college-index', 'text_02') ?>><?= basicEducationText('college-index', 'text_02') ?></div>
            </div>
        </div>

        <!-- Feature Highlights -->
        <div class="cp-features">
            <div class="cp-features-box">

                <div class="cp-feature" data-reveal style="--d: 300ms">
                    <?= academicFeatureIcon('college-index', 'icon_01', 'cp-feature-icon') ?>
                    <div class="cp-feature-text">
                        <h3<?= basicEducationMark('college-index', 'text_03') ?>><?= basicEducationText('college-index', 'text_03') ?></h3>
                        <div class="cms-rich"<?= basicEducationMark('college-index', 'text_04') ?>><?= basicEducationText('college-index', 'text_04') ?></div>
                    </div>
                </div>

                <div class="cp-feature" data-reveal style="--d: 400ms">
                    <?= academicFeatureIcon('college-index', 'icon_02', 'cp-feature-icon') ?>
                    <div class="cp-feature-text">
                        <h3<?= basicEducationMark('college-index', 'text_05') ?>><?= basicEducationText('college-index', 'text_05') ?></h3>
                        <div class="cms-rich"<?= basicEducationMark('college-index', 'text_06') ?>><?= basicEducationText('college-index', 'text_06') ?></div>
                    </div>
                </div>

                <div class="cp-feature" data-reveal style="--d: 500ms">
                    <?= academicFeatureIcon('college-index', 'icon_03', 'cp-feature-icon') ?>
                    <div class="cp-feature-text">
                        <h3<?= basicEducationMark('college-index', 'text_07') ?>><?= basicEducationText('college-index', 'text_07') ?></h3>
                        <div class="cms-rich"<?= basicEducationMark('college-index', 'text_08') ?>><?= basicEducationText('college-index', 'text_08') ?></div>
                    </div>
                </div>

                <div class="cp-feature" data-reveal style="--d: 600ms">
                    <?= academicFeatureIcon('college-index', 'icon_04', 'cp-feature-icon') ?>
                    <div class="cp-feature-text">
                        <h3<?= basicEducationMark('college-index', 'text_09') ?>><?= basicEducationText('college-index', 'text_09') ?></h3>
                        <div class="cms-rich"<?= basicEducationMark('college-index', 'text_10') ?>><?= basicEducationText('college-index', 'text_10') ?></div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Colleges Grid — rendered entirely from the `departments` table.
             Add/edit/archive/delete departments in the admin and this grid
             updates itself; there is no hardcoded list anywhere. -->
        <?php if (empty($publicDepartments)): ?>
        <div class="cd-colleges">
            <div class="cd-grid-empty" data-reveal="scale">
                <i class="bi bi-diagram-3"></i>
                <h3>No departments to show yet</h3>
                <p>Departments published in the admin panel will appear here automatically.</p>
            </div>
            <?php if ($cePreview): ?>
            <div class="ve-add-tile" data-ve-add="department" style="margin:1.5rem auto;max-width:420px"><span style="font-size:28px;line-height:1">+</span><span>Add the first department</span></div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="cd-colleges">
            <div class="cd-grid"<?= ceManageMark('data-ve-department-grid="college"') ?>>
                <?php foreach ($publicDepartments as $index => $dept):
                    $cardName = trim((string)$dept['dept_name']);
                    $logoUrl  = $dept['logo_url'] ?? null;
                    // Card text always describes the department itself: the first
                    // few sentences of "About this department" (or the short
                    // description), cut with "..." when it doesn't fit.
                    $desc     = publicDepartmentCardText($dept);

                    // Per-department CSS modifier from the slug — matches the
                    // existing .cd-card--cased / --coe / ... theme variants for
                    // current departments and does nothing extra for new ones.
                    $modifier = preg_replace('/[^a-z0-9]+/', '-', strtolower((string)$dept['slug']));

                    // Optional accent color (admin-set theme_color, #RRGGBB only).
                    $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($dept['theme_color'] ?? ''))
                            ? $dept['theme_color'] : '';

                    // ONE style attribute only. A second style="" on the same tag
                    // is ignored by the browser, which is why the color never applied.
                    $cardStyle = '--d: ' . (($index % 3) * 100) . 'ms';
                    if ($accent !== '') {
                        $cardStyle .= '; --accent: ' . $accent;
                    }
                ?>
                <a class="cd-card cd-card--<?= htmlspecialchars($modifier) ?><?= ($cePreview && !empty($dept['archived_at'])) ? ' ve-hidden-card' : '' ?>"
                   <?= ceManageMark('data-ve-department-card="' . (int)$dept['dept_id'] . '"') ?>
                   data-reveal="scale"
                   <?= $accent !== '' ? 'data-accent="1"' : '' ?>
                   style="<?= htmlspecialchars($cardStyle) ?>"
                   href="<?= htmlspecialchars(collegeDeptPublicUrl($dept)) ?>">
                    <div class="cd-card-body">
                        <?php if ($cePreview && !empty($dept['archived_at'])): ?><span class="ve-hidden-badge">Hidden from the website</span><?php endif; ?>
                        <?php if ($logoUrl): ?>
                        <div class="skeleton-wrap cd-logo-wrap">
                            <img class="cd-logo" loading="lazy" decoding="async" src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($cardName) ?> logo"<?= ceManageMark('data-ve-basic-logo-id="' . (int)$dept['dept_id'] . '"') ?>>
                        </div>
                        <?php else: ?>
                        <?php if ($cePreview): ?>
                        <div class="cd-logo-wrap cd-logo-empty"<?= ceManageMark('data-ve-basic-logo-id="' . (int)$dept['dept_id'] . '"') ?>><span>Add logo</span></div>
                        <?php else: ?>
                        <div class="cd-logo-wrap cd-logo-empty" aria-hidden="true"></div>
                        <?php endif; ?>
                        <?php endif; ?>
                        <h3 class="cd-title"><?= htmlspecialchars($cardName) ?></h3>
                        <span class="cd-underline" aria-hidden="true"></span>
                        <?php if ($desc !== ''): ?>
                        <p class="cd-desc"><?= htmlspecialchars($desc) ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="cd-btn">View Department <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                </a>
                <?php endforeach; ?>
                <?php if ($cePreview): ?>
                <div class="ve-add-tile" data-ve-add="department"><span style="font-size:28px;line-height:1">+</span><span>Add a department</span></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </section>

    <?= basicEducationEditorBlock('college-index') ?>
    <?php include __DIR__ . '/../../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="/assets/js/skeleton-loader.js"></script>
    <script src="/assets/js/college-dept.js"></script>
</body>
</html>
