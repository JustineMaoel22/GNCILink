<?php
define('CURRENT_PROGRAM', 'BSN');
require_once __DIR__ . '/../../../../components/bulletin-board.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nursing</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/nursing-style.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/bulletin-board-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php include_once __DIR__ . '/../../../../components/index-nav.php'; ?>

    <section class="hero-page nursing-hero" style="background-image: url('/assets/images/nursing-hero.png');" loading="lazy">
        <div class="container h-100">
        <div class="row align-items-center g-0">
            <div class="col-lg-6">
                <nav class="nursing-breadcrumb" aria-label="breadcrumb">
                    <span>ACADEMICS</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/college-departments.php">COLLEGE DEPARTMENTS</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/departments/conursing.php">COLLEGE OF ALLIED MEDICAL PROGRAMS</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>NURSING</span>
                </nav>

                <div class="nursing-hero-content">
                    <h1 class="nursing-title">NURSING</h1>
                    <p class="nursing-subtitle">Compassionate Care. Clinical Excellence. Lifelong Service.</p>
                    <p class="nursing-desc">
                        The Nursing program develops skilled and compassionate professionals committed to improving
                        lives and promoting wellness.
                    </p>
                </div>

                <div class="nursing-badges">
                    <div class="nursing-badge">
                        <img src="/assets/images/svg/grad-hat.svg" alt="Graduation Cap Icon" class="nursing-badge-icon" loading="lazy">
                        <div>
                            <strong>4 Years</strong>
                            <span>Program</span>
                        </div>
                    </div>
                    <div class="nursing-badge">
                        <img src="/assets/images/svg/medal.svg" alt="Award Icon" class="nursing-badge-icon" loading="lazy">
                        <div>
                            <strong>CHED</strong>
                            <span>Accredited</span>
                        </div>
                    </div>
                    <div class="nursing-badge">
                        <img src="/assets/images/svg/stethoscope.svg" alt="Stethoscope Icon" class="nursing-badge-icon" loading="lazy">
                        <div>
                            <strong>BSN</strong>
                            <span>Degree</span>
                        </div>
                    </div>
                    <div class="nursing-badge">
                        <img src="/assets/images/svg/medical_information.svg" alt="Medical Info Icon" class="nursing-badge-icon" loading="lazy">
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

    <section class="nursing-about py-5">
        <div class="container">
            <div class="row g-4 align-items-start">
                <!-- Main content -->
                <div class="col-lg-8" id="about-program">
                    <h2 class="nursing-section-title nursing-section-title--gold">About the Program</h2>
                    <p class="nursing-section-text">
                        The Bachelor of Science in Nursing program is designed to mold young individuals
                        in according to current nursing health care service standards, acquire virtues from the
                        practice of charity and then become world class nurses.
                    </p>

                    <div class="nursing-mission-card" id="our-mission">
                        <div class="nursing-mission-icon">
                            <img src="/assets/images/svg/shield_with_heart.svg" class="pillar-icon-img" loading="lazy">            
                        </div>
                        <div>
                            <h5 class="nursing-mission-title">Our Mission</h5>
                            <p class="nursing-mission-text">
                                Educating skilled and compassionate nurses to deliver high-quality healthcare,
                                uphold professional ethics, and advance the field through research and lifelong
                                learning.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Seal -->
                <div class="col-lg-3 d-flex align-items-center justify-content-center">
                    <img src="/assets/images/logos/con-logo.svg" alt="Guagua National Colleges, Inc. College of Nursing Seal" class="nursing-seal" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <section class="nursing-why py-5" id="why-choose">
        <div class="container">
            <h2 class="nursing-section-title nursing-section-title--gold">Why Choose BSN at GNCI?</h2>
            <p class="nursing-why-intro">
                Our program equips you with the knowledge, skills, and clinical competence to become a compassionate,
                globally competitive, and high-performing registered nurse.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-4 col-md-6">
                    <div class="nursing-why-card">
                        <img src="/assets/images/svg/Health professional team-amico 1.svg" alt="Evidence-Based Practice" class="nursing-why-img" loading="lazy">
                        <h5 class="nursing-why-title">Evidence-Based Practice</h5>
                        <p class="nursing-why-desc">
                            Master the integration of health sciences and research findings to deliver safe,
                            accurate, and high-quality nursing care.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="nursing-why-card">
                        <img src="/assets/images/svg/Medical care-amico 1.svg" alt="Holistic Patient Care" class="nursing-why-img" loading="lazy">
                        <h5 class="nursing-why-title">Holistic Patient Care</h5>
                        <p class="nursing-why-desc">
                            Learn to utilize the nursing process to provide comprehensive, culturally appropriate
                            care to individuals and diverse communities.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="nursing-why-card">
                        <img src="/assets/images/svg/Rheumatology-amico 1.svg" alt="Ethical & Legal Practice" class="nursing-why-img" loading="lazy">
                        <h5 class="nursing-why-title">Ethical &amp; Legal Practice</h5>
                        <p class="nursing-why-desc">
                            Master the application of moral, legal, and ethical principles to ensure professional
                            accountability in all clinical settings.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="nursing-why-card">
                        <img src="/assets/images/svg/Midwives-amico 1.svg" alt="Techno-Intelligent Care" class="nursing-why-img" loading="lazy">
                        <h5 class="nursing-why-title">Techno-Intelligent Care</h5>
                        <p class="nursing-why-desc">
                            Train with advanced health systems and digital tools to enhance patient outcomes
                            through modern, tech-integrated workflows.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="nursing-why-card">
                        <img src="/assets/images/svg/Certification-amico 1.svg" alt="Collaborative Leadership" class="nursing-why-img" loading="lazy">
                        <h5 class="nursing-why-title">Collaborative Leadership</h5>
                        <p class="nursing-why-desc">
                            Build the competencies required for local board licensure examinations (PNLE) and
                            prepare for diverse international healthcare opportunities.
                        </p>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="nursing-why-card">
                        <img src="/assets/images/svg/Certification-amico 1.svg" alt="Global Career Readiness" class="nursing-why-img" loading="lazy">
                        <h5 class="nursing-why-title">Global Career Readiness</h5>
                        <p class="nursing-why-desc">
                            Build the competencies required for local board licensure examinations (PNLE) and
                            prepare for diverse international healthcare opportunities.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="nursing-uniform py-5" id="uniform">
        <div class="container">
            <h2 class="nursing-section-title nursing-section-title--gold">Uniform</h2>

            <div class="row g-4 justify-content-center mt-2">
                <div class="col-lg-5 col-md-6">
                    <div class="nursing-uniform-card">
                        <img src="/assets/images/nurs-unif-female.png" alt="Nursing Female Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="nursing-uniform-card">
                        <img src="/assets/images/nurs-unif-male.png" alt="Nursing Male Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="nursing-uniform-card">
                        <img src="/assets/images/nursing-lecture-unif.png" alt="Nursing Lecture Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="nursing-uniform-card">
                        <img src="/assets/images/nursing-clinical-unif.png" alt="Nursing Clinical Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>     
                <div class="col-lg-5 col-md-6">
                    <div class="nursing-uniform-card">
                        <img src="/assets/images/con-shirt.png" alt="College of Nursing Shirt" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
                <div class="col-lg-5 col-md-6">
                    <div class="nursing-uniform-card">
                        <img src="/assets/images/scrubsuit-nursing.png" alt="Scrub Suit Uniform" loading="lazy" onclick="openUniformLightbox(this.src, this.alt)">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="nursing-careers py-5" id="careers">
        <div class="container">
            <h2 class="nursing-section-title nursing-section-title--gold">Career Opportunities</h2>
            <p class="nursing-careers-intro">
                Graduates of BS Nursing are equipped with knowledge, skills, and compassion to pursue fulfilling
                careers in a variety of healthcare settings.
            </p>

            <div class="row g-4 mt-2">
                <div class="col-lg-6">
                    <div class="nursing-career-card">
                        <div class="nursing-career-img">
                            <img src="/assets/images/nurse-practitioner.png" alt="Nurse Practitioner (NP)" loading="lazy">
                        </div>
                        <div class="nursing-career-content">
                            <h5 class="nursing-career-title">Nurse Practitioner (NP)</h5>
                            <p class="nursing-career-desc">
                                Manage patient care, prescribe medication, and diagnose illnesses.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="nursing-career-card">
                        <div class="nursing-career-img">
                            <img src="/assets/images/crna.png" alt="Certified Registered Nurse Anesthetist (CRNA)" loading="lazy">
                        </div>
                        <div class="nursing-career-content">
                            <h5 class="nursing-career-title">Certified Registered Nurse Anesthetist (CRNA)</h5>
                            <p class="nursing-career-desc">
                                Administer anesthesia and monitor patients during surgical procedures.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="nursing-career-card">
                        <div class="nursing-career-img">
                            <img src="/assets/images/nursing-informatics.png" alt="Nursing Informatics" loading="lazy">
                        </div>
                        <div class="nursing-career-content">
                            <h5 class="nursing-career-title">Nursing Informatics</h5>
                            <p class="nursing-career-desc">
                                Combine nursing science with information technology and data systems to
                                improve patient care.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="nursing-career-card">
                        <div class="nursing-career-img">
                            <img src="/assets/images/legal-nurse.png" alt="Legal Nurse Consultant" loading="lazy">
                        </div>
                        <div class="nursing-career-content">
                            <h5 class="nursing-career-title">Legal Nurse Consultant</h5>
                            <p class="nursing-career-desc">
                                Review medical records and consult with legal teams on medical-related lawsuits.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php render_bulletin_board(); ?>

    <section class="nursing-cta py-4">
        <div class="container">
            <div class="nursing-cta-card">
                <div class="nursing-cta-icon">
                    <img src="/assets/images/svg/volunteer_activism.svg" class="hand-icon"loading="lazy">
                </div>
                <div class="nursing-cta-text">
                    <h5 class="nursing-cta-title">Make a difference. Begin Your Journey with GNCI.</h5>
                    <p class="nursing-cta-desc">Join a community driven by purpose, dedicated to excellence, and built to make an impact.</p>
                </div>
                <div class="nursing-cta-action">
                    <a href="/admissions/apply.php" class="nursing-cta-btn">Apply Now</a>
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