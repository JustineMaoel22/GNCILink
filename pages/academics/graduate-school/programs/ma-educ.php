<?php
define('CURRENT_PROGRAM', 'BSBAFM');
require_once __DIR__ . '/../../../../components/bulletin-board.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master of Arts in Education</title>
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
                    <span>MASTER OF ARTS IN EDUCATION</span>
                </nav>

                <div class="grad-school-hero-content">
                    <h1 class="grad-school-title">MA IN EDUCATION</h1>
                    <p class="grad-school-subtitle">Advancing Educational Excellence Through Leadership, Research, and Practice.</p>
                    <p class="grad-school-desc">
                        The Master of Arts in Education program develops knowledgeable, reflective, and research-oriented education 
                        professionals committed to advancing teaching excellence and improving educational practice.
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
                            <strong>MAEd</strong>
                            <span>Degree</span>
                        </div>
                    </div>
                    <div class="grad-school-badge">
                        <img src="/assets/images/svg/local_library-red.svg" alt="Practicum Icon">
                        <div>
                            <strong>Professional</strong>
                            <span>Advancement</span>
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
                        The Master of Arts in Education program strengthens educators’ knowledge, teaching expertise, 
                        and research capabilities, preparing them to advance educational practice and contribute meaningfully 
                        to improved learning outcomes.
                    </p>

                    <div class="grad-school-mission-card" id="our-mission">
                        <div class="grad-school-mission-icon">
                            <img src="/assets/images/svg/shield_with_heart-red.svg" alt="Mission Icon" class="pillar-icon-img" loading="lazy">
                        </div>
                        <div>
                            <h5 class="grad-school-mission-title">Our Mission</h5>
                            <p class="grad-school-mission-text">
                                To empower educators with advanced knowledge, effective teaching expertise, and strong research capabilities 
                                that promote continuous professional growth, innovative educational practice, and meaningful improvements in 
                                teaching and learning.
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
            <h2 class="grad-school-section-title grad-school-section-title--gold">Why Choose MAEd at GNCI?</h2>
            <p class="grad-school-why-intro">
                Our program develops knowledgeable, reflective, and research-oriented educators who are committed to teaching excellence, 
                professional growth, and the continuous improvement of educational practice through advanced knowledge and inquiry.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-4 col-md-6">
                    <div class="grad-school-why-card">
                        <img src="/assets/images/svg/college project-amico (1) 1.svg" alt="Advanced Educational Knowledge" class="grad-school-why-img" loading="lazy">
                        <h5 class="grad-school-why-title">Advanced Educational Knowledge</h5>
                        <p class="grad-school-why-desc">
                            Deepen your understanding of educational theories, practices, and subject-specific knowledge to strengthen your professional expertise.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="grad-school-why-card">
                        <img src="/assets/images/svg/Learning-amico (1) 1.svg" alt="Enhanced Teaching Expertise" class="grad-school-why-img" loading="lazy">
                        <h5 class="grad-school-why-title">Enhanced Teaching Expertise</h5>
                        <p class="grad-school-why-desc">
                            Develop advanced theoretical and technical skills to improve instructional strategies, classroom practice, and learning outcomes.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="grad-school-why-card">
                        <img src="/assets/images/svg/Problem solving-amico (1) 1.svg" alt="Research and Inquiry Skills" class="grad-school-why-img" loading="lazy">
                        <h5 class="grad-school-why-title">Research and Inquiry Skills</h5>
                        <p class="grad-school-why-desc">
                            Build strong research capabilities for analyzing, validating, contextualizing, and applying educational knowledge to real-world challenges.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="grad-school-why-card">
                        <img src="/assets/images/svg/forming team leadership-amico 1.svg" alt="Professional Growth and Leadership" class="grad-school-why-img" loading="lazy">
                        <h5 class="grad-school-why-title">Professional Growth and Leadership</h5>
                        <p class="grad-school-why-desc">
                            Prepare for greater responsibilities in education by strengthening your professional competence, decision-making, and leadership capabilities.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="grad-school-why-card">
                        <img src="/assets/images/svg/Formula-amico 1.svg" alt="Innovative Educational Practice" class="grad-school-why-img" loading="lazy">
                        <h5 class="grad-school-why-title">Innovative Educational Practice</h5>
                        <p class="grad-school-why-desc">
                            Learn to apply evidence-based approaches and emerging educational concepts to address diverse learning needs and improve teaching effectiveness.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="grad-school-why-card">
                        <img src="/assets/images/svg/Thesis-amico (1) 1.svg" alt="Commitment to Educational Excellence" class="grad-school-why-img" loading="lazy">
                        <h5 class="grad-school-why-title">Commitment to Educational Excellence</h5>
                        <p class="grad-school-why-desc">
                            Develop as a reflective and socially responsible educator dedicated to continuous improvement and meaningful contributions to the education sector.
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
                Graduates of the Master of Arts in Education program are equipped with advanced educational knowledge, 
                teaching expertise, and research competencies to pursue rewarding careers in teaching, educational leadership, 
                curriculum development, and academic research.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-6">
                    <div class="grad-school-career-card">
                        <div class="grad-school-career-img">
                            <img src="/assets/images/school-principal.png" alt="School Principal" loading="lazy">
                        </div>
                        <div class="grad-school-career-content">
                            <h5 class="grad-school-career-title">School Principal</h5>
                            <p class="grad-school-career-desc">
                                Lead schools and academic institutions by overseeing instructional programs, managing faculty, 
                                implementing policies, and driving continuous improvement in educational outcomes.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="grad-school-career-card">
                        <div class="grad-school-career-img">
                            <img src="/assets/images/educ-prog.png" alt="Education Program Manager" loading="lazy">
                        </div>
                        <div class="grad-school-career-content">
                            <h5 class="grad-school-career-title">Education Program Manager</h5>
                            <p class="grad-school-career-desc">
                                Plan, implement, and evaluate academic and professional development programs for schools, government agencies, 
                                NGOs, or private educational organizations.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="grad-school-career-card">
                        <div class="grad-school-career-img">
                            <img src="/assets/images/curriculum-instruction.png" alt="Curriculum and Instruction Specialist" loading="lazy">
                        </div>
                        <div class="grad-school-career-content">
                            <h5 class="grad-school-career-title">Curriculum and Instruction Specialist</h5>
                            <p class="grad-school-career-desc">
                                Develop, evaluate, and improve curricula and teaching strategies, using educational research and assessment 
                                data to enhance learning programs and instructional effectiveness.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="grad-school-career-card">
                        <div class="grad-school-career-img">
                            <img src="/assets/images/educational-research.png" alt="Educational Researcher" loading="lazy">
                        </div>
                        <div class="grad-school-career-content">
                            <h5 class="grad-school-career-title">Educational Researcher</h5>
                            <p class="grad-school-career-desc">
                                Conduct and manage research on teaching, learning, curriculum, and educational systems, providing evidence-based 
                                recommendations that support institutional planning and educational innovation.
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