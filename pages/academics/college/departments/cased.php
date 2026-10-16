<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College of Arts and Sciences, and Education</title>
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
    <?php include_once __DIR__ . '/../../../../components/navbar.php'; ?>
    <?php require_once __DIR__ . '/../../../../components/department-public.php'; ?>
    <?php
        // Logo and PDF come from this department's record, so changing them in
        // Content Editor → College Departments updates this page automatically.
        $dept    = publicDepartment('cased');
        $logoUrl = $dept['logo_url'] ?? null;
        $dean    = publicDepartmentDean($dept);
    ?>

    <section class="dept-intro">
        <div class="dept-hero">
            <div class="dept-hero-inner">
                <nav class="dept-breadcrumb" aria-label="breadcrumb">
                    <span>Academics</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/college/college-departments.php">College Departments</a>
                    <i class="bi bi-chevron-right"></i>
                    <span class="dept-current">College of Arts and Sciences, and Education</span>
                </nav>
                <h1>College of Arts and Sciences, and Education</h1>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam auctor, nisl eget ultricies aliquet, nunc nisl aliquam nisl, eget ultricies nunc nisl eget nunc.</p>
            </div>
        </div>
    </section>

    <section class="dept-about">
        <div class="dept-about-inner skeleton-group">

            <div class="dept-logo-wrap">
                <div class="dept-logo-square"></div>
                <?php if ($logoUrl): ?>
                <div class="skeleton-wrap" style="display:inline-block;">
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="College of Arts, Sciences, and Education seal" class="dept-logo">
                </div>
                <?php endif; ?>
                <div class="dept-dots" aria-hidden="true"></div>
            </div>

            <div class="dept-about-content">
                <div class="skeleton-text-lines">
                    <span class="skeleton-line skeleton-line--60"></span>
                    <span class="skeleton-line skeleton-line--title skeleton-line--80"></span>
                    <span class="skeleton-line skeleton-line--100"></span>
                    <span class="skeleton-line skeleton-line--80" style="margin-bottom:1em;"></span>
                    <span class="skeleton-line skeleton-line--60"></span>
                    <span class="skeleton-line skeleton-line--80"></span>
                    <span class="skeleton-line skeleton-line--80" style="margin-bottom:1em;"></span>
                    <span class="skeleton-line skeleton-line--100"></span>
                    <span class="skeleton-line skeleton-line--60"></span>
                </div>
                <div class="skeleton-real-content">
                    <span class="dept-eyebrow">Overview</span>
                    <h2>About the College of Arts, Sciences, and Education</h2>
                    <p>The college provides a wide range of academic programs and support services designed to foster the holistic development of its students.</p>

                    <p class="dept-offers-label">The college offers:</p>
                    <ul class="dept-offers-list">
                        <li><strong class="offer-green">Specialized Courses</strong> &nbsp;- &nbsp;Enhances Professional Competence</li>
                        <li><strong class="offer-gold">General Education Subjects</strong> - Promotes Intellectual Growth Across Disciplines</li>
                        <li><strong class="offer-maroon">Professional Education Courses</strong> - Strengthen Pedagogical Knowledge And Teaching Skills</li>
                    </ul>

                    <p>Through these learning opportunities, the College prepares future educators and professionals to become competent, innovative, and socially responsible individuals who can effectively respond to the needs of their learners, communities, and professions.</p>
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
                        <img src="/assets/images/test-image.png" alt="Bachelor of Elementary Education">
                    </div>
                    <div class="dept-program-body">
                        <div class="skeleton-text-lines">
                            <span class="skeleton-line skeleton-line--title skeleton-line--80"></span>
                            <span class="skeleton-line skeleton-line--100"></span>
                            <span class="skeleton-line skeleton-line--100"></span>
                            <span class="skeleton-line skeleton-line--60" style="margin-top:.5em;"></span>
                        </div>
                        <div class="skeleton-real-content">
                            <h3>Bachelor of Elementary Education</h3>
                            <p>The Bachelor of Elementary Education is an undergraduate teacher education program that prepares students to become competent, motivated, and responsible elementary school...</p>
                            <a href="/pages/academics/college/programs/beed.php" class="dept-program-link">
                                VIEW DETAILS <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="dept-program-card skeleton-group">
                    <div class="dept-program-img skeleton-wrap">
                        <img src="/assets/images/test-image.png" alt="Bachelor of Secondary Education">
                    </div>
                    <div class="dept-program-body">
                        <div class="skeleton-text-lines">
                            <span class="skeleton-line skeleton-line--title skeleton-line--80"></span>
                            <span class="skeleton-line skeleton-line--100"></span>
                            <span class="skeleton-line skeleton-line--100"></span>
                            <span class="skeleton-line skeleton-line--60" style="margin-top:.5em;"></span>
                        </div>
                        <div class="skeleton-real-content">
                            <h3>Bachelor of Secondary Education</h3>
                            <p>The Bachelor of Secondary Education is an undergraduate teacher education program that prepares future educators with the knowledge, skills, and competencies needed to teach...</p>
                            <a href="/pages/academics/college/programs/bsed.php" class="dept-program-link">
                                VIEW DETAILS <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="dept-program-card skeleton-group">
                    <div class="dept-program-img skeleton-wrap">
                        <img src="/assets/images/test-image.png" alt="Bachelor of Arts in English Language">
                    </div>
                    <div class="dept-program-body">
                        <div class="skeleton-text-lines">
                            <span class="skeleton-line skeleton-line--title skeleton-line--80"></span>
                            <span class="skeleton-line skeleton-line--100"></span>
                            <span class="skeleton-line skeleton-line--100"></span>
                            <span class="skeleton-line skeleton-line--60" style="margin-top:.5em;"></span>
                        </div>
                        <div class="skeleton-real-content">
                            <h3>Bachelor of Arts in English Language</h3>
                            <p>The Bachelor of Arts in English Language program develops students’ proficiency in English communication, critical thinking, and language-related skills. It integrates theory and practice...</p>
                            <a href="/pages/academics/college/programs/bael.php" class="dept-program-link">
                                VIEW DETAILS <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <?php if (!empty($dept['pdf_url'])): ?>
    <!-- Department PDF (uploaded in Content Editor → College Departments) -->
    <section class="dept-programs">
        <div class="dept-programs-inner">
            <span class="dept-eyebrow">Resources</span>
            <h2>Department Document</h2>
            <?php renderDepartmentPdfViewer($dept, ['title' => '']); ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="dept-dean">
        <div class="dept-dean-inner">

            <div class="dept-dean-intro">
                <span class="dept-eyebrow">Leadership &amp; Organization</span>
                <h2>Head of Office</h2>
                <p>Meet the visionary leader guiding the College of Arts, Sciences, and Education in advancing academic excellence, innovation, and service.</p>
            </div>

            <div class="dept-dean-grid skeleton-group">

                <div class="dept-dean-photo-wrap">
                    <div class="dept-dean-square"></div>
                    
                    <div class="dept-dean-photo skeleton-wrap">
                        <img src="<?= htmlspecialchars($dean['photo_url'] ?: '/assets/images/maam-maris.png') ?>" alt="<?= htmlspecialchars($dean['name'] ?: 'Annaliza V. Maris') ?>, Dean of the College of Arts, Sciences, and Education">
                        
                        <!-- Wave Pattern: Gold -> Maroon -> Light Green -->
                        <div class="dept-dean-wave" aria-hidden="true">
                            <svg viewBox="0 0 500 150" preserveAspectRatio="none">
                                <path class="wave-gold" d="M0.00,30.98 C150.00,120.00 350.00,-20.00 500.00,30.98 L500.00,150.00 L0.00,150.00 Z"></path>
                                <path class="wave-maroon" d="M0.00,49.98 C149.99,150.00 349.20,-49.98 500.00,49.98 L500.00,150.00 L0.00,150.00 Z"></path>
                                <path class="wave-green" d="M0.00,79.98 C149.99,170.00 349.20,-20.98 500.00,79.98 L500.00,150.00 L0.00,150.00 Z"></path>
                            </svg>
                        </div>

                        <!-- Glassmorphism Nameplate Badge -->
                        <div class="dept-dean-badge">
                            <span class="dept-dean-badge-title"><?= htmlspecialchars($dean['title'] ?: 'Dean') ?></span>
                            <span class="dept-dean-badge-sub">College of Arts, Sciences,<br>and Education</span>
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
                        <h3><?= htmlspecialchars($dean['name'] ?: 'Annaliza V. Maris, LPT, MA') ?></h3>
                        <div class="dept-dean-rule"></div>
                        <p><?= nl2br(htmlspecialchars($dean['bio'] ?: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.')) ?></p>

                        <hr class="dept-dean-divider">

                        <span class="dept-eyebrow">Get Connected</span>

                        <div class="dept-dean-contacts-grid">
                            <div class="dept-dean-contact">
                                <i class="bi bi-telephone"></i>
                                <div>
                                    <span class="dept-contact-label">Contact Information</span>
                                    <span class="dept-contact-value"><?= htmlspecialchars($dean['phone'] ?: '(045) 900-4473 Loc. 120') ?></span>
                                </div>
                            </div>

                            <div class="dept-dean-contact">
                                <i class="bi bi-geo-alt"></i>
                                <div>
                                    <span class="dept-contact-label">CASED Office</span>
                                    <span class="dept-contact-value"><?= htmlspecialchars($dean['office'] ?: 'Limlingan Hall, Floor 2, Room 202') ?></span>
                                </div>
                            </div>

                            <div class="dept-dean-contact">
                                <i class="bi bi-envelope"></i>
                                <div>
                                    <span class="dept-contact-label">Email Address</span>
                                    <span class="dept-contact-value"><?= htmlspecialchars($dean['email'] ?: 'info@gnc.edu.ph') ?></span>
                                </div>
                            </div>

                            <div class="dept-dean-contact">
                                <i class="bi bi-clock"></i>
                                <div>
                                    <span class="dept-contact-label">Operating Hours</span>
                                    <span class="dept-contact-value"><?= nl2br(htmlspecialchars($dean['hours'] ?: "Monday - Friday\n8:00AM - 4:00PM")) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <section class="dept-dean dept-facebook" style="padding-top:0;">
        <div class="dept-dean-inner">

            <div class="dept-dean-intro">
                <span class="dept-eyebrow">Stay Updated</span>
                <h2>Follow Us on Facebook</h2>
                <p>Get the latest announcements, events, and news from the College of Arts, Sciences, and Education.</p>
            </div>

            <div style="max-width:500px;">
                <iframe
                    src="https://www.facebook.com/plugins/page.php?href=https%3A%2F%2Fwww.facebook.com%2Fprofile.php%3Fid%3D61553299254607&amp;tabs=&amp;width=500&amp;height=150&amp;small_header=false&amp;adapt_container_width=true&amp;hide_cover=false&amp;show_facepile=false"
                    title="CASED Facebook Page"
                    width="500" height="150"
                    style="border:none; overflow:hidden; width:100%; max-width:500px; border-radius:8px;"
                    scrolling="no" frameborder="0" allowfullscreen="true"
                    allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"
                    loading="lazy"></iframe>
            </div>

        </div>
    </section>

    <?php include __DIR__ . '/../../../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
    <script src="/assets/js/skeleton-loader.js?v=2"></script>

</body>
</html>