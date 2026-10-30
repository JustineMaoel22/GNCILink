<?php
define('CURRENT_PROGRAM', 'BSHM');
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
    ['icon' => 'material:room_service', 'title' => 'BSHM', 'subtitle' => 'Degree'],
    ['icon' => 'material:work', 'title' => 'Industry-Ready', 'subtitle' => 'Professionals'],
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
    <title>Hospitality Management</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,100..700,0,0&display=block">
    <link href="/assets/css/pink-theme.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/bulletin-board-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php include_once __DIR__ . '/../../../../components/navbar.php'; ?>

    <section class="hero-page template-hero" style="background-image: url('/assets/images/hospitality-management-image-placeholder.png');" loading="lazy">
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
                    <span title="HOSPITALITY MANAGEMENT">BSHM</span>
                </nav>

                <div class="template-hero-content">
                    <h1 class="template-title">HOSPITALITY MANAGEMENT</h1>
                    <p class="template-subtitle">Developing Future Hospitality Leaders and Service Professionals.</p>
                    <p class="template-desc">
                        The Hospitality Management program develops skilled and service-driven professionals
                        committed to elevating guest experiences and promoting excellence in the hospitality
                        industry.
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
                        The Bachelor of Science in Hospitality Management program prepares students with the knowledge, skills, and competencies needed to excel in food production, accommodation, food and
                        beverage service, and other dynamic areas of the hospitality industry.
                    </p>

                    <div class="template-mission-card" id="our-mission">
                        <div class="template-mission-icon">
                            <?= $renderIcon($missionIcon, 'template-mission-icon-glyph') ?>
                        </div>
                        <div>
                            <h5 class="template-mission-title">Our Mission</h5>
                            <p class="template-mission-text">
                                To develop competent, ethical, and service-oriented hospitality professionals equipped with industry-relevant skills in food and beverage, housekeeping, accommodation, and 
                                operations management, while promoting lifelong learning, responsible citizenship, and pride in being Filipino.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Seal -->
                <div class="col-lg-3 d-flex align-items-center justify-content-center">
                    <img src="/assets/images/logos/cba-logo.svg" alt="Guagua National Colleges, Inc. College of Hospitality Management Seal" class="template-seal" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <section class="template-why py-5" id="why-choose">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold">Why Choose BSHM at GNCI?</h2>
            <p class="template-why-intro">
                Our program develops competent, service-oriented, and industry-ready hospitality professionals equipped with practical expertise in hospitality operations, management, 
                and guest services while upholding professionalism, ethics, and excellence.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Catering service-amico 1.svg" alt="Industry-Ready Hospitality Skills" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Industry-Ready Hospitality Skills</h5>
                        <p class="template-why-desc">
                            Develop practical skills in food production, housekeeping, accommodation, food and beverage service, and hospitality operations.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Hired-amico 1.svg" alt="Diverse Career Opportunities" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Diverse Career Opportunities</h5>
                        <p class="template-why-desc">
                            Prepare for careers in hotels, resorts, restaurants, cruise lines, events, tourism, and other growing hospitality sectors.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/female chef-amico 1.svg" alt="Hands-On Learning Experience" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Hands-On Learning Experience</h5>
                        <p class="template-why-desc">
                            Gain real-world experience through practical training that builds confidence, competence, and service excellence.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Selecting team-amico 1.svg" alt="Hospitality Management and Leadership" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Hospitality Management and Leadership</h5>
                        <p class="template-why-desc">
                            Strengthen your ability to manage people, operations, resources, and services according to industry standards.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Supermarket workers-amico 1.svg" alt="Safety, Ethics and Professionalism" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Safety, Ethics and Professionalism</h5>
                        <p class="template-why-desc">
                            Develop strong values in risk management, workplace safety, ethical conduct, and responsible hospitality service.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Connected world-amico 1.svg" alt="Filipino Pride and Global Perspective" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Filipino Pride and Global Perspective</h5>
                        <p class="template-why-desc">
                            Appreciate Filipino hospitality and culture while developing the skills needed to thrive in local and international hospitality environments.
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

    <section class="template-uniform py-5" id="uniform">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold">Uniform</h2>
            <p class="template-section-desc">The prescribed uniform for BS Hospitality Management reflects professionalism, confidence, and readiness for hospitality practice, promoting discipline and helping 
                students embody the standards expected of future hospitality professionals.</p>

            <div class="row g-4 justify-content-center mt-2">
                <div class="col-lg-5 col-md-6">
                    <div class="template-uniform-card">
                        <img src="/assets/images/hm-regunif-female.png" alt="Hospitality Management Female Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="template-uniform-card">
                        <img src="/assets/images/hm-regunif-male.png" alt="Hospitality Management Male Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="template-uniform-card">
                        <img src="/assets/images/hm-cheflab-female.png" alt="Hospitality Management Female Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="template-uniform-card">
                        <img src="/assets/images/hm-cheflab-male.png" alt="Hospitality Management Male Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="template-uniform-card">
                        <img src="/assets/images/hm-corp-female.png" alt="Hospitality Management Female Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="template-uniform-card">
                        <img src="/assets/images/hm-corp-male.png" alt="Hospitality Management Male Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="template-careers py-5" id="careers">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold">Career Opportunities</h2>
            <p class="template-careers-intro">
                Graduates of BS Hospitality Management are equipped with knowledge, skills, and genuine service
                to pursue fulfilling careers in a variety of hospitality and tourism settings.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/revenue-management-specialist.png" alt="Revenue Management Specialist" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Revenue Management Specialist</h5>
                            <p class="template-career-desc">
                                Analyzes room rates, booking patterns, and market trends to maximize hotel revenue and occupancy through 
                                strategic pricing.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/guest-experience-manager.png" alt="Guest Experience Manager" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Guest Experience Manager</h5>
                            <p class="template-career-desc">
                                Designs and manages exceptional guest experiences by improving service quality, handling feedback, and ensuring customer 
                                satisfaction.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/cruise-hospitality-supervisor.png" alt="Cruise Hospitality Supervisor" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Cruise Hospitality Supervisor</h5>
                            <p class="template-career-desc">
                                Oversees guest services, accommodation, and food and beverage operations aboard cruise ships while maintaining 
                                hospitality standards.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/event-coordinator.png" alt="Events Coordinator" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Events Coordinator</h5>
                            <p class="template-career-desc">
                                Plans, coordinates, and executes corporate events, weddings, conferences, and special occasions while managing clients, 
                                suppliers, and event operations.
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

    <!-- Uniform Lightbox -->
    <div class="uniform-lightbox" id="uniformLightbox" onclick="closeUniformLightbox()">
        <span class="uniform-lightbox-close" onclick="closeUniformLightbox()">&times;</span>
        <img class="uniform-lightbox-img" id="uniformLightboxImg" src="" alt="">
        <p class="uniform-lightbox-caption" id="uniformLightboxCaption"></p>
    </div>

    <?php include_once __DIR__ . '/../../../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>

    <script>
        function openUniformLightbox(src, caption) {
            const lightbox = document.getElementById('uniformLightbox');
            const img = document.getElementById('uniformLightboxImg');
            const captionEl = document.getElementById('uniformLightboxCaption');
            img.src = src;
            captionEl.textContent = caption;
            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeUniformLightbox() {
            const lightbox = document.getElementById('uniformLightbox');
            lightbox.classList.remove('active');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeUniformLightbox();
        });
    </script>

</body>
</html>