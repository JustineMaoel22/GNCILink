<?php
require_once __DIR__ . '/../../components/scholarship-content.php';
cePreviewActive();
$schFields = scholarshipFields();
$schImages = scholarshipImages();
$schEntries = scholarshipEntries();
$schMark = fn($key) => ceField('scholarship-text', ['key'=>$key, 'label'=>$schFields[$key]['label'], 'max'=>$schFields[$key]['max'], 'multiline'=>$schFields[$key]['max'] > 255, 'optional'=>str_ends_with($key, '_grade')]);
$schText = fn($key) => nl2br(ceAttr($schFields[$key]['value']));
$schCards = scholarshipCards($schFields);
$schRequirements = [];
foreach (scholarshipDefaultRequirements() as $i => $value) $schRequirements[] = $schFields['requirement_' . $i]['value'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ceAttr($schFields['copy_1']['value']) ?> | Guagua National Colleges</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/scholarship-style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/scholarship-style.css') ?>" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php $activeSection = 'admissions'; ?>
    <?php include_once __DIR__ . '/../../components/navbar.php'; ?>

    <section class="scholarship-intro">
        <div class="scholarship-hero">
            <div class="scholarship-hero-inner">
                <nav class="scholarship-breadcrumb" aria-label="breadcrumb">
                    <a class="gnc-breadcrumb-home" href="/" aria-label="Home" title="Home"><svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 1 0 7h2v8h4v-5h4v5h4V7h2L8 1Z"/></svg></a><i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>Admissions</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="scholarship-current"><?= $schText('copy_1') ?></span>
                </nav>
                <h1<?= $schMark('copy_1') ?>><?= $schText('copy_1') ?></h1>
                <p<?= $schMark('copy_2') ?>><?= $schText('copy_2') ?></p>
            </div>
        </div>
    </section>

    <section class="scholarship-body">
        <div class="scholarship-container">

            <ul class="nav scholarship-tabs" id="scholarshipTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="gnc-tab" data-bs-toggle="tab" data-bs-target="#gnc-pane" type="button" role="tab" aria-controls="gnc-pane" aria-selected="true"<?= $schMark('copy_3') ?>><?= $schText('copy_3') ?></button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="gov-tab" data-bs-toggle="tab" data-bs-target="#gov-pane" type="button" role="tab" aria-controls="gov-pane" aria-selected="false"<?= $schMark('copy_4') ?>><?= $schText('copy_4') ?></button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="gnc-pane" role="tabpanel" aria-labelledby="gnc-tab" tabindex="0">
                    <p class="sch-lead"<?= $schMark('copy_5') ?>><?= $schText('copy_5') ?></p>

                    <div class="sch-photo-grid">
                        <a href="#basicEdModal" class="sch-photo-card blue" role="button" data-bs-toggle="modal" data-bs-target="#basicEdModal">
                            <img src="<?= ceAttr($schImages['photo_1']['url']) ?>" alt="Students gathered in a classroom for a school program" loading="lazy"<?= ceManageMark('data-ve-img="scholarship-image" data-ve-key="photo_1"') ?>>
                            <div class="sch-photo-caption">
                                <h2<?= $schMark('copy_6') ?>><?= $schText('copy_6') ?></h2>
                                <span<?= $schMark('copy_18') ?>><?= $schText('copy_18') ?></span>
                            </div>
                        </a>

                        <a href="#collegeModal" class="sch-photo-card gold" role="button" data-bs-toggle="modal" data-bs-target="#collegeModal">
                            <img src="<?= ceAttr($schImages['photo_2']['url']) ?>" alt="Group of smiling college students" loading="lazy"<?= ceManageMark('data-ve-img="scholarship-image" data-ve-key="photo_2"') ?>>
                            <div class="sch-photo-caption">
                                <h2<?= $schMark('copy_7') ?>><?= $schText('copy_7') ?></h2>
                                <span<?= $schMark('copy_19') ?>><?= $schText('copy_19') ?></span>
                            </div>
                        </a>

                        <a href="#athletic" class="sch-photo-card green">
                            <img src="<?= ceAttr($schImages['photo_3']['url']) ?>" alt="Student athletes performing in green team uniforms" loading="lazy"<?= ceManageMark('data-ve-img="scholarship-image" data-ve-key="photo_3"') ?>>
                            <div class="sch-photo-caption">
                                <h2<?= $schMark('copy_8') ?>><?= $schText('copy_8') ?></h2>
                            </div>
                        </a>
                    </div>
                    <?php $schEntryCategory = 'gnc'; include __DIR__ . '/../../components/scholarship-entry-list.php'; ?>
                </div>

                <div class="tab-pane fade" id="gov-pane" role="tabpanel" aria-labelledby="gov-tab" tabindex="0">
                    <?php if (cePreviewCanManage() || !array_filter($schEntries, static fn($entry) => $entry['category'] === 'government') || $schFields['copy_9']['value'] !== scholarshipFieldDefaults()['copy_9']['value']): ?>
                    <p class="sch-lead"<?= $schMark('copy_9') ?>><?= $schText('copy_9') ?></p>
                    <?php endif; ?>
                    <?php $schEntryCategory = 'government'; include __DIR__ . '/../../components/scholarship-entry-list.php'; ?>
                </div>
            </div>

        </div>
    </section>

    <?php
    function renderScholarshipCard(array $c, string $prefix, callable $mark): void { ?>
        <div class="sch-voucher <?= $c['type'] ?>">
            <div class="sch-voucher-paper">
                <span class="sch-voucher-badge"<?= $mark($prefix . '_badge') ?>><?= htmlspecialchars($c['badge']) ?></span>
                <div class="sch-voucher-label"<?= $mark($prefix . '_label') ?>><?= htmlspecialchars($c['label']) ?></div>
                <div class="sch-voucher-pct" aria-label="<?= (int)$c['pct'] ?> percent off">
                    <span class="num"<?= $mark($prefix . '_pct') ?>><?= (int)$c['pct'] ?></span>
                    <span class="tag"><span class="sign">%</span><span class="off">OFF</span></span>
                </div>
                <div class="sch-voucher-pill">
                    <?php if ($c['grade'] || cePreviewCanManage()): ?><strong<?= $mark($prefix . '_grade') ?>><?= htmlspecialchars($c['grade']) ?></strong><?php endif; ?>
                    <span<?= $mark($prefix . '_program') ?>><?= htmlspecialchars($c['program']) ?></span>
                </div>
            </div>
        </div>
    <?php } ?>

    <div class="modal fade sch-modal" id="basicEdModal" tabindex="-1" aria-labelledby="basicEdModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="basicEdModalLabel"<?= $schMark('copy_10') ?>><?= $schText('copy_10') ?></h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h3 class="sch-modal-heading"<?= $schMark('copy_11') ?>><?= $schText('copy_11') ?></h3>
                    <div class="sch-voucher-grid">
                        <?php foreach ($schCards['incomingGrade7'] as $i => $card) renderScholarshipCard($card, 'incomingGrade7_' . $i, $schMark); ?>
                    </div>

                    <h3 class="sch-modal-heading"<?= $schMark('copy_12') ?>><?= $schText('copy_12') ?></h3>
                    <div class="sch-voucher-grid">
                        <?php foreach ($schCards['oldStudents'] as $i => $card) renderScholarshipCard($card, 'oldStudents_' . $i, $schMark); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade sch-modal" id="collegeModal" tabindex="-1" aria-labelledby="collegeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="collegeModalLabel"<?= $schMark('copy_13') ?>><?= $schText('copy_13') ?></h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h3 class="sch-modal-heading"<?= $schMark('copy_14') ?>><?= $schText('copy_14') ?></h3>
                    <div class="sch-voucher-grid">
                        <?php foreach ($schCards['collegeIncoming'] as $i => $card) renderScholarshipCard($card, 'collegeIncoming_' . $i, $schMark); ?>
                    </div>

                    <h3 class="sch-modal-heading"<?= $schMark('copy_15') ?>><?= $schText('copy_15') ?></h3>
                    <div class="sch-voucher-grid">
                        <?php foreach ($schCards['collegeOld'] as $i => $card) renderScholarshipCard($card, 'collegeOld_' . $i, $schMark); ?>
                    </div>

                    <h3 class="sch-modal-heading"<?= $schMark('copy_16') ?>><?= $schText('copy_16') ?></h3>
                    <div class="sch-req">
                        <div class="sch-req-main">
                            <p class="sch-req-lead"<?= $schMark('copy_17') ?>><?= $schText('copy_17') ?></p>
                            <ul class="sch-req-list">
                                <?php foreach ($schRequirements as $i => $req): ?>
                                    <li>
                                        <span class="sch-req-icon"><i class="bi bi-file-earmark-text"></i></span>
                                        <span class="sch-req-text"<?= $schMark('requirement_' . $i) ?>><?= htmlspecialchars($req) ?></span>
                                        <i class="bi bi-check-square sch-req-check" aria-hidden="true"></i>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <!-- ILLUSTRATION GOES HERE: replace the src with your requirements illustration -->
                        <img class="sch-req-art" src="<?= ceAttr($schImages['photo_4']['url']) ?>" alt="" loading="lazy"<?= ceManageMark('data-ve-img="scholarship-image" data-ve-key="photo_4"') ?>>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?= ceData('veScholarshipData', ['fields'=>$schFields, 'images'=>$schImages, 'entries'=>$schEntries]) ?>
    <?php include_once __DIR__ . '/../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="/assets/js/skeleton-loader.js"></script>

</body>
</html>
