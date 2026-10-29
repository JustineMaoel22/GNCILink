<?php
define('CURRENT_PROGRAM', 'BSED');
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
    ['icon' => 'material:auto_stories', 'title' => 'BSEd', 'subtitle' => 'Degree'],
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
    <title>Secondary Education</title>
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
                    <a class="gnc-breadcrumb-home" href="/">Home</a><i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>ACADEMICS</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/college-departments.php">COLLEGE DEPARTMENTS</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/departments/cased.php"><?= htmlspecialchars(mb_strtoupper((publicDepartment('cased')['dept_name'] ?? '') ?: 'COLLEGE OF ARTS, SCIENCE AND EDUCATION')) ?></a>
                    <i class="bi bi-chevron-right"></i>
                    <span>SECONDARY EDUCATION</span>
                </nav>

                <div class="template-hero-content">
                    <h1 class="template-title">SECONDARY EDUCATION</h1>
                    <p class="template-subtitle">Shaping Future-Ready Educators Through Knowledge, Leadership, and <br>Excellence.</p>
                    <p class="template-desc">
                        The Bachelor of Secondary Education program prepares competent and innovative educators committed to guiding 
                        young learners, advancing quality education, and making a meaningful impact through effective teaching, professional 
                        excellence, and lifelong learning.
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
                        The Bachelor of Secondary Education is an undergraduate teacher education program that prepares future educators with the knowledge, skills, 
                        and competencies needed to teach effectively in their chosen area of specialization at the secondary level. The program develops learners’ intellectual, 
                        emotional, social, and professional qualities, preparing them to become competent, responsible, and compassionate educators in public and private schools.
                    </p>

                    <div class="template-mission-card" id="our-mission">
                        <div class="template-mission-icon">
                            <?= $renderIcon($missionIcon, 'template-mission-icon-glyph') ?>
                        </div>
                        <div>
                            <h5 class="template-mission-title">Our Mission</h5>
                            <p class="template-mission-text">
                                To develop competent, ethical, and innovative secondary educators equipped with strong content knowledge, effective teaching strategies, 
                                and appropriate ICT skills. It aims to prepare graduates to respond to diverse learning needs, uphold professional and cultural values, 
                                pursue lifelong growth, and contribute meaningfully to the advancement of education and society.
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
            <h2 class="template-section-title template-section-title--gold">Why Choose BSEd at GNCI?</h2>
            <p class="template-why-intro">
                Our program develops competent, innovative, and dedicated educators who are committed to excellence in teaching, learner development, 
                and lifelong learning through effective pedagogy, specialized knowledge, and professional practice.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Studying-amico 1.svg" alt="Comprehensive Secondary Teacher Preparation" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Comprehensive Secondary Teacher Preparation</h5>
                        <p class="template-why-desc">
                            Build a strong foundation in secondary education through essential knowledge, pedagogical skills, and teaching practices designed to 
                            prepare you for both public and private schools.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Demo-amico 1.svg" alt="Specialized Teaching Opportunities" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Specialized Teaching Opportunities</h5>
                        <p class="template-why-desc">
                            Develop expertise in your chosen area of specialization, including Mathematics, English, Filipino, Physical and Biological Sciences, and MAPEH, 
                            while gaining the content knowledge and pedagogy needed to teach high school learners effectively.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/college class-amico 1.svg" alt="Diverse and Learner-Centered Teaching" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Diverse and Learner-Centered Teaching</h5>
                        <p class="template-why-desc">
                            Learn to facilitate meaningful learning through a wide range of teaching methodologies, delivery modes, assessment strategies, 
                            and instructional approaches suited to diverse learners and learning environments.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Thesis-amico 1.svg" alt="Innovative and Technology-Enhanced Education" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Innovative and Technology-Enhanced Education</h5>
                        <p class="template-why-desc">
                            Develop innovative curricula, instructional plans, teaching resources, and approaches while utilizing ICT to promote quality, relevant, 
                            and sustainable educational practices.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Education-amico 1.svg" alt="Professional, Ethical, and Cultural Development" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Professional, Ethical, and Cultural Development</h5>
                        <p class="template-why-desc">
                            Develop the qualities of a model teacher by practicing professional and ethical standards, responding to changing societal needs, 
                            and recognizing your responsibility to the local, national, and global community while preserving and promoting Filipino heritage.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/college project-amico 1.svg" alt="LET Reinforcement and Lifelong Growth" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">LET Readiness and Lifelong Growth</h5>
                        <p class="template-why-desc">
                            Receive comprehensive preparation for the Licensure Examination for Teachers (LET) while gaining field-based and experiential opportunities that 
                            encourage continuous personal and professional growth.
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
                Graduates of BSEd are equipped with specialized knowledge, effective teaching strategies, and
                professional competencies to pursue meaningful careers in secondary-level education and beyond.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/secondary-teacher.png" alt="Secondary School Teacher" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Secondary School Teacher</h5>
                            <p class="template-career-desc">
                                Guide junior and senior high school learners in their chosen subject
                                specialization.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/academic-coordinator.png" alt="Academic Coordinator" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Academic Coordinator</h5>
                            <p class="template-career-desc">
                                Oversee academic programs and support teachers in delivering quality instruction.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/curriculum-and-instruction-specialist.png" alt="Curriculum & Instruction Specialist" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Curriculum & Instruction Specialist</h5>
                            <p class="template-career-desc">
                                Design and refine instructional materials and learning programs for secondary
                                schools.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/education-and-training-specialist.png" alt="Education & Training Specialist" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Education & Training Specialist</h5>
                            <p class="template-career-desc">
                                Develop training programs and advise institutions on effective teaching
                                practices.
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
                    <a href="/admissions/apply.php" class="template-cta-btn">Apply Now</a>
                </div>
            </div>
        </div>
    </section>



    <?php include_once __DIR__ . '/../../../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
</body>
</html>