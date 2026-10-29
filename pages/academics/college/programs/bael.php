<?php
/**
 * This page's program identifier — the ONLY thing the Bulletin Board
 * needs to know about this page. Change this one line and every part
 * of the Bulletin Board (filtering, "View All" links, empty states)
 * follows automatically.
 */
define('CURRENT_PROGRAM', 'BAEL');

require_once __DIR__ . '/../../../../components/bulletin-board.php';
require_once __DIR__ . '/../../../../components/program-public.php';

// The curriculum PDF and the department seal come from the database, so
// changing them in the Content Editor updates this page automatically.
$currentProgram = publicProgram(CURRENT_PROGRAM);
$programDept    = publicDepartment($currentProgram['dept_slug'] ?? 'cased');
$sealUrl        = $programDept['logo_url'] ?? null;
$sealAlt        = 'Guagua National Colleges, Inc. ' . ($programDept['dept_name'] ?? 'College') . ' Seal';

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
    ['icon' => 'material:auto_stories', 'title' => 'BAEL', 'subtitle' => 'Degree'],
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
    <title>English Language</title>
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
                    <span>ENGLISH LANGUAGE</span>
                </nav>

                <div class="template-hero-content">
                    <h1 class="template-title">ENGLISH LANGUAGE</h1>
                    <p class="template-subtitle">Developing Effective Communicators and Language Professionals for a Global Society.</p>
                    <p class="template-desc">
                        The English Language program develops proficient and adaptable communicators equipped with strong language skills, 
                        critical thinking, and practical expertise to thrive in diverse professional and global contexts.
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
                        The Bachelor of Arts in English Language program develops students’ proficiency in English communication, critical thinking, 
                        and language-related skills. It integrates theory and practice to prepare graduates for effective communication across diverse academic, 
                        professional, and social contexts, while providing a strong foundation for employment and further studies.
                    </p>

                    <div class="template-mission-card" id="our-mission">
                        <div class="template-mission-icon">
                            <?= $renderIcon($missionIcon, 'template-mission-icon-glyph') ?>
                        </div>
                        <div>
                            <h5 class="template-mission-title">Our Mission</h5>
                            <p class="template-mission-text">
                                To develop proficient, ethical, and culturally aware communicators who excel in oral and written communication, 
                                collaborate effectively across diverse fields and cultures, and contribute meaningfully to the preservation and 
                                promotion of Filipino heritage.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Seal -->
                <div class="col-lg-3 d-flex align-items-center justify-content-center">
                    <?php if ($sealUrl): ?>
                    <img src="<?= htmlspecialchars($sealUrl) ?>" alt="<?= htmlspecialchars($sealAlt) ?>" class="template-seal" loading="lazy">
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="template-why py-5" id="why-choose">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold">Why Choose BAEL at GNCI?</h2>
            <p class="template-why-intro">
                Our program develops proficient, critical, and culturally aware English language professionals who communicate effectively across 
                diverse contexts and contribute meaningfully to education, communication, and society through ethical and purposeful practice.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/English teacher-amico 1.svg" alt="Strong English Communication Skills" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Strong English Communication Skills</h5>
                        <p class="template-why-desc">
                            Develop advanced oral and written communication skills for academic, professional, and social settings.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Learning-amico 2.svg" alt="Diverse Career Opportunities" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Diverse Career Opportunities</h5>
                        <p class="template-why-desc">
                            Prepare for careers in education, communication, writing, publishing, media, business, and other language-related fields.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Logic-amico 1.svg" alt="Critical and Analytical Thinking" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Critical and Analytical Thinking</h5>
                        <p class="template-why-desc">
                            Strengthen your ability to analyze language, literature, information, and ideas with clarity and depth.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Women talking-amico 1.svg" alt="Effective Communication Across Cultures" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Effective Communication Across Cultures</h5>
                        <p class="template-why-desc">
                            Learn to communicate confidently and appropriately in diverse, multicultural, and professional environments.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Education-amico 1.svg" alt="Professional and Ethical Development" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Professional and Ethical Development</h5>
                        <p class="template-why-desc">
                            Develop professionalism, social responsibility, and ethical awareness essential for effective communication and collaboration.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Globalization-amico 1.svg" alt="Cultural Awareness and Heritage" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Cultural Awareness and Heritage</h5>
                        <p class="template-why-desc">
                            Gain a deeper appreciation of Filipino language, history, and culture while contributing to their preservation and promotion.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
                Graduates of the Bachelor of Arts in English Language program are equipped with strong communication, critical thinking, and language skills to 
                pursue diverse careers in education, media, communication, writing, and other professional fields.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/english-teacher.png" alt="English Language Teacher" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">English Language Teacher / ESL Instructor</h5>
                            <p class="template-career-desc">
                                Teach English language and communication skills in schools, language centers, and other educational institutions.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/technical-writer.png" alt="Technical Writer" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Technical Writer</h5>
                            <p class="template-career-desc">
                                Write user manuals, guides, and complex documents in simple terms.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/content-writer.png" alt="Content Writer" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Content Writer</h5>
                            <p class="template-career-desc">
                                Develop engaging and informative content for websites, social media, marketing materials, and other digital channels.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/communications-specialist.png" alt="Communications Specialist" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Communications Specialist</h5>
                            <p class="template-career-desc">
                                Apply effective communication skills in organizations by preparing communications, managing content, and supporting public and internal information initiatives.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php
    /**
     * Bulletin Board — auto-filtered to CURRENT_PROGRAM ('BAEL') defined
     * at the top of this file. Nothing else on this page changes when
     * you copy this file for a different program; just update that one
     * define().
     */
    render_bulletin_board();
    ?>

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