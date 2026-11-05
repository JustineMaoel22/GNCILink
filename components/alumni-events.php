<?php
$eventsReady = isset($alumni['events_title']);
$alumniEvents = alumniUpcomingEvents(getDB(), (new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))->format('Y-m-d'));
$eventsImage = responsiveBackgroundPath(trim(strip_tags($alumni['events_image'] ?? '')));
if ($eventsReady):
?>
<section class="alumni-events" id="alumni-events" aria-labelledby="alumni-events-title">
    <div class="alumni-events-feature" id="alumni-events-feature"<?= $eventsImage ? ' data-bg="' . $escape($eventsImage) . '" style="background-image:url(&quot;' . $escape($eventsImage) . '&quot;)"' : '' ?>>
        <?php if ($preview): ?><button type="button" class="alumni-background-edit" data-ve-background-for="alumni-events-feature"><i class="bi bi-image" aria-hidden="true"></i> Change background</button><?php endif; ?>
        <div class="alumni-events-intro">
            <h2 id="alumni-events-title"><span<?= $mark('events_title', 'Alumni events heading') ?>><?= $plain('events_title') ?></span><em<?= $mark('events_subtitle', 'Alumni events second line') ?>><?= $plain('events_subtitle') ?></em></h2>
            <p<?= $mark('events_intro', 'Alumni events introduction') ?>><?= $plain('events_intro') ?></p>
        </div>
    </div>
    <div class="alumni-events-list" aria-labelledby="alumni-upcoming-title">
        <h3 class="alumni-events-list-heading" id="alumni-upcoming-title"><i class="bi bi-calendar-event-fill" aria-hidden="true"></i> Upcoming Events</h3>
        <?php if (!$alumniEvents): ?>
            <p class="alumni-events-empty">No upcoming alumni events at this time. Check back for the next gathering.</p>
        <?php endif; ?>
        <?php foreach ($alumniEvents as $event):
            $start = new DateTimeImmutable($event['start_date'], new DateTimeZone('Asia/Manila'));
            $end = !empty($event['end_date']) ? new DateTimeImmutable($event['end_date'], new DateTimeZone('Asia/Manila')) : null;
            $allDay = $start->format('H:i:s') === '00:00:00' && (!$end || $end->format('H:i:s') === '00:00:00');
            $time = $allDay ? 'All Day' : $start->format('g:i A') . ($end && $end->format('H:i:s') !== '00:00:00' ? ' – ' . $end->format('g:i A') : '');
            $dateLabel = $start->format('M j, Y') . ($end && $end->format('Y-m-d') !== $start->format('Y-m-d') ? ' – ' . $end->format('M j, Y') : '');
        ?>
        <article class="alumni-event"<?= ceManageMark('data-ve-post="event" data-ve-id="' . (int)$event['event_id'] . '" data-ve-date="' . $start->format('Y-m') . '" data-ve-label="' . ceAttr($event['title']) . '"') ?>>
            <time class="alumni-event-date" datetime="<?= $escape($start->format('Y-m-d')) ?>" aria-label="<?= $escape($dateLabel) ?>"><strong><?= $start->format('j') ?></strong><span><?= strtoupper($start->format('M')) ?></span></time>
            <div class="alumni-event-info">
                <h4><?= $escape($event['title']) ?></h4>
                <p class="alumni-event-range"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= $escape($dateLabel) ?></p>
                <p><i class="bi bi-clock" aria-hidden="true"></i> <?= $escape($time) ?></p>
                <p><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= $escape(trim($event['location'] ?? '') ?: 'Location to be announced') ?></p>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
