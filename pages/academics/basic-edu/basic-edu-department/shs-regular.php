<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senior High School Regular</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/basic-edu-style.css" rel="stylesheet">
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
                    <a href="/pages/academics/basic-edu/basic-edu-dept.php">Basic Education Departments</a>
                    <i class="bi bi-chevron-right"></i>
                    <span class="dept-current">Senior High School Regular</span>
                </nav>
                <h1>Senior High School Regular</h1>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
            </div>
        </div>
    </section>



    <section class="dept-about">
        <div class="dept-about-inner skeleton-group">

            <div class="dept-logo-wrap">
                <div class="dept-logo-square"></div>
                <div class="skeleton-wrap" style="display:inline-block;">
                    <img src="/assets/images/logos/jhs-logo.svg" alt="Junior High School Regular logo" class="dept-logo">
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
                    <h2>About the Junior High School Regular</h2>
                    <p>The Junior High School Regular offers a well-rounded education designed to foster critical thinking, creativity, and social responsibility in our students.</p>
                </div>
            </div>

        </div>
    </section>
    
    <section class="dept-programs">
        <div class="dept-programs-inner">
            <span class="dept-eyebrow">Our Programs</span>
            <h2>Programs Offered</h2>

            <div class="dept-programs-grid">

                <div class="dept-program-card">
                    <div class="dept-program-img skeleton-wrap">
                        <img src="/assets/images/test-image.png" alt="Bachelor of Science in Business Administration major in Financial Management" loading="lazy">
                    </div>
                    <div class="dept-program-body">
                        <h3>Science, Technology, Engineering, and Mathematics (STEM) Track</h3>
                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
                    </div>
                </div>

                <div class="dept-program-card">
                    <div class="dept-program-img skeleton-wrap">
                        <img src="/assets/images/test-image.png" alt="Bachelor of Science in Computer Science" loading="lazy">
                    </div>
                    <div class="dept-program-body">
                        <h3>Accountancy, Business, and Management (ABM) Track</h3>
                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
                    </div>
                </div>

                <div class="dept-program-card">
                    <div class="dept-program-img skeleton-wrap">
                        <img src="/assets/images/test-image.png" alt="Bachelor of Science in Information Technology" loading="lazy">
                    </div>
                    <div class="dept-program-body">
                        <h3>Humanities and Social Sciences (HUMSS) Track</h3>
                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <section class="dept-principal">
        <div class="dept-principal-inner">

            <div class="dept-principal-intro">
                <span class="dept-eyebrow">Leadership &amp; Organization</span>
                <h2>Head of Office</h2>
                <p>Meet the visionary leader guiding the Senior High School Regular in advancing academic excellence, innovation, and service.</p>
            </div>

            <div class="dept-principal-grid skeleton-group">

                <div class="dept-principal-photo-wrap">
                    <div class="dept-principal-square"></div>

                    <div class="dept-principal-photo">
                        <div class="skeleton-wrap">
                            <img src="/assets/images/oic-cayanan.png" alt="Principal Photo" class="dept-principal-img">
                        </div>
                        
                        <!-- Layered Wave Pattern: Gold -> Maroon -> Light Green -->
                        <div class="dept-principal-wave" aria-hidden="true">
                            <svg viewBox="0 0 500 150" preserveAspectRatio="none">
                                <path class="wave-gold" d="M0.00,30.98 C150.00,120.00 350.00,-20.00 500.00,30.98 L500.00,150.00 L0.00,150.00 Z"></path>
                                <path class="wave-maroon" d="M0.00,49.98 C149.99,150.00 349.20,-49.98 500.00,49.98 L500.00,150.00 L0.00,150.00 Z"></path>
                                <path class="wave-green" d="M0.00,79.98 C149.99,170.00 349.20,-20.98 500.00,79.98 L500.00,150.00 L0.00,150.00 Z"></path>
                            </svg>
                        </div>

                        <!-- Glassmorphism Nameplate Badge -->
                        <div class="dept-principal-badge">
                            <span class="dept-principal-badge-title">Principal</span>
                            <span class="dept-principal-badge-sub">Senior High School Regular</span>
                        </div>
                    </div>

                    <div class="dept-principal-accent" aria-hidden="true"></div>
                </div>

                <div class="dept-principal-content">
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
                        <span class="dept-eyebrow">Meet the Officer-in-Charge</span>
                        <h3>Rhoda SR. Cayanan, RPh, LPT</h3>
                        <div class="dept-principal-rule"></div>
                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</p>

                        <hr class="dept-principal-divider">

                        <span class="dept-eyebrow">Get Connected</span>

                        <div class="dept-principal-contacts-grid">
                            <div class="dept-principal-contact">
                                <i class="bi bi-telephone"></i>
                                <div>
                                    <span class="dept-contact-label">Contact Information</span>
                                    <span class="dept-contact-value">(045) 900-4473 Loc. 117</span>
                                </div>
                            </div>

                            <div class="dept-principal-contact">
                                <i class="bi bi-geo-alt"></i>
                                <div>
                                    <span class="dept-contact-label">SHS Regular Office</span>
                                    <span class="dept-contact-value">Goseco Hall, Floor 2, Room 200</span>
                                </div>
                            </div>

                            <div class="dept-principal-contact">
                                <i class="bi bi-envelope"></i>
                                <div>
                                    <span class="dept-contact-label">Email Address</span>
                                    <span class="dept-contact-value">info@gnc.edu.ph</span>
                                </div>
                            </div>

                            <div class="dept-principal-contact">
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