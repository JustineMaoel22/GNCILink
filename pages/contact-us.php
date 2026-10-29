<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
require_once __DIR__ . '/../components/contact-page-content.php';

if (session_status() === PHP_SESSION_NONE) session_start(['use_strict_mode'=>1,'cookie_httponly'=>true,'cookie_secure'=>authIsHttps()||!authLocalDevelopment(),'cookie_samesite'=>'Lax']);
$contactPreview = cePreviewActive();
$contactFields = contactPageContent($contactPreview);
$contactText = static fn(string $key): string => $contactFields[$key]['content'];
$contactMark = static function (string $key) use ($contactFields): string {
    return ceMark('data-ve-section="' . ceAttr($key) . '" data-ve-field="contact-text" data-ve-plain-section data-ve-max="' . $contactFields[$key]['max'] . '" data-ve-label="' . ceAttr($contactFields[$key]['title']) . '"');
};

// ---------------------------------------------------------------
// Contact details are managed in the Contact Us Content Editor.
// The contact form always emails the published contact address.
// ---------------------------------------------------------------
$contact = [];
foreach (['address', 'phone', 'email', 'hours', 'facebook'] as $key) $contact[$key] = $contactText('contact_' . $key);
// Deliver messages only to the published address, including while previewing a draft.
$contactLiveEmail = getSectionContent('contact-us', 'contact_email') ?? contactPageDefaults()['contact_email']['content'];
$contactLiveEmail = contactPageValidate('contact_email', (string)$contactLiveEmail);

// ---------------------------------------------------------------
// School directory, with numbers and office names managed in the editor.
// ---------------------------------------------------------------
$directDirectory = [];
for ($i = 0; $i < 3; $i++) $directDirectory[] = [$contactText('direct_' . $i . '_number'), $contactText('direct_' . $i . '_office')];

