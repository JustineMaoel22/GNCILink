<?php
require_once __DIR__.'/../../admin/admin-functions.php';
require_once __DIR__.'/../../includes/community-activities.php';
require_once __DIR__.'/../../includes/community-impact-posts.php';
$community=communityActivityContent();$communityPreview=cePreviewCanManage();
$escape=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
$text=static fn($key)=>$community['texts'][$key]??COMMUNITY_IMPACT_TEXT_DEFAULTS[$key]??'';
$field=static fn($key,$label)=>ceField('community-text',['key'=>$key,'label'=>$label,'max'=>COMMUNITY_ACTIVITY_FIELDS[$key],'multiline'=>in_array($key,['intro','hero_description','empty_description','closing_description'],true)]);
$activities=$community['activities']??[];$showcaseImage=communityActivityShowcaseImage($community??[]);
$impact=['items'=>[],'page'=>1,'pages'=>1,'total'=>0];
try{$impact=communityImpactPosts(getDB(),$_GET);}catch(Throwable $error){error_log('Community impact feed: '.$error->getMessage());}
$impactUrl=static fn($page)=>'?'.http_build_query(array_merge($communityPreview?['ce_preview'=>'1']:[],['impact_page'=>$page])).'#community-impact';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $escape($text('hero_title')) ?></title>
    <meta name="description" content="<?= $escape($text('hero_description')) ?>">
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/community-activity-style.css?v=<?= filemtime(__DIR__.'/../../assets/css/community-activity-style.css') ?>" rel="stylesheet">
    <link href="/assets/css/community-impact-style.css?v=<?= filemtime(__DIR__.'/../../assets/css/community-impact-style.css') ?>" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>

    <?php $activeSection = 'student-life'; ?>
    <?php include_once __DIR__ . '/../../components/navbar.php'; ?>

    <?php if($community): ?>
    <section class="community-activity-intro">
        <div class="community-activity-hero" style="<?= $escape('background-image:url('.json_encode($community['hero_image']).')') ?>">
            <div class="community-activity-hero-inner">
                <nav class="community-activity-breadcrumb" aria-label="breadcrumb">
                    <a class="gnc-breadcrumb-home" href="/" aria-label="Home" title="Home"><svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 1 0 7h2v8h4v-5h4v5h4V7h2L8 1Z"/></svg></a><i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>Student Life</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="community-activity-current" data-community-breadcrumb><?= $escape($text('hero_title')) ?></span>
                </nav>
                <h1<?= $field('hero_title','Page title') ?>><?= $escape($text('hero_title')) ?></h1>
                <p<?= $field('hero_description','Banner introduction') ?>><?= $escape($text('hero_description')) ?></p>
            </div>
        </div>
    </section>
    <section class="community-activity-about" aria-labelledby="communityHeading">
        <div class="community-activity-content">
            <header class="community-activity-heading">
                <h2 id="communityHeading"><span<?= $field('heading_first','Section heading: first line') ?>><?= $escape($text('heading_first')) ?></span><br><em<?= $field('heading_accent','Section heading: emphasis') ?>><?= $escape($text('heading_accent')) ?></em> <span<?= $field('heading_last','Section heading: last word') ?>><?= $escape($text('heading_last')) ?></span></h2>
                <p<?= $field('intro','Section introduction') ?>><?= $escape($text('intro')) ?></p>
            </header>
            <?php if($communityPreview): ?><div class="community-activity-editor-actions"><button type="button" class="btn btn-success" data-ve-community-add><i class="bi bi-plus-lg" aria-hidden="true"></i> Add activity</button><small>Click activity text to edit. Select the shared picture to replace it.</small></div><?php endif; ?>
            <div class="community-activity-empty" data-community-empty<?= $activities?' hidden':'' ?>>
                <i class="bi bi-heart" aria-hidden="true"></i><h3<?= $field('empty_title','Empty-state heading') ?>><?= $escape($text('empty_title')) ?></h3><p<?= $field('empty_description','Empty-state description') ?>><?= $escape($text('empty_description')) ?></p>
            </div>
            <div class="community-activity-showcase" data-community-showcase<?= !$activities?' hidden':'' ?>>
                <figure class="community-activity-feature" data-community-feature<?= ceManageMark('data-ve-community-image="showcase" data-ve-img="community-activity"') ?>>
                    <img data-community-image<?= $showcaseImage===''?' hidden':' src="'.$escape($showcaseImage).'"' ?> alt="<?= $escape($text('hero_title')) ?>">
                    <div class="community-activity-no-image" data-community-no-image<?= $showcaseImage!==''?' hidden':'' ?>><i class="bi bi-image" aria-hidden="true"></i><span>No image available</span></div>
                    <figcaption class="visually-hidden" data-community-caption><?= $escape($text('hero_title')) ?></figcaption>
                </figure>
                <div class="community-activity-list-panel">
                    <h3<?= $field('list_heading','Activity list heading') ?>><?= $escape($text('list_heading')) ?></h3>
                    <ol class="community-activity-list" data-community-list>
                        <?php foreach($activities as $index=>$activity): ?>
                        <li class="community-activity-item<?= $index===0?' is-active':'' ?>" data-community-id="<?= $escape($activity['id']) ?>"<?= ceManageMark('data-ve-community-card="'.$escape($activity['id']).'"') ?>>
                            <span class="community-activity-number" aria-hidden="true"><?= str_pad((string)($index+1),2,'0',STR_PAD_LEFT) ?></span>
                            <div class="community-activity-copy">
                                <h4<?= ceField('community-activity-title',['key'=>$activity['id'],'label'=>'Activity title','max'=>255]) ?>><?= $escape($activity['title']) ?></h4>
                                <span class="community-activity-category"<?= ceField('community-activity-category',['key'=>$activity['id'],'label'=>'Category','max'=>100]) ?>><?= $escape($activity['category']) ?></span>
                                <?php if($communityPreview): ?><p class="community-activity-description"<?= ceField('community-activity-description',['key'=>$activity['id'],'label'=>'Activity description','max'=>12000,'multiline'=>true]) ?>><?= $escape($activity['description']) ?></p>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        </div>
    </section>
    <section class="campus-recent-events community-impact" id="community-impact" aria-labelledby="communityImpactHeading"<?= $communityPreview?' data-community-impact-preview':'' ?>>
        <div class="container">
            <h2 id="communityImpactHeading"<?= $field('impact_heading','Community Impact heading') ?>><?= $escape($text('impact_heading')) ?></h2>
            <div data-community-impact-content>
            <?php if(!$impact['items']): ?><p class="campus-recent-empty"<?= $field('impact_empty','Community Impact empty-state message') ?>><?= $escape($text('impact_empty')) ?></p>
            <?php else: ?><div class="campus-recent-grid">
                <?php foreach($impact['items'] as $post):
                    $image=(string)($post['image_path']??'');if($image!==''&&!preg_match('~^https?://~i',$image))$image='/'.ltrim($image,'/');
                    $date=strtotime((string)$post['post_date']); ?>
                <a class="campus-recent-card" href="/news-article.php?slug=<?= rawurlencode($post['slug']) ?>"<?= ceManageMark('data-ve-post="news" data-ve-id="'.(int)$post['news_id'].'" data-ve-label="'.$escape($post['title']).'"') ?>>
                    <?php if($image!==''): ?><img class="campus-recent-image" src="<?= $escape($image) ?>" alt="" loading="lazy" decoding="async">
                    <?php else: ?><div class="campus-recent-placeholder" aria-hidden="true"><i class="bi bi-newspaper"></i></div><?php endif; ?>
                    <div class="campus-recent-content"><span class="campus-recent-type">Community Extension Activity</span><h3><?= $escape($post['title']) ?></h3><?php if($date!==false): ?><time class="campus-recent-meta" datetime="<?= date('Y-m-d',$date) ?>"><i class="bi bi-calendar3" aria-hidden="true"></i><?= date('F j, Y',$date) ?></time><?php endif; ?></div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php if($impact['pages']>1): ?><nav class="campus-recent-pagination" aria-label="Community Impact pages">
                <?php if($impact['page']>1): ?><a href="<?= $escape($impactUrl($impact['page']-1)) ?>" rel="prev" aria-label="Previous page"><i class="bi bi-chevron-left" aria-hidden="true"></i></a><?php endif; ?>
                <?php for($p=1;$p<=$impact['pages'];$p++): ?>
                    <?php if($p===1||$p===$impact['pages']||abs($p-$impact['page'])<=1): ?><a href="<?= $escape($impactUrl($p)) ?>"<?= $p===$impact['page']?' aria-current="page"':'' ?> aria-label="Page <?= $p ?>"><?= $p ?></a>
                    <?php elseif($p===$impact['page']-2||$p===$impact['page']+2): ?><span aria-hidden="true">&hellip;</span><?php endif; ?>
                <?php endfor; ?>
                <?php if($impact['page']<$impact['pages']): ?><a href="<?= $escape($impactUrl($impact['page']+1)) ?>" rel="next" aria-label="Next page"><i class="bi bi-chevron-right" aria-hidden="true"></i></a><?php endif; ?>
            </nav><?php endif; endif; ?>
            </div>
        </div>
    </section>
    <section class="community-mission" aria-labelledby="communityMissionTitle">
        <div class="community-mission-background" data-community-closing-image<?= ceManageMark('data-ve-community-image="closing" data-ve-img="community-closing"') ?>>
            <img src="<?= $escape($community['closing_image']??COMMUNITY_CLOSING_IMAGE_DEFAULT) ?>" alt="" loading="lazy" decoding="async">
        </div>
        <span class="community-mission-orb community-mission-orb-top" aria-hidden="true"></span>
        <span class="community-mission-orb community-mission-orb-left" aria-hidden="true"></span>
        <span class="community-mission-orb community-mission-orb-right" aria-hidden="true"></span>
        <div class="community-mission-content">
            <h2 id="communityMissionTitle"<?= $field('closing_title','Mission banner heading') ?>><?= $escape($text('closing_title')) ?></h2>
            <p<?= $field('closing_description','Mission banner description') ?>><?= $escape($text('closing_description')) ?></p>
        </div>
    </section>

    <?php include_once __DIR__ . '/../../components/footer.php'; ?>

    <script type="application/json" id="communityPageData"><?= json_encode(['content'=>$community,'showcase_image'=>$showcaseImage,'revision'=>communityActivityRevision($community),'preview'=>$communityPreview,'limits'=>COMMUNITY_ACTIVITY_FIELDS],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?></script>
    <?php else: ?><section class="community-activity-about"><div class="community-activity-content community-activity-empty"><h1>Activities are temporarily unavailable</h1><p>Please check back later.</p></div></section><?php endif; ?>
    <script src="/assets/js/community-activities.js?v=<?= filemtime(__DIR__.'/../../assets/js/community-activities.js') ?>"></script>
    <script src="/assets/js/community-impact.js?v=<?= filemtime(__DIR__.'/../../assets/js/community-impact.js') ?>"></script>
</body>
</html>
