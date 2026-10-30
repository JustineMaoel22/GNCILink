<?php
define('CURRENT_PROGRAM', 'BEED');
require_once __DIR__ . '/../../../../components/bulletin-board.php';
require_once __DIR__ . '/../../../../components/program-public.php';
require_once __DIR__ . '/../../../../components/pdf-viewer.php';

$currentProgram = publicProgram(CURRENT_PROGRAM);

/* ---------------------------------------------------------------
   ICONS (hero badges, mission, call-to-action)
   Every icon is a "set:name" string:
     material:school   -> Google Material Symbols (Outlined)
     bi:mortarboard    -> Bootstrap Icons
   Browse names at fonts.google.com/icons  and  icons.getbootstrap.com
   --------------------------------------------------------------- */
$programBadges = [
    ['icon' => 'material:school', 'title' => '4 Years', 'subtitle' => 'Program'],
    ['icon' => 'material:workspace_premium', 'title' => 'CHED', 'subtitle' => 'Accredited'],
    ['icon' => 'material:auto_stories', 'title' => 'BEEd', 'subtitle' => 'Degree'],
    ['icon' => 'material:local_library', 'title' => 'Professional', 'subtitle' => 'Practice'],
];

$renderIcon = function (string $spec, string $class): string {
    $parts = explode(':', $spec, 2);
    $set   = $parts[0] ?? '';
    $name  = $parts[1] ?? '';

    if ($set === 'bi' && preg_match('/^[a-z0-9-]+$/i', $name)) {
        return '<i class="bi bi-' . $name . ' ' . $class . '" aria-hidden="true"></i>';
    }
    if ($set === 'material' && preg_match('/^[a-z0-9_]+$/i', $name)) {
        return '<span class="material-symbols-outlined ' . $class . '" aria-hidden="true">' . $name . '</span>';
    }
    // Unknown/invalid icon spec: neutral fallback so the icon slot never renders blank
    return '<span class="material-symbols-outlined ' . $class . '" aria-hidden="true">star</span>';
};

