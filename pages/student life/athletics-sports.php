<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../admin/admin-functions.php';
require_once __DIR__.'/../../components/athletics-sports-manager.php';
require_once __DIR__.'/../../includes/athletics-news.php';
$activeSection = 'student-life';
$sportsContent=athleticsSportsContent();
$sportsPreview=cePreviewCanManage();
$sportsPrograms=$sportsContent['sports'];
$sportsFacebookEmbed=athleticsSportsFacebookEmbedUrl($sportsContent['facebook_url']);
$sportsText=static fn($key)=>htmlspecialchars($sportsContent['texts'][$key],ENT_QUOTES,'UTF-8');
$sportsField=static fn($key,$label)=>ceField('sports-text',['key'=>$key,'label'=>$label,'max'=>str_ends_with($key,'title')?255:3000]);
$sportsH = static fn($value) => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$sportsNews=['items'=>[],'page'=>1,'pages'=>1,'total'=>0];
try{$sportsNews=athleticsNews(getDB(),$_GET);}catch(Throwable $error){error_log('Athletics news unavailable: '.$error->getMessage());}
$highlightsUrl=static fn($page)=>'?'.http_build_query(array_merge(['highlights_page'=>$page],$sportsPreview?['ce_preview'=>1]:[])).'#competition-highlights';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Celebrate GNC's athletic spirit. Explore basketball, indoor volleyball, and beach volleyball at Guagua National Colleges.">
    <title>Athletics and Sports</title>
    <link rel="icon" type="image/svg+xml" href="/assets/images/logos/gnc-logo-v1.svg">
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link href="/assets/css/facilities-carousel.css?v=<?= filemtime(__DIR__.'/../../assets/css/facilities-carousel.css') ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/athletics-sports-style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/athletics-sports-style.css') ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../../components/navbar.php'; ?>

    <main class="athletics-page"<?= ceManageMark('data-ve-sports-preview') ?>>
        <section class="athletics-hero" aria-labelledby="athleticsTitle">
            <img class="athletics-hero-image" src="<?= $sportsH($sportsContent['images']['hero']) ?>" width="1440" height="320" alt="Athletics and Sports banner" fetchpriority="high"<?= ceManageMark('data-ve-sports-background-area="hero" data-ve-img="sports-banner"') ?>>
            <div class="athletics-hero-copy">
                <nav class="athletics-breadcrumb" aria-label="Breadcrumb">
                    <a href="/">Home</a><span aria-hidden="true">›</span>
                    <span>Student Life</span><span aria-hidden="true">›</span>
                    <span aria-current="page">Athletics and Sports</span>
                </nav>
                <h1 id="athleticsTitle"<?= $sportsField('hero_title','Page heading') ?>><?= $sportsText('hero_title') ?></h1>
                <p<?= $sportsField('hero_description','Page introduction') ?>><?= $sportsText('hero_description') ?></p>
            </div>
        </section>

        <section class="athletics-programs" aria-labelledby="sportsProgramsTitle">
            <div class="athletics-programs-intro">
                <h2 id="sportsProgramsTitle"<?= $sportsField('programs_title','Sports programs heading') ?>><?= $sportsText('programs_title') ?></h2>
                <p<?= $sportsField('programs_description','Sports programs introduction') ?>><?= $sportsText('programs_description') ?></p>
                <?php if($sportsPreview): ?><button type="button" class="athletics-add-sport" data-ve-sport-add><i class="bi bi-plus-lg" aria-hidden="true"></i> Add sport</button><?php endif; ?>
                <?php if($sportsPreview): ?><button type="button" class="athletics-add-sport" data-ve-sports-background="showcase"><i class="bi bi-image" aria-hidden="true"></i> Change background image</button><?php endif; ?>
                <?php if($sportsPreview): ?>
                <div class="athletics-speed-controls" data-ve-sports-speed-controls>
                    <label for="sportsSpeed">Slideshow speed</label>
                    <button type="button" data-ve-sports-slower aria-label="Decrease slideshow speed">−</button>
                    <input id="sportsSpeed" type="range" min="0.25" max="3" step="0.25" value="<?= $sportsH((string)$sportsContent['speed']) ?>" data-ve-sports-speed>
                    <button type="button" data-ve-sports-faster aria-label="Increase slideshow speed">+</button>
                    <output for="sportsSpeed" data-ve-sports-speed-value><?= $sportsH((string)$sportsContent['speed']) ?>×</output>
                    <button type="button" class="athletics-motion-toggle" data-ve-sports-motion aria-pressed="true">Edit cards</button>
                    <small>Speed changes play here. Choose Edit cards to pause for editing.</small>
                </div>
                <?php endif; ?>
            </div>
            <div class="athletics-programs-showcase" style="background-image: url(<?= $sportsH(json_encode($sportsContent['images']['showcase'],JSON_UNESCAPED_SLASHES)) ?>)"<?= ceManageMark('data-ve-sports-background-area="showcase" data-ve-bg="sports-showcase"') ?>>
                <div class="athletics-slideshow" role="region" aria-label="Sports programs slideshow">
                    <button type="button" class="athletics-pause" aria-pressed="false" data-sports-pause>Pause slideshow</button>
                    <div class="athletics-sports-viewport">
                    <div class="athletics-sports-track" style="--sports-scroll-duration: <?= 38 / (float)$sportsContent['speed'] ?>s">
                    <div class="athletics-sports-group" data-sports-original<?= ceManageMark('data-ve-sports-grid') ?>>
                    <?php foreach ($sportsPrograms as $sport): ?>
                    <article class="athletics-sport athletics-sport--<?= $sportsH($sport['id']) ?><?= empty($sport['image']) ? ' athletics-sport--placeholder' : '' ?>"<?= ceManageMark('data-ve-sport="'.$sportsH($sport['id']).'"') ?>>
                        <img class="athletics-sport-image"<?= empty($sport['image']) ? ' hidden' : ' src="'.$sportsH($sport['image']).'"' ?> alt="" loading="lazy" decoding="async"<?= ceManageMark('data-ve-sport-image') ?>>
                        <h3 class="athletics-sport-title">
                            <?php if ($sport['type'] !== '' || $sportsPreview): ?><span<?= ceField('sport-type',['label'=>'Sport label','max'=>80,'optional'=>true,'ph'=>'Add label (optional)']) ?>><?= $sportsH($sport['type']) ?></span><?php endif; ?>
                            <strong<?= ceField('sport-name',['label'=>'Sport name','max'=>120]) ?>><?= $sportsH($sport['name']) ?></strong>
                        </h3>
                    </article>
                    <?php endforeach; ?>
                    </div>
                    </div>
                    </div>
                </div>
            </div>
        </section>
        <?php $visibleTeams=array_filter($sportsContent['teams'],static fn($team)=>!empty($team['image'])); ?>
        <?php if($visibleTeams||$sportsPreview): ?>
        <section class="athletics-teams" aria-labelledby="varsityTeamsTitle">
            <h2 id="varsityTeamsTitle"<?= $sportsField('teams_title','Varsity teams heading') ?>><?= $sportsText('teams_title') ?></h2>
            <?php if($sportsPreview): ?><button type="button" class="athletics-add-sport" data-ve-team-add><i class="bi bi-plus-lg" aria-hidden="true"></i> Add logo</button><?php endif; ?>
            <div class="athletics-teams-grid"<?= ceManageMark('data-ve-teams-grid') ?>>
                <?php foreach($sportsPreview?$sportsContent['teams']:$visibleTeams as $team): ?>
                <figure class="athletics-team"<?= ceManageMark('data-ve-team="'.$sportsH($team['id']).'"') ?>>
                    <img class="athletics-team-logo"<?= empty($team['image'])?' hidden':' src="'.$sportsH($team['image']).'"' ?> alt="<?= $sportsH($team['name']) ?>" loading="lazy" decoding="async"<?= ceManageMark('data-ve-team-image') ?>>
                    <?php if($sportsPreview): ?>
                    <button type="button" class="athletics-team-upload" data-ve-team-upload="<?= $sportsH($team['id']) ?>"<?= !empty($team['image'])?' hidden':'' ?>><i class="bi bi-image" aria-hidden="true"></i> Upload team logo</button>
                    <figcaption<?= ceField('team-name',['label'=>'Team name','max'=>120]) ?>><?= $sportsH($team['name']) ?></figcaption>
                    <?php endif; ?>
                </figure>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
        <?php $visibleFacilities=array_filter($sportsContent['facilities'],static fn($facility)=>!empty($facility['image'])); ?>
        <?php if($visibleFacilities||$sportsPreview): ?>
        <section class="athletics-facilities" aria-labelledby="sportsFacilitiesTitle">
            <h2 id="sportsFacilitiesTitle"<?= $sportsField('facilities_title','Facilities heading') ?>><?= $sportsText('facilities_title') ?></h2>
            <?php if($sportsPreview): ?><button type="button" class="athletics-add-sport" data-ve-facility-add><i class="bi bi-plus-lg" aria-hidden="true"></i> Add facility photo</button><?php endif; ?>
            <div class="athletics-facilities-carousel fcar" role="region" aria-roledescription="carousel" aria-label="Facilities">
                <div class="athletics-facilities-stage fcar-stage" tabindex="0" aria-label="Facilities photos. Use left and right arrow keys."<?= ceManageMark('data-ve-facilities-grid') ?>>
                    <?php foreach($sportsPreview?$sportsContent['facilities']:$visibleFacilities as $facility): ?>
                    <figure class="athletics-facility-slide fcar-slide"<?= ceManageMark('data-ve-facility="'.$sportsH($facility['id']).'"') ?> role="group" aria-roledescription="slide">
                        <img class="athletics-facility-photo"<?= empty($facility['image'])?' hidden':' src="'.$sportsH($facility['image']).'"' ?> alt="<?= $sportsH($facility['name']) ?>" loading="lazy" decoding="async" draggable="false"<?= ceManageMark('data-ve-facility-image') ?>>
                        <?php if($sportsPreview): ?><button type="button" class="athletics-facility-upload" data-ve-facility-upload<?= !empty($facility['image'])?' hidden':'' ?>>Upload facility photo</button><?php endif; ?>
                        <figcaption class="fcar-caption"<?= ceField('facility-name',['label'=>'Facility name','max'=>120]) ?>><?= $sportsH($facility['name']) ?></figcaption>
                    </figure>
                    <?php endforeach; ?>
                </div>
                <div class="athletics-facilities-controls">
                    <button type="button" class="fcar-arrow fcar-prev" data-facility-prev aria-label="Previous facility"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                    <div class="athletics-facilities-dots fcar-dots" aria-label="Choose facility"></div>
                    <button type="button" class="fcar-arrow fcar-next" data-facility-next aria-label="Next facility"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                </div>
            </div>
        </section>
        <?php endif; ?>
        <section class="athletics-highlights" id="competition-highlights" aria-labelledby="competitionHighlightsTitle">
            <h2 id="competitionHighlightsTitle"<?= $sportsField('highlights_title','Competition highlights heading') ?>><?= $sportsText('highlights_title') ?></h2>
            <?php if(!$sportsNews['items']): ?>
                <p class="athletics-highlights-empty">Competition highlights will appear here when Athletics and Sports news is published.</p>
            <?php else: ?>
            <div class="athletics-highlights-grid">
                <?php foreach($sportsNews['items'] as $article):
                    $articleUrl='/news-article.php?'.(!empty($article['slug'])?'slug='.rawurlencode($article['slug']):'id='.(int)$article['news_id']);
                    $articleDate=strtotime($article['post_date']??'');
                    $articleImage=$article['image_path']??'';
                    if($articleImage!==''&&!preg_match('~^(?:https?://|/)~i',$articleImage))$articleImage='/'.$articleImage;
                ?>
                <a class="athletics-highlight" href="<?= $sportsH($articleUrl) ?>">
                    <?php if($articleImage!==''): ?><img src="<?= $sportsH($articleImage) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?>
                    <?php if($articleDate!==false): ?>
                    <time class="athletics-highlight-date" datetime="<?= date('Y-m-d',$articleDate) ?>" aria-label="<?= date('F j, Y',$articleDate) ?>"><span><?= date('M',$articleDate) ?></span><strong><?= date('d',$articleDate) ?></strong><span><?= date('Y',$articleDate) ?></span></time>
                    <?php endif; ?>
                    <div class="athletics-highlight-content">
                        <span class="athletics-highlight-category">Athletics and Sports</span>
                        <h3><?= $sportsH($article['title']) ?></h3>
                        <?php if($articleDate!==false): ?><span class="athletics-highlight-meta"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= date('F j, Y',$articleDate) ?></span><?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php if($sportsNews['pages']>1): ?>
            <nav class="athletics-highlights-pagination" aria-label="Competition highlights pages">
                <?php if($sportsNews['page']>1): ?><a href="<?= $sportsH($highlightsUrl($sportsNews['page']-1)) ?>" aria-label="Previous page" rel="prev">‹</a><?php endif; ?>
                <?php for($p=1;$p<=$sportsNews['pages'];$p++): ?>
                    <?php if($sportsNews['pages']<=7||$p===1||$p===$sportsNews['pages']||abs($p-$sportsNews['page'])<=1): ?>
                        <a href="<?= $sportsH($highlightsUrl($p)) ?>"<?= $p===$sportsNews['page']?' aria-current="page"':'' ?> aria-label="Page <?= $p ?>"><?= $p ?></a>
                    <?php elseif(abs($p-$sportsNews['page'])===2): ?><span aria-hidden="true">…</span><?php endif; ?>
                <?php endfor; ?>
                <?php if($sportsNews['page']<$sportsNews['pages']): ?><a href="<?= $sportsH($highlightsUrl($sportsNews['page']+1)) ?>" aria-label="Next page" rel="next">›</a><?php endif; ?>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </section>
        <section class="athletics-facebook" aria-labelledby="sportsFacebookTitle">
            <span class="athletics-facebook-kicker"<?= $sportsField('facebook_kicker','Facebook section label') ?>><?= $sportsText('facebook_kicker') ?></span>
            <h2 id="sportsFacebookTitle"<?= $sportsField('facebook_title','Facebook section heading') ?>><?= $sportsText('facebook_title') ?></h2>
            <p<?= $sportsField('facebook_description','Facebook section introduction') ?>><?= $sportsText('facebook_description') ?></p>
            <div class="athletics-facebook-box"<?= $sportsPreview?' data-ve-sports-facebook data-ve-pick="sports-facebook" data-ve-link="'.$sportsH($sportsContent['facebook_url']).'"':'' ?>>
                <?php if($sportsFacebookEmbed!==null): ?>
                <iframe src="<?= $sportsH($sportsFacebookEmbed) ?>" width="500" height="130" scrolling="no" frameborder="0" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" allowfullscreen loading="lazy" title="GNC Sports Club Facebook page"></iframe>
                <?php endif; ?>
                <?php if($sportsPreview): ?>
                <button type="button" class="athletics-facebook-edit" data-ve-sports-facebook-edit><i class="bi bi-facebook" aria-hidden="true"></i> <?= $sportsFacebookEmbed!==null?'Change Facebook page link':'Add the Facebook page link' ?></button>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?= ceData('veSportsData',['content'=>$sportsContent,'revision'=>athleticsSportsRevision($sportsContent)]) ?>

    <?php include __DIR__ . '/../../components/footer.php'; ?>
    <?php include_once __DIR__ . '/../../chatbot/widget.php'; ?>
    <script src="/assets/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/facility-coverflow.js?v=<?= filemtime(__DIR__.'/../../assets/js/facility-coverflow.js') ?>" defer></script>
    <script src="/assets/js/athletics-sports.js?v=<?= filemtime(__DIR__.'/../../assets/js/athletics-sports.js') ?>" defer></script>
    <?php if (!$sportsPreview): ?>
    <script>
    // Scroll-reveal: classes are added here (not in the markup), so everything stays visible without JS
    // and inside the editor preview, which is excluded by the PHP check above.
    (function () {
        if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        var groups = [
            { sel: '.athletics-programs-intro > h2',        type: 'up' },
            { sel: '.athletics-programs-intro > p',         type: 'up',   base: 0.1 },
            { sel: '.athletics-programs-showcase .athletics-slideshow', type: 'fade' },
            { sel: '.athletics-teams > h2',                 type: 'up' },
            { sel: '.athletics-team',                       type: 'pop',  stagger: 0.12 },
            { sel: '.athletics-facilities > h2',            type: 'up' },
            { sel: '.athletics-facilities-carousel',        type: 'up',   base: 0.1 },
            { sel: '.athletics-highlights > h2',            type: 'up' },
            { sel: '.athletics-highlight',                  type: 'up',   stagger: 0.1, perRow: 3 },
            { sel: '.athletics-highlights-empty',           type: 'fade' },
            { sel: '.athletics-highlights-pagination',      type: 'fade' },
            { sel: '.athletics-facebook-kicker',            type: 'fade' },
            { sel: '.athletics-facebook > h2',              type: 'up',   base: 0.08 },
            { sel: '.athletics-facebook > p',               type: 'up',   base: 0.16 },
            { sel: '.athletics-facebook-box',               type: 'pop',  base: 0.24 }
        ];

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                io.unobserve(entry.target);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -5% 0px' });

        groups.forEach(function (g) {
            document.querySelectorAll(g.sel).forEach(function (el, i) {
                var step = g.perRow ? i % g.perRow : i;
                var delay = (g.base || 0) + (g.stagger ? step * g.stagger : 0);
                el.classList.add('ath-reveal');
                el.setAttribute('data-reveal', g.type);
                if (delay) el.style.setProperty('--reveal-delay', delay.toFixed(2) + 's');
                io.observe(el);
            });
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>