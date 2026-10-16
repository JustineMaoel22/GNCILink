<?php
/**
 * college-department.php — THE one dynamic College Department detail page.
 *
 *   /pages/academics/college/college-department.php?slug=cased
 *   /pages/academics/college/college-department.php?slug=cba
 *   /pages/academics/college/college-department.php?slug=any-new-department
 *
 * Every piece of content (logo, overview, highlights, programs, dean, contact,
 * Facebook) is read from the database via components/department-public.php.
 * Nothing department-specific is hardcoded — a department created in the
 * Admin Panel (Content Editor → College Departments) automatically gets this
 * exact page at ?slug=<its-slug>.
 *
 * Markup is written against /assets/css/college-dept-style.css (.dept-* classes).
 */
require_once __DIR__ . '/../../../components/department-public.php';

// Content Editor preview (?ce_preview=1, signed in as someone who may manage page content): the page also prints
// editing markers and the current stored values. Visitors never get either.
$cePreview = cePreviewCanManage();

$slug = trim((string)($_GET['slug'] ?? ''));
$dept = $slug !== '' ? publicDepartment($slug, $cePreview) : null; // null when missing OR archived (editors in the preview may open a hidden one to restore it)

if (!$dept) {
    http_response_code(404);
}

$dean       = $dept ? publicDepartmentDean($dept) : ['photo_url' => null, 'name' => '', 'title' => '', 'bio' => '', 'phone' => '', 'email' => '', 'office' => '', 'hours' => ''];
$programs   = $dept ? publicDepartmentPrograms((int)$dept['dept_id']) : [];
$fbEmbedUrl = $dept ? publicDepartmentFacebookEmbedUrl((string)($dept['facebook_url'] ?? '')) : null;
$logoUrl    = $dept['logo_url'] ?? null;
$pageTitle  = $dept ? (string)$dept['dept_name'] : 'Department Not Found';
$isGraduateSchool = ($dept['slug'] ?? '') === 'graduate-school';
$logoUrl = $logoUrl ?: ($isGraduateSchool ? '/assets/images/logos/gnc-logo-v1.svg' : null);
$dean['photo_url'] = $dean['photo_url'] ?: ($isGraduateSchool && is_file($_SERVER['DOCUMENT_ROOT'] . '/assets/images/maam-nazal.png') ? '/assets/images/maam-nazal.png' : null);
$departmentIndexTitle = $isGraduateSchool ? 'Graduate School' : 'College Departments';
$departmentIndexUrl = $isGraduateSchool ? '/pages/academics/graduate-school/graduate-school.php' : '/pages/academics/college/college-dept.php';

/* ---------- tiny render helpers (output escaping + overview formatting) ---------- */

function cd_h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Render the admin's plain-text overview EXACTLY the way it was typed:
 *   - a blank line starts a new paragraph
 *   - a single line break inside a paragraph is kept (<br>)
 *   - a line ending with ":" becomes a bold heading, and the lines right under
 *     it (until the next blank line) become a bullet list — dashes optional
 *   - lines starting with "- " / "• " / "* " are bullet items anywhere
 * If the stored value already contains HTML tags it is passed through as-is.
 */
function cd_overview_html(?string $text): string {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (preg_match('/<[a-z][\s\S]*>/i', $text)) return $text;

    $text   = str_replace(["\r\n", "\r"], "\n", $text);
    $blocks = preg_split('/\n[ \t]*\n/', $text);
    $html   = '';

    foreach ($blocks as $block) {
        $para = [];
        $items = [];
        $listMode = false;

        $flushPara = function () use (&$para, &$html) {
            if ($para) { $html .= '<p>' . implode('<br>', $para) . '</p>'; $para = []; }
        };
        $flushList = function () use (&$items, &$html) {
            if ($items) {
                $html .= '<ul class="dept-offers-list">';
                foreach ($items as $it) { $html .= '<li>' . $it . '</li>'; }
                $html .= '</ul>';
                $items = [];
            }
        };

        foreach (explode("\n", $block) as $line) {
            $line = trim($line);
            if ($line === '') continue;

            if (preg_match('/^[-•*]\s+(.*)$/u', $line, $m)) {          // explicit bullet
                $flushPara();
                $items[] = cd_h($m[1]);
                $listMode = true;
            } elseif (substr($line, -1) === ':') {                      // heading for a list
                $flushPara();
                $flushList();
                $html .= '<p class="dept-offers-label">' . cd_h($line) . '</p>';
                $listMode = true;
            } elseif ($listMode) {                                      // plain line under a heading
                $items[] = cd_h($line);
            } else {
                $para[] = cd_h($line);
            }
        }
        $flushPara();
        $flushList();
    }
    return $html;
}

