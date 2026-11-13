<?php
require_once __DIR__ . '/../../components/scholarship-content.php';
cePreviewActive();
$schFields=scholarshipFields();$schEntries=scholarshipCatalog();$schCategories=scholarshipCategories();
$schPreview=cePreviewCanManage();
$schMark=fn($key)=>ceField('scholarship-text',['key'=>$key,'label'=>$schFields[$key]['label'],'max'=>$schFields[$key]['max'],'multiline'=>$schFields[$key]['max']>255,'optional'=>str_ends_with($key,'_grade')]);
$schText=fn($key)=>nl2br(ceAttr($schFields[$key]['value']));
$requested=is_string($_GET['scholarship']??null)?$_GET['scholarship']:'';
$category=is_string($_GET['category']??null)?$_GET['category']:array_key_first($schCategories);
if(!isset($schCategories[$category]))$category=array_key_first($schCategories);
$selected=null;
foreach($schEntries as $entry)if($entry['id']===$requested){$selected=$entry;$category=$entry['category'];break;}
if(!$selected)foreach($schEntries as $entry)if($entry['category']===$category){$selected=$entry;break;}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ceAttr($schFields['copy_1']['value']) ?> | Guagua National Colleges</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet"><link href="/assets/css/footer-style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/tuition-fees-style.css?v=<?= filemtime(__DIR__.'/../../assets/css/tuition-fees-style.css') ?>" rel="stylesheet">
    <link href="/assets/css/scholarship-workspace.css?v=<?= filemtime(__DIR__.'/../../assets/css/scholarship-workspace.css') ?>" rel="stylesheet">
    <link rel="icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
<?php $activeSection='admissions';include_once __DIR__.'/../../components/navbar.php'; ?>
<section class="tuition-intro">
    <div class="tuition-hero"><div class="tuition-hero-inner">
        <nav class="tuition-breadcrumb" aria-label="breadcrumb"><a href="/">Home</a><i class="bi bi-chevron-right" aria-hidden="true"></i><span>Admissions</span><i class="bi bi-chevron-right" aria-hidden="true"></i><span><?= $schText('copy_1') ?></span></nav>
        <h1<?= $schMark('copy_1') ?>><?= $schText('copy_1') ?></h1>
        <p<?= $schMark('copy_2') ?>><?= $schText('copy_2') ?></p>
    </div></div>
</section>
<main class="tuition-documents scholarship-workspace" id="scholarshipWorkspace">
    <aside class="fee-sidebar" aria-labelledby="schCategoriesTitle">
        <h2 id="schCategoriesTitle"><i class="bi bi-mortarboard" aria-hidden="true"></i> Scholarship Categories</h2>
        <label class="fee-search"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Search scholarships</span><input id="scholarshipSearch" type="search" placeholder="Search scholarships…" aria-controls="scholarshipNavigation" autocomplete="off"></label>
        <?php if($schPreview): ?><button type="button" class="fee-editor-manage" data-ve-scholarship-manage>Manage scholarships</button><?php endif; ?>
        <details class="sch-navigation" open><summary>Browse categories and scholarships</summary>
        <nav id="scholarshipNavigation" aria-label="Scholarship categories">
        <?php foreach($schCategories as $key=>$group): ?>
            <details class="sch-category-group" data-sch-category="<?= ceAttr($key) ?>"<?= $key===$category?' open':'' ?>>
                <summary class="fee-category<?= $key===$category?' is-active':'' ?>"><i class="bi bi-<?= ceAttr($group['icon']??'mortarboard') ?>" aria-hidden="true"></i><span><?= ceAttr($group['label']) ?></span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
                <div class="fee-document-list">
                <?php foreach($schEntries as $entry): if($entry['category']!==$key)continue; ?>
                    <a class="fee-document-choice" data-sch-select="<?= ceAttr($entry['id']) ?>" href="?category=<?= ceAttr($key) ?>&amp;scholarship=<?= ceAttr($entry['id']) ?>"<?= $entry['id']===($selected['id']??'')?' aria-current="true"':'' ?>><?= ceAttr($entry['title']) ?></a>
                <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
        </nav></details>
        <p id="scholarshipSearchStatus" class="fee-search-status" role="status" hidden>No matching scholarships or categories.</p>
    </aside>
    <section class="fee-viewer" aria-label="Scholarship PDF document" id="scholarshipDetails"<?= $selected?' data-scholarship-entry="'.ceAttr($selected['id']).'"':'' ?>>
        <header class="fee-document-header"><div class="fee-document-info"><span class="fee-pdf-icon"><i class="bi bi-file-earmark-pdf-fill" aria-hidden="true"></i></span><div><h2 id="scholarshipTitle" tabindex="-1"><?= ceAttr($selected['title']??$schCategories[$category]['label']) ?></h2><p id="scholarshipCategory"><?= !empty($selected['pdf'])?'PDF Document · ':'' ?><?= ceAttr($schCategories[$category]['label']) ?></p></div></div>
        <a class="fee-download" id="scholarshipDownload"<?= !empty($selected['pdf'])?' href="'.ceAttr($selected['pdf']).'"':' hidden' ?> download><i class="bi bi-download" aria-hidden="true"></i> Download PDF</a></header>
        <?php if($schPreview): ?><button type="button" class="fee-editor-manage" data-ve-scholarship-upload<?= !$selected?' disabled':'' ?>><i class="bi bi-upload" aria-hidden="true"></i> <span id="scholarshipUploadLabel"><?= !empty($selected['pdf'])?'Replace PDF':'Upload PDF' ?></span></button><?php endif; ?>
        <div class="fee-document-stage" id="scholarshipPdf"<?= empty($selected['pdf'])?' hidden':'' ?>><iframe id="scholarshipPdfFrame" title="<?= ceAttr(($selected['title']??'Scholarship').' (PDF)') ?>"<?= !empty($selected['pdf'])?' src="'.ceAttr($selected['pdf']).'#view=FitH"':'' ?>></iframe></div>
        <p class="fee-viewer-note" id="scholarshipPdfNote"<?= empty($selected['pdf'])?' hidden':'' ?>>Having trouble viewing the document? <a id="scholarshipOpenPdf"<?= !empty($selected['pdf'])?' href="'.ceAttr($selected['pdf']).'"':'' ?> target="_blank" rel="noopener">Open PDF in a new tab</a></p>
        <div class="fee-empty" id="scholarshipEmpty"<?= !empty($selected['pdf'])?' hidden':'' ?>><span class="fee-empty-icon"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i></span><h3 id="scholarshipEmptyTitle"><?= $selected?'Scholarship document coming soon':'No scholarships available in this category yet.' ?></h3><p>No PDF document is available yet.</p><a href="/pages/contact-us.php">Contact us for scholarship information</a></div>
    </section>
</main>
<script type="application/json" id="scholarshipCatalogData"><?= json_encode(['categories'=>array_values($schCategories),'entries'=>$schEntries,'category'=>$category,'selected'=>$selected['id']??null],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES) ?></script>
<?= ceData('veScholarshipData',['fields'=>$schFields,'categoryIcons'=>tuitionFeeCategoryIcons(),'images'=>new stdClass(),'entries'=>$schEntries,'categories'=>array_values($schCategories)]) ?>
<script src="/assets/js/scholarship-workspace.js?v=<?= filemtime(__DIR__.'/../../assets/js/scholarship-workspace.js') ?>" defer></script>
<?php include_once __DIR__.'/../../components/footer.php'; ?>
</body>
</html>
