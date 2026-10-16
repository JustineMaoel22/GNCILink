<?php
require_once __DIR__ . '/../config/config.php';

session_start();

// ---------------------------------------------------------------
// Contact details shown on the page (same as the site footer).
// The contact form emails $contact['email'].
// ---------------------------------------------------------------
$contact = [
    'address'  => 'Rep. Eligio Lagman St, Sta. Filomena Rd, Guagua, Pampanga',
    'phone'    => '(045) 123-4567',
    'email'    => 'justinemaoel.gnc@gmail.com',
    'hours'    => 'Monday - Friday, 8:00 AM - 5:00 PM',
    'facebook' => 'https://www.facebook.com/gnc.edu.ph',
];

// ---------------------------------------------------------------
// School directory (from the official GNC directory sheet).
// Edit / add rows here; the page and the search box pick them up.
// ---------------------------------------------------------------
$directDirectory = [
    ['900-4473', "President's Office"],
    ['900-0341', 'College Registrar Office'],
    ['900-2207', 'Accounting Office'],
];

$localDirectory = [
    ['101', "President's Office/Operator/Boardroom"],
    ['105', 'Human Resources Office'],
    ['106', 'College Registrar'],
    ['107', 'Accounting Office'],
    ['108', 'College Guidance'],
    ['109', 'Medical Clinic/Dental Clinic'],
    ['109', 'Office of the Student Affairs'],
    ['113', 'College Library'],
    ['115', 'Elementary Principal Office/Printing'],
    ['116', 'High School Library'],
    ['117', 'High School Principal Office'],
    ['119', 'School Electrician'],
    ['120', 'CASEd Office (Education/Arts and Sciences)'],
    ['121', 'Montessori Office'],
    ['122', 'Guardhouse (Main Gate)'],
    ['123', 'Purchasing Office'],
    ['124', 'Computer Technician Office'],
    ['125', 'College of Engineering Office'],
    ['137', 'CAMP Office'],
    ['138', 'Head, Instruction Cluster (HIC) Office'],
];

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/**
 * Send the contact message.
 * Uses SMTP (PHPMailer) when MAIL_USERNAME / MAIL_PASSWORD are set in your .env
 * (MAIL_HOST, MAIL_PORT, MAIL_FROM_NAME, MAIL_ENCRYPTION are optional) and
 * PHPMailer is installed (composer require phpmailer/phpmailer).
 * Otherwise falls back to PHP's mail(), which only works on a server with mail set up.
 */
function gncSendContactMail(string $to, string $name, string $replyEmail, string $subject, string $body, string $headers): bool {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($autoload)) { require_once $autoload; }

    $env  = fn(string $k, string $d = '') => (string)(($_ENV[$k] ?? getenv($k)) ?: $d);
    $user = $env('MAIL_USERNAME');
    $pass = $env('MAIL_PASSWORD');
    $port = (int)$env('MAIL_PORT', '587');
    $enc  = strtolower($env('MAIL_ENCRYPTION'));
    if ($enc === '') { $enc = ($port === 465) ? 'ssl' : 'tls'; }   // blank = pick by port

    if ($user !== '' && $pass !== '' && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $m = new PHPMailer\PHPMailer\PHPMailer(true);
            $m->isSMTP();
            $m->Host       = $env('MAIL_HOST', 'smtp.gmail.com');
            $m->SMTPAuth   = true;
            $m->Username   = $user;
            $m->Password   = $pass;
            $m->SMTPSecure = ($enc === 'ssl' || $enc === 'smtps')
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $m->Port       = $port;
            $m->CharSet    = 'UTF-8';
            $m->setFrom($user, $env('MAIL_FROM_NAME', 'GNC Website'));   // Gmail requires the sender to be the logged-in account
            $m->addAddress($to);
            $m->addReplyTo($replyEmail, $name);   // Reply goes straight to the visitor
            $m->Subject = $subject;
            $m->Body    = $body;
            $m->send();
            return true;
        } catch (Throwable $ex) {
            error_log('Contact form SMTP error: ' . $ex->getMessage());
            return false;
        }
    }

    $why = ($user === '' || $pass === '') ? 'MAIL_USERNAME / MAIL_PASSWORD are empty (is .env loaded?)'
         : 'PHPMailer is not installed (run: composer require phpmailer/phpmailer)';
    error_log('Contact form: SMTP not used (' . $why . '); falling back to mail()');
    return @mail($to, $subject, $body, $headers);
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$errors  = [];
$success = '';
$old     = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];