$localDirectory = [];
for ($i = 0; $i < 20; $i++) $localDirectory[] = [$contactText('local_' . $i . '_number'), $contactText('local_' . $i . '_office')];

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

            if (gncSendContactMail($contactLiveEmail, $old['name'], $old['email'], '[GNC Website] ' . $subject, $body, $headers)) {
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
    <link href="../assets/css/contact-us-style.css?v=<?= filemtime(__DIR__ . '/../assets/css/contact-us-style.css') ?>" rel="stylesheet">
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
<body<?= $contactPreview ? ' class="gnc-contact-editing"' : '' ?>>
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
        <span class="gnc-contact-kicker"><i class="bi bi-chat-dots-fill"></i> <span<?= $contactMark('hero_kicker') ?>><?= e($contactText('hero_kicker')) ?></span></span>
        <h1 class="gnc-contact-title"><span class="gnc-contact-title-main"<?= $contactMark('hero_title') ?>><?= e($contactText('hero_title')) ?></span> <span<?= $contactMark('hero_accent') ?>><?= e($contactText('hero_accent')) ?></span></h1>
        <p class="gnc-contact-lead"<?= $contactMark('hero_description') ?>><?= e($contactText('hero_description')) ?></p>
    </header>

    <section class="gnc-contact-sec">
        <div class="container">
            <div class="row g-4 gnc-contact-grid">

                <!-- Contact details -->
                <div class="col-lg-5">
                    <div class="gnc-contact-info">
                        <h2<?= $contactMark('info_title') ?>><?= e($contactText('info_title')) ?></h2>
                        <p<?= $contactMark('info_description') ?>><?= e($contactText('info_description')) ?></p>

                        <div class="gnc-contact-item">
                            <span class="gnc-contact-icon"><i class="bi bi-geo-alt-fill"></i></span>
                            <div>
                                <h3<?= $contactMark('address_label') ?>><?= e($contactText('address_label')) ?></h3>
                                <p><span<?= $contactMark('contact_address') ?>><?= e($contactText('contact_address')) ?></span></p>
                            </div>
                        </div>

                        <div class="gnc-contact-item">
                            <span class="gnc-contact-icon"><i class="bi bi-telephone-fill"></i></span>
                            <div>
                                <h3<?= $contactMark('phone_label') ?>><?= e($contactText('phone_label')) ?></h3>
                                <a data-contact-channel="phone" href="tel:<?= e(preg_replace('/[^+\d]/', '', $contact['phone'])) ?>"><span<?= $contactMark('contact_phone') ?>><?= e($contactText('contact_phone')) ?></span></a>
                            </div>
                        </div>

                        <div class="gnc-contact-item">
                            <span class="gnc-contact-icon"><i class="bi bi-envelope-fill"></i></span>
                            <div>
                                <h3<?= $contactMark('email_label') ?>><?= e($contactText('email_label')) ?></h3>
                                <a data-contact-channel="email" href="mailto:<?= e($contact['email']) ?>"><span<?= $contactMark('contact_email') ?>><?= e($contactText('contact_email')) ?></span></a>
                            </div>
                        </div>

                        <div class="gnc-contact-item">
                            <span class="gnc-contact-icon"><i class="bi bi-clock-fill"></i></span>
                            <div>
                                <h3<?= $contactMark('hours_label') ?>><?= e($contactText('hours_label')) ?></h3>
                                <p><span<?= $contactMark('contact_hours') ?>><?= e($contactText('contact_hours')) ?></span></p>
                            </div>
                        </div>

                        <div class="gnc-contact-links">
                            <a class="gnc-contact-pill gnc-contact-pill--gold" href="../index.php#campus-map">
                                <i class="bi bi-map-fill"></i> <span<?= $contactMark('map_label') ?>><?= e($contactText('map_label')) ?></span>
                            </a>
                            <a class="gnc-contact-pill gnc-contact-pill--line" data-contact-channel="facebook" href="<?= e($contact['facebook']) ?>" target="_blank" rel="noopener">
                                <i class="bi bi-facebook"></i> <span<?= $contactMark('facebook_label') ?>><?= e($contactText('facebook_label')) ?></span>
                            </a>
                        </div>
                        <?php if ($contactPreview): ?><p class="small mt-3 mb-0">Facebook page URL: <span<?= $contactMark('contact_facebook') ?>><?= e($contactText('contact_facebook')) ?></span></p><?php endif; ?>
                    </div>
                </div>

                <!-- Form -->
                <div class="col-lg-7">
                    <div class="gnc-contact-card">
                        <h2<?= $contactMark('form_title') ?>><?= e($contactText('form_title')) ?></h2>
                        <p><span<?= $contactMark('form_description') ?>><?= str_replace('*', '<span style="color:#b3261e;">*</span>', e($contactText('form_description'))) ?></span></p>

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
                                    <label for="name"><span<?= $contactMark('name_label') ?>><?= e($contactText('name_label')) ?></span> <span class="req">*</span></label>
                                    <input type="text" id="name" name="name" placeholder="Juan Dela Cruz" value="<?= e($old['name']) ?>" maxlength="100" required>
                                </div>
                                <div class="col-md-6 gnc-field">
                                    <label for="email"><span<?= $contactMark('form_email_label') ?>><?= e($contactText('form_email_label')) ?></span> <span class="req">*</span></label>
                                    <input type="email" id="email" name="email" placeholder="you@example.com" value="<?= e($old['email']) ?>" required>
                                </div>
                            </div>

                            <div class="gnc-field">
                                <label for="subject"><span<?= $contactMark('subject_label') ?>><?= e($contactText('subject_label')) ?></span></label>
                                <input type="text" id="subject" name="subject" placeholder="How can we help?" value="<?= e($old['subject']) ?>" maxlength="150">
                            </div>

                            <div class="gnc-field">
                                <label for="message"><span<?= $contactMark('message_label') ?>><?= e($contactText('message_label')) ?></span> <span class="req">*</span></label>
                                <textarea id="message" name="message" placeholder="Tell us what's on your mind…" maxlength="5000" required><?= e($old['message']) ?></textarea>
                            </div>

                            <button type="submit" class="gnc-contact-submit">
                                <span<?= $contactMark('submit_label') ?>><?= e($contactText('submit_label')) ?></span> <i class="bi bi-send-fill"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- School directory -->
            <section class="gnc-dir" id="school-directory" aria-labelledby="gncDirTitle">
                <div class="gnc-dir-head" data-ct-reveal>
                    <h2 id="gncDirTitle"<?= $contactMark('directory_title') ?>><?= e($contactText('directory_title')) ?></h2>
                    <p<?= $contactMark('directory_description') ?>><?= e($contactText('directory_description')) ?></p>
                </div>

                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="gnc-dir-card gnc-dir-card--direct" data-ct-reveal>
                            <h3><i class="bi bi-telephone-fill"></i> <span<?= $contactMark('direct_title') ?>><?= e($contactText('direct_title')) ?></span></h3>
                            <ul class="gnc-dir-list">
                                <?php foreach ($directDirectory as $i => [$num, $office]): ?>
                                <li class="gnc-dir-item" style="--i:<?= (int)$i ?>">
                                    <span class="gnc-dir-num"<?= $contactMark('direct_' . $i . '_number') ?>><?= e($num) ?></span>
                                    <span class="gnc-dir-name"<?= $contactMark('direct_' . $i . '_office') ?>><?= e($office) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="gnc-dir-card" data-ct-reveal style="--rd:.15s">
                            <h3><i class="bi bi-building"></i> <span<?= $contactMark('local_title') ?>><?= e($contactText('local_title')) ?></span></h3>
                            <ul class="gnc-dir-list gnc-dir-list--cols">
                                <?php foreach ($localDirectory as $i => [$num, $office]): ?>
                                <li class="gnc-dir-item" style="--i:<?= (int)$i ?>">
                                    <span class="gnc-dir-num"<?= $contactMark('local_' . $i . '_number') ?>><?= e($num) ?></span>
                                    <span class="gnc-dir-name"<?= $contactMark('local_' . $i . '_office') ?>><?= e($office) ?></span>
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
    <script>
    <?php if ($contactPreview): ?>
    // Keep the link destinations in step with inline preview text.
    document.addEventListener('input', function (event) {
        var field = event.target.closest('[data-ve-section]');
        if (!field) return;
        var key = field.getAttribute('data-ve-section');
        var channel = { contact_phone: 'phone', contact_email: 'email', contact_facebook: 'facebook' }[key];
        if (!channel) return;
        var link = document.querySelector('[data-contact-channel="' + channel + '"]');
        var value = field.textContent.trim();
        if (link) link.setAttribute('href', channel === 'phone' ? 'tel:' + value.replace(/[^+\d]/g, '') : (channel === 'email' ? 'mailto:' + value : value));
    });
    <?php endif; ?>
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
