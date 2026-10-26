<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institutional Logo and Meaning</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/logo-meaning-style.css?v=3" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
    <script>document.documentElement.classList.add('lm-wait');setTimeout(function(){document.documentElement.classList.remove('lm-wait');},2200);</script>
</head>
<body>
    <?php $activeSection = 'about'; ?>
    <?php include_once __DIR__ . '/../../components/navbar.php'; ?>

    <!-- Opening transition: panels wipe up to reveal the page (pure CSS, never blocks clicks) -->
    <div class="lm-open" aria-hidden="true">
        <i style="--n:0"></i><i style="--n:1"></i><i style="--n:2"></i><i style="--n:3"></i><i style="--n:4"></i><i style="--n:5"></i>
        <div class="lm-open-label">
            <img src="/assets/images/logos/gnc-logo-v1.svg" alt="">
            <span>Logo and Meaning</span>
        </div>
    </div>

    <section class="logo-meaning-intro">
        <div class="logo-meaning-hero">
            <div class="logo-meaning-hero-inner">
                <nav class="logo-meaning-breadcrumb reveal-up" aria-label="breadcrumb">
                    <span>About</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="logo-meaning-current">Institutional Logo and Meaning</span>
                </nav>
                <h1 class="reveal-up" style="transition-delay: 0.1s;">Institutional Logo and Meaning</h1>
                <p class="reveal-up" style="transition-delay: 0.2s;">Our logo embodies the rich heritage, steadfast values, and unwavering commitment 
                    of Guagua National Colleges, Inc. to education, services, and nation-building.</p>
            </div>
        </div>
    </section>

    <section class="logo-meaning-content-section">
        <div class="logo-meaning-container">
            <div class="logo-display-wrapper reveal-scale">
                <img src="/assets/images/logos/gnc-logo-v1.svg" alt="Guagua National Colleges Institutional Logo" class="logo-meaning-graphic">
            </div>

            <div class="logo-info-wrapper">
                <h2 class="reveal-up">About the Logo</h2>
                <p class="reveal-up" style="transition-delay: 0.1s;">The Logo of Guagua National Colleges, Inc. is composed of:</p>

                <div class="meaning-item reveal-up" style="transition-delay: 0.2s;">
                    <div class="icon-circle-box crucifix-icon">
                        <img src="/assets/images/svg/Fides Symbol.svg" alt="Crucifix Icon">
                    </div>
                    <div class="meaning-text">
                        <h3>The Crucifix</h3>
                        <p>Symbolizes Fides or Faith in God and One's self.</p>
                    </div>
                </div>

                <div class="meaning-item reveal-up" style="transition-delay: 0.3s;">
                    <div class="icon-circle-box book-icon">
                        <img src="/assets/images/svg/Scientia Symbol.svg" alt="Book Icon">
                    </div>
                    <div class="meaning-text">
                        <h3>The Book</h3>
                        <p>Symbolizes Scientia or Search for Truth and Knowledge.</p>
                    </div>
                </div>

                <div class="meaning-item reveal-up" style="transition-delay: 0.4s;">
                    <div class="icon-circle-box sword-icon">
                        <img src="/assets/images/svg/Patria Symbol.svg" alt="Sword Icon">
                    </div>
                    <div class="meaning-text">
                        <h3>The Sword</h3>
                        <p>Symbolizes Patria or Love of Country.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <?php include_once __DIR__ . '/../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="/assets/js/logo-meaning.js"></script>
</body>
</html>