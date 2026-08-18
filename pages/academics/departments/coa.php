<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College of Accountancy</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/college-dept-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php $activeSection = 'academics'; ?>
    <?php include_once __DIR__ . '/../../../components/index-nav.php'; ?>

    <section class="dept-intro">
        <div class="dept-hero">
            <div class="dept-hero-inner">
                <nav class="dept-breadcrumb" aria-label="breadcrumb">
                    <a href="/">Home</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/academics">Academics</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/academics/college-programs">College Programs</a>
                    <i class="bi bi-chevron-right"></i>
                    <span class="dept-current">College of Accountancy</span>
                </nav>
                <h1>College of Accountancy</h1>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
            </div>
        </div>
    </section>

    <section class="dept-about">
        <div class="dept-about-inner">

            <div class="dept-logo-wrap">
                <div class="dept-logo-square"></div>
                <img src="/assets/images/logos/coa-logo.svg" alt="College of Accountancy seal" class="dept-logo">
                <div class="dept-dots" aria-hidden="true"></div>
            </div>

            <div class="dept-about-content">
                <span class="dept-eyebrow">Overview</span>
                <h2>About the College of Accountancy</h2>
                <p>The College of Accountancy provides comprehensive student support through enrollment assistance, academic advising, faculty consultation, career and licensure support, OJT coordination, student activities, and efficient document processing.</p>
            </div>

        </div>
    </section>

    <section class="dept-programs">
        <div class="dept-programs-inner">
            <span class="dept-eyebrow">Our Programs</span>
            <h2>Programs Offered</h2>

            <div class="dept-programs-grid">

                <div class="dept-program-card">
                    <div class="dept-program-img">
                        <img src="/assets/images/programs/bsais.jpg" alt="Bachelor of Science in Accounting Information System">
                    </div>
                    <div class="dept-program-body">
                        <h3>Bachelor of Science in Accounting Information System</h3>
                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
                        <a href="/academics/programs/bsais" class="dept-program-link">
                            VIEW DETAILS <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="dept-program-card">
                    <div class="dept-program-img">
                        <img src="/assets/images/programs/bsa.jpg" alt="Bachelor of Science in Accountancy">
                    </div>
                    <div class="dept-program-body">
                        <h3>Bachelor of Science in Accountancy</h3>
                        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
                        <a href="/academics/programs/bsa" class="dept-program-link">
                            VIEW DETAILS <i class="bi bi-arrow-right"></i>
                        </a>
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
                <p>Meet the visionary leader guiding the College of Accountancy in advancing academic excellence, innovation, and service.</p>
            </div>

            <div class="dept-dean-grid">

                <div class="dept-dean-photo-wrap">
                    <div class="dept-dean-square"></div>
                    <div class="dept-dean-photo">
                        <img src="/assets/images/sir-isip.png" alt="Getor N. Isip, Dean of the College of Accountancy">
                        <div class="dept-dean-badge">
                            <span class="dept-dean-badge-title">Dean</span>
                            <span class="dept-dean-badge-sub">College of Accountancy</span>
                        </div>
                    </div>
                    <div class="dept-dean-accent" aria-hidden="true"></div>
                </div>

                <div class="dept-dean-content">
                    <span class="dept-eyebrow">Meet the Dean</span>
                    <h3>Getor N. Isip, CPA, MBA</h3>
                    <div class="dept-dean-rule"></div>
                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</p>

                    <hr class="dept-dean-divider">

                    <span class="dept-eyebrow">Get Connected</span>

                    <div class="dept-dean-contact">
                        <i class="bi bi-telephone"></i>
                        <div>
                            <span class="dept-contact-label">Contact Information</span>
                            <span class="dept-contact-value">(045) 900-4473 Loc. 120</span>
                        </div>
                    </div>

                    <div class="dept-dean-contact">
                        <i class="bi bi-envelope"></i>
                        <div>
                            <span class="dept-contact-label">Email Address</span>
                            <span class="dept-contact-value">info@gnc.edu.ph</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../../../components/index-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>

</body>
</html>