$missionIcon = 'material:shield_with_heart';
$ctaIcon     = 'material:volunteer_activism';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elementary Education</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,100..700,0,0&display=block">
    <link href="/assets/css/blue-theme.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php include_once __DIR__ . '/../../../../components/navbar.php'; ?>

    <section class="hero-page template-hero" style="background-image: url('/assets/images/elem-educ-hero-placeholder.png');" loading="lazy">
        <div class="container h-100">
        <div class="row align-items-center g-0">
            <div class="col-lg-6">
                <nav class="template-breadcrumb" aria-label="breadcrumb">
                    <a class="gnc-breadcrumb-home" href="/" aria-label="Home" title="Home"><svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 1 0 7h2v8h4v-5h4v5h4V7h2L8 1Z"/></svg></a><i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>ACADEMICS</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/college-departments.php" title="COLLEGE DEPARTMENTS">COLLEGE DEPARTMENTS</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/departments/cased.php" title="<?= htmlspecialchars(mb_strtoupper((publicDepartment('cased')['dept_name'] ?? '') ?: 'COLLEGE OF ARTS, SCIENCE AND EDUCATION')) ?>"><?= htmlspecialchars(gncBreadcrumbShortLabel((publicDepartment('cased')['dept_name'] ?? '') ?: 'COLLEGE OF ARTS, SCIENCE AND EDUCATION', 'cased')) ?></a>
                    <i class="bi bi-chevron-right"></i>
                    <span title="ELEMENTARY EDUCATION">BEED</span>
                </nav>

                <div class="template-hero-content">
                    <h1 class="template-title">ELEMENTARY EDUCATION</h1>
                    <p class="template-subtitle">Inspiring Young Minds, Shaping Tomorrow’s Future.</p>
                    <p class="template-desc">
                        The Elementary Education program prepares competent and compassionate educators committed to 
                        nurturing young learners and shaping the future through professional excellence, and effective 
                        learner-centered education.
                    </p>
                </div>

                <div class="template-badges">
                    <?php foreach ($programBadges as $badge): ?>
                    <div class="template-badge">
                        <?= $renderIcon((string)($badge['icon'] ?? ''), 'template-badge-icon') ?>
                        <div>
                            <strong><?= htmlspecialchars((string)($badge['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars((string)($badge['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        </div><!-- /.container -->
    </section>

    <section class="template-about py-5">
        <div class="container">
            <div class="row g-4 align-items-start">
                <!-- Main content -->
                <div class="col-lg-8" id="about-program">
                    <h2 class="template-section-title template-section-title--gold">About the Program</h2>
                    <p class="template-section-text">
                        The Bachelor of Elementary Education is an undergraduate teacher education program that prepares students 
                        to become competent, motivated, and responsible elementary school teachers. It develops essential knowledge, 
                        teaching skills, and pedagogical strategies needed to effectively support the learning and holistic development 
                        of children in both public and private elementary schools.
                    </p>

                    <div class="template-mission-card" id="our-mission">
                        <div class="template-mission-icon">
                            <?= $renderIcon($missionIcon, 'template-mission-icon-glyph') ?>
                        </div>
                        <div>
                            <h5 class="template-mission-title">Our Mission</h5>
                            <p class="template-mission-text">
                                To develop highly competent, ethical, and compassionate elementary educators equipped with effective teaching 
                                strategies, professional skills, and a commitment to lifelong learning. The program prepares future teachers to 
                                respond to the diverse needs of learners, uphold professional and social responsibility, and contribute meaningfully 
                                to the development of society.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Seal -->
                <div class="col-lg-3 d-flex align-items-center justify-content-center">
                    <img src="/assets/images/logos/cased-logo.svg" alt="Guagua National Colleges, Inc. College of Teacher Education Seal" class="template-seal" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <section class="template-why py-5" id="why-choose">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold">Why Choose BEEd at GNCI?</h2>
            <p class="template-why-intro">
                Our program develops competent, compassionate, and innovative elementary educators who are committed to learner-centered teaching, professional 
                excellence, and the holistic development of children through effective pedagogy, ethical practice, and lifelong learning.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Studying-amico 1.svg" alt="Comprehensive Teacher Preparation" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Comprehensive Teacher Preparation</h5>
                        <p class="template-why-desc">
                            Build a strong foundation in elementary education through essential knowledge, teaching strategies, and pedagogical skills.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Learning-amico 2.svg" alt="Diverse Teaching Opportunities" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Diverse Teaching Opportunities</h5>
                        <p class="template-why-desc">
                            Prepare to teach across different learning areas and work with learners in public and private elementary schools.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Kindergarten student-amico 1.svg" alt="Hands-On Teaching Experience" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Hands-On Teaching Experience</h5>
                        <p class="template-why-desc">
                            Develop practical teaching skills through field-based experiences, classroom activities, and real-world educational settings.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/college class-amico 1.svg" alt="Professional & Ethical Development" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Learner-Centered Education</h5>
                        <p class="template-why-desc">
                            Learn to understand diverse learners and apply appropriate teaching methods, assessment tools, and learning approaches.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Education-amico 1.svg" alt="LET Reinforcement & Licensing" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Professional & Ethical Development</h5>
                        <p class="template-why-desc">
                            Develop the personal qualities, professionalism, and ethical responsibility needed to become an effective and exemplary teacher.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/college entrance exam-amico 1.svg" alt="Lifelong Learning Advocacy" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">LET Readiness and Lifelong Growth</h5>
                        <p class="template-why-desc">
                            Receive comprehensive preparation for the Licensure Examination for Teachers (LET) while developing a commitment to continuous personal and professional growth.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- PDF Viewer Integrated -->
    <section class="template-curriculum py-5" id="curriculum">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold">Program Curriculum</h2>
            <?php if (!empty($currentProgram['pdf_url'])): ?>
                <?php renderPdfViewer($currentProgram['pdf_url'], ['title' => 'Program Curriculum', 'download_name' => $currentProgram['pdf_name']]); ?>
            <?php else: ?>
                <p class="template-curriculum-intro">
                    Curriculum details coming soon.
                </p>
            <?php endif; ?>
        </div>
    </section>

    <section class="template-careers py-5" id="careers">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold">Career Opportunities</h2>
            <p class="template-careers-intro">
                Graduates of the Bachelor of Elementary Education program are equipped with strong pedagogical knowledge, teaching skills, and professional competence to 
                pursue meaningful careers in education, child development, and related fields.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/elem-teacher-image.png" alt="Kindergarten Teacher" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Elementary School Teacher</h5>
                            <p class="template-career-desc">
                                Teach and facilitate learning in public or private elementary schools.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/elem-teacher-image2.png" alt="Assistant Teacher" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Special Education Teacher</h5>
                            <p class="template-career-desc">
                                Provide specialized instruction and support to learners with diverse educational needs.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/elem-teacher-image3.png" alt="Curriculum Developer" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Preschool Teacher</h5>
                            <p class="template-career-desc">
                                Guide young learners in their early academic, social, and developmental experiences.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/elem-teacher-image4.png" alt="Education Program Coordinator" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Education Program Coordinator</h5>
                            <p class="template-career-desc">
                                Assist in developing, implementing, and managing educational programs and learning activities.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php render_bulletin_board(); ?>

    <section class="template-cta py-4">
        <div class="container">
            <div class="template-cta-card">
                <div class="template-cta-icon">
                    <?= $renderIcon($ctaIcon, 'template-cta-glyph') ?>
                </div>
                <div class="template-cta-text">
                    <h5 class="template-cta-title">Make a difference. Begin Your Journey with GNCI.</h5>
                    <p class="template-cta-desc">Join a community driven by purpose, dedicated to excellence, and built to make an impact.</p>
                </div>
                <div class="template-cta-action">
                    <a href="/pages/admission/enrollment-procedures.php" class="template-cta-btn">Apply Now</a>
                </div>
            </div>
        </div>
    </section>



    <?php include_once __DIR__ . '/../../../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
</body>
</html>