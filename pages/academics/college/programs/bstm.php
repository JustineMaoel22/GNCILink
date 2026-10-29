<?php
define('CURRENT_PROGRAM', 'BSTM');
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
    ['icon' => 'material:explore', 'title' => 'BSTM', 'subtitle' => 'Degree'],
    ['icon' => 'material:luggage', 'title' => 'Industry-Ready', 'subtitle' => 'Professionals'],
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
    <title>Tourism Management</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,100..700,0,0&display=block">
    <link href="/assets/css/purple-theme.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/bulletin-board-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php include_once __DIR__ . '/../../../../components/navbar.php'; ?>

    <section class="hero-page template-hero" style="background-image: url('/assets/images/tourism-hero-placeholder.png');" loading="lazy">
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
                    <span title="TOURISM MANAGEMENT">BSTM</span>
                </nav>

                <div class="template-hero-content">
                    <h1 class="template-title">TOURISM MANAGEMENT</h1>
                    <p class="template-subtitle">Developing Future Tourism Professionals and Global Hospitality Leaders.</p>
                    <p class="template-desc">
                        The BS Tourism Management program develops skilled and innovative professionals equipped to create 
                        exceptional travel experiences, manage tourism operations, and contribute to the sustainable growth
                        of the global tourism industry.
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
                        The Bachelor of Science in Tourism Management program prepares students with the knowledge and practical skills needed 
                        for careers in travel and tour operations, tourism planning, events management, transportation services, and 
                        emerging sectors of the tourism industry.
                    </p>

                    <div class="template-mission-card" id="our-mission">
                        <div class="template-mission-icon">
                            <?= $renderIcon($missionIcon, 'template-mission-icon-glyph') ?>
                        </div>
                        <div>
                            <h5 class="template-mission-title">Our Mission</h5>
                            <p class="template-mission-text">
                                To develop competent, ethical, and globally capable tourism professionals who can lead innovative tourism, travel, events, and destination 
                                initiatives while promoting lifelong learning, responsible citizenship, and Filipino pride.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Seal -->
                <div class="col-lg-3 d-flex align-items-center justify-content-center">
                    <img src="/assets/images/logos/cba-logo.svg" alt="Guagua National Colleges, Inc. College of Tourism Management Seal" class="template-seal" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <section class="template-why py-5" id="why-choose">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold">Why Choose BSTM at GNCI?</h2>
            <p class="template-why-intro">
                Our program develops competent and innovative tourism professionals who are committed to delivering exceptional travel experiences, managing tourism and events effectively, 
                promoting sustainable destinations, and advancing the tourism industry through ethical, culturally aware, and globally oriented practice.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Design inspiration-amico 1.svg" alt="Comprehensive Tourism Management Skills" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Comprehensive Tourism Management Skills</h5>
                        <p class="template-why-desc">
                            Develop practical knowledge and skills in tourism planning, travel operations, destination management, events, and related tourism services.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Profiling-amico 1.svg" alt="Diverse Career Opportunities" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Diverse Career Opportunities</h5>
                        <p class="template-why-desc">
                            Prepare for careers in travel and tour operations, hotels and resorts, airlines, events management, cruise tourism, destination marketing, and other growing tourism sectors.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Wedding planner-amico 1.svg" alt="Events and MICE Management" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Events and MICE Management</h5>
                        <p class="template-why-desc">
                            Gain hands-on knowledge in planning, organizing, implementing, and evaluating Meetings, Incentives, Conferences, and Exhibitions (MICE) events.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Tour guide-amico 1.svg" alt="Tourism Management and Leadership" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Tour Guiding and Travel Expertise</h5>
                        <p class="template-why-desc">
                            Build expertise in tour guiding, itinerary development, travel planning, and delivering engaging and memorable experiences for diverse travelers.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Ambassador-amico (1) 1.svg" alt="Global and Cross-Cultural Competence" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Global and Cross-Cultural Competence</h5>
                        <p class="template-why-desc">
                            Develop communication skills, including foreign language proficiency, and learn to work confidently with people from different cultures and backgrounds.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Business vision-amico 1.svg" alt="Ethical and Sustainable Tourism Practice" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Ethical and Sustainable Tourism Practice</h5>
                        <p class="template-why-desc">
                            Cultivate professionalism, ethical standards, responsible citizenship, and appreciation for Filipino culture while promoting sustainable tourism development.
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
            <p class="template-section-desc">The prescribed uniform for BS Tourism Management reflects professionalism, confidence, and readiness for the tourism industry, promoting discipline and helping students embody the standards expected of future tourism professionals.</p>

            <div class="row g-4 justify-content-center mt-2">
                <div class="col-lg-5 col-md-6">
                    <div class="template-uniform-card">
                        <img src="/assets/images/tourism-regunif-female.png" alt="Tourism Management Female Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="template-uniform-card">
                        <img src="/assets/images/tourism-regunif-male.png" alt="Tourism Management Male Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="template-uniform-card">
                        <img src="/assets/images/tourism-corp-female.png" alt="Tourism Management Female Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="template-uniform-card">
                        <img src="/assets/images/tourism-corp-male.png" alt="Tourism Management Male Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="template-careers py-5" id="careers">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold">Career Opportunities</h2>
            <p class="template-careers-intro">
                Graduates of the BS Tourism Management program are equipped with specialized knowledge, practical expertise, and professional skills to pursue rewarding 
                careers across travel operations, destination development, events, and emerging sectors of the tourism industry.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/destination-development.png" alt="Destination Development Specialist" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Destination Development Specialist</h5>
                            <p class="template-career-desc">
                                Plans and develops tourism destinations, attractions, and experiences while promoting sustainable growth, community involvement, and visitor satisfaction.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/mice-and-events-coordinator.png" alt="MICE and Events Coordinator" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">MICE and Events Coordinator</h5>
                            <p class="template-career-desc">
                                Organizes and manages Meetings, Incentives, Conferences, and Exhibitions (MICE), ensuring seamless event planning, coordination, and execution.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/travel-experience-designer.png" alt="Travel Experience Designer" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Travel Experience Designer</h5>
                            <p class="template-career-desc">
                                Creates customized travel itineraries and immersive experiences based on travelers’ interests, cultural preferences, and emerging tourism trends.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/sustainable-tourism-officer.png" alt="Sustainable Tourism Officer" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Sustainable Tourism Officer</h5>
                            <p class="template-career-desc">
                                Develops and implements responsible tourism initiatives that protect natural and cultural resources while supporting local communities and long-term destination sustainability.
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