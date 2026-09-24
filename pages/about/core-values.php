<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Core Values</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/core-values-style.css" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php $activeSection = 'about'; ?>
    <?php include_once __DIR__ . '/../../components/index-nav.php'; ?>

    <section class="core-values-intro">
        <div class="core-values-hero">
            <div class="core-values-hero-inner">
                <nav class="core-values-breadcrumb" aria-label="breadcrumb">
                    <span>About</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="dept-current">Core Values</span>
                </nav>
                <h1>Core Values</h1>
                <p>Our core values guide our actions and decisions, reflecting our commitment to excellence and integrity.</p>
            </div>
        </div>
    </section>

    <section class="core-values-main">
        <div class="section-title">
            <h2>Our Core Values</h2>
        </div>
        
        <div class="cards-container">
            <!-- Fides Card -->
            <div class="value-card theme-gold">
                <div class="icon-wrapper">
                    <!-- 👇 PUT YOUR FIDES (CROSS) ICON HERE 👇 -->
                    <img src="/assets/images/svg/Fides Symbol.svg" alt="Fides Icon" style="width: 45px;">
                    <!-- 👆 PUT YOUR FIDES (CROSS) ICON HERE 👆 -->
                </div>
                <h3>Fides</h3>
                <p>Faith in God and<br>One's Self.</p>
            </div>

            <!-- Scientia Card -->
            <div class="value-card theme-green">
                <div class="icon-wrapper">
                    <!-- 👇 PUT YOUR SCIENTIA (BOOK) ICON HERE 👇 -->
                    <img src="/assets/images/svg/Scientia Symbol.svg" alt="Scientia Icon" style="width: 55px;">
                    <!-- 👆 PUT YOUR SCIENTIA (BOOK) ICON HERE 👆 -->
                </div>
                <h3>Scientia</h3>
                <p>Search for Truth<br>and Knowledge.</p>
            </div>

            <!-- Patria Card -->
            <div class="value-card theme-red">
                <div class="icon-wrapper">
                    <!-- 👇 PUT YOUR PATRIA (SWORD) ICON HERE 👇 -->
                    <img src="/assets/images/svg/Patria Symbol.svg" alt="Patria Icon" style="width: 50px;">
                    <!-- 👆 PUT YOUR PATRIA (SWORD) ICON HERE 👆 -->
                </div>
                <h3>Patria</h3>
                <p>Love for Country.</p>
            </div>
        </div>
    </section>

    <?php include_once __DIR__ . '/../../components/index-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
</body>
</html>