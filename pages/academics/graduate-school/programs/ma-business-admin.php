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
    ['icon' => 'material:auto_stories', 'title' => 'MBA', 'subtitle' => 'Degree'],
    ['icon' => 'material:local_library', 'title' => 'Strategic', 'subtitle' => 'Management'],
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
    <title>Master in Business Administration</title>
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
                    <span>MASTER IN BUSINESS ADMINISTRATION</span>
                </nav>

                <div class="template-hero-content">
                    <h1 class="template-title">MA IN BUSINESS ADMINISTRATION</h1>
                    <p class="template-subtitle">Developing Competent Leaders for a Dynamic Global Business Environment.</p>
                    <p class="template-desc">
                        The Master in Business Administration program develops strategic and innovative business leaders equipped to 
                        make sound decisions, manage organizational resources, and drive sustainable growth in a dynamic global business
                        environment.
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
                        The Master in Business Administration program prepares competent and proactive leaders equipped with the strategic knowledge, 
                        management skills, and decision-making capabilities needed to lead organizations, drive business growth, and create positive 
                        social and economic impact.
                    </p>

                    <div class="template-vm-card" id="our-mission">
                        <div class="template-vm-icon">
                            <?= $renderIcon($missionIcon, 'template-vm-icon-glyph') ?>
                        </div>
                        <div>
                            <h5 class="template-vm-card-title">Our Mission</h5>
                            <p class="template-vm-card-text">
                                To develop ethical, innovative, and globally oriented business leaders through excellence in management education, research, 
                                strategic thinking, and collaborative learning, empowering graduates to create sustainable value for organizations and society.
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
            <h2 class="template-section-title template-section-title--gold">Why Choose MBA at GNCI?</h2>
            <p class="whychoose-subtitle">
                Our program develops strategic and innovative business leaders committed to organizational excellence, sustainable growth, and responsible 
                decision-making through advanced management knowledge, analytical thinking, and ethical leadership.
            </p>

            <div class="whychoose-grid">
                <div class="whychoose-card">
                    <img src="/assets/images/svg/Company-amico 1.svg" alt="Strategic Leadership and Management" class="whychoose-illustration" loading="lazy">
                    <h5 class="whychoose-title">Strategic Leadership and Management</h5>
                    <p class="whychoose-desc">
                        Develop advanced leadership and management skills to confidently lead teams, organizations, and business operations.
                    </p>
                </div>

                <div class="whychoose-card">
                    <img src="/assets/images/svg/Hired-amico (1) 1.svg" alt="Diverse Career Opportunities" class="whychoose-illustration" loading="lazy">
                    <h5 class="whychoose-title">Diverse Career Opportunities</h5>
                    <p class="whychoose-desc">
                        Prepare for rewarding careers in business management, entrepreneurship, consulting, finance, marketing, operations, and other industries.
                    </p>
                </div>

                <div class="whychoose-card">
                    <img src="/assets/images/svg/Site Stats-amico 1.svg" alt="Analytical and Strategic Decision-Making" class="whychoose-illustration" loading="lazy">
                    <h5 class="whychoose-title">Analytical and Strategic Decision-Making</h5>
                    <p class="whychoose-desc">
                        Strengthen your ability to analyze complex business situations, evaluate opportunities, and make sound strategic decisions.
                    </p>
                </div>

                <div class="whychoose-card">
                    <img src="/assets/images/svg/International cooperation-amico 1.svg" alt="Global Business Perspective" class="whychoose-illustration" loading="lazy">
                    <h5 class="whychoose-title">Global Business Perspective</h5>
                    <p class="whychoose-desc">
                        Build awareness of global markets, emerging business trends, and opportunities in an increasingly interconnected business environment.
                    </p>
                </div>

                <div class="whychoose-card">
                    <img src="/assets/images/svg/Logic-amico (2) 1.svg" alt="Innovation and Entrepreneurial Thinking" class="whychoose-illustration" loading="lazy">
                    <h5 class="whychoose-title">Innovation and Entrepreneurial Thinking</h5>
                    <p class="whychoose-desc">
                        Cultivate creative and proactive approaches to solving business challenges, developing opportunities, and driving organizational growth.
                    </p>
                </div>

                <div class="whychoose-card">
                    <img src="/assets/images/svg/At the office-amico 1.svg" alt="Professional and Ethical Leadership" class="whychoose-illustration" loading="lazy">
                    <h5 class="whychoose-title">Professional and Ethical Leadership</h5>
                    <p class="whychoose-desc">
                        Develop integrity, teamwork, social responsibility, and ethical leadership values essential for creating sustainable business and societal impact.
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
                    <img src="/assets/images/manager-consultant.png" alt="Management Consultant" class="career-thumb" loading="lazy">
                    <div class="career-body">
                        <h5 class="career-title">Management Consultant</h5>
                        <p class="career-desc">
                            Provide strategic advice to organizations on improving operations, solving complex business challenges, 
                            increasing profitability, and achieving long-term growth.
                        </p>
                    </div>
                </div>

                <div class="career-card">
                    <img src="/assets/images/business-dev-director.png" alt="Business Development Director" class="career-thumb" loading="lazy">
                    <div class="career-body">
                        <h5 class="career-title">Business Development Director</h5>
                        <p class="career-desc">
                            Lead initiatives that identify new markets, partnerships, clients, and revenue opportunities while developing 
                            strategies that support organizational expansion and competitiveness.
                        </p>
                    </div>
                </div>

                <div class="career-card">
                    <img src="/assets/images/corp-strat-manager.png" alt="Corporate Strategy Manager" class="career-thumb" loading="lazy">
                    <div class="career-body">
                        <h5 class="career-title">Corporate Strategy Manager</h5>
                        <p class="career-desc">
                            Develop and implement long-term business strategies by analyzing market trends, competitive environments, 
                            organizational capabilities, and emerging opportunities.
                        </p>
                    </div>
                </div>

                <div class="career-card">
                    <img src="/assets/images/portfolio-manager.png" alt="Investment and Portfolio Manager" class="career-thumb" loading="lazy">
                    <div class="career-body">
                        <h5 class="career-title">Investment and Portfolio Manager</h5>
                        <p class="career-desc">
                            Manage investment portfolios and develop financial strategies by evaluating market opportunities, assessing risks, 
                            and making informed decisions to maximize organizational or client returns.
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