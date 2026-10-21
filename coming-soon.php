<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Something great is coming to GNCI Link. A new experience for the Guagua National Colleges community is on its way.">
    <meta name="theme-color" content="#0b4228">
    <title>Coming Soon | GNCI Link</title>
    <link rel="icon" type="image/svg+xml" href="/assets/images/logos/gnc-logo-v1.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Serif:ital,wght@0,500;0,600;1,500&display=swap" rel="stylesheet">
    <link href="/assets/css/coming-soon-style.css?v=<?= filemtime(__DIR__ . '/assets/css/coming-soon-style.css') ?>" rel="stylesheet">
</head>
<body>
    <div class="coming-soon">
        <header class="coming-header">
            <a class="coming-brand" href="/index.php" aria-label="GNCI Link homepage">
                <span class="coming-brand-mark"><img src="/assets/images/logos/gnc-logo-v1.svg" width="38" height="38" alt=""></span>
                <span>GNCI <strong>LINK</strong></span>
            </a>
            <span class="coming-status"><span aria-hidden="true"></span> We're building</span>
        </header>

        <main class="coming-content">
            <span class="coming-eyebrow">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Z"/></svg>
                A new experience is on its way
            </span>
            <h1>Something great<br><em>is coming.</em></h1>
            <p class="coming-description">We're creating a better digital experience for the GNCI community. <span>Stay tuned.</span></p>
            <div class="coming-building">
                <span>Building something better</span>
                <span class="coming-building-line" aria-hidden="true"></span>
            </div>
            <p class="coming-motto">Fides <span aria-hidden="true">&middot;</span> Scientia <span aria-hidden="true">&middot;</span> Patria</p>
        </main>

        <footer class="coming-footer">
            <p>&copy; <?= (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y') ?> GNCI Link</p>
            <a href="/index.php">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/></svg>
                Official school website
                <span aria-hidden="true">&nearr;</span>
            </a>
        </footer>
    </div>
</body>
</html>