// Result of the previous submit (set just before the redirect below). Shown once, then cleared.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['contact_flash'])) {
    $flash   = $_SESSION['contact_flash'];
    unset($_SESSION['contact_flash']);
    $success = $flash['success'] ?? '';
    $errors  = $flash['errors'] ?? [];
    $old     = array_merge($old, $flash['old'] ?? []);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name']    = trim($_POST['name'] ?? '');
    $old['email']   = trim($_POST['email'] ?? '');
    $old['subject'] = trim($_POST['subject'] ?? '');
    $old['message'] = trim($_POST['message'] ?? '');

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (!empty($_POST['gnc_hp_7f3a'])) {
        // Honeypot filled in: pretend success, send nothing.
        $success = 'Thank you! Your message has been sent.';
        $old = array_map(fn() => '', $old);
    } else {
        if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
            $errors[] = 'Please enter your full name.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (mb_strlen($old['subject']) > 150) {
            $errors[] = 'Subject is too long (150 characters max).';
        }
        if ($old['message'] === '' || mb_strlen($old['message']) > 5000) {
            $errors[] = 'Please enter a message (5000 characters max).';
        }

        if (!$errors) {
            $subject = $old['subject'] !== '' ? $old['subject'] : 'New message from the website';
            $subject = str_replace(["\r", "\n"], ' ', $subject);
            $body    = "Name: {$old['name']}\nEmail: {$old['email']}\n\n{$old['message']}\n";
            $headers = "From: no-reply@gnc.edu.ph\r\n"
                     . 'Reply-To: ' . str_replace(["\r", "\n"], '', $old['email']) . "\r\n"
                     . "Content-Type: text/plain; charset=UTF-8\r\n";

            if (gncSendContactMail($contact['email'], $old['name'], $old['email'], '[GNC Website] ' . $subject, $body, $headers)) {
                $success = 'Thank you! Your message has been sent. We will get back to you soon.';
                $old = array_map(fn() => '', $old);
            } else {
                $errors[] = 'Sorry, we could not send your message right now. Please try again later.';
            }
        }
    }

    // Post/Redirect/Get: remember the result, then reload the page with a normal GET
    // so refreshing the browser never re-submits the form (or re-sends the email).
    $_SESSION['contact_flash'] = ['success' => $success, 'errors' => $errors, 'old' => $old];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#contact-form');
    exit;
}

// Play the opening transition on normal visits, but not right after a form submit.
$showOpen = ($success === '' && !$errors);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/base-style.css" rel="stylesheet">
    <link href="../assets/css/navbar-style.css" rel="stylesheet">
    <link href="../assets/css/footer-style.css" rel="stylesheet">
    <link href="../assets/css/contact-us-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="../assets/images/logos/gnc-logo-v1.svg">
    <script>
        /* Scroll-reveal animations are only enabled when JS runs and motion isn't reduced. */
        (function () {
            try {
                if (!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
                    document.documentElement.classList.add('gnc-ct-js');
                }
            } catch (e) {}
        })();
    </script>
