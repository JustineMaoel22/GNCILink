<?php
require_once __DIR__ . '/../../admin/admin-functions.php';
require_once __DIR__ . '/../../components/college-admission-requirements.php';
require_once __DIR__ . '/../../components/admission-page-manager.php';
$collegeDefaults = collegeAdmissionSectionDefaults();
$collegePreview = cePreviewActive();
$collegeContent = [];
$collegeGroups = ['freshmen'=>'Incoming Freshmen','transferees'=>'Transferees'];
$collegeOrder = [];
foreach (getPageSections('college-admission-requirements') as $section) {
    if (admissionCategoryScope('college-admission-requirements',$section['section_key']) !== null && $section['status'] !== 'published') { unset($collegeGroups[$section['section_key']]); continue; }
    $collegeContent[$section['section_key']] = $collegePreview && $section['draft_content'] !== null ? $section['draft_content'] : $section['content'];
    $collegeOrder[$section['section_key']] = (int)$section['display_order'];
    if (preg_match('/^custom_[a-f0-9]{16}$/D', $section['section_key']) && ($section['status'] === 'published' || $collegePreview)) {
        $collegeGroups[$section['section_key']] = $section['title'];
        $collegeDefaults[$section['section_key']] = ['content'=>''];
    }
    if (isset($collegeGroups[$section['section_key']])) $collegeGroups[$section['section_key']] = admissionCategoryLabel($collegeContent[$section['section_key']],$collegeGroups[$section['section_key']]);
}
uksort($collegeGroups,static fn($a,$b) => ($collegeOrder[$a] ?? 999) <=> ($collegeOrder[$b] ?? 999));
$collegeText = static fn($key) => renderSectionContent($collegeContent[$key] ?? $collegeDefaults[$key]['content']);
$collegeHeading = htmlspecialchars(trim(html_entity_decode(strip_tags($collegeContent['hero_title'] ?? $collegeDefaults['hero_title']['content']), ENT_QUOTES | ENT_HTML5, 'UTF-8')), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $collegeHeading ?></title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/admission-req-style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admission-req-style.css') ?>" rel="stylesheet">
    <link href="/assets/css/basic-admission-req-style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/basic-admission-req-style.css') ?>" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php $activeSection = 'admissions'; ?>
    <?php include_once __DIR__ . '/../../components/navbar.php'; ?>

    <section class="admission-intro">
        <div class="admission-hero">
            <div class="admission-hero-inner">
                <nav class="admission-breadcrumb" aria-label="breadcrumb">
                    <a class="gnc-breadcrumb-home" href="/" aria-label="Home" title="Home"><svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 1 0 7h2v8h4v-5h4v5h4V7h2L8 1Z"/></svg></a><i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>Admissions</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="admission-current"><?= $collegeHeading ?></span>
                </nav>
                <h1<?= ceMark('data-ve-section="hero_title" data-ve-admission-label data-ve-label="Page heading"') ?>><?= $collegeHeading ?></h1>
                <div<?= ceMark('data-ve-section="hero_intro" data-ve-label="Page introduction"') ?>><?= $collegeText('hero_intro') ?></div>
            </div>
        </div>
    </section>

    <section class="admission-requirements">
        <div class="requirements-inner" data-admission-scope="college">

            <div class="requirements-tabs" role="tablist" aria-label="Applicant type" data-admission-category-tabs>
                <?php foreach ($collegeGroups as $key => $title): ?>
                <button type="button" class="req-tab<?= $key===array_key_first($collegeGroups) ? ' is-active' : '' ?>" data-target="<?= ceAttr($key) ?>" role="tab" aria-selected="<?= $key===array_key_first($collegeGroups) ? 'true' : 'false' ?>" id="tab-<?= ceAttr($key) ?>" aria-controls="panel-<?= ceAttr($key) ?>"><?= htmlspecialchars($title,ENT_QUOTES,'UTF-8') ?></button>
                <?php endforeach; ?>
            </div>

            <div class="requirements-panels">

                <div class="requirements-copy" data-admission-category-panels>
                    <?php foreach ($collegeGroups as $key => $title): ?>
                    <div class="req-panel<?= $key===array_key_first($collegeGroups) ? ' is-active' : '' ?>" id="panel-<?= ceAttr($key) ?>" role="tabpanel" aria-labelledby="tab-<?= ceAttr($key) ?>"<?= $key!==array_key_first($collegeGroups) ? ' hidden' : '' ?>>
                        <div class="admission-req-content"<?= ceMark('data-ve-section="' . ceAttr($key) . '"') ?>><?= $collegeText($key) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="requirements-art skeleton-wrap">
                    <img src="/assets/images/svg/college admission-cuate 1.svg" alt="Illustration of a student completing college admission requirements" loading="lazy">
                </div>

            </div>
            <p class="admission-art-credit">Illustrations by <a href="https://storyset.com/" target="_blank" rel="noopener noreferrer">Storyset</a></p>
        </div>
    </section>

    <?php include_once __DIR__ . '/../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="/assets/js/skeleton-loader.js"></script>

    <script>
        (function () {
            document.querySelector('[data-admission-category-tabs]').addEventListener('click', function (event) {
                    var tab = event.target.closest('.req-tab[data-target]');
                    if (!tab) return;
                    var tabs = document.querySelectorAll('.req-tab[data-target]');
                    var panels = document.querySelectorAll('.req-panel');
                    var target = tab.getAttribute('data-target');

                    tabs.forEach(function (t) {
                        t.classList.remove('is-active');
                        t.setAttribute('aria-selected', 'false');
                    });
                    tab.classList.add('is-active');
                    tab.setAttribute('aria-selected', 'true');

                    panels.forEach(function (panel) {
                        var isTarget = panel.id === 'panel-' + target;
                        panel.classList.toggle('is-active', isTarget);
                        panel.hidden = !isTarget;
                    });
            });
        })();
    </script>

</body>
</html>
