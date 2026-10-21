<div class="gnc-evs gnc-reveal">
    <div class="gnc-evs-head">
        <span class="gnc-evs-title"><i class="bi bi-calendar-event-fill"></i> <?= isset($calendarUpcomingHeading) ? htmlspecialchars($calendarUpcomingHeading) : homeTextHtml('events_label') ?></span>
        <?php if ($calendarFilterLabel !== null): ?>
            <a href="<?= htmlspecialchars($calendarFilterUrl([])) ?>" class="gnc-programs-count text-decoration-none">
                <?= htmlspecialchars($calendarFilterLabel) ?> &times;
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($upcomingEvents)): ?>
        <p class="gnc-evs-empty">
            <?php if ($calendarFilterLabel !== null): ?>
                No upcoming events for <?= htmlspecialchars($calendarFilterLabel) ?> at this time.
            <?php else: ?>
                No upcoming events at this time.
            <?php endif; ?>
        </p>
    <?php else: ?>
    <div class="gnc-evs-grid">
        <?php foreach ($upcomingEvents as $ev):
            $evStartTs = strtotime($ev['start_date']);
            $evEndTs   = !empty($ev['end_date']) ? strtotime($ev['end_date']) : null;
            $stLabel   = evt_timeLabel($ev['start_date']);
            $enLabel   = evt_timeLabel($ev['end_date'] ?? null);
            $multiDay  = $evEndTs !== null && date('Y-m-d', $evEndTs) !== date('Y-m-d', $evStartTs);

            $dateLabel = date('M j, Y', $evStartTs);
            if ($multiDay) $dateLabel .= ' - ' . date('M j, Y', $evEndTs);
            $timeLabel = $stLabel !== ''
                ? $stLabel . ($enLabel !== '' ? ' - ' . $enLabel : '')
                : ($enLabel !== '' ? 'Until ' . $enLabel : 'All Day');
        ?>
        <div class="gnc-evs-item" style="--evt-color: <?= evt_categoryColor($ev['category_color'] ?? null, $ev['category_name'] ?? null) ?>;"<?= ceManageMark('data-ve-post="event" data-ve-id="' . (int)($ev['event_id'] ?? 0) . '" data-ve-date="' . htmlspecialchars(date('Y-m', $evStartTs), ENT_QUOTES) . '" data-ve-label="' . htmlspecialchars((string)($ev['title'] ?? 'Event'), ENT_QUOTES) . '"') ?>>
            <div class="gnc-datebox">
                <span class="gnc-datebox-day"><?= date('j', $evStartTs) ?></span>
                <span class="gnc-datebox-mon"><?= strtoupper(date('M', $evStartTs)) ?></span>
            </div>
            <div class="gnc-evs-info">
                <div class="gnc-evs-name"><?= htmlspecialchars($ev['title']) ?></div>
                <div class="gnc-evs-meta"><i class="bi bi-calendar3" aria-hidden="true"></i><span class="visually-hidden">Date: </span> <?= htmlspecialchars($dateLabel) ?></div>
                <div class="gnc-evs-meta"><i class="bi bi-clock" aria-hidden="true"></i><span class="visually-hidden">Time: </span> <?= htmlspecialchars($timeLabel) ?></div>
                <div class="gnc-evs-meta"><i class="bi bi-geo-alt" aria-hidden="true"></i><span class="visually-hidden">Location: </span> <?= htmlspecialchars(!empty($ev['location']) ? $ev['location'] : 'Location not specified') ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
