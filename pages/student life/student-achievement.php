<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/student-achievements.php';
require_once __DIR__ . '/../../admin/admin-functions.php';
$achievementPreview = cePreviewCanManage();
$achievementRecords = [];
$achievementField = static fn($key, $label, $max = 255) => ceField('achievement-field', ['key'=>$key,'label'=>$label,'max'=>$max,'ph'=>'Add '.strtolower($label),'multiline'=>$key==='description','optional'=>in_array($key,['description','program_organization','category_label'],true)]);
$achievements = [];
$achievementDetail = null;
$requestedAchievement = filter_var($_GET['achievement'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$achievementListing = ['items'=>[], 'years'=>[], 'year'=>null, 'categories'=>[], 'category'=>null, 'page'=>1, 'pages'=>1, 'total'=>0];
try {
    $achievements = getDB()->query("SELECT * FROM student_achievements
        WHERE " . ($achievementPreview ? '1=1' : "status='published'") . " AND is_featured=1
        ORDER BY created_at ASC,achievement_id ASC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
    $achievementListing = studentAchievementListing(getDB(), $_GET, $achievementPreview);
    if ($achievementPreview) {
        $achievementRecords = getDB()->query('SELECT * FROM student_achievements ORDER BY created_at ASC,achievement_id ASC')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($achievementRecords as &$record) $record['photos']=studentAchievementPhotos(getDB(),(int)$record['achievement_id']);
        unset($record);
    }
    if ($requestedAchievement) {
        $achievementDetail = studentAchievementById(getDB(), $requestedAchievement, !$achievementPreview);
        if (!$achievementDetail) http_response_code(404);
    }
} catch (Throwable $e) {
    error_log('Student achievements unavailable: ' . $e->getMessage());
}
$achievementH = static fn($text) => htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
$achievementModals=[];
foreach (array_merge($achievements,$achievementListing['items'],$achievementDetail?[$achievementDetail]:[]) as $record) {
    $id=(int)$record['achievement_id'];
    if (!isset($achievementModals[$id])) { $record['photos']=studentAchievementPhotos(getDB(),$id); $achievementModals[$id]=$record; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Achievements</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/student-achievement-style.css?v=<?= substr(hash_file('sha256', __DIR__ . '/../../assets/css/student-achievement-style.css'),0,12) ?>" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>

    <?php $activeSection = 'student-life'; ?>
    <?php include_once __DIR__ . '/../../components/navbar.php'; ?>

    <main>
    <section class="achievement-intro">
        <div class="achievement-hero">
            <div class="achievement-hero-inner">
                <nav class="achievement-breadcrumb" aria-label="breadcrumb">
                    <a class="gnc-breadcrumb-home" href="/" aria-label="Home" title="Home"><svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 1 0 7h2v8h4v-5h4v5h4V7h2L8 1Z"/></svg></a>
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>Student Life</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="achievement-current" aria-current="page">Student Achievements</span>
                </nav>
                <h1>Student Achievements</h1>
                <p>Highlighting GNC students' accomplishments, talents, and excellence.</p>
            </div>
        </div>
    </section>

    <?php if ($requestedAchievement && !$achievementDetail): ?>
    <section class="achievement-detail" id="achievement-detail" aria-labelledby="achievementDetailTitle">
        <div class="achievement-showcase-inner">
            <a href="/pages/student%20life/student-achievement.php#more-achievements">← All achievements</a>
            <h2 id="achievementDetailTitle">Achievement not found</h2>
            <p>This achievement is unavailable.</p>
        </div>
    </section>
    <?php endif; ?>
    <section class="achievement-showcase" aria-labelledby="achievementHeading">
        <div class="achievement-showcase-inner">
            <h2 id="achievementHeading">Excellence Worth Celebrating.</h2>
            <p class="achievement-description">Recognizing the dedication, talent, and accomplishments of our students.</p>
            <h3 id="achievementFeatured">Featured Achievements</h3>
            <?php if ($achievements): ?>
            <div class="achievement-carousel" role="region" aria-roledescription="carousel" aria-labelledby="achievementFeatured" tabindex="0">
                <div class="achievement-slides">
                    <?php foreach ($achievements as $index => $achievement):
                        $summary = trim(preg_replace('/\s+/u', ' ', $achievement['description']));
                        if (!$achievementPreview && mb_strlen($summary) > 220) $summary = mb_substr($summary, 0, 217) . '…';
                    ?>
                    <article class="achievement-slide"<?= ceManageMark('data-ve-achievement-id="'.(int)$achievement['achievement_id'].'"') ?> role="group" aria-roledescription="slide" aria-label="<?= $index + 1 ?> of <?= count($achievements) ?>"<?= $index ? ' hidden' : '' ?>>
                        <?php $featuredPhoto=$achievement['image_path'] ?: $achievement['thumbnail_path']; if ($featuredPhoto): ?>
                        <img class="achievement-image"<?= ceManageMark('data-ve-achievement-image') ?> src="<?= $achievementH($featuredPhoto) ?>" alt="<?= $achievementH($achievement['title']) ?>"<?= $index ? ' loading="lazy"' : ' fetchpriority="high"' ?>>
                        <?php else: ?>
                        <div class="achievement-image achievement-image-empty"<?= ceManageMark('data-ve-achievement-image') ?>><i class="bi bi-trophy" aria-hidden="true"></i></div>
                        <?php endif; ?>
                        <div class="achievement-caption">
                            <span class="achievement-category"<?= $achievementField('category_label','Category',100) ?>><?= $achievementH($achievement['category_label'] ?: ($achievementPreview ? '' : 'Student Achievement')) ?></span>
                            <h4<?= $achievementField('title','Achievement title') ?>><?= $achievementH($achievement['title']) ?></h4>
                            <?php if ($achievementPreview || $achievement['program_organization']): ?><p<?= $achievementField('program_organization','Program / Organization') ?>><?= $achievementH($achievement['program_organization']) ?></p><?php endif; ?>
                            <?php if ($achievementPreview || $summary): ?><p<?= $achievementField('description','Description',12000) ?>><?= $achievementH($summary) ?></p><?php endif; ?>
                            <?php if ($achievementPreview || $achievement['academic_year']): ?><p class="achievement-academic-year">Academic Year <span<?= $achievementField('academic_year','Academic year',9) ?>><?= $achievementH($achievement['academic_year']) ?></span></p><?php endif; ?>
                        </div>
                        <a class="achievement-slide-link<?= $achievementPreview?' achievement-editor-slide-link':'' ?>" href="?achievement=<?= (int)$achievement['achievement_id'] ?>#achievement-dialog-<?= (int)$achievement['achievement_id'] ?>" data-achievement-open="<?= (int)$achievement['achievement_id'] ?>" aria-label="View achievers: <?= $achievementH($achievement['title']) ?>"></a>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php if (count($achievements) > 1): ?>
                <div class="achievement-controls">
                    <button class="achievement-arrow" type="button" data-achievement-step="-1" aria-label="Previous achievement"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                    <div class="achievement-dots">
                        <?php foreach ($achievements as $index => $achievement): ?>
                        <button type="button" class="achievement-dot" data-achievement-index="<?= $index ?>" aria-label="Show achievement <?= $index + 1 ?>: <?= $achievementH($achievement['title']) ?>" aria-pressed="<?= $index ? 'false' : 'true' ?>"></button>
                        <?php endforeach; ?>
                    </div>
                    <button class="achievement-arrow" type="button" data-achievement-step="1" aria-label="Next achievement"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                </div>
                <p class="visually-hidden" data-achievement-status aria-live="polite" aria-atomic="true"></p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="achievement-empty"><i class="bi bi-trophy" aria-hidden="true"></i><p>Student achievements will be featured here soon.</p></div>
            <?php endif; ?>
        </div>
    </section>
    <section class="achievement-more" id="more-achievements" aria-labelledby="moreAchievementsHeading">
        <div class="achievement-showcase-inner">
            <h2 id="moreAchievementsHeading">More Achievements</h2>
            <form class="achievement-filter" action="#more-achievements" method="get" data-warning-optional="true">
                <?php if ($achievementPreview): ?><input type="hidden" name="ce_preview" value="1"><?php endif; ?>
                <label class="visually-hidden" for="achievementYear">Academic Year</label>
                <select name="academic_year" id="achievementYear" class="form-select" aria-controls="achievementGrid">
                    <option value="">Academic Year</option>
                    <?php foreach ($achievementListing['years'] as $year): ?>
                    <option value="<?= $achievementH($year) ?>"<?= $achievementListing['year'] === $year ? ' selected' : '' ?>><?= $achievementH($year) ?></option>
                    <?php endforeach; ?>
                    <?php if ($achievementListing['year'] && !in_array($achievementListing['year'], $achievementListing['years'], true)): ?>
                    <option value="<?= $achievementH($achievementListing['year']) ?>" selected><?= $achievementH($achievementListing['year']) ?></option>
                    <?php endif; ?>
                </select>
                <label class="visually-hidden" for="achievementCategory">Category</label>
                <select name="category" id="achievementCategory" class="form-select" aria-controls="achievementGrid">
                    <option value="">All Categories</option>
                    <?php foreach ($achievementListing['categories'] as $category): ?>
                    <option value="<?= $achievementH($category) ?>"<?= $achievementListing['category'] === $category ? ' selected' : '' ?>><?= $achievementH($category) ?></option>
                    <?php endforeach; ?>
                    <?php if ($achievementListing['category'] && !in_array($achievementListing['category'], $achievementListing['categories'], true)): ?>
                    <option value="<?= $achievementH($achievementListing['category']) ?>" selected><?= $achievementH($achievementListing['category']) ?></option>
                    <?php endif; ?>
                </select>
                <noscript><button type="submit" class="achievement-filter-button">Filter</button></noscript>
            </form>
            <div class="achievement-grid" id="achievementGrid">
                <?php foreach ($achievementListing['items'] as $item): ?>
                <article class="achievement-card"<?= ceManageMark('data-ve-achievement-id="'.(int)$item['achievement_id'].'"') ?>>
                    <a href="?achievement=<?= (int)$item['achievement_id'] ?>#achievement-dialog-<?= (int)$item['achievement_id'] ?>" data-achievement-open="<?= (int)$item['achievement_id'] ?>" class="achievement-card-link">
                        <div class="achievement-card-cover"<?= ceManageMark('data-ve-achievement-image="thumbnail"') ?>>
                            <?php $thumbnail=$item['thumbnail_path'] ?: $item['image_path']; if ($thumbnail): ?><img src="<?= $achievementH($thumbnail) ?>" alt="" loading="lazy" decoding="async"><?php else: ?><div class="achievement-card-placeholder"><i class="bi bi-trophy" aria-hidden="true"></i></div><?php endif; ?>
                            <span class="achievement-card-category"<?= $achievementField('category_label','Category',100) ?>><?= $achievementH($item['category_label'] ?: ($achievementPreview ? '' : 'Student Achievement')) ?></span>
                        </div>
                        <h3<?= $achievementField('title','Achievement title') ?>><?= $achievementH($item['title']) ?></h3>
                    </a>
                    <p class="achievement-card-meta">
                        <?php if ($achievementPreview || $item['program_organization']): ?><span<?= $achievementField('program_organization','Program / Organization') ?>><?= $achievementH($item['program_organization']) ?></span><?php endif; ?>
                        <?php if ($achievementPreview || $item['academic_year']): ?><span class="achievement-card-year"><?= $item['program_organization'] || $achievementPreview ? ' | ' : '' ?>Academic Year <span<?= $achievementField('academic_year','Academic year',9) ?>><?= $achievementH($item['academic_year']) ?></span></span><?php endif; ?>
                    </p>
                </article>
                <?php endforeach; ?>
                <?php if ($achievementPreview): for ($slot=count($achievementListing['items']);$slot<6;$slot++): ?>
                <button class="achievement-add-placeholder" type="button" data-ve-achievement-add aria-label="Add achievement"><span class="achievement-placeholder-picture"><i class="bi bi-image" aria-hidden="true"></i></span><span class="achievement-placeholder-label"><i class="bi bi-plus-circle" aria-hidden="true"></i> Add achievement</span></button>
                <?php endfor; endif; ?>
            </div>
            <?php if (!$achievementListing['items']): ?>
            <p class="achievement-grid-empty" role="status"><?= $achievementListing['category'] ? 'No achievements match the selected filters.' : ($achievementListing['year'] ? 'No achievements published for this academic year yet.' : 'More student achievements will be shared here soon.') ?></p>
            <?php endif; ?>
            <?php if ($achievementListing['pages'] > 1): ?>
            <nav class="achievement-pagination" aria-label="Achievement pages">
                <?php if ($achievementListing['page'] > 1): ?><a href="<?= $achievementH(studentAchievementPageUrl($achievementListing['page'] - 1, $achievementListing['year'], $achievementListing['category'])) ?>" aria-label="Previous page"><i class="bi bi-chevron-left" aria-hidden="true"></i></a><?php endif; ?>
                <?php for ($number = max(1, $achievementListing['page'] - 2); $number <= min($achievementListing['pages'], $achievementListing['page'] + 2); $number++): ?>
                <a href="<?= $achievementH(studentAchievementPageUrl($number, $achievementListing['year'], $achievementListing['category'])) ?>" aria-label="Page <?= $number ?>"<?= $number === $achievementListing['page'] ? ' aria-current="page"' : '' ?>><?= $number ?></a>
                <?php endfor; ?>
                <?php if ($achievementListing['page'] < $achievementListing['pages']): ?><a href="<?= $achievementH(studentAchievementPageUrl($achievementListing['page'] + 1, $achievementListing['year'], $achievementListing['category'])) ?>" aria-label="Next page"><i class="bi bi-chevron-right" aria-hidden="true"></i></a><?php endif; ?>
            </nav>
            <?php endif; ?>
        </div>
    </section>
    </main>
    <?php foreach ($achievementModals as $id=>$record): ?>
    <dialog class="achievement-modal" id="achievement-dialog-<?= $id ?>" aria-labelledby="achievement-modal-title-<?= $id ?>"<?= $requestedAchievement===$id?' open':'' ?>>
        <div class="achievement-modal-header">
            <h2 id="achievement-modal-title-<?= $id ?>"><?= $achievementH($record['title']) ?></h2>
            <form method="dialog"><button class="achievement-modal-close" data-achievement-close aria-label="Close achievement" autofocus>×</button></form>
        </div>
        <div class="achievement-modal-body">
            <div class="visually-hidden"><p><?= $achievementH($record['description']) ?></p>
            <?php $names=array_values(array_filter(array_map('trim',preg_split('/\R/u',$record['achievers'] ?? '')))); if ($names): ?>
            <section class="achievement-modal-achievers" aria-labelledby="achievement-achievers-title-<?= $id ?>"><h3 id="achievement-achievers-title-<?= $id ?>">Achievers</h3><ul><?php foreach ($names as $name): ?><li><?= $achievementH($name) ?></li><?php endforeach; ?></ul></section>
            <?php endif; ?>
            </div>
            <?php $modalPhotos=studentAchievementModalPhotos($record); ?>
            <div class="achievement-modal-photos" role="region" aria-roledescription="carousel" aria-label="Achiever photos">
                <?php foreach ($modalPhotos as $photoIndex=>$photo): ?><figure class="achievement-modal-slide" data-achiever-slide role="group" aria-roledescription="slide" aria-label="<?= $photoIndex+1 ?> of <?= count($modalPhotos) ?>"<?= $photoIndex?' hidden':'' ?>><img src="<?= $achievementH($photo['image_path']) ?>" alt="<?= $achievementH($photo['caption'] ?: $record['title'].' — achievers') ?>" loading="lazy"><?php if ($photo['caption']): ?><figcaption class="visually-hidden"><?= $achievementH($photo['caption']) ?></figcaption><?php endif; ?></figure><?php endforeach; ?>
                <?php if (!$modalPhotos): ?><p class="achievement-modal-empty">Achiever photos will be available soon.</p><?php endif; ?>
                <?php if (count($modalPhotos)>1): ?><button class="achievement-modal-arrow is-previous" type="button" data-achiever-step="-1" aria-label="Previous achiever photo">‹</button><button class="achievement-modal-arrow is-next" type="button" data-achiever-step="1" aria-label="Next achiever photo">›</button><?php endif; ?>
            </div>
            <?php if (count($modalPhotos)>1): ?><div class="achievement-modal-dots" aria-label="Choose achiever photo"><?php foreach ($modalPhotos as $photoIndex=>$photo): ?><button type="button" class="achievement-modal-dot" data-achiever-index="<?= $photoIndex ?>" aria-label="Show achiever photo <?= $photoIndex+1 ?>" aria-pressed="<?= $photoIndex?'false':'true' ?>"></button><?php endforeach; ?></div><?php endif; ?>
            <p class="visually-hidden" data-achiever-status aria-live="polite" aria-atomic="true"></p>
        </div>
    </dialog>
    <?php endforeach; ?>
    <?= ceData('veAchievementData',['records'=>$achievementRecords,'url'=>'/pages/student%20life/student-achievement.php']) ?>

    <?php include_once __DIR__ . '/../../components/footer.php'; ?>
    <?php include_once __DIR__ . '/../../chatbot/widget.php'; ?>
    <script src="/assets/js/student-achievements.js?v=<?= substr(hash_file('sha256', __DIR__ . '/../../assets/js/student-achievements.js'),0,12) ?>" defer></script>
</body>
</html>
