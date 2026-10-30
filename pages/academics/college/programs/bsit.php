<?php
define('CURRENT_PROGRAM', 'BSIT');
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
    ['icon' => 'bi:code-slash', 'title' => 'BSIT', 'subtitle' => 'Degree'],
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
    <title>Information Technology</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,100..700,0,0&display=block">
    <link href="/assets/css/gray-theme.css" rel="stylesheet">
    <link href="/assets/css/bulletin-board-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php include_once __DIR__ . '/../../../../components/navbar.php'; ?>

    <section class="hero-page template-hero" style="background-image: url('/assets/images/comsci-hero-placeholder.png');" loading="lazy">
        <div class="container h-100">
        <div class="row align-items-center g-0">
            <div class="col-lg-6">
                <nav class="template-breadcrumb" aria-label="breadcrumb">
                    <a class="gnc-breadcrumb-home" href="/" aria-label="Home" title="Home"><svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 1 0 7h2v8h4v-5h4v5h4V7h2L8 1Z"/></svg></a><i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>ACADEMICS</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/college-departments.php" title="COLLEGE DEPARTMENTS">COLLEGE DEPARTMENTS</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/departments/cba.php" title="<?= htmlspecialchars(mb_strtoupper((publicDepartment('cba')['dept_name'] ?? '') ?: 'COLLEGE OF BUSINESS ADMINISTRATION')) ?>"><?= htmlspecialchars(gncBreadcrumbShortLabel((publicDepartment('cba')['dept_name'] ?? '') ?: 'COLLEGE OF BUSINESS ADMINISTRATION', 'cba')) ?></a>
                    <i class="bi bi-chevron-right"></i>
                    <span title="INFORMATION TECHNOLOGY">BSIT</span>
                </nav>

                <div class="template-hero-content">
                    <h1 class="template-title">INFORMATION TECHNOLOGY</h1>
                    <p class="template-subtitle">Preparing Future IT Professionals and Technology Innovators.</p>
                    <p class="template-desc">
                        The Information Technology program develops skilled and innovative IT professionals equipped with expertise in software development, 
                        system administration, hardware, and emerging technologies to create effective digital solutions for a connected and technology-driven 
                        society.
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
                        The Bachelor of Science in Information Technology program prepares students to become competent IT professionals with practical knowledge and skills in software
                        development, application installation and administration, system maintenance, and hardware operation. It equips graduates to develop, manage, and maintain technology-based 
                        solutions that meet the needs of modern organizations and communities.
                    </p>

                    <div class="template-mission-card" id="our-mission">
                        <div class="template-mission-icon">
                            <?= $renderIcon($missionIcon, 'template-mission-icon-glyph') ?>
                        </div>
                        <div>
                            <h5 class="template-mission-title">Our Mission</h5>
                            <p class="template-mission-text">
                                To develop competent, ethical, and innovative IT professionals equipped with the knowledge, technical skills, communication abilities, and professional values to design and
                                manage technology-based solutions, pursue lifelong learning, and contribute responsibly to society and nation-building.
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
            <h2 class="template-section-title template-section-title--gold">Why Choose BSIT at GNCI?</h2>
            <p class="template-why-intro">
                Our program develops competent and innovative IT professionals equipped with practical knowledge, technical expertise, and ethical values to design, manage, and maintain technology solutions that 
                support organizations, communities, and the digital future.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Computer troubleshooting-amico 1.svg" alt="Comprehensive IT Skills" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Comprehensive IT Skills</h5>
                        <p class="template-why-desc">
                            Build strong technical skills in software development, application management, networking, hardware, and information systems.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Mobile encryption-amico 1.svg" alt="Hands-On Technology Learning" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Hands-On Technology Learning</h5>
                        <p class="template-why-desc">
                            Gain practical experience using current tools, techniques, and technologies essential for success in the IT industry.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/tech company-amico 1.svg" alt="Diverse Career Opportunities" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Diverse Career Opportunities</h5>
                        <p class="template-why-desc">
                            Prepare for careers in software development, technical support, network administration, systems administration, web development, and other IT-related fields.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Printed circuit board-amico 1.svg" alt="Problem-Solving and Innovation" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Problem-Solving and Innovation</h5>
                        <p class="template-why-desc">
                            Develop critical thinking and problem-solving skills to design, implement, and evaluate technology-based solutions for real-world needs.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Private data-amico 1.svg" alt="Professional and Ethical Development" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Professional and Ethical Development</h5>
                        <p class="template-why-desc">
                            Cultivate professionalism, ethical responsibility, cybersecurity awareness, and social responsibility in the use and management of information technology.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Certification-amico (1) 1.svg" alt="Lifelong Learning and Career Readiness" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Lifelong Learning and Career Readiness</h5>
                        <p class="template-why-desc">
                            Develop adaptability, communication skills, and a commitment to continuous learning to thrive in the rapidly evolving technology industry.
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
                Graduates of the BS Information Technology program are equipped with technical expertise, problem-solving skills, and professional knowledge to pursue rewarding careers in software development, 
                information systems, network management, technical support, and other technology-driven fields.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/cloud-solutions-specialist.png" alt="Cloud Solutions Specialist" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Cloud Solutions Specialist</h5>
                            <p class="template-career-desc">
                                Designs, deploys, and manages cloud-based systems and services, helping organizations improve scalability, accessibility, and operational efficiency.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/cybersecurity-analyst.png" alt="Cybersecurity Analyst" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Cybersecurity Analyst</h5>
                            <p class="template-career-desc">
                                Protects computer systems, networks, and data by identifying security threats, monitoring vulnerabilities, and implementing measures to prevent cyberattacks.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/ui-ux-designer.png" alt="UI/UX Designer" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">UI/UX Designer</h5>
                            <p class="template-career-desc">
                                Creates user-friendly and engaging digital interfaces by combining technology, design principles, and user research to improve the overall digital experience.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/systems-and-network-admin.png" alt="Systems and Network Administrator" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Systems and Network Administrator</h5>
                            <p class="template-career-desc">
                                Manages and maintains an organization's IT infrastructure, ensuring reliable network connectivity, system performance, security, and technical operations.
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