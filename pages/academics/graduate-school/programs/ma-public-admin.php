<?php
define('CURRENT_PROGRAM', 'BSBAFM');
require_once __DIR__ . '/../../../../components/bulletin-board.php';
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
    <link href="/assets/css/grad-school-prog.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php include_once __DIR__ . '/../../../../components/index-nav.php'; ?>

    <section class="hero-page grad-school-hero" style="background-image: url('/assets/images/phd-ineduc-mgt-placeholder.png');" loading="lazy">
        <div class="container h-100">
        <div class="row align-items-center g-0">
            <div class="col-lg-6">
                <nav class="grad-school-breadcrumb" aria-label="breadcrumb">
                    <span>ACADEMICS</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/graduate-school/graduate-school.php">GRADUATE SCHOOL</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>MASTER OF ARTS IN PUBLIC ADMINISTRATION</span>
                </nav>

                <div class="grad-school-hero-content">
                    <h1 class="grad-school-title">MA IN PUBLIC ADMINISTRATION</h1>
                    <p class="grad-school-subtitle">Shaping Future Public Leaders for Effective Governance and Service.</p>
                    <p class="grad-school-desc">
                        The Master in Public Administration program develops competent and strategic public leaders committed 
                        to effective governance, responsive public service, and meaningful community development.
                    </p>
                </div>

                <div class="grad-school-badges">
                    <div class="grad-school-badge">
                        <img src="/assets/images/svg/grad-hat-red.svg" alt="Program Icon">
                        <div>
                            <strong>Trimestral</strong>
                            <span>Program</span>
                        </div>
                    </div>
                    <div class="grad-school-badge">
                        <img src="/assets/images/svg/medal-red.svg" alt="Accredited Icon">
                        <div>
                            <strong>CHED</strong>
                            <span>Accredited</span>
                        </div>
                    </div>
                    <div class="grad-school-badge">
                        <img src="/assets/images/svg/auto_stories-red.svg" alt="Degree Icon">
                        <div>
                            <strong>MPA</strong>
                            <span>Degree</span>
                        </div>
                    </div>
                    <div class="grad-school-badge">
                        <img src="/assets/images/svg/local_library-red.svg" alt="Practicum Icon">
                        <div>
                            <strong>Public</strong>
                            <span>Leadership</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /.container -->
    </section>

    <section class="grad-school-about py-5">
        <div class="container">
            <div class="row g-4 align-items-start">
                <!-- Main content -->
                <div class="col-lg-8" id="about-program">
                    <h2 class="grad-school-section-title grad-school-section-title--gold">About the Program</h2>
                    <p class="grad-school-section-text">
                        The Master in Public Administration program develops competent, effective, and proactive public 
                        leaders and administrators equipped with the knowledge and skills to navigate complex public-sector 
                        challenges. Through advanced learning in governance, policy development, research, technology, and public 
                        management, the program prepares graduates to formulate and implement responsive policies, lead public 
                        organizations, and contribute meaningfully to community and societal development.
                    </p>

                    <div class="grad-school-mission-card" id="our-mission">
                        <div class="grad-school-mission-icon">
                            <img src="/assets/images/svg/shield_with_heart-red.svg" alt="Mission Icon" class="pillar-icon-img" loading="lazy">
                        </div>
                        <div>
                            <h5 class="grad-school-mission-title">Our Mission</h5>
                            <p class="grad-school-mission-text">
                                To cultivate competent and ethical public administrators equipped with advanced knowledge, leadership, research, analytical, 
                                and technological skills to promote effective governance, responsive public service, and sustainable community development.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Seal -->
                <div class="col-lg-3 d-flex align-items-center justify-content-center">
                    <img src="/assets/images/logos/gnc-logo-v1.svg" alt="Guagua National Colleges, Inc. Seal" class="grad-school-seal" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <section class="grad-school-why py-5" id="why-choose">
        <div class="container">
            <h2 class="grad-school-section-title grad-school-section-title--gold">Why Choose MPA at GNCI?</h2>
            <p class="grad-school-why-intro">
                Our program develops knowledgeable, reflective, and research-oriented educators who are committed to teaching excellence, 
                professional growth, and the continuous improvement of educational practice through advanced knowledge and inquiry.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-4 col-md-6">
                    <div class="grad-school-why-card">
                        <img src="/assets/images/svg/follow the leader-amico 1.svg" alt="Public Leadership and Governance" class="grad-school-why-img" loading="lazy">
                        <h5 class="grad-school-why-title">Public Leadership and Governance</h5>
                        <p class="grad-school-why-desc">
                            Develop the leadership and administrative skills needed to effectively manage public institutions and public-oriented organizations.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="grad-school-why-card">
                        <img src="/assets/images/svg/Law firm-amico 1.svg" alt="Policy Development and Implementation" class="grad-school-why-img" loading="lazy">
                        <h5 class="grad-school-why-title">Policy Development and Implementation</h5>
                        <p class="grad-school-why-desc">
                            Build the knowledge and skills to formulate, analyze, and implement policies that address community and societal needs.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="grad-school-why-card">
                        <img src="/assets/images/svg/Research paper-amico 1.svg" alt="Advanced Research and Analytical Skills" class="grad-school-why-img" loading="lazy">
                        <h5 class="grad-school-why-title">Advanced Research and Analytical Skills</h5>
                        <p class="grad-school-why-desc">
                            Strengthen research, critical thinking, and problem-solving abilities for addressing complex issues in public administration.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="grad-school-why-card">
                        <img src="/assets/images/svg/Analysis-amico 1.svg" alt="Technology-Driven Public Administration" class="grad-school-why-img" loading="lazy">
                        <h5 class="grad-school-why-title">Technology-Driven Public Administration</h5>
                        <p class="grad-school-why-desc">
                            Learn to effectively use emerging technologies and information systems to support informed decision-making and efficient public service.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="grad-school-why-card">
                        <img src="/assets/images/svg/Logic-amico (2) 1.svg" alt="Strategic Management and Decision-Making" class="grad-school-why-img" loading="lazy">
                        <h5 class="grad-school-why-title">Strategic Management and Decision-Making</h5>
                        <p class="grad-school-why-desc">
                            Enhance your ability to analyze challenges, manage resources, and make sound decisions in dynamic public-sector environments.
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="grad-school-why-card">
                        <img src="/assets/images/svg/International cooperation-amico 1.svg" alt="Social, Economic and Political Awareness" class="grad-school-why-img" loading="lazy">
                        <h5 class="grad-school-why-title">Social, Economic and Political Awareness</h5>
                        <p class="grad-school-why-desc">
                            Develop a deeper understanding of the social, economic, and political factors that shape governance, public policy, and community development.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="grad-school-curriculum py-5" id="curriculum">
        <div class="container">
            <h2 class="grad-school-section-title grad-school-section-title--gold">Program Curriculum</h2>
            <p class="grad-school-curriculum-intro">
                <!-- TODO: add curriculum overview / year-by-year breakdown or downloadable curriculum PDF -->
                Curriculum details coming soon.
            </p>
        </div>
    </section>

    <section class="grad-school-careers py-5" id="careers">
        <div class="container">
            <h2 class="grad-school-section-title grad-school-section-title--gold">Career Opportunities</h2>
            <p class="grad-school-careers-intro">
                Graduates of the Master in Public Administration program are equipped with advanced leadership, analytical, and management skills 
                to pursue rewarding careers across government agencies, public institutions, non-government organizations, and public-oriented sectors.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-6">
                    <div class="grad-school-career-card">
                        <div class="grad-school-career-img">
                            <img src="/assets/images/public-admin.png" alt="Public Administrator" loading="lazy">
                        </div>
                        <div class="grad-school-career-content">
                            <h5 class="grad-school-career-title">Public Administrator</h5>
                            <p class="grad-school-career-desc">
                                Lead government offices and public institutions by managing programs, resources, personnel, and public services while 
                                ensuring effective and accountable administration.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="grad-school-career-card">
                        <div class="grad-school-career-img">
                            <img src="/assets/images/policy-adviser.png" alt="Policy Adviser" loading="lazy">
                        </div>
                        <div class="grad-school-career-content">
                            <h5 class="grad-school-career-title">Policy Adviser</h5>
                            <p class="grad-school-career-desc">
                                Research and evaluate public policies, analyze social and economic issues, and provide evidence-based recommendations 
                                to support effective government decision-making.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="grad-school-career-card">
                        <div class="grad-school-career-img">
                            <img src="/assets/images/local-gov-admin.png" alt="Local Government Administrator" loading="lazy">
                        </div>
                        <div class="grad-school-career-content">
                            <h5 class="grad-school-career-title">Local Government Administrator</h5>
                            <p class="grad-school-career-desc">
                                Manage local government operations, programs, and community initiatives while promoting efficient service delivery, 
                                good governance, and sustainable local development.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="grad-school-career-card">
                        <div class="grad-school-career-img">
                            <img src="/assets/images/project-manager.png" alt="Program or Project Manager" loading="lazy">
                        </div>
                        <div class="grad-school-career-content">
                            <h5 class="grad-school-career-title">Program or Project Manager</h5>
                            <p class="grad-school-career-desc">
                                Plan, implement, and evaluate public-sector programs and development projects, coordinating stakeholders and
                                resources to achieve organizational and community goals.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php render_bulletin_board(); ?>

    <section class="grad-school-cta py-4">
        <div class="container">
            <div class="grad-school-cta-card">
                <div class="grad-school-cta-icon">
                    <img src="/assets/images/svg/volunteer_activism-red.svg" loading="lazy">
                </div>
                <div class="grad-school-cta-text">
                    <h5 class="grad-school-cta-title">Make a difference. Begin Your Journey with GNCI.</h5>
                    <p class="grad-school-cta-desc">Join a community driven by purpose, dedicated to excellence, and built to make an impact.</p>
                </div>
                <div class="grad-school-cta-action">
                    <a href="/admissions/apply.php" class="grad-school-cta-btn">Apply Now</a>
                </div>
            </div>
        </div>
    </section>



    <?php include_once __DIR__ . '/../../../../components/index-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
</body>
</html>