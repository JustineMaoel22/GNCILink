<?php
require_once __DIR__ . '/../../../components/department-public.php';
// The editor preview needs its session before the document or navbar sends output.
cePreviewActive();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Basic Education Departments</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&amp;display=block">
    <link href="/assets/css/basic-edu-dept-style.css?v=<?= (int)filemtime(__DIR__ . '/../../../assets/css/basic-edu-dept-style.css') ?>" rel="stylesheet">
    <link href="/assets/css/basic-education-content.css?v=<?= (int)filemtime(__DIR__ . '/../../../assets/css/basic-education-content.css') ?>" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
    <script>document.documentElement.classList.add('js-anim');</script>
</head>
<body>
    <?php $activeSection = 'academics'; ?>
    <?php include_once __DIR__ . '/../../../components/navbar.php'; ?>

    <section class="basic-edu-depts">

        <!-- Hero / Page Header -->
        <div class="basic-edu-hero">
            <div class="basic-edu-hero-inner">
                <nav class="basic-edu-breadcrumb" aria-label="breadcrumb" data-reveal>
                    <span>Academics</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="basic-edu-current">Basic Education Departments</span>
                </nav>
                <h1 data-reveal style="--d: 100ms"<?= basicEducationMark('index', 'text_01') ?>><?= basicEducationText('index', 'text_01') ?></h1>
                <div class="cms-rich" data-reveal style="--d: 200ms"<?= basicEducationMark('index', 'text_02') ?>><?= basicEducationText('index', 'text_02') ?></div>
            </div>
        </div>

        <!-- Feature Highlights -->
        <div class="basic-edu-features">
            <div class="basic-edu-features-box">

                <div class="basic-edu-feature" data-reveal style="--d: 300ms">
                    <?= academicFeatureIcon('index', 'icon_01', 'basic-edu-feature-icon') ?>
                    <div class="basic-edu-feature-text">
                        <h3<?= basicEducationMark('index', 'text_03') ?>><?= basicEducationText('index', 'text_03') ?></h3>
                        <div class="cms-rich"<?= basicEducationMark('index', 'text_04') ?>><?= basicEducationText('index', 'text_04') ?></div>
                    </div>
                </div>

                <div class="basic-edu-feature" data-reveal style="--d: 400ms">
                    <?= academicFeatureIcon('index', 'icon_02', 'basic-edu-feature-icon') ?>
                    <div class="basic-edu-feature-text">
                        <h3<?= basicEducationMark('index', 'text_05') ?>><?= basicEducationText('index', 'text_05') ?></h3>
                        <div class="cms-rich"<?= basicEducationMark('index', 'text_06') ?>><?= basicEducationText('index', 'text_06') ?></div>
                    </div>
                </div>

                <div class="basic-edu-feature" data-reveal style="--d: 500ms">
                    <?= academicFeatureIcon('index', 'icon_03', 'basic-edu-feature-icon') ?>
                    <div class="basic-edu-feature-text">
                        <h3<?= basicEducationMark('index', 'text_07') ?>><?= basicEducationText('index', 'text_07') ?></h3>
                        <div class="cms-rich"<?= basicEducationMark('index', 'text_08') ?>><?= basicEducationText('index', 'text_08') ?></div>
                    </div>
                </div>

                <div class="basic-edu-feature" data-reveal style="--d: 600ms">
                    <?= academicFeatureIcon('index', 'icon_04', 'basic-edu-feature-icon') ?>
                    <div class="basic-edu-feature-text">
                        <h3<?= basicEducationMark('index', 'text_09') ?>><?= basicEducationText('index', 'text_09') ?></h3>
                        <div class="cms-rich"<?= basicEducationMark('index', 'text_10') ?>><?= basicEducationText('index', 'text_10') ?></div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Basic Education Departments Grid -->
        <?php
        // Fixed page routes and presentation; names, images and text come from MySQL.
        $basicEduCards = [
            ['key' => 'casa-gradeschool', 'mod' => 'casa'],
            ['key' => 'hs-monte', 'mod' => 'hsm'],
            ['key' => 'jhs-regular', 'mod' => 'jhs'],
            ['key' => 'shs-regular', 'mod' => 'shs'],
        ];
        $basicEduRows = [];
        foreach ($basicEduCards as $card) {
            $row = publicDepartment($card['key'], cePreviewCanManage());
            if ($row) $basicEduRows[] = array_merge($row, $card);
        }
        $basicEduRows = departmentApplyOrder($basicEduRows, departmentStoredOrder('basic-education'));
        ?>
        <div class="basic-edu-colleges">
            <div class="basic-edu-grid"<?= ceManageMark('data-ve-department-grid="basic-education"') ?>>
                <?php foreach ($basicEduRows as $index =>$c): ?>
                <?php $cardDepartment = $c; $logoUrl = $cardDepartment['logo_url'] ?? null; ?>
                <article class="basic-edu-card basic-edu-card--<?= $c['mod'] ?>"<?= ceManageMark('data-ve-department-card="' . (int)$c['dept_id'] . '"') ?> data-reveal="scale" style="--d: <?= ($index % 3) * 100 ?>ms">
                    <div class="basic-edu-card-body">
                        <div class="skeleton-wrap basic-edu-logo-wrap<?= empty($logoUrl) ? ' is-loaded' : '' ?>">
                            <?= basicEducationMedia($logoUrl, $cardDepartment['dept_name'] . ' logo', 'basic-edu-logo', ceManageMark('data-ve-basic-logo-id="' . (int)$cardDepartment['dept_id'] . '"')) ?>
                        </div>
                        <h3 class="basic-edu-title"<?= basicEducationMark('index', 'card_' . $c['key'] . '_name') ?>><?= basicEducationHasFields('index', ['card_' . $c['key'] . '_name']) ? basicEducationText('index', 'card_' . $c['key'] . '_name') : htmlspecialchars($cardDepartment['dept_name']) ?></h3>
                        <span class="basic-edu-underline" aria-hidden="true"></span>
                        <?php $cardDescription = publicDepartmentCardText($cardDepartment); ?>
                        <?php if ($cardDescription !== ''): ?>
                        <p class="basic-edu-desc"><?= htmlspecialchars($cardDescription, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                    <a class="basic-edu-btn" href="<?= htmlspecialchars('/pages/academics/basic-edu/basic-edu-department/' . $c['key'] . '.php') ?>">View Programs <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </article>
                <?php endforeach; ?>
            </div>
        </div>

    </section>

    <?= basicEducationEditorBlock('index') ?>
    <?php include __DIR__ . '/../../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="/assets/js/skeleton-loader.js"></script>
    <script src="/assets/js/basic-edu-dept.js"></script>
    <script>
    // Adds .is-visible to [data-reveal] elements as they scroll into view.
    (function () {
        var els = document.querySelectorAll('[data-reveal]');
        if (!els.length) return;
        if (!('IntersectionObserver' in window)) {
            els.forEach(function (el) { el.classList.add('is-visible'); });
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        els.forEach(function (el) { io.observe(el); });
    })();
    </script>
</body>
</html>
