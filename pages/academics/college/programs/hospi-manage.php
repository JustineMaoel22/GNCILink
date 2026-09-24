<?php
define('CURRENT_PROGRAM', 'BSHM');
require_once __DIR__ . '/../../../../components/bulletin-board.php';
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
    <link href="/assets/css/hospi-manage-style.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/bulletin-board-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php include_once __DIR__ . '/../../../../components/index-nav.php'; ?>

    <section class="hero-page hospitality-hero" style="background-image: url('/assets/images/hospitality-management-image-placeholder.png');" loading="lazy">
        <div class="container h-100">
        <div class="row align-items-center g-0">
            <div class="col-lg-6">
                <nav class="hospitality-breadcrumb" aria-label="breadcrumb">
                    <span>ACADEMICS</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/college-departments.php">COLLEGE DEPARTMENTS</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/departments/cba.php">COLLEGE OF BUSINESS ADMINISTRATION</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>HOSPITALITY MANAGEMENT</span>
                </nav>

                <div class="hospitality-hero-content">
                    <h1 class="hospitality-title">HOSPITALITY MANAGEMENT</h1>
                    <p class="hospitality-subtitle">Developing Future Hospitality Leaders and Service Professionals.</p>
                    <p class="hospitality-desc">
                        The Hospitality Management program develops skilled and service-driven professionals
                        committed to elevating guest experiences and promoting excellence in the hospitality
                        industry.
                    </p>
                </div>

                <div class="hospitality-badges">
                    <div class="hospitality-badge">
                        <img src="/assets/images/svg/grad-hat-pink.svg" alt="Graduation Cap Icon" class="hospitality-badge-icon" loading="lazy">
                        <div>
                            <strong>4 Years</strong>
                            <span>Program</span>
                        </div>
                    </div>
                    <div class="hospitality-badge">
                        <img src="/assets/images/svg/medal-pink.svg" alt="Award Icon" class="hospitality-badge-icon" loading="lazy">
                        <div>
                            <strong>CHED</strong>
                            <span>Accredited</span>
                        </div>
                    </div>
                    <div class="hospitality-badge">
                        <img src="/assets/images/svg/concierge-pink.svg" alt="Degree Icon" class="hospitality-badge-icon" loading="lazy">
                        <div>
                            <strong>BSHM</strong>
                            <span>Degree</span>
                        </div>
                    </div>
                    <div class="hospitality-badge">
                        <img src="/assets/images/svg/work-pink.svg" alt="Industry Icon" class="hospitality-badge-icon" loading="lazy">
                        <div>
                            <strong>Industry-Ready</strong>
                            <span>Professionals</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div><!-- /.container -->
    </section>

    <section class="hospitality-about py-5">
        <div class="container">
            <div class="row g-4 align-items-start">
                <!-- Main content -->
                <div class="col-lg-8" id="about-program">
                    <h2 class="hospitality-section-title hospitality-section-title--gold">About the Program</h2>
                    <p class="hospitality-section-text">
                        The Bachelor of Science in Hospitality Management program prepares students with the knowledge, skills, and competencies needed to excel in food production, accommodation, food and
                        beverage service, and other dynamic areas of the hospitality industry.
                    </p>

                    <div class="hospitality-mission-card" id="our-mission">
                        <div class="hospitality-mission-icon">
                            <img src="/assets/images/svg/shield_with_heart-pink.svg" class="pillar-icon-img" loading="lazy">            
                        </div>
                        <div>
                            <h5 class="hospitality-mission-title">Our Mission</h5>
                            <p class="hospitality-mission-text">
                                To develop competent, ethical, and service-oriented hospitality professionals equipped with industry-relevant skills in food and beverage, housekeeping, accommodation, and 
                                operations management, while promoting lifelong learning, responsible citizenship, and pride in being Filipino.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Seal -->
                <div class="col-lg-3 d-flex align-items-center justify-content-center">
                    <img src="/assets/images/logos/cba-logo.svg" alt="Guagua National Colleges, Inc. College of Hospitality Management Seal" class="hospitality-seal" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <section class="hospitality-why py-5" id="why-choose">
        <div class="container">
            <h2 class="hospitality-section-title hospitality-section-title--gold">Why Choose BSHM at GNCI?</h2>
            <p class="hospitality-why-intro">
                Our program develops competent, service-oriented, and industry-ready hospitality professionals equipped with practical expertise in hospitality operations, management, 
                and guest services while upholding professionalism, ethics, and excellence.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-4 col-md-6">
                    <div class="hospitality-why-card">
                        <img src="/assets/images/svg/Catering service-amico 1.svg" alt="Industry-Ready Hospitality Skills" class="hospitality-why-img" loading="lazy">
                        <h5 class="hospitality-why-title">Industry-Ready Hospitality Skills</h5>
                        <p class="hospitality-why-desc">
                            Develop practical skills in food production, housekeeping, accommodation, food and beverage service, and hospitality operations.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="hospitality-why-card">
                        <img src="/assets/images/svg/Hired-amico 1.svg" alt="Diverse Career Opportunities" class="hospitality-why-img" loading="lazy">
                        <h5 class="hospitality-why-title">Diverse Career Opportunities</h5>
                        <p class="hospitality-why-desc">
                            Prepare for careers in hotels, resorts, restaurants, cruise lines, events, tourism, and other growing hospitality sectors.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="hospitality-why-card">
                        <img src="/assets/images/svg/female chef-amico 1.svg" alt="Hands-On Learning Experience" class="hospitality-why-img" loading="lazy">
                        <h5 class="hospitality-why-title">Hands-On Learning Experience</h5>
                        <p class="hospitality-why-desc">
                            Gain real-world experience through practical training that builds confidence, competence, and service excellence.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="hospitality-why-card">
                        <img src="/assets/images/svg/Selecting team-amico 1.svg" alt="Hospitality Management and Leadership" class="hospitality-why-img" loading="lazy">
                        <h5 class="hospitality-why-title">Hospitality Management and Leadership</h5>
                        <p class="hospitality-why-desc">
                            Strengthen your ability to manage people, operations, resources, and services according to industry standards.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="hospitality-why-card">
                        <img src="/assets/images/svg/Supermarket workers-amico 1.svg" alt="Safety, Ethics and Professionalism" class="hospitality-why-img" loading="lazy">
                        <h5 class="hospitality-why-title">Safety, Ethics and Professionalism</h5>
                        <p class="hospitality-why-desc">
                            Develop strong values in risk management, workplace safety, ethical conduct, and responsible hospitality service.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="hospitality-why-card">
                        <img src="/assets/images/svg/Connected world-amico 1.svg" alt="Filipino Pride and Global Perspective" class="hospitality-why-img" loading="lazy">
                        <h5 class="hospitality-why-title">Filipino Pride and Global Perspective</h5>
                        <p class="hospitality-why-desc">
                            Appreciate Filipino hospitality and culture while developing the skills needed to thrive in local and international hospitality environments.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="hospitality-uniform py-5" id="uniform">
        <div class="container">
            <h2 class="hospitality-section-title hospitality-section-title--gold">Uniform</h2>
            <p class="hospitality-section-desc">The prescribed uniform for BS Hospitality Management reflects professionalism, confidence, and readiness for hospitality practice, promoting discipline and helping 
                students embody the standards expected of future hospitality professionals.</p>

            <div class="row g-4 justify-content-center mt-2">
                <div class="col-lg-5 col-md-6">
                    <div class="hospitality-uniform-card">
                        <img src="/assets/images/hm-regunif-female.png" alt="Hospitality Management Female Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="hospitality-uniform-card">
                        <img src="/assets/images/hm-regunif-male.png" alt="Hospitality Management Male Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="hospitality-uniform-card">
                        <img src="/assets/images/hm-cheflab-female.png" alt="Hospitality Management Female Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="hospitality-uniform-card">
                        <img src="/assets/images/hm-cheflab-male.png" alt="Hospitality Management Male Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="hospitality-uniform-card">
                        <img src="/assets/images/hm-corp-female.png" alt="Hospitality Management Female Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="hospitality-uniform-card">
                        <img src="/assets/images/hm-corp-male.png" alt="Hospitality Management Male Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="hospitality-careers py-5" id="careers">
        <div class="container">
            <h2 class="hospitality-section-title hospitality-section-title--gold">Career Opportunities</h2>
            <p class="hospitality-careers-intro">
                Graduates of BS Hospitality Management are equipped with knowledge, skills, and genuine service
                to pursue fulfilling careers in a variety of hospitality and tourism settings.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-6">
                    <div class="hospitality-career-card">
                        <div class="hospitality-career-img">
                            <img src="/assets/images/revenue-management-specialist.png" alt="Revenue Management Specialist" loading="lazy">
                        </div>
                        <div class="hospitality-career-content">
                            <h5 class="hospitality-career-title">Revenue Management Specialist</h5>
                            <p class="hospitality-career-desc">
                                Analyzes room rates, booking patterns, and market trends to maximize hotel revenue and occupancy through 
                                strategic pricing.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="hospitality-career-card">
                        <div class="hospitality-career-img">
                            <img src="/assets/images/guest-experience-manager.png" alt="Guest Experience Manager" loading="lazy">
                        </div>
                        <div class="hospitality-career-content">
                            <h5 class="hospitality-career-title">Guest Experience Manager</h5>
                            <p class="hospitality-career-desc">
                                Designs and manages exceptional guest experiences by improving service quality, handling feedback, and ensuring customer 
                                satisfaction.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="hospitality-career-card">
                        <div class="hospitality-career-img">
                            <img src="/assets/images/cruise-hospitality-supervisor.png" alt="Cruise Hospitality Supervisor" loading="lazy">
                        </div>
                        <div class="hospitality-career-content">
                            <h5 class="hospitality-career-title">Cruise Hospitality Supervisor</h5>
                            <p class="hospitality-career-desc">
                                Oversees guest services, accommodation, and food and beverage operations aboard cruise ships while maintaining 
                                hospitality standards.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="hospitality-career-card">
                        <div class="hospitality-career-img">
                            <img src="/assets/images/event-coordinator.png" alt="Events Coordinator" loading="lazy">
                        </div>
                        <div class="hospitality-career-content">
                            <h5 class="hospitality-career-title">Events Coordinator</h5>
                            <p class="hospitality-career-desc">
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

    <section class="hospitality-cta py-4">
        <div class="container">
            <div class="hospitality-cta-card">
                <div class="hospitality-cta-icon">
                    <img src="/assets/images/svg/volunteer_activism-pink.svg" class="hand-icon"loading="lazy">
                </div>
                <div class="hospitality-cta-text">
                    <h5 class="hospitality-cta-title">Make a difference. Begin Your Journey with GNCI.</h5>
                    <p class="hospitality-cta-desc">Join a community driven by purpose, dedicated to excellence, and built to make an impact.</p>
                </div>
                <div class="hospitality-cta-action">
                    <a href="/admissions/apply.php" class="hospitality-cta-btn">Apply Now</a>
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

    <?php include_once __DIR__ . '/../../../../components/index-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>

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