<?php
define('CURRENT_PROGRAM', 'BSCE');
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
    ['icon' => 'material:school', 'title' => '5 Years', 'subtitle' => 'Program'],
    ['icon' => 'material:workspace_premium', 'title' => 'CHED', 'subtitle' => 'Accredited'],
    ['icon' => 'material:settings', 'title' => 'BSCE', 'subtitle' => 'Degree'],
    ['icon' => 'material:medical_information', 'title' => 'On-the-Job', 'subtitle' => 'Training'],
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
    <title>Civil Engineering</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,100..700,0,0&display=block">
    <link href="/assets/css/orange-theme.css" rel="stylesheet">
    <link href="/assets/css/bulletin-board-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php include_once __DIR__ . '/../../../../components/navbar.php'; ?>

    <section class="hero-page template-hero" style="background-image: url('/assets/images/civil-engr-hero-placeholder.png');" loading="lazy">
        <div class="container h-100">
        <div class="row align-items-center g-0">
            <div class="col-lg-6">
                <nav class="template-breadcrumb" aria-label="breadcrumb">
                    <a class="gnc-breadcrumb-home" href="/" aria-label="Home" title="Home"><svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 1 0 7h2v8h4v-5h4v5h4V7h2L8 1Z"/></svg></a><i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>ACADEMICS</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/college-departments.php" title="COLLEGE DEPARTMENTS">COLLEGE DEPARTMENTS</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/departments/coe.php" title="<?= htmlspecialchars(mb_strtoupper((publicDepartment('coe')['dept_name'] ?? '') ?: 'COLLEGE OF ENGINEERING')) ?>"><?= htmlspecialchars(gncBreadcrumbShortLabel((publicDepartment('coe')['dept_name'] ?? '') ?: 'COLLEGE OF ENGINEERING', 'coe')) ?></a>
                    <i class="bi bi-chevron-right"></i>
                    <span title="CIVIL ENGINEERING">BSCE</span>
                </nav>

                <div class="template-hero-content">
                    <h1 class="template-title">CIVIL ENGINEERING</h1>
                    <p class="template-subtitle">Developing Future Civil Engineers and Builders of Sustainable Infrastructure.</p>
                    <p class="template-desc">
                        The Civil Engineering program develops competent and innovative professionals equipped to plan, design, 
                        construct, and maintain sustainable infrastructure that builds safer, stronger, and more connected communities.
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
                        The Civil Engineering program develops competent and innovative professionals equipped to plan, design, construct, and maintain 
                        sustainable infrastructure that builds safer, stronger, and more connected communities.
                    </p>

                    <div class="template-mission-card" id="our-mission">
                        <div class="template-mission-icon">
                            <?= $renderIcon($missionIcon, 'template-mission-icon-glyph') ?>
                        </div>
                        <div>
                            <h5 class="template-mission-title">Our Mission</h5>
                            <p class="template-mission-text">
                                To develop competent, innovative, and ethical civil engineers equipped with strong technical knowledge, problem-solving skills, 
                                and practical expertise to design and develop safe, sustainable, and resilient infrastructure. The program fosters professional 
                                responsibility, effective collaboration, and a commitment to addressing the evolving needs of society and the environment.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Seal -->
                <div class="col-lg-3 d-flex align-items-center justify-content-center">
                    <img src="/assets/images/logos/coe-logo.svg" alt="Guagua National Colleges, Inc. College of Engineering Seal" class="template-seal" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <section class="template-why py-5" id="why-choose">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold">Why Choose BSCE at GNCI?</h2>
            <p class="template-why-intro">
                Our program develops technically proficient and innovative civil engineers who are committed to infrastructure excellence, public safety, 
                and the sustainable advancement of our built environment through rigorous analysis and ethical practice.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Architect-amico 1.svg" alt="Technical Design Expertise" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Technical Design Expertise</h5>
                        <p class="template-why-desc">
                            Master the principles of structural analysis, design, and systems planning required to build safe and efficient infrastructure.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Construction worker-amico 1.svg" alt="Applied Engineering Skills" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Applied Engineering Skills</h5>
                        <p class="template-why-desc">
                            Gain hands-on experience by conducting laboratory experiments and utilizing modern engineering tools essential for professional practice.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Building permit-amico 1.svg" alt="Sustainable Impact Focus" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Sustainable Impact Focus</h5>
                        <p class="template-why-desc">
                            Develop a deep understanding of how civil engineering solutions affect the environment, economy, and society at large.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Construction hat-amico 1.svg" alt="Collaborative Leadership" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Collaborative Leadership</h5>
                        <p class="template-why-desc">
                            Learn to function effectively in multidisciplinary and multicultural teams, preparing you to manage complex projects in diverse work environments.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Building safety-amico 1.svg" alt="Professional Ethics" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Professional Ethics</h5>
                        <p class="template-why-desc">
                            Ground your practice in professional responsibility, ensuring every project meets rigorous ethical standards and regulatory constraints.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Construction costs-amico 1.svg" alt="Licensure Exam Preparation" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Specialized Field Mastery</h5>
                        <p class="template-why-desc">
                            Build focused expertise in at least one specialized branch of civil engineering, enhancing your readiness for immediate industry impact.
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
                Graduates of the BS Civil Engineering program are equipped with strong technical knowledge, engineering expertise, and professional skills to pursue 
                rewarding careers across the construction, infrastructure, transportation, environmental, and structural engineering sectors.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/architectural-and-engineering-manager.png" alt="Architectural and Engineering Manager" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Architectural and Engineering Manager</h5>
                            <p class="template-career-desc">
                                Oversees entire teams of engineers and architects to plan and coordinate complex design projects.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/quantity-surveyor.png" alt="Quantity Surveyor" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Quantity Surveyor</h5>
                            <p class="template-career-desc">
                                Manages the financial and contractual aspects of construction projects, ensuring value for money and strict budget compliance.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/environmental-engineer.png" alt="Environmental Engineer" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Environmental Engineerr</h5>
                            <p class="template-career-desc">
                                Develops solutions to protect the environment, including designing waste management systems, water treatment plants, and sustainability initiatives.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/geotechnical-engineer.png" alt="Geotechnical Engineer" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Geotechnical Engineer</h5>
                            <p class="template-career-desc">
                                Assesses the stability of soil and rock before construction begins to prevent structural failure or landslides.
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