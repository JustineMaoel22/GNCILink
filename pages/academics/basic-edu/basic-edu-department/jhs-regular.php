<?php
require_once __DIR__ . '/../../../../components/department-public.php';
// Content Editor preview (?ce_preview=1, a signed-in manager): editing markers + current values. Visitors never get either.
$cePreview = cePreviewCanManage();
$beText = function ($v) use ($cePreview) { $v = htmlspecialchars((string)$v); return $cePreview ? $v : nl2br($v); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Junior High School Regular</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/basic-edu-style.css" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css?v=2" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php $activeSection = 'academics'; ?>
    <?php include_once __DIR__ . '/../../../../components/navbar.php'; ?>
    <?php require_once __DIR__ . '/../../../../components/department-public.php'; ?>
    <?php
        // Head of Office (Principal/OIC) comes from this department's record,
        // so editing it in Content Editor → Basic Education Departments
        // updates this page automatically.
        $dept = publicDepartment('jhs-regular', $cePreview);
        $dean = $dept ?? [];
    ?>

    <section class="dept-intro">
        <div class="dept-hero">
            <div class="dept-hero-inner">
                <nav class="dept-breadcrumb reveal-up" aria-label="breadcrumb">
                    <span>Academics</span>
                    <i class="bi bi-chevron-right"></i>
                    <a href="/pages/academics/basic-edu/basic-edu-dept.php">Basic Education Departments</a>
                    <i class="bi bi-chevron-right"></i>
                    <span class="dept-current">Junior High School Regular</span>
                </nav>
                <h1 class="reveal-up" style="transition-delay: 0.1s;"<?= basicEducationMark('jhs-regular', 'text_01') ?>><?= basicEducationText('jhs-regular', 'text_01') ?></h1>
                <p class="reveal-up" style="transition-delay: 0.2s;"<?= ceField('dept-hero', ['max' => 255, 'label' => 'Line under the title']) ?>><?= htmlspecialchars(trim((string)($dept['hero_text'] ?? '')) ?: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.') ?></p>
            </div>
        </div>
    </section>

    <section class="dept-about">
        <div class="dept-about-inner skeleton-group">

            <div class="dept-logo-wrap reveal-scale" style="transition-delay: 0.1s;">
                <div class="dept-logo-square"></div>
                <div class="skeleton-wrap" style="display:inline-block;">
                    <img src="<?= htmlspecialchars(($dean['logo_url'] ?? '') ?: '/assets/images/logos/jhs-logo.svg') ?>" alt="Junior High School Regular logo" class="dept-logo"<?= ceManageMark('data-ve-img="dept-logo"') ?>>
                </div>
                <div class="dept-dots" aria-hidden="true"></div>
            </div>

            <div class="dept-about-content">
                <div class="skeleton-text-lines">
                    <span class="skeleton-line skeleton-line--60"></span>
                    <span class="skeleton-line skeleton-line--title skeleton-line--80"></span>
                    <span class="skeleton-line skeleton-line--100"></span>
                    <span class="skeleton-line skeleton-line--100"></span>
                    <span class="skeleton-line skeleton-line--80"></span>
                </div>
                <div class="skeleton-real-content reveal-up" style="transition-delay: 0.2s;">
                    <span class="dept-eyebrow"<?= basicEducationMark('jhs-regular', 'text_02') ?>><?= basicEducationText('jhs-regular', 'text_02') ?></span>
                    <h2<?= basicEducationMark('jhs-regular', 'text_03') ?>><?= basicEducationText('jhs-regular', 'text_03') ?></h2>
                    <p<?= ceField('dept-about', ['max' => 10000, 'multiline' => true, 'label' => 'About this department']) ?>><?= $beText(trim((string)($dept['about_text'] ?? '')) ?: 'The Junior High School Regular offers a well-rounded education designed to foster critical thinking, creativity, and social responsibility in our students.') ?></p>
                </div>
            </div>

        </div>
    </section>

    <section class="dept-principal">
        <div class="dept-principal-inner">

            <div class="dept-principal-intro reveal-up">
                <span class="dept-eyebrow"<?= basicEducationMark('jhs-regular', 'text_04') ?>><?= basicEducationText('jhs-regular', 'text_04') ?></span>
                <h2<?= basicEducationMark('jhs-regular', 'text_05') ?>><?= basicEducationText('jhs-regular', 'text_05') ?></h2>
                <p<?= basicEducationMark('jhs-regular', 'text_06') ?>><?= basicEducationText('jhs-regular', 'text_06') ?></p>
            </div>

            <div class="dept-principal-grid skeleton-group">

                <div class="dept-principal-photo-wrap reveal-scale" style="transition-delay: 0.1s;">
                    <div class="dept-principal-square"></div>

                    <div class="dept-principal-photo"<?= ceManageMark('data-ve-img="dean-photo"') ?>>
                        <div class="skeleton-wrap">
                            <img src="<?= htmlspecialchars(($dean['dean_photo_url'] ?? '') ?: '/assets/images/hic-nulud.png') ?>" alt="Principal Photo" class="dept-principal-img">
                        </div>

                        <!-- Layered Wave Pattern: Gold -> Maroon -> Light Green -->
                        <div class="dept-principal-wave" aria-hidden="true">
                            <svg viewBox="0 0 500 150" preserveAspectRatio="none">
                                <path class="wave-gold" d="M0.00,30.98 C150.00,120.00 350.00,-20.00 500.00,30.98 L500.00,150.00 L0.00,150.00 Z"></path>
                                <path class="wave-maroon" d="M0.00,49.98 C149.99,150.00 349.20,-49.98 500.00,49.98 L500.00,150.00 L0.00,150.00 Z"></path>
                                <path class="wave-green" d="M0.00,79.98 C149.99,170.00 349.20,-20.98 500.00,79.98 L500.00,150.00 L0.00,150.00 Z"></path>
                            </svg>
                        </div>

                        <!-- Glassmorphism Nameplate Badge -->
                        <div class="dept-principal-badge">
                            <span class="dept-principal-badge-title"<?= ceField('dean-title', ['max' => 255, 'label' => 'Position']) ?>><?= htmlspecialchars(($dean['dean_title'] ?? '') ?: 'Principal') ?></span>
                            <span class="dept-principal-badge-sub">Junior High School Regular</span>
                        </div>
                    </div>

                    <div class="dept-principal-accent" aria-hidden="true"></div>
                </div>

                <div class="dept-principal-content">
                    <div class="skeleton-text-lines">
                        <span class="skeleton-line skeleton-line--60"></span>
                        <span class="skeleton-line skeleton-line--title skeleton-line--80"></span>
                        <span class="skeleton-line skeleton-line--100"></span>
                        <span class="skeleton-line skeleton-line--100"></span>
                        <span class="skeleton-line skeleton-line--100"></span>
                        <span class="skeleton-line skeleton-line--80" style="margin-bottom:1em;"></span>
                        <span class="skeleton-line skeleton-line--60"></span>
                        <span class="skeleton-line skeleton-line--60"></span>
                    </div>
                    <div class="skeleton-real-content reveal-up" style="transition-delay: 0.2s;">
                        <span class="dept-eyebrow"<?= basicEducationMark('jhs-regular', 'text_07') ?>><?= basicEducationText('jhs-regular', 'text_07') ?></span>
                        <h3<?= ceField('dean-name', ['max' => 255, 'label' => 'Name']) ?>><?= htmlspecialchars(($dean['dean_name'] ?? '') ?: 'Allen Jan S. Nulud, Ph.D.') ?></h3>
                        <div class="dept-principal-rule"></div>
                        <p<?= ceField('dean-bio', ['max' => 5000, 'label' => 'Short biography', 'multiline' => true]) ?>><?= $beText(($dean['dean_bio'] ?? '') ?: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.') ?></p>

                        <hr class="dept-principal-divider">

                        <span class="dept-eyebrow"<?= basicEducationMark('jhs-regular', 'text_08') ?>><?= basicEducationText('jhs-regular', 'text_08') ?></span>

                        <div class="dept-principal-contacts-grid">
                            <div class="dept-principal-contact">
                                <i class="bi bi-telephone"></i>
                                <div>
                                    <span class="dept-contact-label">Contact Information</span>
                                    <span class="dept-contact-value"<?= ceField('dean-phone', ['max' => 255, 'label' => 'Phone']) ?>><?= htmlspecialchars(($dean['dean_phone'] ?? '') ?: '(045) 900-4473 Loc. 117') ?></span>
                                </div>
                            </div>

                            <div class="dept-principal-contact">
                                <i class="bi bi-geo-alt"></i>
                                <div>
                                    <span class="dept-contact-label">JHS Regular Office</span>
                                    <span class="dept-contact-value"<?= ceField('dean-office', ['max' => 255, 'label' => 'Office location']) ?>><?= htmlspecialchars(($dean['dean_office'] ?? '') ?: 'Goseco Hall, Floor 2, Room 200') ?></span>
                                </div>
                            </div>

                            <div class="dept-principal-contact">
                                <i class="bi bi-envelope"></i>
                                <div>
                                    <span class="dept-contact-label">Email Address</span>
                                    <span class="dept-contact-value"<?= ceField('dean-email', ['max' => 255, 'label' => 'Email']) ?>><?= htmlspecialchars(($dean['dean_email'] ?? '') ?: 'info@gnc.edu.ph') ?></span>
                                </div>
                            </div>

                            <div class="dept-principal-contact">
                                <i class="bi bi-clock"></i>
                                <div>
                                    <span class="dept-contact-label">Operating Hours</span>
                                    <span class="dept-contact-value"<?= ceField('dean-hours', ['max' => 500, 'label' => 'Operating hours', 'multiline' => true]) ?>><?= $beText(($dean['dean_hours'] ?? '') ?: "Monday - Friday\n8:00AM - 4:00PM") ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <?= ceDeptEditorBlock($dept, 'basic') ?>
    <?= basicEducationEditorBlock('jhs-regular') ?>
    <?php include __DIR__ . '/../../../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
    <script src="/assets/js/skeleton-loader.js?v=2"></script>
    <script src="/assets/js/basic-edu.js"></script>
</body>
</html>