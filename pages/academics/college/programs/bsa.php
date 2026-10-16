<?php
define('CURRENT_PROGRAM', 'BSA');
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
    ['icon' => 'material:percent_discount', 'title' => 'BSA', 'subtitle' => 'Degree'],
    ['icon' => 'material:account_balance', 'title' => 'Accounting', 'subtitle' => 'Expertise'],
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
    <title>Accountancy</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,100..700,0,0&display=block">
    <link href="/assets/css/yellow-theme.css" rel="stylesheet">
    <link href="/assets/css/bulletin-board-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php include_once __DIR__ . '/../../../../components/navbar.php'; ?>

    <section class="hero-page template-hero" style="background-image: url('/assets/images/accounting-hero-placeholder.png');" loading="lazy">
        <div class="container h-100">
        <div class="row align-items-center g-0">
            <div class="col-lg-6">
                <nav class="template-breadcrumb" aria-label="breadcrumb">
                    <span>ACADEMICS</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/college-departments.php">COLLEGE DEPARTMENTS</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/departments/coa.php"><?= htmlspecialchars(mb_strtoupper((publicDepartment('coa')['dept_name'] ?? '') ?: 'COLLEGE OF ACCOUNTANCY')) ?></a>
                    <i class="bi bi-chevron-right"></i>
                    <span>ACCOUNTANCY</span>
                </nav>

                <div class="template-hero-content">
                    <h1 class="template-title">ACCOUNTANCY</h1>
                    <p class="template-subtitle">Developing Future Certified Public Accountants and Financial Leaders.</p>
                    <p class="template-desc">
                        The BS Accountancy program develops competent and ethical accounting professionals equipped with strong analytical, financial, and 
                        business skills to support sound decision-making and contribute to the success of organizations and the broader business community.
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
                        The Bachelor of Science in Accountancy is a program that provides quality accounting education and develops students' 
                        technical, analytical, and professional skills. It prepares future accountants to confidently address complex financial challenges, 
                        uphold professional ethics, and contribute responsibly to organizations and society.
                    </p>

                    <div class="template-mission-card" id="our-mission">
                        <div class="template-mission-icon">
                            <?= $renderIcon($missionIcon, 'template-mission-icon-glyph') ?>
                        </div>
                        <div>
                            <h5 class="template-mission-title">Our Mission</h5>
                            <p class="template-mission-text">
                                To develop competent, ethical, and globally minded accounting professionals equipped with strong technical, analytical, communication, 
                                and problem-solving skills, committed to professional excellence, responsible citizenship, and ethical service to organizations and society.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Seal -->
                <div class="col-lg-3 d-flex align-items-center justify-content-center">
                    <img src="/assets/images/logos/coa-logo.svg" alt="Guagua National Colleges, Inc. College of Accountancy Seal" class="template-seal" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <section class="template-why py-5" id="why-choose">
        <div class="container">
            <h2 class="template-section-title template-section-title--gold">Why Choose BSA at GNCI?</h2>
            <p class="template-why-intro">
                Our program develops competent, ethical, and globally minded accounting professionals equipped with strong technical, analytical, and 
                communication skills to address financial challenges and contribute responsibly to organizations and society.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Analytics-amico 1.svg" alt="Comprehensive Accounting Education" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Comprehensive Accounting Education</h5>
                        <p class="template-why-desc">
                            Build a strong foundation in financial accounting, taxation, auditing, management accounting, and accounting information systems.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Finance leaders-amico 1.svg" alt="Professional and Career Readiness" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Professional and Career Readiness</h5>
                        <p class="template-why-desc">
                            Develop the ability to organize, process, analyze, and manage financial data to produce reliable and relevant accounting information.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Logic-amico (1) 1.svg" alt="Critical and Analytical Thinking" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Critical and Analytical Thinking</h5>
                        <p class="template-why-desc">
                            Strengthen your ability to analyze financial information, resolve business problems, and make sound strategic decisions.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Accountant-amico 1.svg" alt="Ethical and Responsible Practice" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Ethical and Responsible Practice</h5>
                        <p class="template-why-desc">
                            Develop integrity, professionalism, and ethical judgment essential to responsible accounting practice and good corporate citizenship.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Financial data-amico 1.svg" alt="Global and Strategic Perspective" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Global and Strategic Perspective</h5>
                        <p class="template-why-desc">
                            Gain the knowledge and skills to address complex business challenges and adapt to evolving local and global business environments.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Online tech talks-amico 1.svg" alt="Effective Communication Skills" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Effective Communication Skills</h5>
                        <p class="template-why-desc">
                            Develop clear and confident oral and written communication skills in English and Filipino for professional and organizational settings.
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
                Graduates of the BS Accountancy program are equipped with strong accounting knowledge, analytical skills, and professional 
                competence to pursue rewarding careers across accounting, finance, auditing, taxation, and business sectors.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/certified-public-accountant.png" alt="Certified Public Accountant (CPA)" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Certified Public Accountant (CPA)</h5>
                            <p class="template-career-desc">
                                Provide professional accounting, auditing, taxation, and financial reporting services to organizations and clients while ensuring compliance with applicable standards and regulations.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/external-auditor.png" alt="External Auditor" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">External Auditor</h5>
                            <p class="template-career-desc">
                                Examine financial records and statements, assess internal controls, and provide independent assurance on the accuracy and reliability of financial information.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/management-accountant.png" alt="Management Accountant" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Management Accountant</h5>
                            <p class="template-career-desc">
                                Analyze financial data, prepare budgets and reports, and provide insights that support effective planning, cost management, and strategic business decisions.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/tax-accountant.png" alt="Tax Accountant" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Tax Accountant</h5>
                            <p class="template-career-desc">
                                Prepare and review tax returns, provide tax-related advice, and help individuals and organizations comply with tax laws while managing their tax obligations effectively.
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
</body>
</html>