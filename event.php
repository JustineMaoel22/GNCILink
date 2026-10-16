<?php
require_once __DIR__.'/config/config.php';
$id = max(0, (int)($_GET['id'] ?? 0));
$stmt = getDB()->prepare("SELECT e.*,m.file_path AS image FROM events e LEFT JOIN media_library m ON m.media_id=e.media_id WHERE e.event_id=? AND e.status='published'");
$stmt->execute([$id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$event) http_response_code(404);
function eventEscape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
require_once __DIR__.'/components/recommendation-catalog.php';
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= eventEscape($event['title'] ?? 'Event not found') ?> | Guagua National Colleges</title>
<link rel="stylesheet" href="/assets/css/bootstrap.min.css"><link rel="stylesheet" href="/assets/css/base-style.css">
<link rel="stylesheet" href="/assets/css/navbar-style.css"><link rel="stylesheet" href="/assets/css/footer-style.css">
</head><body>
<?php $activeSection='home'; include __DIR__.'/components/navbar.php'; ?>
<main class="container py-5" style="max-width:900px">
    <a href="/#events">&larr; School calendar</a>
    <?php if ($event): ?>
    <article class="py-4">
        <p class="text-uppercase text-muted">School event</p>
        <h1><?= eventEscape($event['title']) ?></h1>
        <p><time datetime="<?= eventEscape($event['start_date']) ?>"><?= eventEscape(date('F j, Y · g:i a', strtotime($event['start_date']))) ?></time>
        <?php if ($event['end_date']): ?> – <?= eventEscape(date('F j, Y · g:i a', strtotime($event['end_date']))) ?><?php endif; ?></p>
        <?php if ($event['location']): ?><p><strong>Location:</strong> <?= eventEscape($event['location']) ?></p><?php endif; ?>
        <?php if ($event['image']): ?><img class="img-fluid rounded mt-3" src="<?= eventEscape(gncRecImage($event['image'])) ?>" alt="" style="max-height:500px;object-fit:contain"><?php endif; ?>
    </article>
    <?php else: ?><h1 class="mt-4">Event not found</h1><p>This event is not available.</p><?php endif; ?>
</main>
<?php include __DIR__.'/components/footer.php'; ?>
<script src="/assets/js/bootstrap.bundle.min.js"></script>
</body></html>
