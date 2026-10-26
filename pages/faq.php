<?php
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/faqs.php';
$faq=null;
try{$faq=faqPageContent(getDB());}catch(Throwable $error){error_log('FAQ page: '.$error->getMessage());}
$faqPreview=cePreviewCanManage();$activeSection='student-life';
$faqH=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
$faqField=static fn($key,$label)=>ceField('faq-text',['key'=>$key,'label'=>$label,'max'=>FAQ_TEXT_LIMITS[$key],'multiline'=>in_array($key,['intro','empty_description'],true)]);
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $faqH($faq['texts']['title']??'Frequently Asked Questions') ?></title>
<link rel="icon" href="/assets/images/logos/gnc-logo-v1.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/css/bootstrap.min.css"><link rel="stylesheet" href="/assets/css/navbar-style.css"><link rel="stylesheet" href="/assets/css/footer-style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/css/faq-style.css?v=<?= filemtime(__DIR__.'/../assets/css/faq-style.css') ?>">
</head><body>
<?php include __DIR__.'/../components/navbar.php'; ?>
<main class="faq-page"><div class="faq-container">
<?php if($faq): ?>
    <header class="faq-heading"><h1<?= $faqField('title','FAQ page title') ?>><?= $faqH($faq['texts']['title']) ?></h1><p<?= $faqField('intro','FAQ introduction') ?>><?= $faqH($faq['texts']['intro']) ?></p></header>
    <?php if($faqPreview): ?><div class="faq-editor-actions"><button class="btn btn-success" type="button" data-ve-faq-add><i class="bi bi-plus-lg" aria-hidden="true"></i> Add FAQ</button><small>Click a question or answer to edit it.</small></div><?php endif; ?>
    <div class="faq-empty" data-faq-empty<?= $faq['items']?' hidden':'' ?>><i class="bi bi-question-circle" aria-hidden="true"></i><h2<?= $faqField('empty_title','Empty-state heading') ?>><?= $faqH($faq['texts']['empty_title']) ?></h2><p<?= $faqField('empty_description','Empty-state description') ?>><?= $faqH($faq['texts']['empty_description']) ?></p></div>
    <div class="faq-list" data-faq-list<?= !$faq['items']?' hidden':'' ?>>
    <?php foreach($faq['items'] as $item): ?>
        <details class="faq-item"<?= $faqPreview?' open':'' ?><?= ceManageMark('data-ve-faq-item="'.$faqH($item['id']).'"') ?>>
            <summary><span<?= ceField('faq-question',['key'=>$item['id'],'label'=>'Question','max'=>1000]) ?>><?= $faqH($item['question']) ?></span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
            <div class="faq-answer"<?= ceField('faq-answer',['key'=>$item['id'],'label'=>'Answer','max'=>20000,'multiline'=>true]) ?>><?= $faqH($item['answer']) ?></div>
        </details>
    <?php endforeach; ?>
    </div>
    <?php if($faqPreview): ?><script type="application/json" id="faqPageData"><?= json_encode(['content'=>$faq,'revision'=>faqPageRevision($faq)],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?></script><?php endif; ?>
<?php else: ?><div class="faq-empty"><h1>FAQs are temporarily unavailable</h1><p>Please check back later.</p></div><?php endif; ?>
</div></main>
<?php include __DIR__.'/../components/footer.php'; ?>
</body></html>
