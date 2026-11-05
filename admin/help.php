<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/admin-functions.php';
initSession();requireLogin();
require_once __DIR__.'/../includes/admin-help.php';
$topics=adminHelpTopics();
$topicIds=array_column($topics,'id');
$selected=is_string($_GET['topic']??null)&&in_array($_GET['topic'],$topicIds,true)?$_GET['topic']:'getting-started';
$query=is_string($_GET['q']??null)?mb_substr(trim($_GET['q']),0,200):'';
$matches=$query!==''?adminHelpSearch($topics,$query):[];
$pageTitle='Help / User Guide';
require __DIR__.'/../components/header-admin.php';
$e=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?>
<link rel="stylesheet" href="/assets/css/admin-help.css?v=<?= (int)filemtime(__DIR__.'/../assets/css/admin-help.css') ?>">
<div class="help-page" data-help-role="<?= $e(rbacRole()) ?>">
<div class="page-header"><div><h1>Help / User Guide</h1><p>Find a feature, learn the steps and get back to your work.</p></div><span class="help-role"><i class="bi bi-person-check" aria-hidden="true"></i> <?= $e(rbacRole()) ?> workspace</span></div>
<section class="data-card help-search-card" aria-labelledby="help-search-title">
<div class="help-search-intro"><h2 id="help-search-title">How can we help you?</h2><p>Search a task or browse the topics available to your role.</p></div>
<form method="get" action="/admin/help.php" id="help-search-form" role="search"><label class="visually-hidden" for="help-search">Search help topics</label><div class="help-search-input"><i class="bi bi-search" aria-hidden="true"></i><input class="form-control" type="search" name="q" id="help-search" maxlength="200" autocomplete="off" value="<?= $e($query) ?>" placeholder="Try: publish an announcement or reset password" aria-controls="help-search-results" aria-describedby="help-search-hint"><button class="btn btn-gnc" type="submit">Search</button></div><p id="help-search-hint">Results include guides, steps and frequently asked questions.</p></form>
</section>
<aside class="help-tour-promo"><span class="help-tour-icon"><i class="bi bi-signpost-split" aria-hidden="true"></i></span><div><h2>New here? Take a guided walkthrough.</h2><p>A short tour highlights your menu and key controls. It will not edit or publish content.</p></div><button class="btn btn-outline-secondary" type="button" data-start-onboarding>Start guided walkthrough <i class="bi bi-arrow-right" aria-hidden="true"></i></button></aside>
<noscript><p class="help-note">Use the topic links and Search button to browse. Enable JavaScript to run the guided walkthrough.</p></noscript>
<label class="help-topic-picker" for="help-topic-select">Browse topics<select class="form-select" id="help-topic-select"><?php foreach($topics as $topic): ?><option value="<?= $e($topic['id']) ?>"<?= $topic['id']===$selected?' selected':'' ?>><?= $e($topic['title']) ?></option><?php endforeach; ?></select></label>
<div class="help-layout">
<nav class="data-card help-topics" aria-label="Help topics"><h2>Browse topics</h2><div class="help-topic-links">
<?php foreach($topics as $topic): ?><a href="/admin/help.php?topic=<?= $e($topic['id']) ?>#help-content" data-help-topic="<?= $e($topic['id']) ?>"<?= $topic['id']===$selected&&$query===''?' aria-current="page"':'' ?>><i class="bi bi-<?= $e($topic['icon']) ?>" aria-hidden="true"></i><span><?= $e($topic['title']) ?></span><i class="bi bi-chevron-right help-topic-arrow" aria-hidden="true"></i></a><?php endforeach; ?>
</div></nav>
<div class="help-content" id="help-content">
<section class="data-card help-search-results" id="help-search-results" aria-labelledby="help-results-heading"<?= $query===''?' hidden':'' ?>><div class="help-result-heading"><div><p class="help-eyebrow">Search help</p><h2 id="help-results-heading" tabindex="-1">Search results</h2></div><a href="/admin/help.php?topic=<?= $e($selected) ?>#help-content" id="help-clear-search">Clear search</a></div><p id="help-result-count" role="status" aria-live="polite"><?= count($matches) ?> matching <?= count($matches)===1?'topic':'topics' ?></p><div id="help-result-list">
<?php foreach($matches as $match): ?><a class="help-result" data-help-result="<?= $e($match['id']) ?>" href="/admin/help.php?topic=<?= $e($match['id']) ?>#help-content"><strong><?= $e($match['title']) ?></strong><span><?= $e($match['summary']) ?></span><span class="help-result-action">Read guide <i class="bi bi-arrow-right" aria-hidden="true"></i></span></a><?php endforeach; ?>
</div><div class="help-no-results" id="help-no-results"<?= $matches?' hidden':'' ?>><i class="bi bi-search" aria-hidden="true"></i><h3>No matching topics</h3><p>Try a shorter phrase such as password, draft or approval, or choose a topic from the menu.</p></div></section>
<?php foreach($topics as $topic): ?>
<article class="data-card help-article" id="help-<?= $e($topic['id']) ?>" data-help-article="<?= $e($topic['id']) ?>" data-help-keywords="<?= $e($topic['keywords']) ?>" aria-labelledby="help-title-<?= $e($topic['id']) ?>"<?= $topic['id']!==$selected||$query!==''?' hidden':'' ?>>
<header class="help-article-heading"><span class="help-article-icon"><i class="bi bi-<?= $e($topic['icon']) ?>" aria-hidden="true"></i></span><div><p class="help-eyebrow">Your guide</p><h2 id="help-title-<?= $e($topic['id']) ?>" tabindex="-1"><?= $e($topic['title']) ?></h2></div></header>
<p class="help-summary"><?= $e($topic['summary']) ?></p>
<h3><?= $topic['id']==='getting-started'?'Set up in four steps':'Step by step' ?></h3>
<ol class="help-steps"><?php foreach($topic['steps'] as $step): ?><li><?= $e($step) ?></li><?php endforeach; ?></ol>
<aside class="help-callout"><i class="bi bi-info-circle" aria-hidden="true"></i><p><?= $e($topic['note']) ?></p></aside>
<?php if($topic['route']): ?><a class="btn btn-gnc help-module-link" href="<?= $e($topic['route']) ?>"><?= $e($topic['cta']) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a><p class="help-return-hint">Use the Back button in your browser to return to this guide and keep your place.</p><?php else: ?><div class="help-start-tip"><h3>Explore your workspace</h3><p>The guided walkthrough explains the controls available to your role. Use Next or Back at your own pace. Skip saves your choice to stop; Finish records completion. Restart it here whenever you need it.</p><button class="btn btn-gnc" type="button" data-start-onboarding>Show me around <i class="bi bi-arrow-right" aria-hidden="true"></i></button></div><?php endif; ?>
<section class="help-faqs" aria-labelledby="help-faq-title-<?= $e($topic['id']) ?>"><h3 id="help-faq-title-<?= $e($topic['id']) ?>">Common questions</h3><?php foreach($topic['faqs'] as $faq): ?><details><summary><?= $e($faq['question']) ?></summary><p><?= $e($faq['answer']) ?></p></details><?php endforeach; ?></section>
</article>
<?php endforeach; ?>
<p class="help-support"><i class="bi bi-chat-square-text" aria-hidden="true"></i> Still need help? Contact your Super Admin or the person responsible for the website. Describe the step and error message; keep passwords and verification codes private.</p>
</div></div></div>
<script src="/assets/js/admin-help.js?v=<?= (int)filemtime(__DIR__.'/../assets/js/admin-help.js') ?>"></script>
<?php require __DIR__.'/../components/footer-admin.php'; ?>
