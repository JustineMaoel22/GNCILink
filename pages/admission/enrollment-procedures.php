<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../admin/admin-functions.php';
require_once __DIR__ . '/../../components/enrollment-procedures-content.php';
$enrollmentPreview = cePreviewActive();
$basicEnrollmentSteps = basicEnrollmentSteps();
$enrollmentImages = enrollmentIllustrations($basicEnrollmentSteps);
$basicEnrollment = basicEnrollmentDefaults();
$enrollmentTextIds=[];foreach(['title','location','instruction'] as $fieldKey)$enrollmentTextIds[$fieldKey]=substr(hash('sha256','enrollment-step-text:'.$fieldKey),0,24);
foreach ($basicEnrollment as $key => &$field) {
    $field['content'] = getSectionContent('enrollment-procedures', $key, $enrollmentPreview) ?? $field['content'];
}
unset($field);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Procedures</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/enrollment-procedures-style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/enrollment-procedures-style.css') ?>" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>

    <?php $activeSection = 'admissions'; ?>
    <?php include_once __DIR__ . '/../../components/navbar.php'; ?>

    <section class="enrollment-intro">
        <div class="enrollment-hero">
            <div class="enrollment-hero-inner">
                <nav class="enrollment-breadcrumb" aria-label="breadcrumb">
                    <a class="gnc-breadcrumb-home" href="/" aria-label="Home" title="Home"><svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 1 0 7h2v8h4v-5h4v5h4V7h2L8 1Z"/></svg></a><i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>Admissions</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="enrollment-current" title="Enrollment Procedures">Enrollment</span>
                </nav>
                <h1>Enrollment Procedures</h1>
                <p>GNCI welcomes applicants whose academic credentials, character, and potential demonstrate their readiness to benefit from the school's quality education and enriching intellectual, social, and spiritual environment.</p>
            </div>
        </div>
    </section>

    <section class="enrollment-procedures">
        <div class="enrollment-procedures-inner">

            <ul class="nav nav-pills enrollment-tabs enrollment-level-tabs" id="enrollmentLevelTab" role="tablist" aria-label="Education level">
                <li class="nav-item" role="presentation"><button class="nav-link active" id="college-tab" data-bs-toggle="pill" data-bs-target="#college-pane" type="button" role="tab" aria-controls="college-pane" aria-selected="true">College</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" id="basic-education-tab" data-bs-toggle="pill" data-bs-target="#basic-education-pane" type="button" role="tab" aria-controls="basic-education-pane" aria-selected="false">Basic Education</button></li>
            </ul>
            <div class="tab-content" id="enrollmentLevelContent">
                <div class="tab-pane fade show active" id="college-pane" role="tabpanel" aria-labelledby="college-tab" tabindex="0">
                    <h2 class="enrollment-level-title">College</h2>
            <ul class="nav nav-pills enrollment-tabs" id="enrollmentTab" role="tablist" aria-label="College applicant type">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="freshmen-tab" data-bs-toggle="pill"
                        data-bs-target="#freshmen-pane" type="button" role="tab"
                        aria-controls="freshmen-pane" aria-selected="true">
                        Incoming Freshmen
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="transferees-tab" data-bs-toggle="pill"
                        data-bs-target="#transferees-pane" type="button" role="tab"
                        aria-controls="transferees-pane" aria-selected="false">
                        Transferees
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="old-students-tab" data-bs-toggle="pill"
                        data-bs-target="#old-students-pane" type="button" role="tab"
                        aria-controls="old-students-pane" aria-selected="false">
                        Old Students
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="enrollmentTabContent">
                <div class="tab-pane fade show active" id="freshmen-pane" role="tabpanel" aria-labelledby="freshmen-tab" tabindex="0">

                    <h2 class="enrollment-pane-title">Incoming Freshmen</h2>
                    <p class="enrollment-pane-desc">All incoming freshmen are required to submit the following documents and complete the steps during the enrollment process.</p>

                    <div class="enrollment-steps-grid">

                        <?php
                        $freshmenSteps = [
                            ['num' => 1,  'title' => 'Registrar\'s Office', 'sub' => 'Window 9',      'desc' => 'Secure copy of enrollment procedure and Student Profile.', 'icon' => '/assets/images/svg/enrollment-procedures/fresh-1.svg', 'bar' => 'green'],
                            ['num' => 2,  'title' => 'Business Office',     'sub' => 'Window 1 or 2',  'desc' => 'Pay for the Classification Test fee.',                    'icon' => '/assets/images/svg/enrollment-procedures/fresh-2.svg', 'bar' => 'gold'],
                            ['num' => 3,  'title' => 'Guidance Office',     'sub' => 'GH 200',         'desc' => 'Take the Classification Test.',                            'icon' => '/assets/images/svg/enrollment-procedures/fresh-3.svg', 'bar' => 'green'],
                            ['num' => 4,  'title' => 'Registrar\'s Office', 'sub' => 'Window 8 or 9',  'desc' => 'Submit all the required credentials for enrollment.',     'icon' => '/assets/images/svg/enrollment-procedures/fresh-4.svg', 'bar' => 'gold'],
                            ['num' => 5,  'title' => 'Registrar\'s Office', 'sub' => 'Window 9',       'desc' => 'Claim your official Enrollment Form.',                     'icon' => '/assets/images/svg/enrollment-procedures/fresh-5.svg', 'bar' => 'green'],
                            ['num' => 6,  'title' => 'Dean\'s Office',      'sub' => '',               'desc' => 'Pay the Club Membership Fee.',                             'icon' => '/assets/images/svg/enrollment-procedures/fresh-6.svg', 'bar' => 'gold'],
                            ['num' => 7,  'title' => 'MIS Office',         'sub' => 'LH 200',         'desc' => 'Have your ID picture taken.',                              'icon' => '/assets/images/svg/enrollment-procedures/fresh-7.svg', 'bar' => 'green'],
                            ['num' => 8,  'title' => 'College Library',    'sub' => 'PH 200',         'desc' => 'Secure your Library Card.',                                'icon' => '/assets/images/svg/enrollment-procedures/fresh-8.svg', 'bar' => 'gold'],
                            ['num' => 9,  'title' => 'Business Office',    'sub' => 'Window 1 or 2',  'desc' => 'Complete fee assessment and receive your Class Cards.',   'icon' => '/assets/images/svg/enrollment-procedures/fresh-9.svg', 'bar' => 'green'],
                            ['num' => 10, 'title' => 'Registrar\'s Office','sub' => 'Window 7 or 8',  'desc' => 'Have your Class Cards validated.',                         'icon' => '/assets/images/svg/enrollment-procedures/fresh-10.svg', 'bar' => 'gold'],
                        ];

                        foreach ($freshmenSteps as $step): ?>
                            <div class="enrollment-step-card">
                                <div class="enrollment-step-label">STEP <?= $step['num']; ?></div>
                                <div class="enrollment-step-title"><?= htmlspecialchars($step['title']); ?></div>
                                <?php if (!empty($step['sub'])): ?>
                                    <div class="enrollment-step-sub"><?= htmlspecialchars($step['sub']); ?></div>
                                <?php endif; ?>

                                <?= enrollmentIllustration($enrollmentImages, 'fresh-' . $step['num'], $step['title']) ?>

                                <p class="enrollment-step-desc"><?= htmlspecialchars($step['desc']); ?></p>

                                <div class="enrollment-step-bar enrollment-step-bar--<?= $step['bar']; ?>"></div>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>

                <div class="tab-pane fade" id="transferees-pane" role="tabpanel" aria-labelledby="transferees-tab" tabindex="0">

                    <h2 class="enrollment-pane-title">Transferees</h2>
                    <p class="enrollment-pane-desc">All transferee students are required to submit the following documents and complete the steps during the enrollment process.</p>

                    <div class="enrollment-steps-grid">

                        <?php
                        $transfereeSteps = [
                            ['num' => 1, 'title' => 'Registrar\'s Office', 'sub' => 'Window 9',      'desc' => 'Secure a copy of the Enrollment Procedure, Student Profile form, Curriculum, and Pre-enrollment Form.', 'icon' => '/assets/images/svg/enrollment-procedures/trans-1.svg', 'bar' => 'green'],
                            ['num' => 2, 'title' => 'Guidance Office',     'sub' => 'GH 200',        'desc' => 'Fill out a Cumulative Record Folder.',                                                                    'icon' => '/assets/images/svg/enrollment-procedures/trans-2.svg', 'bar' => 'gold'],
                            ['num' => 3, 'title' => 'Registrar\'s Office', 'sub' => 'Window 8',      'desc' => 'Get evaluated and check your respective college\'s bulletin board for the class schedule.',              'icon' => '/assets/images/svg/enrollment-procedures/trans-3.svg', 'bar' => 'green'],
                            ['num' => 4, 'title' => 'Dean\'s Office',      'sub' => '',              'desc' => 'Secure approval for the subjects you plan to enroll in.',                                                'icon' => '/assets/images/svg/enrollment-procedures/trans-4.svg', 'bar' => 'gold'],
                            ['num' => 5, 'title' => 'Registrar\'s Office', 'sub' => 'Window 8',      'desc' => 'Submit your required credentials, completed Student Profile form, and the Pre-enrollment form approved by the Dean.', 'icon' => '/assets/images/svg/enrollment-procedures/trans-5.svg', 'bar' => 'green'],
                            ['num' => 6, 'title' => 'Registrar\'s Office', 'sub' => 'Window 9',      'desc' => 'Claim your Official Enrollment Form.',                                                                    'icon' => '/assets/images/svg/enrollment-procedures/trans-6.svg', 'bar' => 'gold'],
                            ['num' => 7, 'title' => 'College Library',     'sub' => 'PH 200',        'desc' => 'Secure your Library Card.',                                                                                'icon' => '/assets/images/svg/enrollment-procedures/trans-7.svg', 'bar' => 'green'],
                            ['num' => 8, 'title' => 'Business Office',     'sub' => 'Window 1 or 2', 'desc' => 'Complete fee assessment and receive your Class Cards.',                                                   'icon' => '/assets/images/svg/enrollment-procedures/trans-8.svg', 'bar' => 'gold'],
                            ['num' => 9, 'title' => 'Registrar\'s Office', 'sub' => 'Window 7 or 8', 'desc' => 'Have your Class Cards validated.',                                                                         'icon' => '/assets/images/svg/enrollment-procedures/trans-9.svg', 'bar' => 'green'],
                        ];

                        foreach ($transfereeSteps as $step): ?>
                            <div class="enrollment-step-card">
                                <div class="enrollment-step-label">STEP <?= $step['num']; ?></div>
                                <div class="enrollment-step-title"><?= htmlspecialchars($step['title']); ?></div>
                                <?php if (!empty($step['sub'])): ?>
                                    <div class="enrollment-step-sub"><?= htmlspecialchars($step['sub']); ?></div>
                                <?php endif; ?>

                                <?= enrollmentIllustration($enrollmentImages, 'trans-' . $step['num'], $step['title']) ?>

                                <p class="enrollment-step-desc"><?= htmlspecialchars($step['desc']); ?></p>

                                <div class="enrollment-step-bar enrollment-step-bar--<?= $step['bar']; ?>"></div>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>

                <div class="tab-pane fade" id="old-students-pane" role="tabpanel" aria-labelledby="old-students-tab" tabindex="0">

                    <h2 class="enrollment-pane-title">Old Students</h2>
                    <p class="enrollment-pane-desc">All old students are required to submit the following documents and complete the steps during the enrollment process.</p>

                    <div class="enrollment-steps-grid">

                        <?php
                        $oldStudentSteps = [
                            ['num' => 1, 'title' => 'Registrar\'s Office', 'sub' => 'Window 9',      'desc' => 'Present your clearance slip to get your Pre-Enrollment Form. List your chosen subjects based on your department\'s bulletin board.', 'icon' => '/assets/images/svg/enrollment-procedures/old-1.svg', 'bar' => 'green'],
                            ['num' => 2, 'title' => 'Dean\'s Office',      'sub' => '',              'desc' => 'Present your Pre-Enrollment Form to your Dean for academic advising and approval.',                                                   'icon' => '/assets/images/svg/enrollment-procedures/old-2.svg', 'bar' => 'gold'],
                            ['num' => 3, 'title' => 'Registrar\'s Office', 'sub' => 'Window 9',      'desc' => 'Submit your approved form to claim your Official Enrollment Form.',                                                                   'icon' => '/assets/images/svg/enrollment-procedures/old-3.svg', 'bar' => 'green'],
                            ['num' => 4, 'title' => 'Dean\'s Office',      'sub' => '',              'desc' => 'Pay the Club Membership Fee.',                                                                                                          'icon' => '/assets/images/svg/enrollment-procedures/old-4.svg', 'bar' => 'gold'],
                            ['num' => 5, 'title' => 'College Library',     'sub' => 'PH 200',        'desc' => 'Secure your Library card.',                                                                                                             'icon' => '/assets/images/svg/enrollment-procedures/old-5.svg', 'bar' => 'green'],
                            ['num' => 6, 'title' => 'Business Office',     'sub' => 'Window 1 or 2', 'desc' => 'Complete fee assessment and receive your Class Cards.',                                                                                'icon' => '/assets/images/svg/enrollment-procedures/old-6.svg', 'bar' => 'gold'],
                            ['num' => 7, 'title' => 'Registrar\'s Office', 'sub' => 'Window 7 or 8', 'desc' => 'Have your Class Cards validated.',                                                                                                      'icon' => '/assets/images/svg/enrollment-procedures/old-7.svg', 'bar' => 'green'],
                        ];

                        foreach ($oldStudentSteps as $step): ?>
                            <div class="enrollment-step-card">
                                <div class="enrollment-step-label">STEP <?= $step['num']; ?></div>
                                <div class="enrollment-step-title"><?= htmlspecialchars($step['title']); ?></div>
                                <?php if (!empty($step['sub'])): ?>
                                    <div class="enrollment-step-sub"><?= htmlspecialchars($step['sub']); ?></div>
                                <?php endif; ?>

                                <?= enrollmentIllustration($enrollmentImages, 'old-' . $step['num'], $step['title']) ?>

                                <p class="enrollment-step-desc"><?= htmlspecialchars($step['desc']); ?></p>

                                <div class="enrollment-step-bar enrollment-step-bar--<?= $step['bar']; ?>"></div>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>

            </div>
                </div>
                <div class="tab-pane fade" id="basic-education-pane" role="tabpanel" aria-labelledby="basic-education-tab" tabindex="0">
                    <h2 class="enrollment-pane-title"<?= ceMark('data-ve-section="basic_education_title" data-ve-plain-section data-ve-label="Basic Education heading"') ?>><?= htmlspecialchars(html_entity_decode(strip_tags($basicEnrollment['basic_education_title']['content']), ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="enrollment-pane-desc"<?= ceMark('data-ve-section="basic_education_procedures" data-ve-label="Basic Education enrollment procedures"') ?>><?= renderSectionContent($basicEnrollment['basic_education_procedures']['content']) ?></div>
                    <?php if ($basicEnrollmentSteps || cePreviewCanManage()): ?>
                    <div class="enrollment-steps-grid"<?= ceManageMark('data-ve-enrollment-grid') ?>>
                        <?php foreach ($basicEnrollmentSteps as $index => $step): ?>
                        <div class="enrollment-step-card" data-gnc-layout-scope="<?= htmlspecialchars($step['step_id'], ENT_QUOTES, 'UTF-8') ?>">
                            <div class="enrollment-step-label">STEP <?= $index + 1 ?></div>
                            <div class="enrollment-step-title" data-gnc-text="<?= $enrollmentTextIds['title'] ?>"><?= htmlspecialchars($step['title'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php if ($step['location'] !== ''): ?><div class="enrollment-step-sub" data-gnc-text="<?= $enrollmentTextIds['location'] ?>"><?= htmlspecialchars($step['location'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                            <?= enrollmentIllustration($enrollmentImages, 'basic-' . $step['step_id'], $step['title']) ?>
                            <p class="enrollment-step-desc" data-gnc-text="<?= $enrollmentTextIds['instruction'] ?>"><?= nl2br(htmlspecialchars($step['instruction'], ENT_QUOTES, 'UTF-8')) ?></p>
                            <div class="enrollment-step-bar enrollment-step-bar--<?= $index % 2 ? 'gold' : 'green' ?>"></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (cePreviewCanManage()): ?>
                    <div class="mt-3"><button type="button" class="btn btn-outline-success" data-ve-enrollment-add><i class="bi bi-plus-lg" aria-hidden="true"></i> Add step</button></div>
                    <?php endif; ?>
                </div>

            </div>
            <p class="enrollment-art-credit">Illustrations by <a href="https://storyset.com/" target="_blank" rel="noopener noreferrer">Storyset</a></p>
        </div>
    </section>

    <script>window.GNC_ENROLLMENT_TEXT_IDS=<?= json_encode($enrollmentTextIds,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
    <?php if (cePreviewCanManage()): ?>
    <script type="application/json" id="veEnrollmentIllustrations"><?= json_encode($enrollmentImages, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
    <script type="application/json" id="veEnrollmentSteps"><?= json_encode(['steps'=>$basicEnrollmentSteps,'revision'=>basicEnrollmentRevision($basicEnrollmentSteps)], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
    <?php endif; ?>
    <?php include_once __DIR__ . '/../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>
    

    <script src="/assets/js/skeleton-loader.js"></script>

    <script>
    (function () {
        function showEnrollmentAnchor() {
            if (window.location.hash === '#basic-education-pane') bootstrap.Tab.getOrCreateInstance(document.getElementById('basic-education-tab')).show();
        }
        showEnrollmentAnchor();
        window.addEventListener('hashchange', showEnrollmentAnchor);
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
            !('IntersectionObserver' in window)) { return; }
        document.documentElement.classList.add('js-reveal');

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) { return; }
                var card = entry.target;
                card.classList.add('reveal-animating', 'is-visible');
                observer.unobserve(card);
                // Remove the stagger transition once done so hover stays snappy
                setTimeout(function () { card.classList.remove('reveal-animating'); }, 1200);
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

        function observePane(pane) {
            pane.querySelectorAll('.enrollment-step-card:not(.is-visible)').forEach(function (card, i) {
                card.style.setProperty('--i', i % 5);
                observer.observe(card);
            });
        }

        document.querySelectorAll('.tab-pane').forEach(observePane);

        // Replay the reveal each time a tab is shown
        document.querySelectorAll('#enrollmentTab [data-bs-toggle="pill"], #enrollmentLevelTab [data-bs-toggle="pill"]').forEach(function (btn) {
            btn.addEventListener('shown.bs.tab', function () {
                var pane = document.querySelector(btn.getAttribute('data-bs-target'));
                pane.querySelectorAll('.enrollment-step-card').forEach(function (card) {
                    card.classList.remove('is-visible', 'reveal-animating');
                });
                requestAnimationFrame(function () { observePane(pane); });
            });
        });
    })();
    </script>

</body>
</html>