/**
 * One-line summary for the page banner when the admin left "Line under the
 * page title" empty: the first paragraph of the short description, trimmed
 * to a sentence so it never repeats the whole About text.
 */
function cd_banner_summary(?string $text, int $max = 220): string {
    $text = trim((string)$text);
    if ($text === '') return '';
    $text  = str_replace(["\r\n", "\r"], "\n", $text);
    $first = preg_split('/\n[ \t]*\n/', $text)[0];
    $first = trim((string)preg_replace('/\s+/u', ' ', $first));
    if (mb_strlen($first) <= $max) return $first;
    $cut = mb_substr($first, 0, $max);
    $dot = mb_strrpos($cut, '. ');
    if ($dot !== false && $dot > 60) return mb_substr($cut, 0, $dot + 1);
    return rtrim(mb_substr($cut, 0, (int)mb_strrpos($cut, ' ')), " ,;:-") . '…';
}

// "About this department" box; if it was left empty, fall back to the short description.
$aboutSource = $dept ? (trim((string)($dept['about_text'] ?? '')) !== '' ? $dept['about_text'] : ($dept['description'] ?? '')) : '';
$aboutHtml   = cd_overview_html($aboutSource);
$deanTitle   = trim((string)$dean['title']);
$meetLabel   = $deanTitle !== '' ? 'Meet the ' . $deanTitle : 'Meet the Head of Office';
$officeLabel = trim((string)($dept['office_label'] ?? ''));
if ($officeLabel === '' && $dept) {
    // Admin-set label (e.g. "CASED Office"); fall back to a neutral one.
    $officeLabel = 'Department Office';
}
// Banner line = "Line under the page title" (hero_text); if empty, a one-sentence summary.
$heroBlurb = $dept
    ? (trim((string)($dept['hero_text'] ?? '')) !== ''
        ? trim((string)$dept['hero_text'])
        : cd_banner_summary($dept['description'] ?? ''))
    : '';
$hasContact = trim((string)$dean['phone']) !== '' || trim((string)$dean['email']) !== ''
    || trim((string)$dean['office']) !== '' || trim((string)$dean['hours']) !== ''
    || $cePreview;   // the editor always shows every contact line, so an empty one can be filled in