</head>
<body>
    <?php $activeSection = 'contact-us'; ?>
    <?php include __DIR__ . '/../components/navbar.php'; ?>

    <main class="gnc-contact<?= $showOpen ? '' : ' gnc-contact--quick' ?>">
    <?php if ($showOpen): ?>
    <!-- Opening transition: panels wipe up to reveal the page (pure CSS, never blocks clicks) -->
    <div class="gnc-contact-open" aria-hidden="true">
        <i style="--n:0"></i><i style="--n:1"></i><i style="--n:2"></i><i style="--n:3"></i><i style="--n:4"></i><i style="--n:5"></i>
        <div class="gnc-contact-open-label">
            <img src="../assets/images/logos/gnc-logo-v1.svg" alt="">
            <span>Contact Us</span>
        </div>
    </div>
    <?php endif; ?>
    <header class="gnc-contact-hero">
        <span class="gnc-contact-kicker"><i class="bi bi-chat-dots-fill"></i> Get in touch</span>
        <h1 class="gnc-contact-title">Contact <span>Us</span></h1>
        <p class="gnc-contact-lead">Questions about admissions, programs, or campus life? Send us a message and the GNC team will get back to you.</p>
    </header>

    <section class="gnc-contact-sec">
        <div class="container">
            <div class="row g-4 gnc-contact-grid">

                <!-- Contact details -->
                <div class="col-lg-5">
                    <div class="gnc-contact-info">
                        <h2>Reach Out to GNC</h2>
                        <p>We're happy to help. Use any of the channels below or send us a message.</p>

                        <div class="gnc-contact-item">
                            <span class="gnc-contact-icon"><i class="bi bi-geo-alt-fill"></i></span>
                            <div>
                                <h3>Address</h3>
                                <p><?= e($contact['address']) ?></p>
                            </div>
                        </div>

                        <div class="gnc-contact-item">
                            <span class="gnc-contact-icon"><i class="bi bi-telephone-fill"></i></span>
                            <div>
                                <h3>Phone</h3>
                                <a href="tel:<?= e(preg_replace('/[^+\d]/', '', $contact['phone'])) ?>"><?= e($contact['phone']) ?></a>
                            </div>
                        </div>

                        <div class="gnc-contact-item">
                            <span class="gnc-contact-icon"><i class="bi bi-envelope-fill"></i></span>
                            <div>
                                <h3>Email</h3>
                                <a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a>
                            </div>
                        </div>

                        <div class="gnc-contact-item">
                            <span class="gnc-contact-icon"><i class="bi bi-clock-fill"></i></span>
                            <div>
                                <h3>Office Hours</h3>
                                <p><?= e($contact['hours']) ?></p>
                            </div>
                        </div>

                        <div class="gnc-contact-links">
                            <a class="gnc-contact-pill gnc-contact-pill--gold" href="../index.php#campus-map">
                                <i class="bi bi-map-fill"></i> Campus Map
                            </a>
                            <a class="gnc-contact-pill gnc-contact-pill--line" href="<?= e($contact['facebook']) ?>" target="_blank" rel="noopener">
                                <i class="bi bi-facebook"></i> Facebook
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <div class="col-lg-7">
                    <div class="gnc-contact-card">
                        <h2>Send a Message</h2>
                        <p>Fields marked with <span style="color:#b3261e;">*</span> are required.</p>

                        <?php if ($success): ?>
                            <div class="gnc-alert gnc-alert--ok" role="status"><i class="bi bi-check-circle-fill"></i> <?= e($success) ?></div>
                        <?php endif; ?>
                        <?php if ($errors): ?>
                            <div class="gnc-alert gnc-alert--error" role="alert">
                                <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="#contact-form" id="contact-form" novalidate>
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                            <div class="gnc-hp" aria-hidden="true">
                                <input type="text" name="gnc_hp_7f3a" value="" tabindex="-1" autocomplete="off" aria-hidden="true">
                            </div>

                            <div class="row">
                                <div class="col-md-6 gnc-field">
                                    <label for="name">Full Name <span class="req">*</span></label>
                                    <input type="text" id="name" name="name" placeholder="Juan Dela Cruz" value="<?= e($old['name']) ?>" maxlength="100" required>
                                </div>
                                <div class="col-md-6 gnc-field">
                                    <label for="email">Email Address <span class="req">*</span></label>
                                    <input type="email" id="email" name="email" placeholder="you@example.com" value="<?= e($old['email']) ?>" required>
                                </div>
                            </div>

                            <div class="gnc-field">
                                <label for="subject">Subject</label>
                                <input type="text" id="subject" name="subject" placeholder="How can we help?" value="<?= e($old['subject']) ?>" maxlength="150">
                            </div>

                            <div class="gnc-field">
                                <label for="message">Message <span class="req">*</span></label>
                                <textarea id="message" name="message" placeholder="Tell us what's on your mind…" maxlength="5000" required><?= e($old['message']) ?></textarea>
                            </div>

                            <button type="submit" class="gnc-contact-submit">
                                Send Message <i class="bi bi-send-fill"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- School directory -->
            <section class="gnc-dir" id="school-directory" aria-labelledby="gncDirTitle">
                <div class="gnc-dir-head" data-ct-reveal>
                    <h2 id="gncDirTitle">School Directory</h2>
                    <p>Find the right office and its phone line.</p>
                </div>

                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="gnc-dir-card gnc-dir-card--direct" data-ct-reveal>
                            <h3><i class="bi bi-telephone-fill"></i> Direct Lines</h3>
                            <ul class="gnc-dir-list">
                                <?php foreach ($directDirectory as $i => [$num, $office]): ?>
                                <li class="gnc-dir-item" style="--i:<?= (int)$i ?>">
                                    <span class="gnc-dir-num"><?= e($num) ?></span>
                                    <span class="gnc-dir-name"><?= e($office) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="gnc-dir-card" data-ct-reveal style="--rd:.15s">
                            <h3><i class="bi bi-building"></i> Local Lines</h3>
                            <ul class="gnc-dir-list gnc-dir-list--cols">
                                <?php foreach ($localDirectory as $i => [$num, $office]): ?>
                                <li class="gnc-dir-item" style="--i:<?= (int)$i ?>">
                                    <span class="gnc-dir-num"><?= e($num) ?></span>
                                    <span class="gnc-dir-name"><?= e($office) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

            </section>
        </div>
    </section>
    </main>

    <?php include __DIR__ . '/../components/footer.php'; ?>
    <?php include __DIR__ . '/../chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
    <script>
    // Reveal directory blocks as they scroll into view.
    (function () {
        var els = document.querySelectorAll('[data-ct-reveal]');
        if (!els.length) return;
        if (!('IntersectionObserver' in window)) {
            els.forEach(function (el) { el.classList.add('is-in'); });
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); }
            });
        }, { threshold: 0.15 });
        els.forEach(function (el) { io.observe(el); });
    })();
    </script>
</body>
</html>