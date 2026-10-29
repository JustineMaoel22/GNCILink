<?php
define('CURRENT_PROGRAM', 'BSBAFM');
require_once __DIR__ . '/../../../../components/bulletin-board.php';

/* ---------------------------------------------------------------
   ICONS (hero badges, mission, call-to-action)
   Every icon is a "set:name" string:
     material:school   -> Google Material Symbols (Outlined)
     bi:mortarboard    -> Bootstrap Icons
   Browse names at fonts.google.com/icons  and  icons.getbootstrap.com
   --------------------------------------------------------------- */
$programBadges = [
    ['icon' => 'material:school', 'title' => 'Trimestral', 'subtitle' => 'Program'],
    ['icon' => 'material:workspace_premium', 'title' => 'CHED', 'subtitle' => 'Accredited'],
    ['icon' => 'material:auto_stories', 'title' => 'Ph.D. in EM', 'subtitle' => 'Degree'],
    ['icon' => 'material:local_library', 'title' => 'Educational', 'subtitle' => 'Innovation'],
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
    <title>PhD in Educational Management</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,100..700,0,0&display=block">
    <link href="/assets/css/green-theme.css" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php include_once __DIR__ . '/../../../../components/navbar.php'; ?>

    <section class="hero-page template-hero skeleton-hero" data-bg="/assets/images/gnc-graduate-school-placeholder.png">
        <div class="container h-100">
        <div class="row align-items-center g-0">
            <div class="col-lg-6">
                <nav class="template-breadcrumb" aria-label="breadcrumb">
                    <a class="gnc-breadcrumb-home" href="/">Home</a><i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>ACADEMICS</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/graduate-school/graduate-school.php">GRADUATE SCHOOL</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>PHD IN EDUCATIONAL MANAGEMENT</span>
                </nav>

                <div class="template-hero-content">
                    <h1 class="template-title">PHD IN EDUCATIONAL<br>MANAGEMENT</h1>
                    <p class="template-subtitle">Advancing Educational Leaders Through Research, Innovation, and Excellence.</p>
                    <p class="template-desc">
                        The Doctor of Philosophy in Educational Management program develops expert educational leaders, researchers, 
                        and innovators equipped to advance educational practices, solve complex challenges, and contribute to meaningful 
                        transformation in the field of education.
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
                        The Doctor of Philosophy major in Educational Management is an doctorate program that develops educators and education 
                        professionals into highly competent leaders, researchers, and innovators. It emphasizes expert-level knowledge in educational 
                        management, advanced research, and the development of innovative theories, programs, systems, and practices that address complex challenges in education. Through rigorous scholarly work, the program prepares graduates to contribute meaningful solutions and transformative practices to the field of education.
                    </p>

                    <div class="template-vm-card" id="our-mission">
                        <div class="template-vm-icon">
                            <?= $renderIcon($missionIcon, 'template-vm-icon-glyph') ?>
                        </div>
                        <div>
                            <h5 class="template-vm-card-title">Our Mission</h5>
                            <p class="template-vm-card-text">
                                To develop competent, ethical, and globally minded finance professionals
                                equipped with strong technical, analytical, communication, and problem-solving
                                skills, committed to professional excellence, responsible citizenship, and
                                ethical service to organizations and society.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Seal -->
                <div class="col-lg-3 d-flex align-items-center justify-content-center">
                    <img src="/assets/images/logos/gnc-logo-v1.svg" alt="Guagua National Colleges, Inc. College of Business Seal" class="template-seal" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <section class="template-why py-5" id="why-choose">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold">Why Choose PhD in EM at GNCI?</h2>
            <p class="whychoose-subtitle">
                Our program develops visionary educational leaders and accomplished researchers equipped with advanced knowledge, strategic 
                problem-solving skills, and innovative approaches to improve educational systems and drive meaningful transformation in the field of education.
            </p>

            <div class="whychoose-grid">
                <div class="whychoose-card">
                    <div class="skeleton-wrap">
                        <img src="/assets/images/svg/college project-amico (1) 1.svg" alt="Strong Financial Management Skills" class="whychoose-illustration" loading="lazy">
                    </div>
                    <h5 class="whychoose-title">Expertise in Educational Management</h5>
                    <p class="whychoose-desc">
                        Develop advanced theoretical knowledge and leadership expertise in educational management and administration.
                    </p>
                </div>

                <div class="whychoose-card">
                    <div class="skeleton-wrap">
                        <img src="/assets/images/svg/Learning-amico (1) 1.svg" alt="Diverse Career Opportunities" class="whychoose-illustration" loading="lazy">
                    </div>
                    <h5 class="whychoose-title">Advanced Research and Innovation</h5>
                    <p class="whychoose-desc">
                        Build the skills to conduct independent research and develop innovative theories, programs, systems, and practices in education.
                    </p>
                </div>

                <div class="whychoose-card">
                    <div class="skeleton-wrap">
                        <img src="/assets/images/svg/Problem solving-amico (1) 1.svg" alt="Strategic and Analytical Thinking" class="whychoose-illustration" loading="lazy">
                    </div>
                    <h5 class="whychoose-title">Complex Problem-Solving</h5>
                    <p class="whychoose-desc">
                        Strengthen your ability to analyze and address complex challenges across diverse educational settings.
                    </p>
                </div>

                <div class="whychoose-card">
                    <div class="skeleton-wrap">
                        <img src="/assets/images/svg/forming team leadership-amico 1.svg" alt="Practical Business and Financial Application" class="whychoose-illustration" loading="lazy">
                    </div>
                    <h5 class="whychoose-title">Educational Leadership and Systems Development</h5>
                    <p class="whychoose-desc">
                        Learn to design, implement, and evaluate complex educational programs and systems for meaningful institutional improvement.
                    </p>
                </div>

                <div class="whychoose-card">
                    <div class="skeleton-wrap">
                        <img src="/assets/images/svg/Formula-amico 1.svg" alt="Professional and Ethical Development" class="whychoose-illustration" loading="lazy">
                    </div>
                    <h5 class="whychoose-title">Professional and Scholarly Excellence</h5>
                    <p class="whychoose-desc">
                        Develop the intellectual discipline, professionalism, and scholarly competence required for advanced roles in education.
                    </p>
                </div>

                <div class="whychoose-card">
                    <div class="skeleton-wrap">
                        <img src="/assets/images/svg/Thesis-amico (1) 1.svg" alt="Effective Communication Skills" class="whychoose-illustration" loading="lazy">
                    </div>
                    <h5 class="whychoose-title">Impactful Educational Transformation</h5>
                    <p class="whychoose-desc">
                        Prepare to communicate research-based insights and lead initiatives that contribute to the continuous advancement of education.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="template-curriculum py-5" id="curriculum">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold">Program Curriculum</h2>
            <p class="template-section-text">
                <!-- TODO: add curriculum overview / year-by-year breakdown or downloadable curriculum PDF -->
                Curriculum details coming soon.
            </p>
        </div>
    </section>

    <section class="template-career py-5" id="careers">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold">Career Opportunities</h2>
            <p class="whychoose-subtitle">
                Graduates of the Bachelor of Elementary Education program are equipped with strong pedagogical knowledge, teaching skills,
                and professional competence to pursue meaningful careers in education, child development, and related fields.
            </p>

            <div class="career-grid">
                <div class="career-card">
                    <img src="/assets/images/academic-executive.png" alt="Academic Executive" class="career-thumb" loading="lazy">
                    <div class="career-body">
                        <h5 class="career-title">Academic Executive</h5>
                        <p class="career-desc">
                            Lead the strategic direction and institutional development of colleges and universities, overseeing academic programs, 
                            operations, policy, and long-term growth.
                        </p>
                    </div>
                </div>

                <div class="career-card">
                    <img src="/assets/images/education-administrator.png" alt="Education Administrator" class="career-thumb" loading="lazy">
                    <div class="career-body">
                        <h5 class="career-title">Education Administrator</h5>
                        <p class="career-desc">
                            Direct educational systems and institutions by managing policies, programs, resources, and organizational strategies to 
                            improve educational outcomes and institutional performance.
                        </p>
                    </div>
                </div>

                <div class="career-card">
                    <img src="/assets/images/education-policy.png" alt="Stockbroker / Trader" class="career-thumb" loading="lazy">
                    <div class="career-body">
                        <h5 class="career-title">Education Policy and Program Consultant</h5>
                        <p class="career-desc">
                            Provide expert guidance to schools, government agencies, and educational organizations in developing, evaluating, 
                            and improving policies, programs, and education systems.
                        </p>
                    </div>
                </div>

                <div class="career-card">
                    <img src="/assets/images/director-research.png" alt="Director of Research and Academic Development" class="career-thumb" loading="lazy">
                    <div class="career-body">
                        <h5 class="career-title">Director of Research and Academic Development</h5>
                        <p class="career-desc">
                            Lead advanced research initiatives, curriculum development, and institutional innovation while translating research 
                            findings into effective educational programs and practices.
                        </p>
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
    <script src="/assets/js/skeleton-loader.js"></script>
</body>
</html>