/** Text of a stored value: visitors get line breaks as <br>; the editor gets the raw text (it edits exactly what is stored). */
$cdText = function (?string $v) use ($cePreview): string {
    return $cePreview ? cd_h($v) : nl2br(cd_h($v));
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= cd_h($pageTitle) ?></title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/college-dept-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
    <style>
        /* Small additions for states the stylesheet doesn't cover
           (missing logo / photo / programs, 404, Facebook embed). */
        .dept-logo-empty,
        .dept-dean-empty {
            display: flex; align-items: center; justify-content: center;
            background: #f1ede6; color: var(--gnc-green-light, #1F5E2C);
        }
        .dept-logo-empty { border-radius: 8px; font-size: 4rem; }
        .dept-dean-empty { width: 100%; height: 100%; font-size: 5rem; }
        .dept-program-img .dept-program-avatar { width: 100%; height: 100%; background: #f1ede6; }
        .dept-program-empty {
            font-family: 'Inter', sans-serif; font-size: 14px;
            color: var(--gnc-text-gray, #6b6b66);
            background: #fff; border: 1px dashed var(--gnc-border, #e6e1d8);
            border-radius: 10px; padding: 32px 20px; text-align: center;
        }
        .dept-notfound .dept-about-inner { grid-template-columns: 1fr; text-align: center; }
        .dept-notfound i.dept-notfound-icon { font-size: 2.6rem; color: var(--gnc-green-light, #1F5E2C); }
        .dept-fb-lead {
            font-family: 'Inter', sans-serif; font-size: 15px; line-height: 1.7;
            color: var(--gnc-text-gray, #6b6b66); 
            margin: 16px 0 24px; /* Adjusted to reduce the large 56px gap */
            max-width: 100ch;
        }
        /* Compact Facebook Page card (header + Follow Page / Share bar).
           Height matches the &height=150 in the embed URL, so no empty space. */
        .dept-fb-box { 
            max-width: 500px; /* Narrowed slightly to match screenshot proportions */ 
            transition: transform 0.3s cubic-bezier(0.22, 0.61, 0.36, 1), box-shadow 0.3s ease;
            border-radius: 8px;
        }
        .dept-fb-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.12);
        }
        .dept-fb-box iframe {
            display: block; 
            width: 100%; 
            height: 130px; /* Reduced from 165px to fit the compact header perfectly */
            border: none; /* Removed custom border to let native Facebook border show */
            background: transparent;
            border-radius: inherit;
        }
    </style>
    <script>document.documentElement.classList.add('js-anim');</script>
</head>
<body>
    <?php $activeSection = 'academics'; ?>
    <?php include_once __DIR__ . '/../../../components/navbar.php'; ?>

    <main<?= ($dept && !empty($dept['archived_at'])) ? ceManageMark('data-ve-status="draft" style="position:relative"') : '' ?>>

        <!-- ═══ Hero (inside .dept-intro so the --gnc-* variables apply) ═══ -->
        <section class="dept-intro">
            <div class="dept-hero">
                <div class="dept-hero-inner">
                    <nav class="dept-breadcrumb" aria-label="breadcrumb">
                        <span>Academics</span>
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        <a href="<?= cd_h($departmentIndexUrl) ?>"><?= cd_h($departmentIndexTitle) ?></a>
                        <?php if ($dept): ?>
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        <span class="dept-current" aria-current="page"><?= cd_h($pageTitle) ?></span>
                        <?php endif; ?>
                    </nav>
                    <h1 class="dept-hero-title"<?= $dept ? ceField('dept-name', ['max' => 255, 'label' => 'Department name']) : '' ?>><?= cd_h($pageTitle) ?></h1>
                    <?php if ($heroBlurb !== '' || ($cePreview && $dept)): ?>
                    <p class="dept-hero-blurb"<?= $dept ? ceField('dept-hero', ['max' => 255, 'label' => 'Line under the title', 'ph' => 'Add a line under the title']) : '' ?>><?= cd_h($heroBlurb) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if (!$dept): ?>

        <section class="dept-about dept-notfound">
            <div class="dept-about-inner">
                <div class="dept-about-content" data-reveal style="--d: 120ms">
                    <i class="bi bi-diagram-3 dept-notfound-icon" aria-hidden="true"></i>
                    <h2>Department not found</h2>
                    <p>The department you're looking for doesn't exist or is no longer published.</p>
                    <a class="dept-program-link" href="<?= cd_h($departmentIndexUrl) ?>">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i> Back to College Departments
                    </a>
                </div>
            </div>
        </section>

        <?php else: ?>

        <!-- ═══ 1 · About / Overview ═══ -->
        <section class="dept-about">
            <div class="dept-about-inner">
                <div class="dept-logo-wrap" data-reveal="scale">
                    <span class="dept-logo-square" aria-hidden="true"></span>
                    <?php if ($logoUrl): ?>
                        <img class="dept-logo" src="<?= cd_h($logoUrl) ?>" alt="<?= cd_h($pageTitle) ?> logo"<?= ceManageMark('data-ve-img="dept-logo"') ?>>
                    <?php else: ?>
                        <div class="dept-logo dept-logo-empty" aria-hidden="true"<?= ceManageMark('data-ve-img="dept-logo"') ?>><i class="bi bi-building"></i></div>
                    <?php endif; ?>
                    <span class="dept-dots" aria-hidden="true"></span>
                </div>
                <div class="dept-about-content" data-reveal="right" style="--d: 150ms">
                    <span class="dept-eyebrow">Overview</span>
                    <h2>About the <?= cd_h($pageTitle) ?></h2>
                    <?php if ($cePreview): ?>
                        <?php $aboutPh = (trim((string)($dept['about_text'] ?? '')) === '' && trim((string)($dept['description'] ?? '')) !== '')
                            ? 'Empty: visitors currently see the short description here. Type to write the full text.' : 'Write about this department…'; ?>
                        <div class="dept-about-raw"<?= ceField('dept-about', ['max' => 10000, 'multiline' => true, 'label' => 'About this department', 'ph' => $aboutPh]) ?>><?= cd_h($dept['about_text'] ?? '') ?></div>
                    <?php elseif ($aboutHtml !== ''): ?>
                        <?= $aboutHtml ?>
                    <?php else: ?>
                        <p>Information about this department will be published soon. Please check back later.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- ═══ 2 · Programs Offered (from `programs` where dept_id = this dept) ═══ -->
        <section class="dept-programs">
            <div class="dept-programs-inner">
                <span class="dept-eyebrow" data-reveal="right">Our Programs</span>
                <h2 data-reveal="right" style="--d: 80ms">Programs Offered</h2>
                <?php if (empty($programs)): ?>
                    <div class="dept-program-empty">Programs for this department will be listed here soon.</div>
                <?php else: ?>
                <div class="dept-programs-grid">
                    <?php foreach ($programs as $i =>$p): ?>
                    <article class="dept-program-card" data-reveal="scale" style="--d: <?= ($i % 3) * 100 ?>ms"<?= !empty($p['slug']) ? ceManageMark('data-ve-goto="' . cd_h(publicProgramPageUrl($p)) . '" data-ve-label="' . cd_h($p['program_name']) . '"') : '' ?>>
                        <div class="dept-program-img">
                            <?php if (!empty($p['image_url'])): ?>
                                <img src="<?= cd_h($p['image_url']) ?>" alt="<?= cd_h($p['program_name']) ?>" loading="lazy" decoding="async">
                            <?php else: ?>
                                <div class="dept-program-avatar" aria-hidden="true"></div>
                            <?php endif; ?>
                        </div>
                        <div class="dept-program-body">
                            <h3><?= cd_h($p['program_name']) ?></h3>
                            <?php if (trim((string)($p['description'] ?? '')) !== ''): ?>
                                <p><?= cd_h($p['description']) ?></p>
                            <?php endif; ?>
                            <a class="dept-program-link"
                               href="<?= cd_h(publicProgramPageUrl($p)) ?>"
                               aria-label="View details for <?= cd_h($p['program_name']) ?>">
                                View Details <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </a>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($cePreview): ?>
                <div class="ve-add-tile" data-ve-add="program" data-ve-dept-id="<?= (int)$dept['dept_id'] ?>" style="margin-top:1.25rem"><span style="font-size:28px;line-height:1">+</span><span>Add a program to this department</span></div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ═══ 3 · Head of Office + Get Connected (from departments.dean_*) ═══ -->
        <section class="dept-dean">
            <div class="dept-dean-inner">

                <div class="dept-dean-intro" data-reveal="right">
                    <span class="dept-eyebrow">Leadership &amp; Organization</span>
                    <h2>Head of Office</h2>
                    <p>Meet the visionary leader guiding the <?= cd_h($pageTitle) ?> in advancing academic excellence, innovation, and service.</p>
                </div>

                <div class="dept-dean-grid">
                    <div class="dept-dean-photo-wrap" data-reveal="scale">
                        <span class="dept-dean-square" aria-hidden="true"></span>
                        <span class="dept-dean-accent" aria-hidden="true"></span>
                        <div class="dept-dean-photo"<?= ceManageMark('data-ve-img="dean-photo"') ?>>
                            <?php if (!empty($dean['photo_url'])): ?>
                                <img src="<?= cd_h($dean['photo_url']) ?>" alt="<?= cd_h($dean['name']) ?>" loading="lazy" decoding="async">
                            <?php else: ?>
                                <div class="dept-dean-empty" aria-hidden="true"><i class="bi bi-person-fill"></i></div>
                            <?php endif; ?>

                            <div class="dept-dean-wave" aria-hidden="true">
                                <svg viewBox="0 0 260 90" preserveAspectRatio="none" focusable="false">
                                    <path class="wave-gold"   d="M0 38 C50 8 100 62 160 34 S235 18 260 30 V90 H0 Z"/>
                                    <path class="wave-maroon" d="M0 54 C55 28 110 76 170 50 S238 38 260 48 V90 H0 Z"/>
                                    <path class="wave-green"  d="M0 70 C60 50 120 88 180 66 S240 60 260 66 V90 H0 Z"/>
                                </svg>
                            </div>

                            <div class="dept-dean-badge">
                                <span class="dept-dean-badge-title"<?= ceField('dean-title', ['max' => 255, 'label' => 'Position', 'ph' => 'Position']) ?>><?= cd_h($cePreview ? $deanTitle : ($deanTitle !== '' ? $deanTitle : 'Head of Office')) ?></span>
                                <span class="dept-dean-badge-sub"><?= cd_h($pageTitle) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="dept-dean-content" data-reveal="left" style="--d: 150ms">
                        <span class="dept-eyebrow"><?= cd_h($meetLabel) ?></span>
                        <h3<?= ceField('dean-name', ['max' => 255, 'label' => 'Name', 'ph' => 'Name of the head of office']) ?>><?= cd_h($cePreview ? $dean['name'] : ($dean['name'] !== '' ? $dean['name'] : 'To be announced')) ?></h3>
                        <div class="dept-dean-rule" aria-hidden="true"></div>
                        <?php if (trim((string)$dean['bio']) !== '' || $cePreview): ?>
                            <p<?= ceField('dean-bio', ['max' => 5000, 'multiline' => true, 'label' => 'Short biography', 'ph' => 'Write a short introduction']) ?>><?= $cdText($dean['bio']) ?></p>
                        <?php endif; ?>

                        <?php if ($hasContact): ?>
                        <hr class="dept-dean-divider">
                        <span class="dept-eyebrow">Get Connected</span>
                        <div class="dept-dean-contacts-grid">
                            <?php if (trim((string)$dean['phone']) !== '' || $cePreview): ?>
                            <div class="dept-dean-contact" data-reveal="scale">
                                <i class="bi bi-telephone" aria-hidden="true"></i>
                                <div>
                                    <span class="dept-contact-label">Contact Information</span>
                                    <span class="dept-contact-value"<?= ceField('dean-phone', ['max' => 255, 'label' => 'Phone', 'ph' => 'Add a phone number']) ?>><?= $cdText($dean['phone']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (trim((string)$dean['email']) !== '' || $cePreview): ?>
                            <div class="dept-dean-contact" data-reveal="scale">
                                <i class="bi bi-envelope" aria-hidden="true"></i>
                                <div>
                                    <span class="dept-contact-label">Email Address</span>
                                    <span class="dept-contact-value"<?= ceField('dean-email', ['max' => 255, 'label' => 'Email', 'ph' => 'Add an email address']) ?>><?= $cdText($dean['email']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (trim((string)$dean['office']) !== '' || $cePreview): ?>
                            <div class="dept-dean-contact" data-reveal="scale">
                                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                <div>
                                    <span class="dept-contact-label"<?= ceField('dept-office-label', ['max' => 100, 'label' => 'Office label', 'ph' => 'Office label']) ?>><?= cd_h($officeLabel) ?></span>
                                    <span class="dept-contact-value"<?= ceField('dean-office', ['max' => 255, 'label' => 'Office location', 'ph' => 'Add the office location']) ?>><?= $cdText($dean['office']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (trim((string)$dean['hours']) !== '' || $cePreview): ?>
                            <div class="dept-dean-contact" data-reveal="scale">
                                <i class="bi bi-clock" aria-hidden="true"></i>
                                <div>
                                    <span class="dept-contact-label">Operating Hours</span>
                                    <span class="dept-contact-value"<?= ceField('dean-hours', ['max' => 500, 'multiline' => true, 'label' => 'Operating hours', 'ph' => 'Add the operating hours']) ?>><?= $cdText($dean['hours']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </section>

        <!-- ═══ 4 · Follow Us on Facebook (from departments.facebook_url) ═══ -->
        <?php if ($fbEmbedUrl !== null || $cePreview): ?>
        <section class="dept-programs">
            <div class="dept-programs-inner">
                <span class="dept-eyebrow" data-reveal="right">Stay Updated</span>
                <h2 data-reveal="right" style="--d: 80ms">Follow Us on Facebook</h2>
                <p class="dept-fb-lead" data-reveal="right" style="--d: 120ms">Get the latest announcements, events, and news from the <?= cd_h($pageTitle) ?>.</p>
                <?php if ($fbEmbedUrl !== null): ?>
                <div class="dept-fb-box" data-reveal="scale" style="--d: 200ms<?= $cePreview ? '; position: relative' : '' ?>">
                    <iframe src="<?= cd_h($fbEmbedUrl) ?>" scrolling="no" frameborder="0"
                            allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"
                            allowfullscreen loading="lazy"
                            title="<?= cd_h($pageTitle) ?> on Facebook"></iframe>
                    <?php if ($cePreview): ?>
                    <?php /* The Facebook plugin is another website's frame: clicks inside it never reach the editor, so a see-through cover takes the click. */ ?>
                    <div data-ve-pick="dept-facebook" data-ve-label="Facebook page" title="Click to change the Facebook page" style="position:absolute;inset:0;z-index:2;cursor:pointer"></div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="ve-add-tile" data-ve-pick="dept-facebook" data-ve-label="Facebook page"><span style="font-size:28px;line-height:1">+</span><span>Add the Facebook page link</span></div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php endif; ?>

    </main>

    <?= ceDeptEditorBlock($dept, $isGraduateSchool ? 'graduate' : 'college') ?>
    <?php include __DIR__ . '/../../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
    <script src="/assets/js/college-department.js"></script>
</body>
</html>
