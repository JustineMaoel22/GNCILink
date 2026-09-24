<?php
/**
 * Public Vision & Mission page.
 * Vision/Mission text is managed from the admin Content Editor
 * (Select Page → Vision & Mission) and stored in page_sections;
 * only the text is dynamic — icons and layout are unchanged.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../admin/admin-functions.php';

$visionText  = getSectionContent('vision-mission', 'vision');
$missionText = getSectionContent('vision-mission', 'mission');

// Fallback to the original copy if the CMS row hasn't been created yet
// (e.g. migration not yet run), so the page never renders blank.
if ($visionText === null) {
    $visionText = "Our graduates will be globally\ncompetitive citizens.\nThey will uphold our founding values.";
}
if ($missionText === null) {
    $missionText = "We will be the leading learning\ninstitution in Western\nPampanga.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vision and Mission</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/vision-mission-style.css" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php $activeSection = 'about'; ?>
    <?php include_once __DIR__ . '/../../components/index-nav.php'; ?>

    <section class="vision-mission-intro">
        <div class="vision-mission-hero">
            <div class="vision-mission-hero-inner">
                <nav class="vision-mission-breadcrumb" aria-label="breadcrumb">
                    <span>About</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="dept-current">Vision and Mission</span>
                </nav>
                <h1>Vision and Mission</h1>
                <p>Our vision inspires our direction and our mission drives our daily commitment to education and service.</p>
            </div>
        </div>
    </section>

    <section class="vision-mission-content py-5">
        <div class="container vision-mission-container">
            <div class="row g-4 justify-content-center">

                <!-- Vision Card -->
                <div class="col-lg-6">
                    <div class="vm-card">
                        <!-- Colored sidebar with seal watermark and icon circle inside -->
                        <div class="vm-sidebar vm-bg-green">
                            <div class="vm-watermark-seal"></div>
                            <div class="vm-icon-circle">
                                <img src="/assets/images/svg/vision icon.svg" alt="Vision Icon" style="width: 42px;">
                            </div>
                        </div>

                        <!-- Full-card watermark (architectural illustration) -->
                        <div class="vm-watermark-building"></div>

                        <!-- Text Content -->
                        <div class="vm-content">
                            <h2 class="vm-title text-green">Our Vision</h2>
                            <p class="vm-text">
                                <?= nl2br(htmlspecialchars($visionText)) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Mission Card -->
                <div class="col-lg-6">
                    <div class="vm-card">
                        <!-- Colored sidebar with seal watermark and icon circle inside -->
                        <div class="vm-sidebar vm-bg-gold">
                            <div class="vm-watermark-seal"></div>
                            <div class="vm-icon-circle">
                                <img src="/assets/images/svg/mission icon.svg" alt="Mission Icon" style="width: 42px;">
                            </div>
                        </div>

                        <!-- Full-card watermark (architectural illustration) -->
                        <div class="vm-watermark-building"></div>

                        <!-- Text Content -->
                        <div class="vm-content">
                            <h2 class="vm-title text-gold">Our Mission</h2>
                            <p class="vm-text">
                                <?= nl2br(htmlspecialchars($missionText)) ?>
                            </p>
                        </div>
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