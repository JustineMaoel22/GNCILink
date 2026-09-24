<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College of Engineering</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/college-dept-style.css" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css?v=2" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php $activeSection = 'academics'; ?>
    <?php include_once __DIR__ . '/../../../../components/index-nav.php'; ?>

    <section class="dept-intro">
        <div class="dept-hero">
            <div class="dept-hero-inner">
                <nav class="dept-breadcrumb" aria-label="breadcrumb">
                    <span>Academics</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/college-departments.php">College Departments</a>
                    <i class="bi bi-chevron-right"></i>
                    <span class="dept-current">College of Engineering</span>
                </nav>
                <h1>College of Engineering</h1>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
            </div>
        </div>
    </section>

    <section class="dept-about">
        <div class="dept-about-inner skeleton-group">

            <div class="dept-logo-wrap">
                <div class="dept-logo-square"></div>
                <div class="skeleton-wrap" style="display:inline-block;">
                    <img src="/assets/images/logos/coe-logo.svg" alt="College of Engineering seal" class="dept-logo">
                </div>
                <div class="dept-dots" aria-hidden="true"></div>
            </div>

            <div class="dept-about-content">
                <div class="skeleton-text-lines">
                    <span class="skeleton-line skeleton-line--60"></span>
                    <span class="skeleton-line skeleton-line--title skeleton-line--80"></span>
                    <span class="skeleton-line skeleton-line--100"></span>
                    <span class="skeleton-line skeleton-line--100"></span>
                    <span class="skeleton-line skeleton-line--80"></span>
                </div>
                <div class="skeleton-real-content">
                    <span class="dept-eyebrow">Overview</span>
                    <h2>About the College of Engineering</h2>
                    <p>The College of Engineering provides comprehensive student support through enrollment assistance, academic advising, faculty consultation, career and licensure support, OJT coordination, student activities, and efficient document processing.</p>
                </div>
            </div>

        </div>
    </section>

    <section class="dept-programs">
        <div class="dept-programs-inner">
            <span class="dept-eyebrow">Our Programs</span>
            <h2>Programs Offered</h2>

            <div class="dept-programs-grid">

                <div class="dept-program-card skeleton-group">
                    <div class="dept-program-img skeleton-wrap">
                        <img src="/assets/images/test-image.png" alt="Bachelor of Science in Civil Engineering">
                    </div>
                    <div class="dept-program-body">
                        <div class="skeleton-text-lines">
                            <span class="skeleton-line skeleton-line--title skeleton-line--80"></span>
                            <span class="skeleton-line skeleton-line--100"></span>
                            <span class="skeleton-line skeleton-line--100"></span>
                            <span class="skeleton-line skeleton-line--60" style="margin-top:.5em;"></span>
                        </div>
                        <div class="skeleton-real-content">
                            <h3>Bachelor of Science in Civil Engineering</h3>
                            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
                            <a href="/pages/academics/college/programs/civil-engr.php" class="dept-program-link">
                                VIEW DETAILS <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <section class="dept-dean">
        <div class="dept-dean-inner">

            <div class="dept-dean-intro">
                <span class="dept-eyebrow">Leadership &amp; Organization</span>
                <h2>Head of Office</h2>
                <p>Meet the visionary leader guiding the College of Engineering in advancing academic excellence, innovation, and service.</p>
            </div>

            <div class="dept-dean-grid skeleton-group">

                <div class="dept-dean-photo-wrap">
                    <div class="dept-dean-square"></div>
                    
                    <div class="dept-dean-photo">
                        <div class="skeleton-wrap">
                            <img src="/assets/images/maam-sacdalan.png" alt="Ma. Lydia P. Sacdalan, Dean of the College of Engineering">
                        </div>
                        
                        <!-- Layered Wave Pattern: Gold -> Maroon -> Light Green -->
                        <div class="dept-dean-wave" aria-hidden="true">
                            <svg viewBox="0 0 500 150" preserveAspectRatio="none">
                                <path class="wave-gold" d="M0.00,30.98 C150.00,120.00 350.00,-20.00 500.00,30.98 L500.00,150.00 L0.00,150.00 Z"></path>
                                <path class="wave-maroon" d="M0.00,49.98 C149.99,150.00 349.20,-49.98 500.00,49.98 L500.00,150.00 L0.00,150.00 Z"></path>
                                <path class="wave-green" d="M0.00,79.98 C149.99,170.00 349.20,-20.98 500.00,79.98 L500.00,150.00 L0.00,150.00 Z"></path>
                            </svg>
                        </div>

                        <!-- Glassmorphism Nameplate Badge -->
                        <div class="dept-dean-badge">
                            <span class="dept-dean-badge-title">Dean</span>
                            <span class="dept-dean-badge-sub">College of Engineering</span>
                        </div>
                    </div>

                    <div class="dept-dean-accent" aria-hidden="true"></div>
                </div>

                <div class="dept-dean-content">
                    <div class="skeleton-text-lines">
                        <span class="skeleton-line skeleton-line--60"></span>
                        <span class="skeleton-line skeleton-line--title skeleton-line--80"></span>
                        <span class="skeleton-line skeleton-line--100"></span>
                        <span class="skeleton-line skeleton-line--100"></span>
                        <span class="skeleton-line skeleton-line--100"></span>
                        <span class="skeleton-line skeleton-line--80" style="margin-bottom:1em;"></span>
                        <span class="skeleton-line skeleton-line--60"></span>
                        <span class="skeleton-line skeleton-line--60"></span>
                    </div>
                    <div class="skeleton-real-content">
                        <span class="dept-eyebrow">Meet the Dean</span>
                        <h3>Engr. Ma. Lydia P. Sacdalan, ME-1, MMEP, ENP, SO-3</h3>
                        <div class="dept-dean-rule"></div>
                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</p>

                        <hr class="dept-dean-divider">

                        <span class="dept-eyebrow">Get Connected</span>

                        <div class="dept-dean-contacts-grid">
                            <div class="dept-dean-contact">
                                <i class="bi bi-telephone"></i>
                                <div>
                                    <span class="dept-contact-label">Contact Information</span>
                                    <span class="dept-contact-value">(045) 900-4473 Loc. 125</span>
                                </div>
                            </div>

                            <div class="dept-dean-contact">
                                <i class="bi bi-geo-alt"></i>
                                <div>
                                    <span class="dept-contact-label">COE Office</span>
                                    <span class="dept-contact-value">Limlingan Hall, Floor 2, Room 205</span>
                                </div>
                            </div>

                            <div class="dept-dean-contact">
                                <i class="bi bi-envelope"></i>
                                <div>
                                    <span class="dept-contact-label">Email Address</span>
                                    <span class="dept-contact-value">info@gnc.edu.ph</span>
                                </div>
                            </div>

                            <div class="dept-dean-contact">
                                <i class="bi bi-clock"></i>
                                <div>
                                    <span class="dept-contact-label">Operating Hours</span>
                                    <span class="dept-contact-value">Monday - Friday<br>8:00AM - 4:00PM</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../../../../components/index-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
    <script src="/assets/js/skeleton-loader.js?v=2"></script>

</body>
</html>