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
    ['icon' => 'material:auto_stories', 'title' => 'MAEd', 'subtitle' => 'Degree'],
    ['icon' => 'material:local_library', 'title' => 'Professional', 'subtitle' => 'Advancement'],
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
    <title>Master of Arts in Education</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,100..700,0,0&display=block">
    <link href="/assets/css/green-theme.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php include_once __DIR__ . '/../../../../components/index-nav.php'; ?>

    <section class="hero-page template-hero" style="background-image: url('/assets/images/gnc-graduate-school-placeholder.png');" loading="lazy">
        <div class="container h-100">
        <div class="row align-items-center g-0">
            <div class="col-lg-6">
                <nav class="template-breadcrumb" aria-label="breadcrumb">
                    <span>ACADEMICS</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/graduate-school/graduate-school.php">GRADUATE SCHOOL</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>MASTER OF ARTS IN EDUCATION</span>
                </nav>

                <div class="template-hero-content">
                    <h1 class="template-title">MA IN EDUCATION</h1>
                    <p class="template-subtitle">Advancing Educational Excellence Through Leadership, Research, and Practice.</p>
                    <p class="template-desc">
                        The Master of Arts in Education program develops knowledgeable, reflective, and research-oriented education 
                        professionals committed to advancing teaching excellence and improving educational practice.
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
                        The Master of Arts in Education program strengthens educators’ knowledge, teaching expertise, 
                        and research capabilities, preparing them to advance educational practice and contribute meaningfully 
                        to improved learning outcomes.
                    </p>

                    <div class="template-vm-card" id="our-mission">
                        <div class="template-vm-icon">
                            <?= $renderIcon($missionIcon, 'template-vm-icon-glyph') ?>
                        </div>
                        <div>
                            <h5 class="template-vm-card-title">Our Mission</h5>
                            <p class="template-vm-card-text">
                                To empower educators with advanced knowledge, effective teaching expertise, and strong research capabilities 
                                that promote continuous professional growth, innovative educational practice, and meaningful improvements in 
                                teaching and learning.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Seal -->
                <div class="col-lg-3 d-flex align-items-center justify-content-center">
                    <img src="/assets/images/logos/gnc-logo-v1.svg" alt="Guagua National Colleges, Inc. Seal" class="template-seal" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <section class="template-why py-5" id="why-choose">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold">Why Choose MAEd at GNCI?</h2>
            <p class="whychoose-subtitle">
                Our program develops knowledgeable, reflective, and research-oriented educators who are committed to teaching excellence, 
                professional growth, and the continuous improvement of educational practice through advanced knowledge and inquiry.
            </p>

            <div class="whychoose-grid">
                <div class="whychoose-card">
                    <img src="/assets/images/svg/college project-amico (1) 1.svg" alt="Advanced Educational Knowledge" class="whychoose-illustration" loading="lazy">
                    <h5 class="whychoose-title">Advanced Educational Knowledge</h5>
                    <p class="whychoose-desc">
                        Deepen your understanding of educational theories, practices, and subject-specific knowledge to strengthen your professional expertise.
                    </p>
                </div>

                <div class="whychoose-card">
                    <img src="/assets/images/svg/Learning-amico (1) 1.svg" alt="Enhanced Teaching Expertise" class="whychoose-illustration" loading="lazy">
                    <h5 class="whychoose-title">Enhanced Teaching Expertise</h5>
                    <p class="whychoose-desc">
                        Develop advanced theoretical and technical skills to improve instructional strategies, classroom practice, and learning outcomes.
                    </p>
                </div>

                <div class="whychoose-card">
                    <img src="/assets/images/svg/Problem solving-amico (1) 1.svg" alt="Research and Inquiry Skills" class="whychoose-illustration" loading="lazy">
                    <h5 class="whychoose-title">Research and Inquiry Skills</h5>
                    <p class="whychoose-desc">
                        Build strong research capabilities for analyzing, validating, contextualizing, and applying educational knowledge to real-world challenges.
                    </p>
                </div>

                <div class="whychoose-card">
                    <img src="/assets/images/svg/forming team leadership-amico 1.svg" alt="Professional Growth and Leadership" class="whychoose-illustration" loading="lazy">
                    <h5 class="whychoose-title">Professional Growth and Leadership</h5>
                    <p class="whychoose-desc">
                        Prepare for greater responsibilities in education by strengthening your professional competence, decision-making, and leadership capabilities.
                    </p>
                </div>

                <div class="whychoose-card">
                    <img src="/assets/images/svg/Formula-amico 1.svg" alt="Innovative Educational Practice" class="whychoose-illustration" loading="lazy">
                    <h5 class="whychoose-title">Innovative Educational Practice</h5>
                    <p class="whychoose-desc">
                        Learn to apply evidence-based approaches and emerging educational concepts to address diverse learning needs and improve teaching effectiveness.
                    </p>
                </div>

                <div class="whychoose-card">
                    <img src="/assets/images/svg/Thesis-amico (1) 1.svg" alt="Commitment to Educational Excellence" class="whychoose-illustration" loading="lazy">
                    <h5 class="whychoose-title">Commitment to Educational Excellence</h5>
                    <p class="whychoose-desc">
                        Develop as a reflective and socially responsible educator dedicated to continuous improvement and meaningful contributions to the education sector.
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
                Graduates of the Master of Arts in Education program are equipped with advanced educational knowledge, 
                teaching expertise, and research competencies to pursue rewarding careers in teaching, educational leadership, 
                curriculum development, and academic research.
            </p>

            <div class="career-grid">
                <div class="career-card">
                    <img src="/assets/images/school-principal.png" alt="School Principal" class="career-thumb" loading="lazy">
                    <div class="career-body">
                        <h5 class="career-title">School Principal</h5>
                        <p class="career-desc">
                            Lead schools and academic institutions by overseeing instructional programs, managing faculty, 
                            implementing policies, and driving continuous improvement in educational outcomes.
                        </p>
                    </div>
                </div>

                <div class="career-card">
                    <img src="/assets/images/educ-prog.png" alt="Education Program Manager" class="career-thumb" loading="lazy">
                    <div class="career-body">
                        <h5 class="career-title">Education Program Manager</h5>
                        <p class="career-desc">
                            Plan, implement, and evaluate academic and professional development programs for schools, government agencies, 
                            NGOs, or private educational organizations.
                        </p>
                    </div>
                </div>

                <div class="career-card">
                    <img src="/assets/images/curriculum-instruction.png" alt="Curriculum and Instruction Specialist" class="career-thumb" loading="lazy">
                    <div class="career-body">
                        <h5 class="career-title">Curriculum and Instruction Specialist</h5>
                        <p class="career-desc">
                            Develop, evaluate, and improve curricula and teaching strategies, using educational research and assessment 
                            data to enhance learning programs and instructional effectiveness.
                        </p>
                    </div>
                </div>

                <div class="career-card">
                    <img src="/assets/images/educational-research.png" alt="Educational Researcher" class="career-thumb" loading="lazy">
                    <div class="career-body">
                        <h5 class="career-title">Educational Researcher</h5>
                        <p class="career-desc">
                            Conduct and manage research on teaching, learning, curriculum, and educational systems, providing evidence-based 
                            recommendations that support institutional planning and educational innovation.
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



    <?php include_once __DIR__ . '/../../../../components/index-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
</body>
</html>