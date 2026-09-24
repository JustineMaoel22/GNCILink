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
    <link href="/assets/css/logo-meaning-style.css" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php $activeSection = 'about'; ?>
    <?php include_once __DIR__ . '/../../components/index-nav.php'; ?>

    <section class="logo-meaning-intro">
        <div class="logo-meaning-hero">
            <div class="logo-meaning-hero-inner">
                <nav class="logo-meaning-breadcrumb" aria-label="breadcrumb">
                    <span>About</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="logo-meaning-current">Institutional Logo and Meaning</span>
                </nav>
                <h1>Institutional Logo and Meaning</h1>
                <p>Our logo embodies the rich heritage, steadfast values, and unwavering commitment 
                    of Guagua National Colleges, Inc. to education, services, and nation-building.</p>
            </div>
        </div>
    </section>

    <section class="logo-meaning-content-section">
        <div class="logo-meaning-container">
            <div class="logo-display-wrapper">
                <img src="/assets/images/logos/gnc-logo-v1.svg" alt="Guagua National Colleges Institutional Logo" class="logo-meaning-graphic">
            </div>

            <div class="logo-info-wrapper">
                <h2>About the Logo</h2>
                <p>The Logo of Guagua National Colleges, Inc. is composed of:</p>

                <div class="meaning-item">
                    <div class="icon-circle-box crucifix-icon">
                        <img src="/assets/images/svg/Fides Symbol.svg" alt="Crucifix Icon">
                    </div>
                    <div class="meaning-text">
                        <h3>The Crucifix</h3>
                        <p>Symbolizes Fides or Faith in God and One's self.</p>
                    </div>
                </div>

                <div class="meaning-item">
                    <div class="icon-circle-box book-icon">
                        <img src="/assets/images/svg/Scientia Symbol.svg" alt="Book Icon">
                    </div>
                    <div class="meaning-text">
                        <h3>The Book</h3>
                        <p>Symbolizes Scientia or Search for Truth and Knowledge.</p>
                    </div>
                </div>

                <div class="meaning-item">
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


    <?php include_once __DIR__ . '/../../components/index-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>

</body>
</html>