<?php
require_once __DIR__ . '/config/config.php';

// Fetch the latest published announcements for the public homepage
try {
    $db = getDB();
    $stmt = $db->query("
        SELECT a.announcement_id, a.title, a.slug, a.content, a.published_at, a.created_at,
            c.category_name, m.file_path as image_path
        FROM announcements a
        LEFT JOIN categories c ON a.category_id = c.category_id
        LEFT JOIN media_library m ON a.featured_image = m.media_id
        WHERE a.status = 'published'
        ORDER BY COALESCE(a.published_at, a.created_at) DESC
        LIMIT 3
    ");
    $publicAnnouncements = $stmt->fetchAll();
} catch (Exception $e) {
    $publicAnnouncements = [];
}

// ---------------------------------------------------------------
// Events calendar (public, published only)
// ---------------------------------------------------------------
$evtMonth = isset($_GET['evt_month']) ? max(1, min(12, (int)$_GET['evt_month'])) : (int)date('n');
$evtYear  = isset($_GET['evt_year'])  ? (int)$_GET['evt_year']                  : (int)date('Y');

$evtFirstOfMonth = mktime(0, 0, 0, $evtMonth, 1, $evtYear);
$evtDaysInMonth  = (int)date('t', $evtFirstOfMonth);
$evtStartWeekday = (int)date('w', $evtFirstOfMonth); // 0 = Sunday
$evtMonthLabel   = date('F Y', $evtFirstOfMonth);

$evtPrevMonth = $evtMonth - 1; $evtPrevYear = $evtYear;
if ($evtPrevMonth < 1) { $evtPrevMonth = 12; $evtPrevYear--; }
$evtNextMonth = $evtMonth + 1; $evtNextYear = $evtYear;
if ($evtNextMonth > 12) { $evtNextMonth = 1; $evtNextYear++; }

// ---------------------------------------------------------------
// Category colors
// Holiday = yellow, School Event = green, Emergency = red
// ---------------------------------------------------------------
function evt_categoryColor(?string $categoryColor, ?string $categoryName = null): string {
    if (!empty($categoryColor)) return $categoryColor;
    $name = strtolower(trim($categoryName ?? ''));
    if (strpos($name, 'holiday') !== false)   return '#EABA3B';
    if (strpos($name, 'emergency') !== false || strpos($name, 'suspension') !== false) return '#B3312D';
    if (strpos($name, 'school') !== false)    return '#1F5E2C';
    return '#6c757d';
}

try {
    $evtRangeStart = sprintf('%04d-%02d-01 00:00:00', $evtYear, $evtMonth);
    $evtRangeEnd   = date('Y-m-d 23:59:59', mktime(0, 0, 0, $evtMonth, $evtDaysInMonth, $evtYear));

    $stmt = $db->prepare("
        SELECT e.event_id, e.title, e.start_date, e.end_date, e.status, c.category_name, c.category_color
        FROM events e
        LEFT JOIN categories c ON e.category_id = c.category_id
        WHERE e.status = 'published' AND e.start_date BETWEEN ? AND ?
        ORDER BY e.start_date ASC
    ");
    $stmt->execute([$evtRangeStart, $evtRangeEnd]);
    $publicMonthEvents = $stmt->fetchAll();
} catch (Exception $e) {
    $publicMonthEvents = [];
}

$publicEventsByDay = [];
foreach ($publicMonthEvents as $ev) {
    $day = (int)date('j', strtotime($ev['start_date']));
    $publicEventsByDay[$day][] = $ev;
}

// Upcoming events (next 3 published, from today onward, any month)
try {
    $stmt = $db->prepare("
        SELECT e.event_id, e.title, e.location, e.start_date, e.end_date, c.category_name, c.category_color
        FROM events e
        LEFT JOIN categories c ON e.category_id = c.category_id
        WHERE e.status = 'published' AND e.start_date >= ?
        ORDER BY e.start_date ASC
        LIMIT 3
    ");
    $stmt->execute([date('Y-m-d 00:00:00')]);
    $upcomingEvents = $stmt->fetchAll();
} catch (Exception $e) {
    $upcomingEvents = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guagua National Colleges</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/index-style.css?v=2" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="assets/images/logos/GNC LOGO 1.svg">
</head>
<body>

    <nav class="navbar gnc-navbar sticky-top navbar-expand-lg shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 me-3">
                <img src="assets/images/logos/GNC LOGO 1.svg" alt="GNC Logo" width="46" height="46">
                <div class="brand-text d-none d-md-block">
                    <span class="brand-name d-block">Guagua National Colleges, Inc.</span>
                    <span class="brand-tagline d-block">Fides, Scientia et. Patria</span>
                </div>
            </a>

            <button class="navbar-toggler border-0 ms-auto me-2" type="button"
                    data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item active">
                        <a class="nav-link active" href="#">Home</a>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="#">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Academics</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Admissions</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Student Life</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">News & Events</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Contact Us</a></li>

                    <li class="nav-item d-flex align-items-center">
                        <button id="search-toggle" class="btn-search" type="button" aria-label="Search">
                            <i class="bi bi-search"></i>
                        </button>
                    </li>
                    <li class="nav-item" id="search-box-item" style="display:none;">
                        <form class="d-flex" action="#" onsubmit="return false;">
                            <input id="search-input" class="form-control form-control-sm"
                                type="search" placeholder="Search…" aria-label="Search"
                                style="min-width:160px;">
                        </form>
                    </li>

                    <li class="nav-item ms-2">
                        <a class="btn-portal" href="auth/login.php" target="_blank">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-fill" viewBox="0 0 16 16">
                                <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
                            </svg>
                            Student Portal
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="hero-slideshow">
        <div class="hero-slide slide-1 active" data-slide="0">
            <div class="slide-bg" style="background-image: url('assets/images/gnc-front.png');"></div>
            <div class="slide-overlay"></div>
            <div class="container h-100">
                <div class="hero-content">
                    <h1 class="hero-title-gold mb-0">EMPOWERING MINDS.</h1>
                    <h1 class="hero-title-white">INSPIRING FUTURES.</h1>
                    <p class="hero-desc">
                        A Legacy of academic excellence, character formation,<br>
                        and service to the community since 1918.
                    </p>
                    <div class="hero-ctas">
                        <a href="auth/login.php" target="_blank" class="btn-enroll">
                            ENROLL NOW
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/>
                            </svg>
                        </a>
                        <a href="#about" class="btn-explore">EXPLORE GNC</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="hero-slide slide-2" data-slide="1">
            <div class="slide-video-wrap">
                <video
                    id="hero-video"
                    class="slide-video"
                    src="assets/video/gnc-drone-shot.mov"
                    poster="assets/images/gnc-front.png"
                    muted
                    loop
                    playsinline
                    preload="auto">
                </video>
            </div>
            <div class="slide-overlay"></div>
        </div>

        <div class="hero-slide slide-3" data-slide="2">
            <div class="slide-bg" style="background-image: url('assets/images/the-devs.png');"></div>
            <div class="slide-overlay"></div>
            <div class="container h-100">
                <div class="hero-content">
                    <h1 class="hero-title-gold mb-0">MEET THE</h1>
                    <h1 class="hero-title-white">DEVELOPERS.</h1>
                    <p class="hero-desc">
                        We are a dedicated group of student developers working together as part of our capstone project to design and develop our school’s official website. Our goal is to create a clean, user-friendly, and informative platform that effectively represents the school and serves the needs of students, teachers, parents, and visitors.
                    </p>
                </div>
            </div>
        </div>

        <button type="button" class="slide-arrow slide-arrow-prev" id="slide-prev" aria-label="Previous slide">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
            </svg>
        </button>
        <button type="button" class="slide-arrow slide-arrow-next" id="slide-next" aria-label="Next slide">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
            </svg>
        </button>

        <div class="hero-dots">
            <span class="active" data-target="0"></span>
            <span data-target="1"></span>
            <span data-target="2"></span>
        </div>
    </div>

    <div class="gnc-pillars">
        <div class="container">
            <div class="pillar-card">
                <div class="pillar-wrap">

                    <div class="pillar-item">
                        <div class="pillar-icon-wrap fides-bg">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L12 6" stroke="#C9A84C" stroke-width="2" stroke-linecap="round"/>
                                <path d="M10 4H14" stroke="#C9A84C" stroke-width="2" stroke-linecap="round"/>
                                <path d="M4 22V12L12 6L20 12V22" stroke="#C9A84C" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M9 22V17C9 15.9 9.9 15 11 15H13C14.1 15 15 15.9 15 17V22" stroke="#C9A84C" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div>
                            <p class="pillar-title fides-color">FIDES</p>
                            <p class="pillar-desc">Faith in God and One's Self.</p>
                        </div>
                    </div>

                    <div class="pillar-item">
                        <div class="pillar-icon-wrap scientia-bg">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2 6C2 6 5 5 7 5C9 5 11 6 12 7C13 6 15 5 17 5C19 5 22 6 22 6V19C22 19 19 18 17 18C15 18 13 19 12 20C11 19 9 18 7 18C5 18 2 19 2 19V6Z"
                                    stroke="#094024" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M12 7V20" stroke="#094024" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div>
                            <p class="pillar-title scientia-color">SCIENTIA</p>
                            <p class="pillar-desc">Search for Truth and Knowledge.</p>
                        </div>
                    </div>

                    <div class="pillar-item">
                        <div class="pillar-icon-wrap patria-bg">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5 19L19 5" stroke="#b03030" stroke-width="1.8" stroke-linecap="round"/>
                                <path d="M19 19L5 5" stroke="#b03030" stroke-width="1.8" stroke-linecap="round"/>
                                <circle cx="12" cy="12" r="3" stroke="#b03030" stroke-width="1.8"/>
                            </svg>
                        </div>
                        <div>
                            <p class="pillar-title patria-color">PATRIA</p>
                            <p class="pillar-desc">Love for Country.</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>  

    <section class="gnc-announcements py-5" id="announcements">
        <div class="container">
            <div class="text-center mb-5" style="max-width:640px;margin-left:auto;margin-right:auto;">
                <span class="d-inline-block mb-2" style="color:#EABA3B;font-weight:700;font-size:.78rem;letter-spacing:1.5px;text-transform:uppercase;">
                    Latest Announcements
                </span>
                <h2 class="mb-2" style="font-family: 'Noto Serif', serif; color:#094024;font-weight:800;">Stay Updated, Stay Informed</h2>
                <p class="text-muted mb-0">Get the latest news, events, and important updates from our official Facebook page.</p>
            </div>

            <?php if (empty($publicAnnouncements)): ?>
                <p class="text-muted text-center">No announcements have been posted yet. Please check back soon.</p>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($publicAnnouncements as $ann):
                        $excerpt = strip_tags($ann['content']);
                        $excerpt = mb_strlen($excerpt) > 110 ? mb_substr($excerpt, 0, 110) . '…' : $excerpt;
                        $dateToShow = $ann['published_at'] ?? $ann['created_at'];
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border-0 gnc-announcement-card">
                            <?php if (!empty($ann['image_path'])): ?>
                            <img src="<?= htmlspecialchars($ann['image_path']) ?>" class="card-img-top" alt="<?= htmlspecialchars($ann['title']) ?>" style="height:190px;object-fit:cover;">
                            <?php else: ?>
                            <div style="height:190px;background:#eef1ee;display:flex;align-items:center;justify-content:center;color:#c3cbc4;">
                                <i class="bi bi-megaphone" style="font-size:2rem;"></i>
                            </div>
                            <?php endif; ?>
                            <div class="card-body d-flex flex-column">
                                <small class="text-muted mb-2">
                                    <i class="bi bi-calendar3"></i> <?= date('F d, Y', strtotime($dateToShow)) ?>
                                </small>
                                <h5 class="card-title" style="font-family: 'Noto Serif', serif; color:#094024;font-weight:800;">
                                    <?= htmlspecialchars($ann['title']) ?>
                                </h5>
                                <p class="card-text text-muted flex-grow-1" style="font-family: 'Inter', sans-serif; font-size: .9rem;">
                                    <?= htmlspecialchars($excerpt) ?>
                                </p>
                                <a href="announcement.php?slug=<?= urlencode($ann['slug']) ?>" class="gnc-read-more text-decoration-none mt-1">
                                    Read More
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="text-center mt-5">
                    <a href="announcements.php" class="btn gnc-view-all-btn">
                        View All Announcements &amp; News
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="gnc-events-cal py-5" id="events" style="background:#f7f8f6;">
        <div class="container">
            <h2 class="mb-4" style="font-family:'Noto Serif', serif; color:#094024; font-weight:800;">Calendar of Events</h2>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="gnc-cal-card">
                        <div class="gnc-cal-header">
                            <a href="?evt_month=<?= $evtPrevMonth ?>&evt_year=<?= $evtPrevYear ?>#events" class="gnc-cal-nav">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                            <span class="gnc-cal-title"><?= htmlspecialchars($evtMonthLabel) ?></span>
                            <a href="?evt_month=<?= $evtNextMonth ?>&evt_year=<?= $evtNextYear ?>#events" class="gnc-cal-nav">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </div>

                        <div class="gnc-cal-grid">
                            <?php foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $dow): ?>
                                <div class="gnc-cal-dow"><?= $dow ?></div>
                            <?php endforeach; ?>

                            <?php for ($i = 0; $i < $evtStartWeekday; $i++): ?>
                                <div class="gnc-cal-cell gnc-cal-empty"></div>
                            <?php endfor; ?>

                            <?php for ($day = 1; $day <= $evtDaysInMonth; $day++):
                                $isToday = ($day == (int)date('j') && $evtMonth == (int)date('n') && $evtYear == (int)date('Y'));
                                $dayHasEvent = !empty($publicEventsByDay[$day]);
                                $dayTitles = $dayHasEvent ? implode("\n", array_map(fn($e) => $e['title'], $publicEventsByDay[$day])) : '';
                                $dayColors = $dayHasEvent ? array_values(array_unique(array_map(fn($e) => evt_categoryColor($e['category_color'] ?? null, $e['category_name'] ?? null), $publicEventsByDay[$day]))) : [];
                            ?>
                                <div class="gnc-cal-cell<?= $isToday ? ' gnc-cal-today' : '' ?><?= $dayHasEvent ? ' gnc-cal-hasevent' : '' ?>"
                                     <?= $dayHasEvent ? 'title="' . htmlspecialchars($dayTitles) . '"' : '' ?>>
                                    <?= $day ?>
                                    <?php if ($dayHasEvent): ?>
                                        <span class="gnc-cal-dots">
                                            <?php foreach (array_slice($dayColors, 0, 3) as $c): ?>
                                                <span class="gnc-cal-dot" style="background:<?= $c ?>;"></span>
                                            <?php endforeach; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <div class="gnc-cal-legend">
                            <span><span class="gnc-cal-dot" style="background:#EABA3B;"></span> Holiday</span>
                            <span><span class="gnc-cal-dot" style="background:#1F5E2C;"></span> School Event</span>
                            <span><span class="gnc-cal-dot" style="background:#B3312D;"></span> Emergency</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="gnc-upcoming-card">
                        <div class="gnc-upcoming-header">
                            <i class="bi bi-calendar-event-fill"></i> Upcoming Events
                        </div>
                        <div class="gnc-upcoming-list">
                            <?php if (empty($upcomingEvents)): ?>
                                <p class="text-muted px-3 py-4 mb-0">No upcoming events at this time.</p>
                            <?php else: foreach ($upcomingEvents as $ev): ?>
                                <div class="gnc-upcoming-item" style="border-left: 4px solid <?= evt_categoryColor($ev['category_color'] ?? null, $ev['category_name'] ?? null) ?>;">
                                    <i class="bi bi-calendar3 gnc-upcoming-icon" style="color: <?= evt_categoryColor($ev['category_color'] ?? null, $ev['category_name'] ?? null) ?>;"></i>
                                    <div>
                                        <div class="gnc-upcoming-title"><?= htmlspecialchars($ev['title']) ?></div>
                                        <div class="gnc-upcoming-date">
                                            <?= date('F j, Y', strtotime($ev['start_date'])) ?>
                                            <?php if (!empty($ev['location'])): ?>
                                                · <?= htmlspecialchars($ev['location']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <style>
        .gnc-cal-card, .gnc-upcoming-card {
            background:#fff; border-radius:12px;
            box-shadow:0 2px 10px rgba(0,0,0,0.06);
            overflow:hidden; height:100%;
        }
        .gnc-cal-header {
            display:flex; align-items:center; gap:.75rem;
            padding:1rem 1.25rem; border-bottom:1px solid #f0f0f0;
        }
        .gnc-cal-title { font-weight:700; color:#094024; font-size:1.05rem; }
        .gnc-cal-nav {
            width:32px; height:32px; border-radius:8px;
            display:flex; align-items:center; justify-content:center;
            background:#f5f5f5; color:#094024; text-decoration:none;
            transition:background .15s;
        }
        .gnc-cal-nav:hover { background:#e8e8e8; }
        .gnc-cal-nav:last-of-type { margin-left:auto; }
        .gnc-cal-grid {
            display:grid; grid-template-columns:repeat(7,1fr);
            gap:2px; padding:1rem 1.25rem;
        }
        .gnc-cal-dow {
            text-align:center; font-size:.68rem; font-weight:700;
            color:#999; text-transform:uppercase; padding-bottom:6px;
        }
        .gnc-cal-cell {
            aspect-ratio:1; display:flex; flex-direction:column; align-items:center; justify-content:center;
            font-size:.82rem; color:#444; border-radius:6px; position:relative;
        }
        .gnc-cal-empty { background:none; }
        .gnc-cal-today { background:#DDEBFD; font-weight:700; color:#094024; border:1px solid #1877F2; }
        .gnc-cal-hasevent {
            background:#f3f6f3; font-weight:700; cursor:default;
        }
        .gnc-cal-hasevent.gnc-cal-today { background:#DDEBFD; border-color:#1877F2; }
        .gnc-cal-dots { display:flex; gap:3px; margin-top:2px; }
        .gnc-cal-dot {
            display:inline-block; width:6px; height:6px; border-radius:50%;
        }
        .gnc-cal-legend {
            display:flex; flex-wrap:wrap; gap:14px; padding:0 1.25rem 1rem;
            font-size:.78rem; color:#555;
        }
        .gnc-cal-legend .gnc-cal-dot { width:9px; height:9px; margin-right:5px; }

        .gnc-upcoming-header {
            background:#094024; color:#fff; font-weight:700;
            padding:1rem 1.25rem; font-size:.95rem;
        }
        .gnc-upcoming-header i { color:#EABA3B; margin-right:.4rem; }
        .gnc-upcoming-item {
            display:flex; gap:.75rem; align-items:flex-start;
            padding:.9rem 1.25rem; border-bottom:1px solid #f5f5f5;
        }
        .gnc-upcoming-item:last-child { border-bottom:none; }
        .gnc-upcoming-icon { color:#EABA3B; font-size:1.1rem; margin-top:2px; }
        .gnc-upcoming-title { font-weight:700; color:#094024; font-size:.9rem; }
        .gnc-upcoming-date { font-size:.78rem; color:#888; margin-top:2px; }
    </style>
    
    <script src="assets/js/index.js?v=2"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.body.addEventListener('click', async (e) => {
                const navLink = e.target.closest('.gnc-cal-nav');
                if (!navLink) return;

                e.preventDefault(); 
                
                const url = navLink.href;

                try {
                    const response = await fetch(url);
                    const html = await response.text();

                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newCalendar = doc.querySelector('.gnc-cal-card').innerHTML;

                    document.querySelector('.gnc-cal-card').innerHTML = newCalendar;
                    
                    window.history.pushState({}, '', url);
                } catch (error) {
                    console.error('Failed to load calendar:', error);
                    window.location.href = url;
                }
            });
        });
    </script>
</body>
</html>