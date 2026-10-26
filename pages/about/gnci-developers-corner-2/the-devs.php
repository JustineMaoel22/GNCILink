<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meet the Developers</title>
    <!-- Bootstrap 5 CSS -->
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="icon" href="/assets/images/logos/gnc-logo-v1.svg" type="image/svg+xml">
    <!-- Google Fonts for Serif (Headings) and Sans-Serif (Body) -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;1,600&family=Inter:wght@300;400&display=swap" rel="stylesheet">
    <!-- External Custom CSS -->
    <link rel="stylesheet" href="/assets/css/the-devs-style.css.css?v=<?= filemtime(__DIR__.'/../../../assets/css/the-devs-style.css.css') ?>">
</head>
<body class="developers-page">

    <?php $activeSection = 'about'; ?>
    <?php include_once __DIR__ . '/../../../components/navbar.php'; ?>

    <section class="hero-section">
        
        <!-- ========================================== -->
        <!-- BACKGROUND IMAGE -->
        <!-- ========================================== -->
        <div class="bg-image-container">
            <img src="/assets/images/hero-bg.png" alt="Blurred Festival Background"> 
            <!-- ADD YOUR BACKGROUND IMAGE ASSET PATH IN THE SRC ABOVE -->
        </div>

        <!-- ========================================== -->
        <!-- DECORATIVE SHAPES & ANIMATIONS -->
        <!-- ========================================== -->
        <!-- Pure CSS Shapes -->
        <div class="shape circle-solid anim-float"></div>
        <div class="shape circle-hole hole-top anim-float-delayed"></div>
        <div class="shape circle-hole hole-bottom anim-float"></div>

        <!-- Image Placeholders for the Green Spheres -->
        <img src="/assets/images/green-sphere-top-left.png" alt="Green Sphere Top Left" class="sphere sphere-top-left anim-float-delayed"> 
        <!-- ADD YOUR 3D SPHERE IMAGE ASSET PATH ABOVE -->
        
        <img src="/assets/images/green-sphere-bot-left.png" alt="Large Green Sphere Bottom Left" class="sphere sphere-bottom-left anim-float"> 
        <!-- ADD YOUR 3D SPHERE IMAGE ASSET PATH ABOVE -->
        
        <img src="/assets/images/green-sphere-bot-right.png" alt="Small Green Sphere Bottom Right" class="sphere sphere-bottom-right anim-float-delayed"> 
        <!-- ADD YOUR 3D SPHERE IMAGE ASSET PATH ABOVE -->


        <!-- ========================================== -->
        <!-- MAIN CONTENT -->
        <!-- ========================================== -->
        <div class="container content-container">
            <div class="row align-items-center">
                
                <!-- Left Side: Header & Paragraph -->
                <div class="col-lg-6 text-ivory pe-lg-5">
                    <h1 class="serif-font display-4 fw-bold mb-4 text-ivory">
                        Meet the <span class="text-accent fst-italic">developers</span>
                    </h1>
                    <p class="fs-5 lh-lg text-ivory">
                        A team united by creativity, dedication, and a shared vision. Meet the people who brought our ideas to life and turned our final-year capstone project into something meaningful.
                    </p>
                </div>

                <!-- Right Side: Gradient Card -->
                <div class="col-lg-5 offset-lg-1">
                    <div class="quote-wrapper anim-float">
                        <!-- Yellow Offset Rectangle -->
                        <div class="quote-bg-rect"></div>
                        
                        <!-- Main Card -->
                        <div class="quote-card">
                            <div class="star-shape"></div>
                            
                            <h3 class="serif-font fst-italic fw-normal lh-base mb-5 fs-2 text-ivory">
                                Built with passion, late nights, and an endless drive to create something meaningful.
                            </h3>
                            
                            <div class="d-flex align-items-center">
                                <span class="me-3" style="width: 30px; height: 1px; background-color: #F9F6F1;"></span>
                                <p class="mb-0 text-uppercase tracking-wider fs-6 letter-spacing-1 text-ivory">
                                    GNCILink Dev Team
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- TEAM SECTION -->
    <!-- ========================================== -->
    <section class="team-section">
        <div class="container">
            
            <!-- Section Header -->
            <div class="text-center section-header mx-auto">
                <h6 class="text-accent text-uppercase fw-bold letter-spacing-1 mb-2">The People Behind the Pixels</h6>
                <h2 class="serif-font team-section-title fw-bold mb-3">Four Roles. One Team</h2>
                <p class="text-dark mt-2 lh-base">
                    Each person owned a different piece of the puzzle.<br class="d-none d-md-block">
                    Together, we designed, developed, tested, and delivered our capstone from the ground up.
                </p>
            </div>
            
            <!-- Team Grid -->
            <div class="row g-4 mt-5">
                
                <?php require_once __DIR__ . '/../../../includes/website-developers.php'; ?>
                <?php foreach (gncWebsiteDevelopers() as $index => $member): ?>
                <div class="col-sm-6 col-lg-3<?= $index % 2 ? ' mt-lg-5' : '' ?>">
                    <div class="team-card">
                        <img src="<?= htmlspecialchars($member['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($member['name'], ENT_QUOTES, 'UTF-8') ?>" class="img-fluid w-100 team-img">
                        <div class="text-start mt-3">
                            <p class="team-role mb-1"><?= htmlspecialchars($member['role'], ENT_QUOTES, 'UTF-8') ?></p>
                            <h5 class="team-name serif-font"><?= htmlspecialchars($member['name'], ENT_QUOTES, 'UTF-8') ?></h5>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- ADVISER / THANK YOU SECTION -->
    <!-- ========================================== -->
    <section class="adviser-section position-relative overflow-hidden">
        
        <!-- MAIN BACKGROUND IMAGE (Includes # THANK YOU text) -->
        <div class="adviser-bg-image-container">
            <img src="/assets/images/BG-adviser.png" alt="Thank You Background">
        </div>

        <div class="container position-relative z-3">
            <div class="row align-items-center">
                
                <!-- Left Side: Adviser Photo & Name Card -->
                <div class="col-lg-5 mb-5 mb-lg-0 pe-lg-5">
                    <div class="adviser-image-wrapper position-relative">
                        
                        <img src="/assets/images/Image with Sparkle.png" alt="Capstone Adviser" class="img-fluid w-100 adviser-img position-relative z-2">
                        
                        <!-- Yellow Name Card Overlapping -->
                        <div class="adviser-name-card">
                            <p class="text-uppercase fw-bold mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Capstone Adviser</p>
                            <h3 class="serif-font fw-bold mb-0 text-dark-green">Jhelou A. Supan</h3>
                            <p class="mb-0 text-end fst-italic text-dark-green" style="font-size: 0.85rem; margin-top: 0.5rem;">MLIS, RL, ITS</p>
                        </div>

                    </div>
                </div>

                <!-- Right Side: Thank You Message -->
                <div class="col-lg-7 ps-lg-5">
                    <h6 class="text-uppercase fw-bold letter-spacing-1 mb-4 text-ivory" style="font-size: 0.75rem;">With Our Deepest Gratitude</h6>
                    
                    <h2 class="serif-font fst-italic fw-bold lh-base mb-4 fs-1 text-ivory">
                        “The best projects don't begin with answers. They begin with the courage to ask a better question.”
                    </h2>
                    
                    <p class="lh-lg mb-5 text-ivory" style="font-size: 0.95rem;">
                        Thank you for challenging our initial ideas, celebrating every small achievement, and encouraging us to push beyond our limits and step outside our comfort zones to create something truly meaningful. Your guidance not only helped shape this project into something better than we first imagined but also gave us the confidence to face challenges, grow as individuals, and keep moving forward.
                    </p>
                    
                    <div class="d-flex align-items-center text-ivory">
                        <span class="me-3" style="width: 30px; height: 1px; background-color: #F9F6F1;"></span>
                        <p class="mb-0 text-uppercase letter-spacing-1">
                            Your Capstone Advisees
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- GALLERY SECTION -->
    <!-- ========================================== -->
    <section class="gallery-section">
        <div class="container-fluid px-4 px-md-5">
            <div class="row g-3">
                
                <!-- Row 1, Image 1 (Wider) -->
                <div class="col-md-7">
                    <div class="gallery-image-wrapper">
                        <img src="/assets/images/gallery-1.png" alt="Team Group Photo 1" class="img-fluid gallery-img">
                        <!-- ADD GALLERY IMAGE 1 ASSET PATH ABOVE -->
                    </div>
                </div>
                
                <!-- Row 1, Image 2 (Narrower) -->
                <div class="col-md-5">
                    <div class="gallery-image-wrapper">
                        <img src="/assets/images/gallery-2.png" alt="Team Group Photo 2" class="img-fluid gallery-img">
                        <!-- ADD GALLERY IMAGE 2 ASSET PATH ABOVE -->
                    </div>
                </div>

                <!-- Row 2, Image 1 (Narrower) -->
                <div class="col-md-5">
                    <div class="gallery-image-wrapper">
                        <img src="/assets/images/gallery-3.png" alt="Team Group Photo 3" class="img-fluid gallery-img">
                        <!-- ADD GALLERY IMAGE 3 ASSET PATH ABOVE -->
                    </div>
                </div>

                <!-- Row 2, Image 2 (Wider) -->
                <div class="col-md-7">
                    <div class="gallery-image-wrapper">
                        <img src="/assets/images/gallery-4.png" alt="Team Group Photo 4" class="img-fluid gallery-img">
                        <!-- ADD GALLERY IMAGE 4 ASSET PATH ABOVE -->
                    </div>
                </div>

            </div>
        </div>
    </section>

    <?php include_once __DIR__ . '/../../../components/footer.php'; ?>

    <!-- Bootstrap 5 JS -->
</body>
</html>
