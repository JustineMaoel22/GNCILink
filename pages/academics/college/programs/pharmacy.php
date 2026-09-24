<?php
define('CURRENT_PROGRAM', 'BSPh');
require_once __DIR__ . '/../../../../components/bulletin-board.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/pharmacy-style.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/bulletin-board-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php include_once __DIR__ . '/../../../../components/index-nav.php'; ?>

    <section class="hero-page pharmacy-hero" style="background-image: url('/assets/images/pharmacy-hero-placeholder.png');" loading="lazy">
        <div class="container h-100">
        <div class="row align-items-center g-0">
            <div class="col-lg-6">
                <nav class="pharmacy-breadcrumb" aria-label="breadcrumb">
                    <span>ACADEMICS</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/college-departments.php">COLLEGE DEPARTMENTS</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/departments/camp.php">COLLEGE OF MEDICAL TECHNOLOGY AND PHARMACY</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>PHARMACY</span>
                </nav>

                <div class="pharmacy-hero-content">
                    <h1 class="pharmacy-title">PHARMACY</h1>
                    <p class="pharmacy-subtitle">Advancing Healthcare Through Medicines, Innovation, and Patient Care.</p>
                    <p class="pharmacy-desc">
                        The Pharmacy program develops knowledgeable and patient-centered professionals committed to ensuring medication safety
                        and optimizing health outcomes.
                    </p>
                </div>

                <div class="pharmacy-badges">
                    <div class="pharmacy-badge">
                        <img src="/assets/images/svg/grad-hat-purple.svg" alt="Graduation Cap Icon" class="pharmacy-badge-icon" loading="lazy">
                        <div>
                            <strong>4 Years</strong>
                            <span>Program</span>
                        </div>
                    </div>
                    <div class="pharmacy-badge">
                        <img src="/assets/images/svg/medal-purple.svg" alt="Award Icon" class="pharmacy-badge-icon" loading="lazy">
                        <div>
                            <strong>CHED</strong>
                            <span>Accredited</span>
                        </div>
                    </div>
                    <div class="pharmacy-badge">
                        <img src="/assets/images/svg/microscope-purple.svg" alt="Degree Icon" class="pharmacy-badge-icon" loading="lazy">
                        <div>
                            <strong>BSPh</strong>
                            <span>Degree</span>
                        </div>
                    </div>
                    <div class="pharmacy-badge">
                        <img src="/assets/images/svg/medical_information-purple.svg" alt="Industry Icon" class="pharmacy-badge-icon" loading="lazy">
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

    <section class="pharmacy-about py-5">
        <div class="container">
            <div class="row g-4 align-items-start">
                <!-- Main content -->
                <div class="col-lg-8" id="about-program">
                    <h2 class="pharmacy-section-title pharmacy-section-title--gold">About the Program</h2>
                    <p class="pharmacy-section-text">
                        The Bachelor of Science in Pharmacy program prepares scientifically competent and research-oriented professionals dedicated to 
                        delivering the full spectrum of pharmaceutical services essential for modern healthcare delivery.
                    </p>

                    <div class="pharmacy-mission-card" id="our-mission">
                        <div class="pharmacy-mission-icon">
                            <img src="/assets/images/svg/shield_with_heart-purple.svg" class="pillar-icon-img" loading="lazy">            
                        </div>
                        <div>
                            <h5 class="pharmacy-mission-title">Our Mission</h5>
                            <p class="pharmacy-mission-text">
                                To produce pharmacists who can deliver the full spectrum of pharmaceutical services required in health care delivery.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Seal -->
                <div class="col-lg-3 d-flex align-items-center justify-content-center">
                    <img src="/assets/images/logos/camp-logo.svg" alt="College of Medical Technology and Pharmacy Logo" class="pharmacy-seal" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <section class="pharmacy-why py-5" id="why-choose">
        <div class="container">
            <h2 class="pharmacy-section-title pharmacy-section-title--gold">Why Choose BSPh at GNCI?</h2>
            <p class="pharmacy-why-intro">
                Our program develops compassionate and scientifically adept pharmacists who are committed to clinical excellence, patient safety, 
                and the advancement of public health through innovative practice and research.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-4 col-md-6">
                    <div class="pharmacy-why-card">
                        <img src="/assets/images/svg/Health professional team-amico-purple.svg" alt="Innovation-Driven Curriculum" class="pharmacy-why-img" loading="lazy">
                        <h5 class="pharmacy-why-title">Innovation-Driven Curriculum</h5>
                        <p class="pharmacy-why-desc">
                            Master an updated, research-oriented curriculum designed to foster academic excellence and proficiency in modern pharmaceutical sciences.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="pharmacy-why-card">
                        <img src="/assets/images/svg/Medical care-amico-purple.svg" alt="Clinical Healthcare Partnership" class="pharmacy-why-img" loading="lazy">
                        <h5 class="pharmacy-why-title">Clinical Healthcare Partnership</h5>
                        <p class="pharmacy-why-desc">
                            Train as a vital member of the healthcare team, gaining the skills to provide essential medication therapy and compassionate patient care.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="pharmacy-why-card">
                        <img src="/assets/images/svg/Rheumatology-amico-purple.svg" alt="Diverse Career Pathways" class="pharmacy-why-img" loading="lazy">
                        <h5 class="pharmacy-why-title">Diverse Career Pathways</h5>
                        <p class="pharmacy-why-desc">
                            Prepare for dynamic roles in hospitals, community pharmacies, government agencies, and pharmaceutical industries through multi-field training.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="pharmacy-why-card">
                        <img src="/assets/images/svg/Midwives-amico-purple.svg" alt="Advanced Scientific Foundation" class="pharmacy-why-img" loading="lazy">
                        <h5 class="pharmacy-why-title">Advanced Scientific Foundation</h5>
                        <p class="pharmacy-why-desc">
                            Master the integration of clinical pharmacology, pharmacotherapeutics, and patient care with a focus on ethical medication management.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="pharmacy-why-card">
                        <img src="/assets/images/svg/Certification-amico-purple.svg" alt="Holistic Wellness Advocacy" class="pharmacy-why-img" loading="lazy">
                        <h5 class="pharmacy-why-title">Holistic Wellness Advocacy</h5>
                        <p class="pharmacy-why-desc">
                            Contribute to the physical, mental, and social health of individuals and communities through dedicated pharmaceutical service.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="pharmacy-why-card">
                        <img src="/assets/images/svg/Certification-amico-purple.svg" alt="Global Professional Readiness" class="pharmacy-why-img" loading="lazy">
                        <h5 class="pharmacy-why-title">Global Professional Readiness</h5>
                        <p class="pharmacy-why-desc">
                            Build the competencies required for the Pharmacist Licensure Examination and thrive in local and international professional environments.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="pharmacy-uniform py-5" id="uniform">
        <div class="container">
            <h2 class="pharmacy-section-title pharmacy-section-title--gold">Uniform</h2>
            <p class="pharmacy-section-desc">The prescribed uniform for BS Pharmacy reflects professionalism, cleanliness, and readiness for pharmaceutical practice, promoting discipline and helping students embody the standards expected of future pharmacy professionals.</p>

            <div class="row g-4 justify-content-center mt-2">
                <div class="col-lg-5 col-md-6">
                    <div class="pharmacy-uniform-card">
                        <img src="/assets/images/medtech-unif-female.png" alt="Pharmacy Management Female Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="pharmacy-uniform-card">
                        <img src="/assets/images/medtech-unif-male.png" alt="Pharmacy Management Male Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="pharmacy-uniform-card">
                        <img src="/assets/images/medtech-unif-3rd&4th.png" alt="3rd Year and 4th Year Pharmacy Management Female Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="pharmacy-careers py-5" id="careers">
        <div class="container">
            <h2 class="pharmacy-section-title pharmacy-section-title--gold">Career Opportunities</h2>
            <p class="pharmacy-careers-intro">
                Graduates of the BS Pharmacy program are equipped with comprehensive scientific knowledge, clinical expertise, and professional 
                dedication to pursue rewarding careers across the pharmaceutical, clinical, and public health sectors.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-6">
                    <div class="pharmacy-career-card">
                        <div class="pharmacy-career-img">
                            <img src="/assets/images/medical-science-liason.png" alt="Medical Science Liaison (MSL)" loading="lazy">
                        </div>
                        <div class="pharmacy-career-content">
                            <h5 class="pharmacy-career-title">Medical Science Liaison (MSL)</h5>
                            <p class="pharmacy-career-desc">
                                Acts as a bridge between the pharmaceutical company and the medical community, providing scientific data on new therapies.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="pharmacy-career-card">
                        <div class="pharmacy-career-img">
                            <img src="/assets/images/clinical-research-associate.png" alt="Clinical Research Associate (CRA)" loading="lazy">
                        </div>
                        <div class="pharmacy-career-content">
                            <h5 class="pharmacy-career-title">Clinical Research Associate (CRA)</h5>
                            <p class="pharmacy-career-desc">
                                Designs, monitors, and evaluates the clinical trials required to bring new drugs to market.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="pharmacy-career-card">
                        <div class="pharmacy-career-img">
                            <img src="/assets/images/pharmacovigilance.png" alt="Pharmacovigilance" loading="lazy">
                        </div>
                        <div class="pharmacy-career-content">
                            <h5 class="pharmacy-career-title">Pharmacovigilance</h5>
                            <p class="pharmacy-career-desc">
                                Monitors, investigates, and reports adverse drug reactions to ensure ongoing medication safety post-market launch.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="pharmacy-career-card">
                        <div class="pharmacy-career-img">
                            <img src="/assets/images/ambulatory-care.png" alt="Sustainable Pharmacy Officer" loading="lazy">
                        </div>
                        <div class="pharmacy-career-content">
                            <h5 class="pharmacy-career-title">Ambulatory Care Pharmacist</h5>
                            <p class="pharmacy-career-desc">
                                Manages chronic illnesses in an outpatient clinic setting, often adjusting medication dosages under collaborative practice agreements.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php render_bulletin_board(); ?>

    <section class="pharmacy-cta py-4">
        <div class="container">
            <div class="pharmacy-cta-card">
                <div class="pharmacy-cta-icon">
                    <img src="/assets/images/svg/volunteer_activism-purple.svg" class="hand-icon"loading="lazy">
                </div>
                <div class="pharmacy-cta-text">
                    <h5 class="pharmacy-cta-title">Make a difference. Begin Your Journey with GNCI.</h5>
                    <p class="pharmacy-cta-desc">Join a community driven by purpose, dedicated to excellence, and built to make an impact.</p>
                </div>
                <div class="pharmacy-cta-action">
                    <a href="/admissions/apply.php" class="pharmacy-cta-btn">Apply Now</a>
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