<?php
require_once __DIR__ . '/../../../components/department-public.php';
$orgPreview = cePreviewCanManage();
$settings = studentOrgPageContent();
$leadershipImages = studentOrgPageImages();
$organizations = studentOrganizations($orgPreview);
$categories = studentOrgCategories();
$category = max(0, (int)($_GET['category'] ?? 0));
$archived = $orgPreview && ($_GET['archived'] ?? '') === '1';
$filtered = array_values(array_filter($organizations, fn($org) => (!$orgPreview || !empty($org['archived_at']) === $archived) && (!$category || (int)$org['category_id'] === $category)));
$total = count($filtered); $pages = max(1, (int)ceil($total / 6));
$page = min($pages, max(1, (int)($_GET['p'] ?? 1)));
$visible = array_slice($filtered, ($page - 1) * 6, 6);
$orgH = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$orgMark = static fn($key) => ceField('org-setting', ['key'=>$key,'max'=>$settings[$key]['max'],'label'=>$settings[$key]['label'],'multiline'=>$settings[$key]['max']>255,'rich'=>studentOrgPageIsRich($key)]);
$orgField = static fn($key) => ceField('org-field', ['key'=>$key,'label'=>studentOrganizationFields()[$key][0],'max'=>studentOrganizationFields()[$key][1],'multiline'=>$key==='description','rich'=>$key==='description']);
$leadImage = static function($key,$class) use($leadershipImages,$orgH) {
    $image=$leadershipImages[$key];
    return '<div class="'.$class.'"'.ceManageMark('data-ve-org-page-media="'.$key.'" data-warning-optional="true" data-ve-label="'.$orgH($image['label']).'"').'><img src="'.$orgH($image['path']).'" alt="'.($key==='retreat_photo'?'GNCI campus':'').'" loading="lazy"></div>';
};
$pageLink = static fn($number, $filter) => studentOrganizationsUrl() . '?' . http_build_query(array_filter(['category'=>$filter,'p'=>$number,'ce_preview'=>$orgPreview?'1':null,'archived'=>$archived?'1':null],fn($value)=>$value!==null));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $orgH($settings['hero_title']['value']) ?></title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet"><link href="/assets/css/navbar-style.css" rel="stylesheet"><link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/student-org-style.css?v=<?= filemtime(__DIR__.'/../../../assets/css/student-org-style.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined&amp;display=block">
    <link rel="icon" href="/assets/images/logos/gnc-logo-v1.svg">
    <?php if ($orgPreview || isset($_GET['category']) || isset($_GET['p'])): ?>
    <style>
        /* Result navigation must not replay entrances, including card scroll reveals. */
        .student-org-hero,
        .student-org-breadcrumb,
        .student-org-hero h1,
        .student-org-hero .cms-rich,
        .student-org-directory h2::after,
        .student-org-filters a,
        .student-org-grid,
        .student-org-card,
        .student-org-pagination,
        .js-reveal { animation: none !important; }
    </style>
    <?php endif; ?>
