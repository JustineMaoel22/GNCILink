<?php
define('CURRENT_PROGRAM', 'BSAIS');
require_once __DIR__ . '/../../../../components/bulletin-board.php';
require_once __DIR__ . '/../../../../components/program-public.php';
require_once __DIR__ . '/../../../../components/pdf-viewer.php'; // 1. Require the PDF Viewer Component

// 2. Fetch the program data so we can get its pdf_url
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
    ['icon' => 'material:percent_discount', 'title' => 'BSAIS', 'subtitle' => 'Degree'],
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
    <title>Accounting Information System</title>
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
                    <span>ACCOUNTING INFORMATION SYSTEM</span>
                </nav>

                <div class="template-hero-content">
                    <h1 class="template-title">ACCOUNTING INFORMATION<br>SYSTEM</h1>
                    <p class="template-subtitle">Developing Future Certified Public Accountants and Financial Leaders.</p>
                    <p class="template-desc">
                        The Accounting Information System program develops skilled and analytical professionals equipped to 
                        integrate accounting expertise with information technology, enabling efficient financial management, 
                        data-driven decision-making, and innovative business solutions.
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
                        The Bachelor of Science in Accounting Information System is a program that integrates accounting, business, and information technology to 
                        prepare students for professional careers in managing financial information and developing effective accounting systems. The program equips 
                        students with the technical, analytical, and business skills needed to support accurate financial management and technology-driven decision-making.
                    </p>

                    <div class="template-mission-card" id="our-mission">
                        <div class="template-mission-icon">
                            <?= $renderIcon($missionIcon, 'template-mission-icon-glyph') ?>
                        </div>
                        <div>
                            <h5 class="template-mission-title">Our Mission</h5>
                            <p class="template-mission-text">
                                To develop competent, ethical, and adaptable professionals equipped with accounting, technology, communication, and analytical skills 
                                to generate reliable information, support sound decision-making, and contribute responsibly to organizations, the profession, and 
                                Philippine society.
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
            <h2 class="template-section-title template-section-title--gold">Why Choose BSAIS at GNCI?</h2>
            <p class="template-why-intro">
                Our program develops competent and ethical accounting information systems professionals who integrate accounting, business, and technology to 
                manage financial information, support sound decision-making, and contribute responsibly to organizations and society.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Operating system upgrade-amico 1.svg" alt="Technical Accounting Expertise" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Integrated Accounting and Information Technology</h5>
                        <p class="template-why-desc">
                            Build strong competencies in accounting principles, information systems, and business technology for today's digital workplace.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Audit-amico 1.svg" alt="Applied Financial Skills" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Practical Accounting Information Systems Skills</h5>
                        <p class="template-why-desc">
                            Develop the ability to organize, process, analyze, and manage financial data to produce reliable and relevant accounting information.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Personal finance-amico 1.svg" alt="Business Impact Focus" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Diverse Career Opportunities</h5>
                        <p class="template-why-desc">
                            Prepare for careers in accounting information systems, financial management, business analysis, auditing, systems administration, and related fields.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Logic-amico (1) 1.svg" alt="Collaborative Leadership" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Critical and Analytical Thinking</h5>
                        <p class="template-why-desc">
                            Strengthen your ability to analyze financial and business information, solve problems, and support effective organizational decision-making.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Accountant-amico 1.svg" alt="Professional Ethics" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Professional Ethics and Responsibility</h5>
                        <p class="template-why-desc">
                            Develop integrity, ethical judgment, accountability, and professionalism essential to responsible practice in accounting and information systems.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="template-why-card">
                        <img src="/assets/images/svg/Online tech talks-amico 1.svg" alt="Licensure Exam Preparation" class="template-why-img" loading="lazy">
                        <h5 class="template-why-title">Communication and Professional Development</h5>
                        <p class="template-why-desc">
                            Enhance your oral and written communication skills while developing the adaptability and lifelong learning mindset needed in a changing professional environment.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Integrated PDF Viewer here -->
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
                Graduates of the BS Accounting Information System program are equipped with strong accounting, business, information technology, 
                and analytical skills to pursue rewarding careers across accounting, finance, auditing, information systems, and business technology sectors.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/accounting-information-system-specialist.png" alt="Accounting Information Systems Specialist" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Accounting Information Systems Specialist</h5>
                            <p class="template-career-desc">
                                Design, maintain, and improve accounting information systems to ensure accurate, secure, and efficient processing of financial data.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/internal-auditor.png" alt="Internal Auditor" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Internal Auditor</h5>
                            <p class="template-career-desc">
                                Evaluate financial records, internal controls, and business processes to identify risks, ensure compliance, and improve organizational operations.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/financial-systems-analyst.png" alt="Financial Systems Analyst" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Financial Systems Analyst</h5>
                            <p class="template-career-desc">
                                Analyze financial systems and business processes, recommend technology-driven improvements, and help organizations make informed financial decisions.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="template-career-card">
                        <div class="template-career-img">
                            <img src="/assets/images/accounting-software-specialist.png" alt="Accounting Software Specialist" loading="lazy">
                        </div>
                        <div class="template-career-content">
                            <h5 class="template-career-title">Accounting Software Specialist</h5>
                            <p class="template-career-desc">
                                Assist organizations in implementing, configuring, and supporting accounting software while providing technical guidance and user support.
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