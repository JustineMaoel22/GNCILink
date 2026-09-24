<?php
define('CURRENT_PROGRAM', 'BSMLS');
require_once __DIR__ . '/../../../../components/bulletin-board.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Laboratory Science</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/medtech-style.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/bulletin-board-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php include_once __DIR__ . '/../../../../components/index-nav.php'; ?>

    <section class="hero-page medtech-hero" style="background-image: url('/assets/images/medtech-hero.png');" loading="lazy">
        <div class="container h-100">
        <div class="row align-items-center g-0">
            <div class="col-lg-6">
                <nav class="medtech-breadcrumb" aria-label="breadcrumb">
                    <span>ACADEMICS</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/college-departments.php">COLLEGE DEPARTMENTS</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/departments/camp.php">COLLEGE OF MEDICAL TECHNOLOGY AND PHARMACY</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>BS IN MEDICAL LABORATORY SCIENCE</span>
                </nav>

                <div class="medtech-hero-content">
                    <h1 class="medtech-title">MEDICAL LABORATORY SCIENCE</h1>
                    <p class="medtech-subtitle">Precision in Science. Excellence in Diagnosis. Commitment to Healthcare.</p>
                    <p class="medtech-desc">
                        The Medical Laboratory Science program develops analytical and precision-driven professionals committed 
                        to delivering accurate diagnostic insights and advancing excellence in healthcare.
                    </p>
                </div>

                <div class="medtech-badges">
                    <div class="medtech-badge">
                        <img src="/assets/images/svg/grad-hat-green.svg" alt="Graduation Cap Icon" class="medtech-badge-icon" loading="lazy">
                        <div>
                            <strong>4 Years</strong>
                            <span>Program</span>
                        </div>
                    </div>
                    <div class="medtech-badge">
                        <img src="/assets/images/svg/medal-green.svg" alt="Award Icon" class="medtech-badge-icon" loading="lazy">
                        <div>
                            <strong>CHED</strong>
                            <span>Accredited</span>
                        </div>
                    </div>
                    <div class="medtech-badge">
                        <img src="/assets/images/svg/stethoscope-green.svg" alt="Stethoscope Icon" class="medtech-badge-icon" loading="lazy">
                        <div>
                            <strong>BSML</strong>
                            <span>Degree</span>
                        </div>
                    </div>
                    <div class="medtech-badge">
                        <img src="/assets/images/svg/medical_information-green.svg" alt="Medical Info Icon" class="medtech-badge-icon" loading="lazy">
                        <div>
                            <strong>Clinical</strong>
                            <span>Experience</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div><!-- /.container -->
    </section>

    <section class="medtech-about py-5">
        <div class="container">

            <!-- About / Vision & Mission + Seal row -->
            <div class="row g-4 align-items-start">
                <!-- Main content -->
                <div class="col-lg-8" id="about-program">
                    <h2 class="medtech-section-title medtech-section-title--gold">About the Program</h2>
                    <p class="medtech-section-text">
                        The Bachelor of Science in Medical Laboratory Science (BSMLS) program
                        develops competent, ethical, and skilled professionals essential to
                        disease detection, diagnosis, and patient care. Through rigorous scientific
                        training and clinical practice, students gain the expertise to deliver
                        accurate diagnostic results and contribute to better health outcomes.
                    </p>

                    <h3 class="medtech-section-title medtech-section-title--gold mt-4" id="vision-mission">Vision &amp; Mission</h3>

                    <div class="medtech-vm-stack">
                        <div class="medtech-vm-card">
                            <div class="medtech-vm-icon">
                                <span class="medtech-vm-icon-mask medtech-vm-icon-mask--vision" role="img" aria-label="Vision icon"></span>
                            </div>
                            <div>
                                <h5 class="medtech-vm-card-title">Vision</h5>
                                <p class="medtech-vm-card-text">
                                    The College of Medical Laboratory Science aspires to be a preeminent center of education,
                                    dedicated to nurturing highly skilled and research-driven medical technologists.
                                </p>
                            </div>
                        </div>

                        <div class="medtech-vm-card">
                            <div class="medtech-vm-icon">
                                <span class="medtech-vm-icon-mask medtech-vm-icon-mask--mission" role="img" aria-label="Mission icon"></span>
                            </div>
                            <div>
                                <h5 class="medtech-vm-card-title">Mission</h5>
                                <p class="medtech-vm-card-text">
                                    To foster the growth of a new generation of medical technologists who excel in applying
                                    analytical and critical thinking skills to advance healthcare through precise diagnostics
                                    and pioneering research.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seal -->
                <div class="col-lg-3 d-flex align-items-center justify-content-center">
                    <img src="/assets/images/svg/medtech-logo.svg" alt="Guagua National Colleges, Inc. College of Nursing Seal" class="medtech-seal" loading="lazy">
                </div>
            </div>
            <!-- /About / Vision & Mission + Seal row -->

            <!-- Core Values: own full-width row so cards aren't squeezed beside the seal column -->
            <div class="row g-4">
                <div class="col-12">
                    <h3 class="medtech-section-title medtech-section-title--gold mt-4" id="core-values">Core Values</h3>
                    <p class="corevalue-subtitle">Guiding our commitment to excellence in education, research, and healthcare</p>

                    <div class="corevalue-stack">
                        <div class="corevalue-card">
                            <div class="corevalue-icon"><img src="/assets/images/svg/trophy.svg" alt="" class="corevalue-icon-img"></div>
                            <div class="corevalue-titlegroup">
                                <span class="corevalue-letter">M</span>
                                <h5 class="corevalue-title">Mastery</h5>
                            </div>
                            <p class="corevalue-text">Striving for excellence in every aspect of education, research, and professional practice.</p>
                        </div>

                        <div class="corevalue-card">
                            <div class="corevalue-icon"><img src="/assets/images/svg/finance_mode.svg" alt="" class="corevalue-icon-img"></div>
                            <div class="corevalue-titlegroup">
                                <span class="corevalue-letter">E</span>
                                <h5 class="corevalue-title">Empowerment</h5>
                            </div>
                            <p class="corevalue-text">Empowering our students and faculty to take ownership of their learning and teaching, fostering a culture of independence and growth.</p>
                        </div>

                        <div class="corevalue-card">
                            <div class="corevalue-icon"><img src="/assets/images/svg/psychology.svg" alt="" class="corevalue-icon-img"></div>
                            <div class="corevalue-titlegroup">
                                <span class="corevalue-letter">D</span>
                                <h5 class="corevalue-title">Dedication</h5>
                            </div>
                            <p class="corevalue-text">Dedicated to the pursuit of knowledge, the enhancement of skills, and the betterment of healthcare through unwavering commitment.</p>
                        </div>

                        <div class="corevalue-card">
                            <div class="corevalue-icon"><img src="/assets/images/svg/group-people.svg" alt="" class="corevalue-icon-img"></div>
                            <div class="corevalue-titlegroup">
                                <span class="corevalue-letter">T</span>
                                <h5 class="corevalue-title">Teamwork</h5>
                            </div>
                            <p class="corevalue-text">Foster a collaborative and supportive environment, valuing teamwork and effective communication among students, faculty, and the healthcare community.</p>
                        </div>

                        <div class="corevalue-card">
                            <div class="corevalue-icon"><img src="/assets/images/svg/balance.svg" alt="" class="corevalue-icon-img"></div>
                            <div class="corevalue-titlegroup">
                                <span class="corevalue-letter">E</span>
                                <h5 class="corevalue-title">Ethics</h5>
                            </div>
                            <p class="corevalue-text">Uphold the highest ethical standards, promoting integrity, honesty, and responsibility in all our endeavors.</p>
                        </div>

                        <div class="corevalue-card">
                            <div class="corevalue-icon"><img src="/assets/images/svg/category.svg" alt="" class="corevalue-icon-img"></div>
                            <div class="corevalue-titlegroup">
                                <span class="corevalue-letter">C</span>
                                <h5 class="corevalue-title">Creativity</h5>
                            </div>
                            <p class="corevalue-text">Encouraging creativity and innovation in research, teaching methods, and problem-solving, contributing to advancements in medical laboratory science.</p>
                        </div>

                        <div class="corevalue-card">
                            <div class="corevalue-icon"><img src="/assets/images/svg/diversity_1.svg" alt="" class="corevalue-icon-img"></div>
                            <div class="corevalue-titlegroup">
                                <span class="corevalue-letter">H</span>
                                <h5 class="corevalue-title">Humanity</h5>
                            </div>
                            <p class="corevalue-text">We recognize the importance of empathy and compassion in healthcare, promoting a human-centered approach in all interactions and practices.</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Core Values -->

        </div>
    </section>