</head>
<body>
    <?php $activeSection='student-life'; include __DIR__.'/../../../components/navbar.php'; ?>
    <main>
    <section class="student-org-intro">
        <div class="student-org-hero"><div class="student-org-hero-inner">
            <nav class="student-org-breadcrumb" aria-label="breadcrumb"><a class="gnc-breadcrumb-home" href="/" aria-label="Home" title="Home"><svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 1 0 7h2v8h4v-5h4v5h4V7h2L8 1Z"/></svg></a><i class="bi bi-chevron-right" aria-hidden="true"></i><span>Student Life</span><i class="bi bi-chevron-right" aria-hidden="true"></i><span title="Student Organizations and Leadership Programs">Organizations</span></nav>
            <h1<?= $orgMark('hero_title') ?>><?= $orgH($settings['hero_title']['value']) ?></h1>
            <div class="cms-rich"<?= $orgMark('hero_description') ?>><?= renderSectionContent($settings['hero_description']['value']) ?></div>
        </div></div>
    </section>
    <section class="student-org-directory" aria-labelledby="organizationDirectoryTitle">
        <h2 id="organizationDirectoryTitle"<?= $orgMark('section_title') ?>><?= $orgH($settings['section_title']['value']) ?></h2>
        <div class="student-org-subtitle cms-rich"<?= $orgMark('section_description') ?>><?= renderSectionContent($settings['section_description']['value']) ?></div>
        <nav class="student-org-filters" aria-label="Organization categories">
            <a href="<?= $orgH($pageLink(1,0)) ?>" class="<?= !$category?'is-active':'' ?>"<?= !$category?' aria-current="true"':'' ?>>All</a>
            <?php foreach ($categories as $cat): ?><a href="<?= $orgH($pageLink(1,(int)$cat['category_id'])) ?>" class="<?= $category===(int)$cat['category_id']?'is-active':'' ?>"<?= $category===(int)$cat['category_id']?' aria-current="true"':'' ?>><?= $orgH($cat['name']) ?></a><?php endforeach; ?>
            <?php if ($orgPreview): ?><button type="button" class="org-canvas-control" data-ve-org-categories>Manage categories</button><?php endif; ?>
            <?php if ($orgPreview): ?><a href="<?= $orgH(studentOrganizationsUrl().'?ce_preview=1'.($archived?'':'&archived=1')) ?>" data-ve-org-archive-view><?= $archived?'Active organizations':'Archived' ?></a><?php endif; ?>
        </nav>
        <?php if (!$visible): ?>
        <div class="student-org-empty"><i class="bi bi-people" aria-hidden="true"></i><h3><?= $archived?'No archived organizations':($category?'No organizations in this category':'No student organizations are published yet') ?></h3><p><?= $archived?'Organizations you archive will appear here after Publish.':($orgPreview?'Use Add organization below, then click its text and pictures to edit.':'Check back soon to discover ways to get involved on campus.') ?></p></div>
        <?php endif; ?>
        <div class="student-org-grid">
            <?php foreach ($visible as $org): ?>
            <article class="student-org-card" data-org-url="<?= $orgH(studentOrganizationsUrl($org['slug']).($orgPreview?'&ce_preview=1':'')) ?>"<?= ceManageMark('data-ve-org-card="'.(int)$org['org_id'].'" data-ve-label="'.$orgH($org['name']).'"') ?>>
                <div class="student-org-card-cover"><span class="org-cover-picture"<?= ceManageMark('data-ve-org-media="cover" data-warning-image data-warning-label="Organization cover"') ?>><?php if ($org['cover_url']): ?><img src="<?= $orgH($org['cover_url']) ?>" alt="" loading="lazy"><?php elseif ($orgPreview): ?><span>Add cover image</span><?php endif; ?></span>
                    <?php if ($org['category_name']): ?><span class="student-org-category"><?= $orgH($org['category_name']) ?></span><?php endif; ?>
                    <?php if ($orgPreview && ($org['status']!=='published' || $archived)): ?><span class="student-org-draft"><?= $archived?'Archived':'Unpublished' ?></span><?php endif; ?>
                </div>
                <div class="student-org-card-body"><div class="student-org-card-heading">
                    <span class="student-org-card-logo" data-image-frame-fit="contain"<?= ceManageMark('data-ve-org-media="logo" data-warning-image data-warning-label="Organization logo"') ?>><?php if ($org['logo_url']): ?><img src="<?= $orgH($org['logo_url']) ?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-people" aria-hidden="true"></i><?php endif; ?></span>
                    <h3<?= $orgField('name') ?>><?= $orgH($org['name']) ?></h3>
                </div><div class="cms-rich"<?= $orgField('description') ?>><?= renderSectionContent($org['description']) ?></div>
                <?php if ($orgPreview): ?><button type="button" class="org-canvas-control" data-ve-org-details>Edit organization page</button> <button type="button" class="org-canvas-control" data-ve-org-assign-category>Assign category<?= $org['category_name']?': '.$orgH($org['category_name']):'' ?></button><?php endif; ?></div>
                <?php if(!$orgPreview): ?><a class="student-org-card-link" href="<?= $orgH(studentOrganizationsUrl($org['slug'])) ?>" aria-label="View <?= $orgH($org['name']) ?>"></a><?php endif; ?>
            </article>
            <?php endforeach; ?>
            <?php if ($orgPreview && !$archived): ?><button type="button" class="org-add-card" data-ve-org-add data-warning-placeholder="true"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Add organization</span></button><?php endif; ?>
        </div>
        <?php if ($pages>1): ?><nav class="student-org-pagination" aria-label="Organization pages">
            <?php if ($page>1): ?><a href="<?= $orgH($pageLink($page-1,$category)) ?>" aria-label="Previous page"><i class="bi bi-chevron-left" aria-hidden="true"></i></a><?php endif; ?>
            <?php for ($i=max(1,$page-2);$i<=min($pages,$page+2);$i++): ?><a href="<?= $orgH($pageLink($i,$category)) ?>"<?= $i===$page?' class="is-active" aria-current="page"':'' ?>><?= $i ?></a><?php endfor; ?>
            <?php if ($page<$pages): ?><a href="<?= $orgH($pageLink($page+1,$category)) ?>" aria-label="Next page"><i class="bi bi-chevron-right" aria-hidden="true"></i></a><?php endif; ?>
        </nav><?php endif; ?>
    </section>
    <section class="org-leadership" aria-labelledby="orgLeadershipTitle" data-warning-section="Leadership and Capacity Building">
        <header class="org-lead-heading">
            <h2 id="orgLeadershipTitle"<?= $orgMark('leadership_title') ?>><?= $orgH($settings['leadership_title']['value']) ?></h2>
            <div class="cms-rich"<?= $orgMark('leadership_description') ?>><?= renderSectionContent($settings['leadership_description']['value']) ?></div>
        </header>
        <div class="org-lead-training">
            <?= $leadImage('training_background','org-lead-background') ?>
            <div class="org-lead-training-content">
                <h3<?= $orgMark('training_title') ?>><?= $orgH($settings['training_title']['value']) ?></h3>
                <div class="cms-rich"<?= $orgMark('training_description') ?>><?= renderSectionContent($settings['training_description']['value']) ?></div>
                <ul class="org-lead-topics" aria-label="Leadership training topics">
                    <?php for($i=1;$i<=5;$i++): $key='training_topic_'.$i; ?>
                    <?php if(trim($settings[$key]['value'])!==''||$orgPreview): ?><li<?= $orgMark($key) ?>><?= $orgH($settings[$key]['value']) ?></li><?php endif; ?>
                    <?php endfor; ?>
                </ul>
            </div>
        </div>
        <section class="org-lead-retreat" aria-labelledby="orgRetreatTitle" data-warning-section="Leadership Formation Retreats">
            <header class="org-lead-heading">
                <h3 id="orgRetreatTitle"<?= $orgMark('retreat_title') ?>><?= $orgH($settings['retreat_title']['value']) ?></h3>
                <div class="cms-rich"<?= $orgMark('retreat_description') ?>><?= renderSectionContent($settings['retreat_description']['value']) ?></div>
            </header>
            <div class="org-lead-retreat-grid">
                <div class="org-lead-photo-frame"><?= $leadImage('retreat_photo','org-lead-photo') ?></div>
                <div class="org-lead-callouts">
                    <div class="org-lead-callout"><?= studentOrgIcon('retreat_benefit_icon','org-lead-icon') ?><div class="cms-rich"<?= $orgMark('retreat_benefit') ?>><?= renderSectionContent($settings['retreat_benefit']['value']) ?></div></div>
                    <div class="org-lead-callout"><?= studentOrgIcon('retreat_location_icon','org-lead-icon') ?><div class="cms-rich"<?= $orgMark('retreat_location') ?>><?= renderSectionContent($settings['retreat_location']['value']) ?></div></div>
                </div>
            </div>
        </section>
        <div class="org-lead-closing">
            <?= $leadImage('closing_background','org-lead-background') ?>
            <span class="org-lead-orb org-lead-orb-top" aria-hidden="true"></span><span class="org-lead-orb org-lead-orb-left" aria-hidden="true"></span><span class="org-lead-orb org-lead-orb-right" aria-hidden="true"></span>
            <div class="org-lead-closing-content"><h2<?= $orgMark('leadership_closing') ?>><?= $orgH($settings['leadership_closing']['value']) ?></h2></div>
        </div>
    </section>
    </main>
    <?= ceData('veOrgData',['fields'=>$settings,'images'=>$leadershipImages,'icons'=>academicFeatureIconChoices(),'organization'=>null,'organizations'=>$visible,'categories'=>$categories,'listingUrl'=>studentOrganizationsUrl()]) ?>
    <?php include __DIR__.'/../../../components/footer.php'; ?>
    <?php if (!$orgPreview): ?>
    <script src="/assets/js/student-org-filters.js?v=<?= filemtime(__DIR__.'/../../../assets/js/student-org-filters.js') ?>"></script>
    <script>
    // Scroll-reveal: classes are added here (not in the markup), so the page stays fully visible without JS
    // and inside the editor preview, which is excluded by the PHP check above.
    (function () {
        if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches
            || ['category','p'].some(function(key){return new URLSearchParams(location.search).has(key);})) return;

        var groups = [
            { sel: '.student-org-card',          type: 'up',    stagger: 0.09, perRow: 3 },
            { sel: '.student-org-empty',         type: 'pop' },
            { sel: '.student-org-pagination',    type: 'fade' },
            { sel: '.org-lead-heading',          type: 'up' },
            { sel: '.org-lead-training-content', type: 'up' },
            { sel: '.org-lead-topics li',        type: 'pop',   stagger: 0.07 },
            { sel: '.org-lead-photo-frame',      type: 'left' },
            { sel: '.org-lead-callout',          type: 'right', stagger: 0.14 },
            { sel: '.org-lead-closing-content',  type: 'up' }
        ];

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                io.unobserve(entry.target);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });

        groups.forEach(function (g) {
            document.querySelectorAll(g.sel).forEach(function (el, i) {
                var step = g.perRow ? i % g.perRow : i;
                el.classList.add('js-reveal');
                el.setAttribute('data-reveal', g.type);
                if (g.stagger) el.style.setProperty('--reveal-delay', (step * g.stagger).toFixed(2) + 's');
                io.observe(el);
            });
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>
