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
    <link href="/assets/css/basic-edu-dept-style.css" rel="stylesheet">
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
                <p data-reveal style="--d: 200ms"<?= basicEducationMark('index', 'text_02') ?>><?= basicEducationText('index', 'text_02') ?></p>
            </div>
        </div>

        <!-- Feature Highlights -->
        <div class="basic-edu-features">
            <div class="basic-edu-features-box">

                <div class="basic-edu-feature" data-reveal style="--d: 300ms">
                    <div class="basic-edu-feature-icon">
                        <img src="/assets/images/svg/grad-hat-white.svg" alt="Quality Education">
                    </div>
                    <div class="basic-edu-feature-text">
                        <h3<?= basicEducationMark('index', 'text_03') ?>><?= basicEducationText('index', 'text_03') ?></h3>
                        <p<?= basicEducationMark('index', 'text_04') ?>><?= basicEducationText('index', 'text_04') ?></p>
                    </div>
                </div>

                <div class="basic-edu-feature" data-reveal style="--d: 400ms">
                    <div class="basic-edu-feature-icon">
                        <img src="/assets/images/svg/person_play.svg" alt="Experienced Faculty">
                    </div>
                    <div class="basic-edu-feature-text">
                        <h3<?= basicEducationMark('index', 'text_05') ?>><?= basicEducationText('index', 'text_05') ?></h3>
                        <p<?= basicEducationMark('index', 'text_06') ?>><?= basicEducationText('index', 'text_06') ?></p>
                    </div>
                </div>

                <div class="basic-edu-feature" data-reveal style="--d: 500ms">
                    <div class="basic-edu-feature-icon">
                        <img src="/assets/images/svg/psychology-white.svg" alt="Holistic Development">
                    </div>
                    <div class="basic-edu-feature-text">
                        <h3<?= basicEducationMark('index', 'text_07') ?>><?= basicEducationText('index', 'text_07') ?></h3>
                        <p<?= basicEducationMark('index', 'text_08') ?>><?= basicEducationText('index', 'text_08') ?></p>
                    </div>
                </div>

                <div class="basic-edu-feature" data-reveal style="--d: 600ms">
                    <div class="basic-edu-feature-icon">
                        <img src="/assets/images/svg/real_estate_agent.svg" alt="Community Focused">
                    </div>
                    <div class="basic-edu-feature-text">
                        <h3<?= basicEducationMark('index', 'text_09') ?>><?= basicEducationText('index', 'text_09') ?></h3>
                        <p<?= basicEducationMark('index', 'text_10') ?>><?= basicEducationText('index', 'text_10') ?></p>
                    </div>
                </div>

            </div>
        </div>

        <!-- Basic Education Departments Grid -->
        <?php
        // Each card is a single link, so the whole card navigates;
        // "View Programs" is the visual call-to-action.
        // 'key' is the department's slug in the `departments` table; its logo
        // is managed in Content Editor -> Basic Education Departments and read
        // below via publicDepartment(). 'logo' is only the fallback used when
        // no logo has been set there yet.
        // 'mod' picks the button color (see .basic-edu-card--* in the CSS).
        // Display order: 1 CASA, 2 High School Montessori, 3 JHS Regular, 4 SHS Regular.
        $basicEduCards = [
            ['key' => 'casa-gradeschool', 'mod' => 'casa', 'name' => 'CASA and Grade School Montessori',
             'logo' => '/assets/images/logos/casa-monte-logo.svg',
             'alt'  => 'CASA and Grade School Montessori logo',
             'href' => '/pages/academics/basic-edu/basic-edu-department/casa-gradeschool.php',
             'desc' => 'The College is dedicated to the holistic development of students, preparing highly motivated and competent educators who drive positive transformation in their communities while preserving and promoting Filipino historical and cultural heritage.'],
            ['key' => 'hs-monte', 'mod' => 'hsm',  'name' => 'High School Montessori',
             'logo' => '/assets/images/logos/hs-monte-logo.svg',
             'alt'  => 'High School Montessori logo',
             'href' => '/pages/academics/basic-edu/basic-edu-department/hs-monte.php',
             'desc' => 'The College is dedicated to the holistic development of students, preparing highly motivated and competent educators who drive positive transformation in their communities while preserving and promoting Filipino historical and cultural heritage.'],
            ['key' => 'jhs-regular', 'mod' => 'jhs',  'name' => 'Junior High School Regular',
             'logo' => '/assets/images/logos/jhs-logo.svg',
             'alt'  => 'Junior High School Regular logo',
             'href' => '/pages/academics/basic-edu/basic-edu-department/jhs-regular.php',
             'desc' => 'The College is dedicated to the holistic development of students, preparing highly motivated and competent educators who drive positive transformation in their communities while preserving and promoting Filipino historical and cultural heritage.'],
            ['key' => 'shs-regular', 'mod' => 'shs',  'name' => 'Senior High School Regular',
             'logo' => '/assets/images/logos/shs-logo.svg',
             'alt'  => 'Senior High School Regular logo',
             'href' => '/pages/academics/basic-edu/basic-edu-department/shs-regular.php',
             'desc' => 'The College is dedicated to the holistic development of students, preparing highly motivated and competent educators who drive positive transformation in their communities while preserving and promoting Filipino historical and cultural heritage.'],
        ];
        ?>
        <div class="basic-edu-colleges">
            <div class="basic-edu-grid">
                <?php foreach ($basicEduCards as $index =>$c): ?>
                <?php $cardDepartment = publicDepartment($c['key']); $logoUrl = ($cardDepartment['logo_url'] ?? null) ?: $c['logo']; ?>
                <a class="basic-edu-card basic-edu-card--<?= $c['mod'] ?>" data-reveal="scale" style="--d: <?= ($index % 3) * 100 ?>ms" href="<?= htmlspecialchars($c['href']) ?>">
                    <div class="basic-edu-card-body">
                        <div class="skeleton-wrap basic-edu-logo-wrap">
                            <img class="basic-edu-logo" src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($c['alt']) ?>"<?= ceManageMark('data-ve-basic-logo-id="' . (int)($cardDepartment['dept_id'] ?? 0) . '"') ?>>
                        </div>
                        <h3 class="basic-edu-title"<?= basicEducationMark('index', 'card_' . $c['key'] . '_name') ?>><?= basicEducationText('index', 'card_' . $c['key'] . '_name') ?></h3>
                        <span class="basic-edu-underline" aria-hidden="true"></span>
                        <p class="basic-edu-desc"<?= basicEducationMark('index', 'card_' . $c['key'] . '_desc') ?>><?= basicEducationText('index', 'card_' . $c['key'] . '_desc') ?></p>
                    </div>
                    <span class="basic-edu-btn">View Programs <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

    </section>

    <?= basicEducationEditorBlock('index') ?>
    <?php include __DIR__ . '/../../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
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
