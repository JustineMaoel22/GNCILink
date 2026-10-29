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
    ['icon' => 'material:auto_stories', 'title' => 'MPA', 'subtitle' => 'Degree'],
    ['icon' => 'material:local_library', 'title' => 'Public', 'subtitle' => 'Leadership'],
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
    <title>Master of Arts in Public Administration</title>
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
                    <span>MASTER OF ARTS IN PUBLIC ADMINISTRATION</span>
                </nav>

                <div class="template-hero-content">
                    <h1 class="template-title">MA IN PUBLIC ADMINISTRATION</h1>
                    <p class="template-subtitle">Shaping Future Public Leaders for Effective Governance and Service.</p>
                    <p class="template-desc">
                        The Master in Public Administration program develops competent and strategic public leaders committed 
                        to effective governance, responsive public service, and meaningful community development.
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
                        The Master in Public Administration program develops competent, effective, and proactive public 
                        leaders and administrators equipped with the knowledge and skills to navigate complex public-sector 
                        challenges. Through advanced learning in governance, policy development, research, technology, and public 
                        management, the program prepares graduates to formulate and implement responsive policies, lead public 
                        organizations, and contribute meaningfully to community and societal development.
                    </p>

                    <div class="template-vm-card" id="our-mission">
                        <div class="template-vm-icon">
                            <?= $renderIcon($missionIcon, 'template-vm-icon-glyph') ?>
                        </div>
                        <div>
                            <h5 class="template-vm-card-title">Our Mission</h5>
                            <p class="template-vm-card-text">
                                To cultivate competent and ethical public administrators equipped with advanced knowledge, leadership, research, analytical, 
                                and technological skills to promote effective governance, responsive public service, and sustainable community development.
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
            <h2 class="template-section-title template-section-title--gold">Why Choose MPA at GNCI?</h2>
            <p class="whychoose-subtitle">
                Our program develops knowledgeable, reflective, and research-oriented educators who are committed to teaching excellence, 
                professional growth, and the continuous improvement of educational practice through advanced knowledge and inquiry.
            </p>

            <div class="whychoose-grid">
                <div class="whychoose-card">
                    <div class="skeleton-wrap">
                        <img src="/assets/images/svg/follow the leader-amico 1.svg" alt="Public Leadership and Governance" class="whychoose-illustration" loading="lazy">
                    </div>
                    <h5 class="whychoose-title">Public Leadership and Governance</h5>
                    <p class="whychoose-desc">
                        Develop the leadership and administrative skills needed to effectively manage public institutions and public-oriented organizations.
                    </p>
                </div>

                <div class="whychoose-card">
                    <div class="skeleton-wrap">
                        <img src="/assets/images/svg/Law firm-amico 1.svg" alt="Policy Development and Implementation" class="whychoose-illustration" loading="lazy">
                    </div>
                    <h5 class="whychoose-title">Policy Development and Implementation</h5>
                    <p class="whychoose-desc">
                        Build the knowledge and skills to formulate, analyze, and implement policies that address community and societal needs.
                    </p>
                </div>

                <div class="whychoose-card">
                    <div class="skeleton-wrap">
                        <img src="/assets/images/svg/Research paper-amico 1.svg" alt="Advanced Research and Analytical Skills" class="whychoose-illustration" loading="lazy">
                    </div>
                    <h5 class="whychoose-title">Advanced Research and Analytical Skills</h5>
                    <p class="whychoose-desc">
                        Strengthen research, critical thinking, and problem-solving abilities for addressing complex issues in public administration.
                    </p>
                </div>

                <div class="whychoose-card">
                    <div class="skeleton-wrap">
                        <img src="/assets/images/svg/Analysis-amico 1.svg" alt="Technology-Driven Public Administration" class="whychoose-illustration" loading="lazy">
                    </div>
                    <h5 class="whychoose-title">Technology-Driven Public Administration</h5>
                    <p class="whychoose-desc">
                        Learn to effectively use emerging technologies and information systems to support informed decision-making and efficient public service.
                    </p>
                </div>

                <div class="whychoose-card">
                    <div class="skeleton-wrap">
                        <img src="/assets/images/svg/Logic-amico (2) 1.svg" alt="Strategic Management and Decision-Making" class="whychoose-illustration" loading="lazy">
                    </div>
                    <h5 class="whychoose-title">Strategic Management and Decision-Making</h5>
                    <p class="whychoose-desc">
                        Enhance your ability to analyze challenges, manage resources, and make sound decisions in dynamic public-sector environments.
                    </p>
                </div>

                <div class="whychoose-card">
                    <div class="skeleton-wrap">
                        <img src="/assets/images/svg/International cooperation-amico 1.svg" alt="Social, Economic and Political Awareness" class="whychoose-illustration" loading="lazy">
                    </div>
                    <h5 class="whychoose-title">Social, Economic and Political Awareness</h5>
                    <p class="whychoose-desc">
                        Develop a deeper understanding of the social, economic, and political factors that shape governance, public policy, and community development.
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
                Graduates of the Master in Public Administration program are equipped with advanced leadership, analytical, and management skills 
                to pursue rewarding careers across government agencies, public institutions, non-government organizations, and public-oriented sectors.
            </p>

            <div class="career-grid">
                <div class="career-card">
                    <img src="/assets/images/public-admin.png" alt="Public Administrator" class="career-thumb" loading="lazy">
                    <div class="career-body">
                        <h5 class="career-title">Public Administrator</h5>
                        <p class="career-desc">
                            Lead government offices and public institutions by managing programs, resources, personnel, and public services while 
                            ensuring effective and accountable administration.
                        </p>
                    </div>
                </div>

                <div class="career-card">
                    <img src="/assets/images/policy-adviser.png" alt="Policy Adviser" class="career-thumb" loading="lazy">
                    <div class="career-body">
                        <h5 class="career-title">Policy Adviser</h5>
                        <p class="career-desc">
                            Research and evaluate public policies, analyze social and economic issues, and provide evidence-based recommendations 
                            to support effective government decision-making.
                        </p>
                    </div>
                </div>

                <div class="career-card">
                    <img src="/assets/images/local-gov-admin.png" alt="Local Government Administrator" class="career-thumb" loading="lazy">
                    <div class="career-body">
                        <h5 class="career-title">Local Government Administrator</h5>
                        <p class="career-desc">
                            Manage local government operations, programs, and community initiatives while promoting efficient service delivery, 
                            good governance, and sustainable local development.
                        </p>
                    </div>
                </div>

                <div class="career-card">
                    <img src="/assets/images/project-manager.png" alt="Program or Project Manager" class="career-thumb" loading="lazy">
                    <div class="career-body">
                        <h5 class="career-title">Program or Project Manager</h5>
                        <p class="career-desc">
                            Plan, implement, and evaluate public-sector programs and development projects, coordinating stakeholders and
                            resources to achieve organizational and community goals.
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