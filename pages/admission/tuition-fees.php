<?php
require_once __DIR__ . '/../../admin/admin-functions.php';
require_once __DIR__ . '/../../components/tuition-fee-documents.php';
$feeFields = tuitionFeeFields();
$feePreview = cePreviewCanManage();
$feeMark = function ($key) use ($feeFields) { $f=$feeFields[$key]; return ceField('tuition-text',['key'=>$key,'label'=>$f['label'],'max'=>$f['max'],'multiline'=>$f['max']>255,'rich'=>$f['max']>255,'optional'=>str_starts_with($key,'empty_')]); };
$feeText = fn($key) => $feeFields[$key]['max']>255 ? renderSectionContent($feeFields[$key]['value']) : htmlspecialchars($feeFields[$key]['value'],ENT_QUOTES,'UTF-8');
$feeCategories = tuitionFeeCategories();
$feeCategory = (string)($_GET['category'] ?? 'college');
if (!isset($feeCategories[$feeCategory])) $feeCategory = 'college';
$feeSelected = $feeCategories[$feeCategory];
$feeDocumentIndex = filter_var($_GET['document'] ?? 0, FILTER_VALIDATE_INT);
if ($feeDocumentIndex === false || $feeDocumentIndex < 0 || !isset($feeSelected['documents'][$feeDocumentIndex])) $feeDocumentIndex = 0;
$feeDocument = $feeSelected['documents'][$feeDocumentIndex] ?? null;
$feeH = fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tuition and Fees</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=block">
    <link href="/assets/css/tuition-fees-style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/tuition-fees-style.css') ?>" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>

    <?php $activeSection = 'admissions'; ?>
    <?php include_once __DIR__ . '/../../components/navbar.php'; ?>

    <section class="tuition-intro">
        <div class="tuition-hero">
            <div class="tuition-hero-inner">
                <nav class="tuition-breadcrumb" aria-label="breadcrumb">
                    <span>Admissions</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="tuition-current">Tuition and Fees</span>
                </nav>
                <h1<?= $feeMark('hero_title') ?>><?= $feeText('hero_title') ?></h1>
                <div class="tuition-hero-description cms-rich"<?= $feeMark('hero_intro') ?>><?= $feeText('hero_intro') ?></div>
            </div>
        </div>
    </section>

    <main class="tuition-documents" aria-label="Tuition and fee documents">
        <aside class="fee-sidebar" aria-labelledby="feeCategoriesTitle">
            <h2 id="feeCategoriesTitle"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> <span<?= $feeMark('categories_title') ?>><?= $feeText('categories_title') ?></span></h2>
            <label class="fee-search"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Search fee documents</span><input type="search" id="feeSearch" placeholder="Search documents…" autocomplete="off" aria-controls="feeCategoryList feeDocumentList"></label>
            <nav id="feeCategoryList" class="fee-categories" aria-label="Fee categories">
                <?php foreach ($feeCategories as $key => $category): ?>
                <a class="fee-category<?= $key === $feeCategory ? ' is-active' : '' ?>" href="?category=<?= $feeH($key) ?>" data-fee-category="<?= $feeH($key) ?>"<?= $key === $feeCategory ? ' aria-current="true"' : '' ?>><i class="bi bi-<?= $feeH($category['icon']) ?>" aria-hidden="true"></i><span<?= $feeMark('category_' . $key) ?>><?= $feeH($category['label']) ?></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                <?php endforeach; ?>
            </nav>
            <p class="fee-search-status" id="feeSearchStatus" role="status" hidden>No matching documents or categories.</p>
            <div id="feeDocumentList" class="fee-document-list" aria-label="Matching fee documents" hidden></div>
        </aside>
        <section class="fee-viewer" aria-labelledby="feeDocumentTitle">
            <header class="fee-document-header">
                <div class="fee-document-info"><span class="fee-pdf-icon"><i class="bi bi-file-earmark-pdf-fill" aria-hidden="true"></i></span><div><h2 id="feeDocumentTitle"><?= $feeH($feeDocument['title'] ?? $feeSelected['label'] . ' — Tuition and Fees') ?></h2><p id="feeDocumentMeta"><?= $feeDocument ? 'PDF Document · ' . $feeH($feeDocument['size']) : 'Tuition and fee schedules' ?></p></div></div>
                <a class="fee-download" id="feeDownload"<?= $feeDocument ? ' href="' . $feeH($feeDocument['url']) . '"' : ' hidden' ?> download><i class="bi bi-download" aria-hidden="true"></i> <span<?= $feeMark('download_label') ?>><?= $feeText('download_label') ?></span></a>
            </header>
            <div class="fee-document-selector" id="feeDocumentSelector"<?= count($feeSelected['documents']) > 1 ? '' : ' hidden' ?>>
                <label for="feeDocumentSelect">Choose a PDF</label>
                <select id="feeDocumentSelect" aria-controls="feePdfFrame">
                    <?php foreach ($feeSelected['documents'] as $index => $document): ?>
                    <option value="<?= $index ?>"<?= $index === $feeDocumentIndex ? ' selected' : '' ?>><?= $feeH($document['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($feePreview): ?><button type="button" class="fee-editor-manage" data-ve-fee-manage><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Manage PDFs for this category</button><?php endif; ?>
            <div class="fee-document-stage">
                <iframe id="feePdfFrame" title="Tuition and fees PDF document"<?= $feeDocument ? ' src="' . $feeH($feeDocument['url']) . '#view=FitH"' : ' hidden' ?>></iframe>
                <div class="fee-empty" id="feeEmpty"<?= $feeDocument ? ' hidden' : '' ?>><span class="fee-empty-icon"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i></span><span class="fee-empty-label" id="feeEmptyCategory"><?= $feeH($feeSelected['label']) ?></span><h3<?= $feeMark('empty_title') ?>><?= $feeText('empty_title') ?></h3><div class="fee-empty-description cms-rich"<?= $feeMark('empty_intro') ?>><?= $feeText('empty_intro') ?></div><a href="/pages/contact-us.php"><span<?= $feeMark('contact_label') ?>><?= $feeText('contact_label') ?></span> <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
            </div>
            <p class="fee-viewer-note" id="feeViewerNote"<?= $feeDocument ? '' : ' hidden' ?>>Having trouble viewing the document? <a id="feeOpenPdf"<?= $feeDocument ? ' href="' . $feeH($feeDocument['url']) . '"' : '' ?> target="_blank" rel="noopener">Open PDF in a new tab <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a></p>
        </section>
        <section class="fee-guidance" aria-label="Fee and payment information">
            <div class="fee-semester-notice">
                <?= tuitionFeeIcon('semester_icon', 'fee-notice-icon', $feeFields) ?>
                <div><p class="fee-notice-title"<?= $feeMark('semester_notice') ?>><?= $feeText('semester_notice') ?></p><p<?= $feeMark('treasurer_notice') ?>><?= $feeText('treasurer_notice') ?></p></div>
            </div>
            <div class="fee-policy-grid">
                <?php foreach (['payment', 'promissory', 'discounts', 'refunds'] as $policy): ?>
                <article class="fee-policy fee-policy-<?= $policy ?>" aria-labelledby="fee-policy-<?= $policy ?>">
                    <?= tuitionFeeIcon($policy . '_icon', 'fee-policy-icon', $feeFields) ?>
                    <h2 id="fee-policy-<?= $policy ?>"<?= $feeMark($policy . '_title') ?>><?= $feeText($policy . '_title') ?></h2>
                    <div class="fee-policy-description cms-rich"<?= $feeMark($policy . '_intro') ?>><?= $feeText($policy . '_intro') ?></div>
                </article>
                <?php endforeach; ?>
            </div>
            <div class="fee-online-payment">
                <?= tuitionFeeIcon('online_payment_icon', 'fee-online-icon', $feeFields) ?>
                <div>
                    <h2<?= $feeMark('online_payment_title') ?>><?= $feeText('online_payment_title') ?></h2>
                    <div class="fee-online-description cms-rich"<?= $feeMark('online_payment_intro') ?>><?= $feeText('online_payment_intro') ?></div>
                    <a href="/pages/admission/online-payment-guide.php"><span<?= $feeMark('online_payment_link') ?>><?= $feeText('online_payment_link') ?></span> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
            </div>
        </section>
    </main>
    <script type="application/json" id="feeDocumentsData"><?= json_encode($feeCategories, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
    <?= ceData('veTuitionData', ['fields'=>$feeFields, 'icons'=>tuitionFeeIconChoices()]) ?>
    <script src="/assets/js/tuition-fees.js?v=<?= filemtime(__DIR__ . '/../../assets/js/tuition-fees.js') ?>" defer></script>
    <?php include_once __DIR__ . '/../../components/footer.php'; ?>

    
</body>
</html>
