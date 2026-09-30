<?php
define('CURRENT_PROGRAM', 'BSCS');
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
    ['icon' => 'bi:code-slash', 'title' => 'BSCS', 'subtitle' => 'Degree'],
    ['icon' => 'bi:cpu', 'title' => 'Professional', 'subtitle' => 'Computing Practice'],
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
    <title>Computer Science</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,100..700,0,0&display=block">
    <link href="/assets/css/gray-theme.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php include_once __DIR__ . '/../../../../components/index-nav.php'; ?>

    <section class="hero-page template-hero" style="background-image: url('/assets/images/comsci-hero-placeholder.png');" loading="lazy">
        <div class="container h-100">
        <div class="row align-items-center g-0">
            <div class="col-lg-6">
                <nav class="template-breadcrumb" aria-label="breadcrumb">
                    <span>ACADEMICS</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/college-departments.php">COLLEGE DEPARTMENTS</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/departments/cba.php">COLLEGE OF BUSINESS ADMINISTRATION</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>COMPUTER SCIENCE</span>
                </nav>

                <div class="template-hero-content">
                    <h1 class="template-title">COMPUTER SCIENCE</h1>
                    <p class="template-subtitle">Developing Future Computer Scientists and Innovative Computing Professionals.</p>
                    <p class="template-desc">
                        The Computer Science program develops proficient and innovative computing professionals equipped to design, develop, 
                        and implement intelligent solutions that address complex technological challenges and advance the digital world.
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
                        The Bachelor of Science in Computer Science program prepares students to become skilled computing professionals and researchers. It develops strong 
                        foundations in programming, software development, problem-solving, and emerging technologies, equipping graduates to design innovative computing solutions 
                        for real-world challenges.
                    </p>

                    <div class="template-mission-card" id="our-mission">
                        <div class="template-mission-icon">
                            <?= $renderIcon($missionIcon, 'template-mission-icon-glyph') ?>
                        </div>
                        <div>
                            <h5 class="template-mission-title">Our Mission</h5>
                            <p class="template-mission-text">
                                To develop competent, innovative, and ethical computing professionals who can solve complex problems, design effective technological solutions, 
                                communicate effectively, pursue lifelong learning, and contribute responsibly to society and national development.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Seal -->
                <div class="col-lg-3 d-flex align-items-center justify-content-center">
                    <img src="/assets/images/logos/cba-logo.svg" alt="Guagua National Colleges, Inc. College of Computer Studies Seal" class="template-seal" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <section class="template-why py-5" id="why-choose">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold">Why Choose BSCS at GNCI?</h2>
            <p class="template-why-intro">
                Our program develops competent and innovative computing professionals who are committed to sound technical decision-making, ethical development practices, 
                innovation, and sustainable technology growth through strong analytical and practical skills.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Operating system-amico 2.svg" alt="Strong Programming and Software Skills" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Strong Programming and Software Skills</h5>
                        <p class="template-why-desc">
                            Build a solid foundation in programming, algorithms, data structures, databases, and core computing concepts essential for modern technology careers.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Visionary technology-amico 1.svg" alt="Innovative Problem-Solving" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Innovative Problem-Solving</h5>
                        <p class="template-why-desc">
                            Develop the ability to analyze complex problems, design efficient solutions, and apply computing principles to real-world challenges.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Cyborg-amico 1.svg" alt="Diverse Career Opportunities" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Diverse Career Opportunities</h5>
                        <p class="template-why-desc">
                            Prepare for careers in software development, web and mobile development, data science, cybersecurity, systems analysis, research, and other technology-driven fields.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Deconstructed robot-amico 1.svg" alt="Emerging Technology and Innovation" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Emerging Technology and Innovation</h5>
                        <p class="template-why-desc">
                            Explore emerging technologies and innovative computing practices that prepare you to adapt to the rapidly evolving digital landscape.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Private data-amico 1.svg" alt="Ethical and Professional Development" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Ethical and Professional Development</h5>
                        <p class="template-why-desc">
                            Develop professional, ethical, legal, and social awareness essential for responsible technology development and effective collaboration.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Researching-amico 1.svg" alt="Lifelong Learning and Research" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Lifelong Learning and Research</h5>
                        <p class="template-why-desc">
                            Cultivate curiosity, critical thinking, and continuous learning skills needed to pursue advanced studies, research, and long-term professional growth.
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
                Graduates of the BS Computer Science program are equipped with strong technical knowledge, problem-solving abilities, and professional skills to pursue rewarding 
                careers across software development, information technology, data, cybersecurity, and other computing-related fields.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/software-developer.png" alt="Software Developer" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Software Developer</h5>
                            <p class="template-career-desc">
                                Design, develop, test, and maintain software applications and systems that address organizational and user needs.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/web-and-mobile-application-developer.png" alt="Web & Mobile Application Developer" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Web & Mobile Application Developer</h5>
                            <p class="template-career-desc">
                                Create responsive websites and mobile applications using modern programming languages, development tools, and user-centered design principles.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/data-analyst.png" alt="Data Analyst" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Data Analyst</h5>
                            <p class="template-career-desc">
                                Collect, process, and analyze data to generate meaningful insights that support informed decision-making and organizational strategies.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/systems-analyst.png" alt="Systems Analyst" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Systems Analyst</h5>
                            <p class="template-career-desc">
                                Evaluate business and computing requirements, design appropriate system solutions, and help organizations improve their technology processes 
                                and operations.
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

    <?php include_once __DIR__ . '/../../../../components/index-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
</body>
</html>