<section class="medtech-why py-5">
        <div class="container">
            <h2 class="medtech-section-title medtech-section-title--gold" id="why-choose">Why Choose BSMLS at GNCI?</h2>
            <p class="whychoose-subtitle">
                Our program develops analytical and precision-driven professionals who are committed to clinical excellence,
                technical competence, and the advancement of healthcare through evidence-based laboratory diagnostics and
                compassionate service.
            </p>

            <div class="whychoose-grid">
                <div class="whychoose-card">
                    <img src="/assets/images/svg/Doctors-amico 1.svg" alt="" class="whychoose-illustration">
                    <h5 class="whychoose-title">Advanced Clinical Competence</h5>
                    <p class="whychoose-desc">Master the performance of rigorous laboratory tests for accurate diagnosis, treatment, and disease management using modern equipment.</p>
                </div>

                <div class="whychoose-card">
                    <img src="/assets/images/svg/Vaccine development-amico 1.svg" alt="" class="whychoose-illustration">
                    <h5 class="whychoose-title">Evidence-Based Diagnostics</h5>
                    <p class="whychoose-desc">Develop analytical skills in specimen collection, results validation, and health information management to ensure high-quality patient care.</p>
                </div>

                <div class="whychoose-card">
                    <img src="/assets/images/svg/Science-amico 2.svg" alt="" class="whychoose-illustration">
                    <h5 class="whychoose-title">Inter-professional Collaboration</h5>
                    <p class="whychoose-desc">Learn to work effectively in multi-disciplinary healthcare teams, emphasizing leadership, ethical practice, and interpersonal communication.</p>
                </div>

                <div class="whychoose-card">
                    <img src="/assets/images/svg/Researchers-amico 1.svg" alt="" class="whychoose-illustration">
                    <h5 class="whychoose-title">Research-Oriented Inquiry</h5>
                    <p class="whychoose-desc">Apply scientific research skills and critical thinking to develop innovations in laboratory science and broader healthcare management.</p>
                </div>

                <div class="whychoose-card">
                    <img src="/assets/images/svg/Doctor-amico 1.svg" alt="" class="whychoose-illustration">
                    <h5 class="whychoose-title">Safety &amp; Quality Standards</h5>
                    <p class="whychoose-desc">Master strict biosafety protocols, waste management practices, and ethical standards essential for a professional medical laboratory environment.</p>
                </div>

                <div class="whychoose-card">
                    <img src="/assets/images/svg/Doctor-amico 1.svg" alt="" class="whychoose-illustration">
                    <h5 class="whychoose-title">Global Professional Readiness</h5>
                    <p class="whychoose-desc">Build the competencies required for local licensure (MTLE) and engage in lifelong professional development for global healthcare opportunities.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="medtech-uniform py-5" id="medtech-uniform">
        <div class="container">
            <h2 class="medtech-section-title medtech-section-title--gold">Uniform</h2>
            <p>The prescribed uniform for BS Medical Laboratory Science reflects professionalism, cleanliness, and readiness for laboratory practice, it promotes discipline and helps students embody 
                the standards expected of future medical laboratory professionals.</p>

            <div class="row g-4 justify-content-center mt-2">
                <div class="col-lg-5 col-md-6">
                    <div class="medtech-uniform-card">
                        <img src="/assets/images/medtech-unif-female.png" alt="Medtech Female Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="medtech-uniform-card">
                        <img src="/assets/images/medtech-unif-male.png" alt="Medtech Male Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="medtech-uniform-card">
                        <img src="/assets/images/medtech-unif-3rd&4th.png" alt="Medtech 3rd and 4th Year Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="medtech-career py-5">
        <div class="container">
            <h2 class="medtech-section-title medtech-section-title--gold" id="career-opportunities">Career Opportunities</h2>

            <div class="career-grid">
                <div class="career-card">
                    <img src="/assets/images/rmt.png" alt="Registered Medical Technologist" class="career-thumb">
                    <div class="career-body">
                        <h5 class="career-title">Registered Medical Technologist (RMT)</h5>
                        <p class="career-desc">Performs complex clinical laboratory tests on biological samples that provides essential data for diagnosis, treatment, and monitoring of diseases.</p>
                    </div>
                </div>

                <div class="career-card">
                    <img src="/assets/images/mls.png" alt="Molecular Laboratory Scientist" class="career-thumb">
                    <div class="career-body">
                        <h5 class="career-title">Molecular Laboratory Scientist</h5>
                        <p class="career-desc">Conducts complex genetic and molecular testing to detect and characterize inherited, malignant, and infectious diseases.</p>
                    </div>
                </div>

                <div class="career-card">
                    <img src="/assets/images/das.png" alt="Diagnostic Application Specialist" class="career-thumb">
                    <div class="career-body">
                        <h5 class="career-title">Diagnostic Application Specialist</h5>
                        <p class="career-desc">Provides product demonstrations, software setup, and hands-on training for complex laboratory analyzers and medical devices.</p>
                    </div>
                </div>

                <div class="career-card">
                    <img src="/assets/images/qao.png" alt="Quality Assurance Officer" class="career-thumb">
                    <div class="career-body">
                        <h5 class="career-title">Quality Assurance Officer</h5>
                        <p class="career-desc">Ensures testing procedures and medical equipment strictly comply with regulatory and accreditation standards.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php render_bulletin_board(); ?>

    <section class="medtech-cta py-4">
        <div class="container">
            <div class="medtech-cta-card">
                <div class="medtech-cta-icon">
                    <img src="/assets/images/svg/volunteer_activism-green.svg" class="hand-icon"loading="lazy">
                </div>
                <div class="medtech-cta-text">
                    <h5 class="medtech-cta-title">Make a difference. Begin Your Journey with GNCI.</h5>
                    <p class="medtech-cta-desc">Join a community driven by purpose, dedicated to excellence, and built to make an impact.</p>
                </div>
                <div class="medtech-cta-action">
                    <a href="/admissions/apply.php" class="medtech-cta-btn">Apply Now</a>
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