<?php
require_once __DIR__ . '/../../components/payment-guide-content.php';
$paymentPreview = cePreviewCanManage();
$paymentImages = paymentGuideImages();
$guideFields = paymentGuideFields();
$guideMark = fn($key) => ceField('payment-guide-text', ['key' => $key, 'label' => $guideFields[$key]['label'], 'max' => $guideFields[$key]['max'], 'rich' => $guideFields[$key]['type'] === 'rich', 'multiline' => $guideFields[$key]['type'] === 'rich']);
$guideText = fn($key) => $guideFields[$key]['type'] === 'rich' ? renderSectionContent($guideFields[$key]['value']) : ceAttr($guideFields[$key]['value']);
$guideLink = fn($key) => ceManageMark('data-ve-payment-link="' . ceAttr($key) . '"');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $guideText('hero_title') ?> | Guagua National Colleges</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/tuition-fees-style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/tuition-fees-style.css') ?>" rel="stylesheet">
    <link href="/assets/css/online-payment-guide.css?v=<?= filemtime(__DIR__ . '/../../assets/css/online-payment-guide.css') ?>" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php $activeSection = 'admissions'; include_once __DIR__ . '/../../components/navbar.php'; ?>
    <section class="tuition-intro">
        <div class="tuition-hero">
            <div class="tuition-hero-inner">
                <nav class="tuition-breadcrumb" aria-label="breadcrumb">
                    <span>Admissions</span><i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span class="tuition-current" aria-current="page"><?= $guideText('hero_title') ?></span>
                </nav>
                <h1<?= $guideMark('hero_title') ?>><?= $guideText('hero_title') ?></h1>
                <div class="tuition-hero-description cms-rich"<?= $guideMark('hero_intro') ?>><?= $guideText('hero_intro') ?></div>
            </div>
        </div>
    </section>
    <main class="payment-guide" aria-labelledby="paymentStepsTitle">
        <?php if ($paymentPreview): ?>
        <aside class="payment-guide-admin-note" role="note">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <p><strong>Downloading from Storyset?</strong> Choose <strong>Download SVG</strong>. Illustrations downloaded from Storyset must be in <strong>.svg</strong> format. Then click an illustration here, choose <strong>Change illustration</strong>, upload the SVG, and publish.</p>
        </aside>
        <?php endif; ?>
        <div class="payment-guide-heading">
            <h2 id="paymentStepsTitle"<?= $guideMark('guide_title') ?>><?= $guideText('guide_title') ?></h2>
            <div class="cms-rich"<?= $guideMark('guide_intro') ?>><?= $guideText('guide_intro') ?></div>
        </div>
        <section aria-labelledby="completePaymentTitle">
            <header class="payment-method-heading">
                <h3 id="completePaymentTitle"<?= $guideMark('payment_title') ?>><?= $guideText('payment_title') ?></h3>
                <div class="cms-rich"<?= $guideMark('payment_intro') ?>><?= $guideText('payment_intro') ?></div>
            </header>
            <div class="payment-options">
                <?php foreach ([
                    ['id' => 'gcash'], ['id' => 'banking'],
                ] as $option): ?>
                <article class="payment-option" aria-labelledby="payment-<?= $option['id'] ?>">
                    <h4 id="payment-<?= $option['id'] ?>"<?= $guideMark($option['id'] . '_title') ?>><?= $guideText($option['id'] . '_title') ?></h4>
                    <div class="payment-option-body">
                        <div class="payment-illustration">
                            <?= paymentGuideIllustration($paymentImages, $option['id'], 'payment-option-art') ?>
                            <span<?= $guideMark($option['id'] . '_label') ?>><?= $guideText($option['id'] . '_label') ?></span>
                        </div>
                        <div class="payment-instructions">
                            <ol class="payment-steps">
                                <li><div class="cms-rich"<?= $guideMark($option['id'] . '_step_1') ?>><?= $guideText($option['id'] . '_step_1') ?></div></li>
                                <li><div class="cms-rich"<?= $guideMark($option['id'] . '_step_2') ?>><?= $guideText($option['id'] . '_step_2') ?></div></li>
                                <li><div><div class="cms-rich"<?= $guideMark($option['id'] . '_step_3') ?>><?= $guideText($option['id'] . '_step_3') ?></div>
                                    <dl class="payment-account">
                                        <dt<?= $guideMark('account_name_label') ?>><?= $guideText('account_name_label') ?></dt><dd<?= $guideMark('account_name') ?>><?= $guideText('account_name') ?></dd>
                                        <dt<?= $guideMark('account_number_label') ?>><?= $guideText('account_number_label') ?></dt><dd<?= $guideMark('account_number') ?>><?= $guideText('account_number') ?></dd>
                                    </dl>
                                </div></li>
                                <li><div class="cms-rich"<?= $guideMark($option['id'] . '_step_4') ?>><?= $guideText($option['id'] . '_step_4') ?></div></li>
                                <li><div class="cms-rich"<?= $guideMark($option['id'] . '_step_5') ?>><?= $guideText($option['id'] . '_step_5') ?></div></li>
                            </ol>
                            <div class="payment-receipt-note cms-rich"<?= $guideMark($option['id'] . '_receipt_note') ?>><?= $guideText($option['id'] . '_receipt_note') ?></div>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="payment-verification" aria-labelledby="paymentVerificationTitle">
            <header class="payment-method-heading">
                <h3 id="paymentVerificationTitle"<?= $guideMark('verification_title') ?>><?= $guideText('verification_title') ?></h3>
                <div class="cms-rich"<?= $guideMark('verification_intro') ?>><?= $guideText('verification_intro') ?></div>
            </header>
            <ol class="verification-cards">
                <li class="verification-card">
                    <div class="verification-instruction"><div class="cms-rich"<?= $guideMark('send_receipt') ?>><?= $guideText('send_receipt') ?></div></div>
                    <?= paymentGuideIllustration($paymentImages, 'send-receipt', 'verification-art') ?>
                    <a class="verification-office-link" href="<?= $guideText('accounting_link_url') ?>"<?= $guideLink('accounting_link_url') ?> target="_blank" rel="noopener noreferrer"><span<?= $guideMark('accounting_link_label') ?>><?= $guideText('accounting_link_label') ?></span> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </li>
                <li class="verification-card">
                    <div class="verification-instruction"><div class="cms-rich"<?= $guideMark('details_intro') ?>><?= $guideText('details_intro') ?></div></div>
                    <ol class="verification-details" type="a">
                        <li<?= $guideMark('detail_name') ?>><?= $guideText('detail_name') ?></li>
                        <li<?= $guideMark('detail_course') ?>><?= $guideText('detail_course') ?></li>
                        <li<?= $guideMark('detail_date') ?>><?= $guideText('detail_date') ?></li>
                        <li<?= $guideMark('detail_purpose') ?>><?= $guideText('detail_purpose') ?></li>
                    </ol>
                    <?= paymentGuideIllustration($paymentImages, 'payment-details', 'verification-art') ?>
                </li>
                <li class="verification-card">
                    <div class="verification-instruction"><div class="cms-rich"<?= $guideMark('confirmation_intro') ?>><?= $guideText('confirmation_intro') ?></div></div>
                    <?= paymentGuideIllustration($paymentImages, 'confirmation', 'verification-art') ?>
                </li>
            </ol>
        </section>
        <section class="payment-processing" aria-labelledby="paymentProcessingTitle">
            <header class="payment-method-heading">
                <h3 id="paymentProcessingTitle"<?= $guideMark('processing_title') ?>><?= $guideText('processing_title') ?></h3>
                <div class="cms-rich"<?= $guideMark('processing_intro') ?>><?= $guideText('processing_intro') ?></div>
            </header>
            <ol class="verification-cards processing-cards">
                <li class="verification-card processing-card">
                    <div class="verification-instruction"><div class="cms-rich"<?= $guideMark('processing_time') ?>><?= $guideText('processing_time') ?></div></div>
                    <?= paymentGuideIllustration($paymentImages, 'processing', 'verification-art') ?>
                </li>
                <li class="verification-card processing-card">
                    <div class="verification-instruction"><div class="cms-rich"<?= $guideMark('claim_location') ?>><?= $guideText('claim_location') ?></div></div>
                    <?= paymentGuideIllustration($paymentImages, 'window-3', 'verification-art') ?>
                </li>
                <li class="verification-card processing-card">
                    <div class="verification-instruction"><div class="cms-rich"<?= $guideMark('claim_proof') ?>><?= $guideText('claim_proof') ?></div></div>
                    <?= paymentGuideIllustration($paymentImages, 'claim-receipt', 'verification-art') ?>
                </li>
            </ol>
        </section>
        <aside class="payment-guide-help" aria-labelledby="paymentHelpTitle">
            <div><h2 id="paymentHelpTitle"<?= $guideMark('help_title') ?>><?= $guideText('help_title') ?></h2><div class="cms-rich"<?= $guideMark('help_intro') ?>><?= $guideText('help_intro') ?></div></div>
            <a class="fee-download" href="<?= $guideText('contact_link_url') ?>"<?= $guideLink('contact_link_url') ?>><span<?= $guideMark('contact_link_label') ?>><?= $guideText('contact_link_label') ?></span> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </aside>
        <p class="payment-art-credit">Illustrations by <a href="https://storyset.com/" target="_blank" rel="noopener noreferrer">Storyset</a></p>
        <a class="payment-guide-back" href="<?= $guideText('back_link_url') ?>"<?= $guideLink('back_link_url') ?>><i class="bi bi-arrow-left" aria-hidden="true"></i> <span<?= $guideMark('back_link_label') ?>><?= $guideText('back_link_label') ?></span></a>
    </main>
    <?= ceData('vePaymentGuideData', ['images' => $paymentImages, 'fields' => $guideFields]) ?>
    <?php include_once __DIR__ . '/../../components/footer.php'; ?>
</body>
</html>
