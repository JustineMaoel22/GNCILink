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
    <link href="/assets/css/enrollment-procedures-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>

    <?php $activeSection = 'admissions'; ?>
    <?php include_once __DIR__ . '/../../components/index-nav.php'; ?>

    <section class="enrollment-intro">
        <div class="enrollment-hero">
            <div class="enrollment-hero-inner">
                <nav class="enrollment-breadcrumb" aria-label="breadcrumb">
                    <span>Admissions</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="enrollment-current">Enrollment Procedures</span>
                </nav>
                <h1>Enrollment Procedures</h1>
                <p>GNCI welcomes applicants whose academic credentials, character, and potential demonstrate their readiness to benefit from the school's quality education and enriching intellectual, social, and spiritual environment.</p>
            </div>
        </div>
    </section>

    <section class="enrollment-procedures">
        <div class="enrollment-procedures-inner">

            <ul class="nav nav-pills enrollment-tabs" id="enrollmentTab" role="tablist">
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

                                <div class="enrollment-step-icon">
                                    <img src="<?= $step['icon']; ?>" alt="<?= htmlspecialchars($step['title']); ?>">
                                </div>

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

                                <div class="enrollment-step-icon">
                                    <img src="<?= $step['icon']; ?>" alt="<?= htmlspecialchars($step['title']); ?>">
                                </div>

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

                                <div class="enrollment-step-icon">
                                    <img src="<?= $step['icon']; ?>" alt="<?= htmlspecialchars($step['title']); ?>">
                                </div>

                                <p class="enrollment-step-desc"><?= htmlspecialchars($step['desc']); ?></p>

                                <div class="enrollment-step-bar enrollment-step-bar--<?= $step['bar']; ?>"></div>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>

            </div>
        </div>
    </section>

    <?php include_once __DIR__ . '/../../components/index-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>

</body>
</html>