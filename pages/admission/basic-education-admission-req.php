<?php
require_once __DIR__ . '/../../admin/admin-functions.php';
require_once __DIR__ . '/../../components/basic-admission-requirements.php';
require_once __DIR__ . '/../../components/admission-page-manager.php';
$basicSchools = basicAdmissionRequirements();
$basicDefaults = basicAdmissionSectionDefaults();
$basicPreview = cePreviewActive();
$basicSections = [];
$basicOrder = [];
foreach (getPageSections('basic-education-admission-requirements') as $section) {
    $scope = admissionCategoryScope('basic-education-admission-requirements',$section['section_key']);
    if ($scope !== null && $section['status'] !== 'published') {
        unset($basicSchools[$scope]['groups'][substr($section['section_key'],strlen($scope)+1)]);
        continue;
    }
    $basicSections[$section['section_key']] = $basicPreview && $section['draft_content'] !== null ? $section['draft_content'] : $section['content'];
    $basicOrder[$section['section_key']] = (int)$section['display_order'];
    if (preg_match('/^(casa|montessori|jhs-regular|shs-regular)_(custom_[a-f0-9]{16})$/D', $section['section_key'], $match)
        && ($section['status'] === 'published' || $basicPreview)) {
        $basicSchools[$match[1]]['groups'][$match[2]] = ['title'=>$section['title']];
        $basicDefaults[$section['section_key']] = ['title'=>$section['title'], 'content'=>''];
    }
    if ($scope !== null) {
        $group = substr($section['section_key'],strlen($scope)+1);
        if (isset($basicSchools[$scope]['groups'][$group])) $basicSchools[$scope]['groups'][$group]['title'] = admissionCategoryLabel($basicSections[$section['section_key']],$basicSchools[$scope]['groups'][$group]['title']);
    }
}
foreach ($basicSchools as $scope => &$school) {
    uksort($school['groups'],static fn($a,$b) => ($basicOrder[$scope.'_'.$a] ?? 999) <=> ($basicOrder[$scope.'_'.$b] ?? 999));
}
unset($school);
$basicH = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$basicText = static function (string $key) use ($basicSections, $basicDefaults): string {
    return trim(html_entity_decode(strip_tags($basicSections[$key] ?? $basicDefaults[$key]['content']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
};
$basicTextMark = static function (string $key) use ($basicDefaults, $basicH): string {
    return ceMark('data-ve-section="' . $basicH($key) . '" data-ve-field="admission-label" data-ve-admission-label data-ve-label="' . $basicH($basicDefaults[$key]['title']) . '"');
};
foreach ($basicSchools as $key => &$school) $school['label'] = $basicText('school_label_' . $key);
unset($school);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Basic Education Admission Requirements</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/admission-req-style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admission-req-style.css') ?>" rel="stylesheet">
    <link href="/assets/css/basic-admission-req-style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/basic-admission-req-style.css') ?>" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body class="basic-admission-page">
    <?php $activeSection = 'admissions'; include __DIR__ . '/../../components/navbar.php'; ?>
    <main>
        <section class="admission-intro">
            <div class="admission-hero"><div class="admission-hero-inner">
                <nav class="admission-breadcrumb" aria-label="breadcrumb">
                    <a class="gnc-breadcrumb-home" href="/">Home</a><i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>Admissions</span><i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span class="admission-current"><?= $basicH($basicText('hero_title')) ?></span>
                </nav>
                <h1<?= $basicTextMark('hero_title') ?>><?= $basicH($basicText('hero_title')) ?></h1>
                <p<?= $basicTextMark('hero_intro') ?>><?= $basicH($basicText('hero_intro')) ?></p>
            </div></div>
        </section>
        <section class="admission-requirements" aria-label="Basic Education requirements">
            <div class="requirements-inner" data-basic-admission>
                <div class="requirements-tabs basic-school-tabs" role="tablist" aria-label="School program">
                    <?php foreach ($basicSchools as $schoolKey => $school): $first = $schoolKey === 'casa'; ?>
                    <button type="button" class="req-tab<?= $first ? ' is-active' : '' ?>" role="tab" id="school-tab-<?= $schoolKey ?>" aria-controls="school-panel-<?= $schoolKey ?>" aria-selected="<?= $first ? 'true' : 'false' ?>" tabindex="<?= $first ? '0' : '-1' ?>" data-basic-school="<?= $schoolKey ?>"><span<?= $basicTextMark('school_label_' . $schoolKey) ?>><?= $basicH($school['label']) ?></span></button>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($basicSchools as $schoolKey => $school): ?>
                <section id="school-panel-<?= $schoolKey ?>" role="tabpanel" aria-labelledby="school-tab-<?= $schoolKey ?>" data-basic-school-panel="<?= $schoolKey ?>" data-admission-scope="<?= $schoolKey ?>"<?= $schoolKey !== 'casa' ? ' hidden' : '' ?>>
                    <h2 class="basic-school-title"><?= $basicH($school['title']) ?></h2>
                    <div class="requirements-tabs basic-applicant-tabs" role="tablist" aria-label="<?= $basicH($school['label']) ?> applicant type" data-admission-category-tabs>
                        <?php foreach ($school['groups'] as $key => $group): $first = $key === array_key_first($school['groups']); $sectionKey = $schoolKey . '_' . $key; ?>
                        <button type="button" class="req-tab<?= $first ? ' is-active' : '' ?>" role="tab" id="applicant-tab-<?= $sectionKey ?>" aria-controls="applicant-panel-<?= $sectionKey ?>" aria-selected="<?= $first ? 'true' : 'false' ?>" tabindex="<?= $first ? '0' : '-1' ?>" data-basic-applicant="<?= $sectionKey ?>"><?= $basicH($group['title']) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="requirements-panels">
                        <div class="requirements-copy" data-admission-category-panels>
                            <?php foreach ($school['groups'] as $key => $group): $sectionKey = $schoolKey . '_' . $key; $first = $key === array_key_first($school['groups']); ?>
                            <div class="req-panel<?= $first ? ' is-active' : '' ?>" id="applicant-panel-<?= $sectionKey ?>" role="tabpanel" aria-labelledby="applicant-tab-<?= $sectionKey ?>" data-basic-applicant-panel="<?= $sectionKey ?>"<?= !$first ? ' hidden' : '' ?>>
                                <div class="admission-req-content"<?= ceMark('data-ve-section="' . $basicH($sectionKey) . '" data-ve-label="' . $basicH($basicDefaults[$sectionKey]['title']) . '"') ?>><?= renderSectionContent($basicSections[$sectionKey] ?? $basicDefaults[$sectionKey]['content']) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="requirements-art skeleton-wrap">
                            <img src="/assets/images/svg/college admission-cuate 1.svg" alt="Illustration of a student preparing admission documents" loading="lazy">
                        </div>
                    </div>
                </section>
                <?php endforeach; ?>
                <p class="admission-art-credit">Illustrations by <a href="https://storyset.com/" target="_blank" rel="noopener noreferrer">Storyset</a></p>
            </div>
        </section>
    </main>
    <?php include __DIR__ . '/../../components/footer.php'; ?>
    <script src="/assets/js/skeleton-loader.js"></script>
    <script src="/assets/js/basic-admission-requirements.js?v=<?= filemtime(__DIR__ . '/../../assets/js/basic-admission-requirements.js') ?>"></script>
</body>
</html>
