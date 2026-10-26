<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scholarship</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/scholarship-style.css" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php $activeSection = 'admissions'; ?>
    <?php include_once __DIR__ . '/../../components/navbar.php'; ?>

    <section class="scholarship-intro">
        <div class="scholarship-hero">
            <div class="scholarship-hero-inner">
                <nav class="scholarship-breadcrumb" aria-label="breadcrumb">
                    <span>Admissions</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="scholarship-current">Scholarship</span>
                </nav>
                <h1>Scholarship</h1>
                <p>GNCI welcomes applicants whose academic credentials, character, and potential demonstrate their readiness to benefit from the school’s quality education and enriching intellectual, social, and spiritual environment.</p>
            </div>
        </div>
    </section>

    <section class="scholarship-body">
        <div class="scholarship-container">

            <ul class="nav scholarship-tabs" id="scholarshipTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="gnc-tab" data-bs-toggle="tab" data-bs-target="#gnc-pane" type="button" role="tab" aria-controls="gnc-pane" aria-selected="true">GNC Scholarships</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="gov-tab" data-bs-toggle="tab" data-bs-target="#gov-pane" type="button" role="tab" aria-controls="gov-pane" aria-selected="false">Government Scholarships</button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="gnc-pane" role="tabpanel" aria-labelledby="gnc-tab" tabindex="0">
                    <p class="sch-lead">The school is committed to providing quality education and offering various scholarship programs to support deserving and qualified students.</p>

                    <div class="sch-photo-grid">
                        <a href="#basicEdModal" class="sch-photo-card blue" role="button" data-bs-toggle="modal" data-bs-target="#basicEdModal">
                            <img src="/assets/images/acad_scholar_basic_edu.png" alt="Students gathered in a classroom for a school program" loading="lazy">
                            <div class="sch-photo-caption">
                                <h2>Academic Scholarship</h2>
                                <span>(Basic Education Department)</span>
                            </div>
                        </a>

                        <a href="#collegeModal" class="sch-photo-card gold" role="button" data-bs-toggle="modal" data-bs-target="#collegeModal">
                            <img src="/assets/images/acad_scholar_college.png" alt="Group of smiling college students" loading="lazy">
                            <div class="sch-photo-caption">
                                <h2>Academic Scholarship</h2>
                                <span>(College Department)</span>
                            </div>
                        </a>

                        <a href="#athletic" class="sch-photo-card green">
                            <img src="/assets/images/athletic_scholar.png" alt="Student athletes performing in green team uniforms" loading="lazy">
                            <div class="sch-photo-caption">
                                <h2>Athletic Scholarship</h2>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="tab-pane fade" id="gov-pane" role="tabpanel" aria-labelledby="gov-tab" tabindex="0">
                    <p class="sch-lead">Government scholarship information will be posted here.</p>
                </div>
            </div>

        </div>
    </section>

    <?php
    // type: regular | montessori
    $incomingGrade7 = [
        ['badge' => 'Rank 1', 'label' => 'Full Scholarship',    'pct' => 100, 'grade' => 'Grade 7', 'program' => 'Regular Program',    'type' => 'regular'],
        ['badge' => 'Rank 2', 'label' => 'Half Scholarship',    'pct' => 50,  'grade' => 'Grade 7', 'program' => 'Regular Program',    'type' => 'regular'],
        ['badge' => 'Rank 1', 'label' => 'Half Scholarship',    'pct' => 50,  'grade' => 'Grade 7', 'program' => 'Montessori Program', 'type' => 'montessori'],
        ['badge' => 'Rank 2', 'label' => 'Partial Scholarship', 'pct' => 25,  'grade' => 'Grade 7', 'program' => 'Montessori Program', 'type' => 'montessori'],
    ];
    $oldStudents = [
        ['badge' => '1st Honor', 'label' => 'Half Scholarship',    'pct' => 50, 'grade' => '', 'program' => 'Montessori Program', 'type' => 'montessori'],
        ['badge' => '2nd Honor', 'label' => 'Partial Scholarship', 'pct' => 25, 'grade' => '', 'program' => 'Montessori Program', 'type' => 'montessori'],
    ];

    function renderScholarshipCard(array $c): void { ?>
        <div class="sch-voucher <?= $c['type'] ?>">
            <div class="sch-voucher-paper">
                <span class="sch-voucher-badge"><?= htmlspecialchars($c['badge']) ?></span>
                <div class="sch-voucher-label"><?= htmlspecialchars($c['label']) ?></div>
                <div class="sch-voucher-pct" aria-label="<?= (int)$c['pct'] ?> percent off">
                    <span class="num"><?= (int)$c['pct'] ?></span>
                    <span class="tag"><span class="sign">%</span><span class="off">OFF</span></span>
                </div>
                <div class="sch-voucher-pill">
                    <?php if ($c['grade']): ?><strong><?= htmlspecialchars($c['grade']) ?></strong><?php endif; ?>
                    <span><?= htmlspecialchars($c['program']) ?></span>
                </div>
            </div>
        </div>
    <?php } ?>

    <div class="modal fade sch-modal" id="basicEdModal" tabindex="-1" aria-labelledby="basicEdModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="basicEdModalLabel">Academic Scholarship for Basic Education Students</h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h3 class="sch-modal-heading">Incoming Grade 7 Students</h3>
                    <div class="sch-voucher-grid">
                        <?php foreach ($incomingGrade7 as $card) renderScholarshipCard($card); ?>
                    </div>

                    <h3 class="sch-modal-heading">Old Students (Advanced CASA - Grade 6 &amp; Grade 8 - Grade 10)</h3>
                    <div class="sch-voucher-grid">
                        <?php foreach ($oldStudents as $card) renderScholarshipCard($card); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $collegeIncoming = [
        ['badge' => 'Rank 1', 'label' => 'Full Scholarship', 'pct' => 100, 'grade' => 'First Year', 'program' => 'College Program', 'type' => 'regular'],
        ['badge' => 'Rank 2', 'label' => 'Half Scholarship', 'pct' => 50,  'grade' => 'First Year', 'program' => 'College Program', 'type' => 'regular'],
    ];
    $collegeOld = [
        ['badge' => "President's Lister", 'label' => 'Half Scholarship',    'pct' => 50, 'grade' => '', 'program' => 'College Program', 'type' => 'navy'],
        ['badge' => "Dean's Lister",      'label' => 'Partial Scholarship', 'pct' => 25, 'grade' => '', 'program' => 'College Program', 'type' => 'navy'],
    ];
    $collegeRequirements = [
        'To be a regular student.',
        'Letter of Application addressed to GNCI President signed by the Dean.',
        'Grades from Last Semester. All course subject grades must be 1.8 and above.',
        'Submit all the requirements at Business Office Window 3.',
    ];
    ?>

    <div class="modal fade sch-modal" id="collegeModal" tabindex="-1" aria-labelledby="collegeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="collegeModalLabel">Academic Scholarship for College Students</h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h3 class="sch-modal-heading">Incoming Freshmen Students</h3>
                    <div class="sch-voucher-grid">
                        <?php foreach ($collegeIncoming as $card) renderScholarshipCard($card); ?>
                    </div>

                    <h3 class="sch-modal-heading">Old Students</h3>
                    <div class="sch-voucher-grid">
                        <?php foreach ($collegeOld as $card) renderScholarshipCard($card); ?>
                    </div>

                    <h3 class="sch-modal-heading">Requirements for Application</h3>
                    <div class="sch-req">
                        <div class="sch-req-main">
                            <p class="sch-req-lead">Students are required to submit the following documents to apply for academic scholarship.</p>
                            <ul class="sch-req-list">
                                <?php foreach ($collegeRequirements as $i => $req): ?>
                                    <li>
                                        <span class="sch-req-icon"><i class="bi bi-file-earmark-text"></i></span>
                                        <span class="sch-req-text"><?= htmlspecialchars($req) ?></span>
                                        <i class="bi bi-check-square sch-req-check" aria-hidden="true"></i>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <!-- ILLUSTRATION GOES HERE: replace the src with your requirements illustration -->
                        <img class="sch-req-art" src="/assets/images/scholarship/requirements-illustration.png" alt="" loading="lazy">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once __DIR__ . '/../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="/assets/js/skeleton-loader.js"></script>

</body>
</html>