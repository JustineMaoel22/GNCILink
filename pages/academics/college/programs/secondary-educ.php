<?php
define('CURRENT_PROGRAM', 'BSED');
require_once __DIR__ . '/../../../../components/bulletin-board.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secondary Education</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/educ-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php include_once __DIR__ . '/../../../../components/index-nav.php'; ?>

    <section class="hero-page educ-hero" style="background-image: url('/assets/images/elem-educ-hero-placeholder.png');" loading="lazy">
        <div class="container h-100">
        <div class="row align-items-center g-0">
            <div class="col-lg-6">
                <nav class="educ-breadcrumb" aria-label="breadcrumb">
                    <span>ACADEMICS</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/college-departments.php">COLLEGE DEPARTMENTS</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/departments/cased.php">COLLEGE OF ARTS, SCIENCE AND EDUCATION</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>SECONDARY EDUCATION</span>
                </nav>

                <div class="educ-hero-content">
                    <h1 class="educ-title">SECONDARY EDUCATION</h1>
                    <p class="educ-subtitle">Shaping Future-Ready Educators Through Knowledge, Leadership, and <br>Excellence.</p>
                    <p class="educ-desc">
                        The Bachelor of Secondary Education program prepares competent and innovative educators committed to guiding 
                        young learners, advancing quality education, and making a meaningful impact through effective teaching, professional 
                        excellence, and lifelong learning.
                    </p>
                </div>

                <div class="educ-badges">
                    <div class="educ-badge">
                        <img src="/assets/images/svg/grad-hat-blue.svg" alt="Program Icon">
                        <div>
                            <strong>4 Years</strong>
                            <span>Program</span>
                        </div>
                    </div>
                    <div class="educ-badge">
                        <img src="/assets/images/svg/medal-blue.svg" alt="Accredited Icon">
                        <div>
                            <strong>CHED</strong>
                            <span>Accredited</span>
                        </div>
                    </div>
                    <div class="educ-badge">
                        <img src="/assets/images/svg/book1-blue.svg" alt="Degree Icon">
                        <div>
                            <strong>BSEd</strong>
                            <span>Degree</span>
                        </div>
                    </div>
                    <div class="educ-badge">
                        <img src="/assets/images/svg/local_library-blue.svg" alt="Practicum Icon">
                        <div>
                            <strong>Professional</strong>
                            <span>Practice</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div><!-- /.container -->
    </section>

    <section class="educ-about py-5">
        <div class="container">
            <div class="row g-4 align-items-start">
                <!-- Main content -->
                <div class="col-lg-8" id="about-program">
                    <h2 class="educ-section-title educ-section-title--gold">About the Program</h2>
                    <p class="educ-section-text">
                        The Bachelor of Secondary Education is an undergraduate teacher education program that prepares future educators with the knowledge, skills, 
                        and competencies needed to teach effectively in their chosen area of specialization at the secondary level. The program develops learners’ intellectual, 
                        emotional, social, and professional qualities, preparing them to become competent, responsible, and compassionate educators in public and private schools.
                    </p>

                    <div class="educ-mission-card" id="our-mission">
                        <div class="educ-mission-icon">
                            <img src="/assets/images/svg/shield_with_heart-blue.svg" alt="Mission Icon" class="pillar-icon-img" loading="lazy">
                        </div>
                        <div>
                            <h5 class="educ-mission-title">Our Mission</h5>
                            <p class="educ-mission-text">
                                To develop competent, ethical, and innovative secondary educators equipped with strong content knowledge, effective teaching strategies, 
                                and appropriate ICT skills. It aims to prepare graduates to respond to diverse learning needs, uphold professional and cultural values, 
                                pursue lifelong growth, and contribute meaningfully to the advancement of education and society.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Seal -->
                <div class="col-lg-3 d-flex align-items-center justify-content-center">
                    <img src="/assets/images/logos/cased-logo.svg" alt="Guagua National Colleges, Inc. College of Teacher Education Seal" class="educ-seal" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <section class="educ-why py-5" id="why-choose">
        <div class="container">
            <h2 class="educ-section-title educ-section-title--gold">Why Choose BSEd at GNCI?</h2>
            <p class="educ-why-intro">
                Our program develops competent, innovative, and dedicated educators who are committed to excellence in teaching, learner development, 
                and lifelong learning through effective pedagogy, specialized knowledge, and professional practice.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-4 col-md-6">
                    <div class="educ-why-card">
                        <img src="/assets/images/svg/Studying-amico 1.svg" alt="Comprehensive Secondary Teacher Preparation" class="educ-why-img" loading="lazy">
                        <h5 class="educ-why-title">Comprehensive Secondary Teacher Preparation</h5>
                        <p class="educ-why-desc">
                            Build a strong foundation in secondary education through essential knowledge, pedagogical skills, and teaching practices designed to 
                            prepare you for both public and private schools.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="educ-why-card">
                        <img src="/assets/images/svg/Demo-amico 1.svg" alt="Specialized Teaching Opportunities" class="educ-why-img" loading="lazy">
                        <h5 class="educ-why-title">Specialized Teaching Opportunities</h5>
                        <p class="educ-why-desc">
                            Develop expertise in your chosen area of specialization, including Mathematics, English, Filipino, Physical and Biological Sciences, and MAPEH, 
                            while gaining the content knowledge and pedagogy needed to teach high school learners effectively.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="educ-why-card">
                        <img src="/assets/images/svg/college class-amico 1.svg" alt="Diverse and Learner-Centered Teaching" class="educ-why-img" loading="lazy">
                        <h5 class="educ-why-title">Diverse and Learner-Centered Teaching</h5>
                        <p class="educ-why-desc">
                            Learn to facilitate meaningful learning through a wide range of teaching methodologies, delivery modes, assessment strategies, 
                            and instructional approaches suited to diverse learners and learning environments.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="educ-why-card">
                        <img src="/assets/images/svg/Thesis-amico 1.svg" alt="Innovative and Technology-Enhanced Education" class="educ-why-img" loading="lazy">
                        <h5 class="educ-why-title">Innovative and Technology-Enhanced Education</h5>
                        <p class="educ-why-desc">
                            Develop innovative curricula, instructional plans, teaching resources, and approaches while utilizing ICT to promote quality, relevant, 
                            and sustainable educational practices.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="educ-why-card">
                        <img src="/assets/images/svg/Education-amico 1.svg" alt="Professional, Ethical, and Cultural Development" class="educ-why-img" loading="lazy">
                        <h5 class="educ-why-title">Professional, Ethical, and Cultural Development</h5>
                        <p class="educ-why-desc">
                            Develop the qualities of a model teacher by practicing professional and ethical standards, responding to changing societal needs, 
                            and recognizing your responsibility to the local, national, and global community while preserving and promoting Filipino heritage.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="educ-why-card">
                        <img src="/assets/images/svg/college project-amico 1.svg" alt="LET Reinforcement and Lifelong Growth" class="educ-why-img" loading="lazy">
                        <h5 class="educ-why-title">LET Readiness and Lifelong Growth</h5>
                        <p class="educ-why-desc">
                            Receive comprehensive preparation for the Licensure Examination for Teachers (LET) while gaining field-based and experiential opportunities that 
                            encourage continuous personal and professional growth.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="educ-curriculum py-5" id="curriculum">
        <div class="container">
            <h2 class="educ-section-title educ-section-title--gold">Program Curriculum</h2>
            <p class="educ-curriculum-intro">
                <!-- TODO: add curriculum overview / year-by-year breakdown or downloadable curriculum PDF -->
                Curriculum details coming soon.
            </p>
        </div>
    </section>

    <section class="educ-careers py-5" id="careers">
        <div class="container">
            <h2 class="educ-section-title educ-section-title--gold">Career Opportunities</h2>
            <p class="educ-careers-intro">
                Graduates of BSEd are equipped with specialized knowledge, effective teaching strategies, and
                professional competencies to pursue meaningful careers in secondary-level education and beyond.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-6">
                    <div class="educ-career-card">
                        <div class="educ-career-img">
                            <img src="/assets/images/secondary-teacher.png" alt="Secondary School Teacher" loading="lazy">
                        </div>
                        <div class="educ-career-content">
                            <h5 class="educ-career-title">Secondary School Teacher</h5>
                            <p class="educ-career-desc">
                                Guide junior and senior high school learners in their chosen subject
                                specialization.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="educ-career-card">
                        <div class="educ-career-img">
                            <img src="/assets/images/academic-coordinator.png" alt="Academic Coordinator" loading="lazy">
                        </div>
                        <div class="educ-career-content">
                            <h5 class="educ-career-title">Academic Coordinator</h5>
                            <p class="educ-career-desc">
                                Oversee academic programs and support teachers in delivering quality instruction.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="educ-career-card">
                        <div class="educ-career-img">
                            <img src="/assets/images/curriculum-and-instruction-specialist.png" alt="Curriculum & Instruction Specialist" loading="lazy">
                        </div>
                        <div class="educ-career-content">
                            <h5 class="educ-career-title">Curriculum & Instruction Specialist</h5>
                            <p class="educ-career-desc">
                                Design and refine instructional materials and learning programs for secondary
                                schools.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="educ-career-card">
                        <div class="educ-career-img">
                            <img src="/assets/images/education-and-training-specialist.png" alt="Education & Training Specialist" loading="lazy">
                        </div>
                        <div class="educ-career-content">
                            <h5 class="educ-career-title">Education & Training Specialist</h5>
                            <p class="educ-career-desc">
                                Develop training programs and advise institutions on effective teaching
                                practices.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php render_bulletin_board(); ?>

    <section class="educ-cta py-4">
        <div class="container">
            <div class="educ-cta-card">
                <div class="educ-cta-icon">
                    <img src="/assets/images/svg/volunteer_activism-blue.svg" loading="lazy">
                </div>
                <div class="educ-cta-text">
                    <h5 class="educ-cta-title">Make a difference. Begin Your Journey with GNCI.</h5>
                    <p class="educ-cta-desc">Join a community driven by purpose, dedicated to excellence, and built to make an impact.</p>
                </div>
                <div class="educ-cta-action">
                    <a href="/admissions/apply.php" class="educ-cta-btn">Apply Now</a>
                </div>
            </div>
        </div>
    </section>



    <?php include_once __DIR__ . '/../../../../components/index-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
</body>
</html>