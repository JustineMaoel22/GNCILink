<?php
$calendarMonthUrl = static fn($month, $year) => '?' . http_build_query(array_merge($calendarNavigationParams ?? [], [
    'evt_month'=>$month, 'evt_year'=>$year,
])) . '#events';
?>
<div class="gnc-cal-card">
    <div class="gnc-cal-header">
        <a href="<?= htmlspecialchars($calendarMonthUrl($evtPrevMonth, $evtPrevYear)) ?>" class="gnc-cal-nav" aria-label="Previous month">
            <i class="bi bi-chevron-left"></i>
        </a>
        <span class="gnc-cal-title"><?= htmlspecialchars(date('F', $evtFirstOfMonth)) ?> <span><?= htmlspecialchars(date('Y', $evtFirstOfMonth)) ?></span></span>
        <?php if ($evtNextYear <= 2050): ?>
        <a href="<?= htmlspecialchars($calendarMonthUrl($evtNextMonth, $evtNextYear)) ?>" class="gnc-cal-nav" aria-label="Next month">
            <i class="bi bi-chevron-right"></i>
        </a>
        <?php else: ?>
        <span class="gnc-cal-nav" aria-disabled="true" aria-label="Maximum calendar year 2050 reached" title="The calendar ends in December 2050"><i class="bi bi-chevron-right" aria-hidden="true"></i></span>
        <?php endif; ?>
    </div>

    <div class="gnc-cal-grid">
        <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow): ?>
            <div class="gnc-cal-dow"><?= $dow ?></div>
        <?php endforeach; ?>

        <?php for ($i = 0; $i < $evtStartWeekday; $i++): ?>
            <div class="gnc-cal-cell gnc-cal-empty"></div>
        <?php endfor; ?>

        <?php for ($day = 1; $day <= $evtDaysInMonth; $day++):
            $isToday = ($day == (int)date('j') && $evtMonth == (int)date('n') && $evtYear == (int)date('Y'));
            $dayEvents = $publicEventsByDay[$day] ?? [];
            $dayHasEvent = !empty($dayEvents);
            $dayTitles = $dayHasEvent ? implode("\n", array_map(function ($e) {
                $t = evt_timeLabel($e['start_date']);
                return $e['title'] . ($t !== '' ? ' (' . $t . ')' : '');
            }, $dayEvents)) : '';
            $visibleEvents = array_slice($dayEvents, 0, 2);
            $extraCount = count($dayEvents) - count($visibleEvents);
        ?>
            <div class="gnc-cal-cell<?= $isToday ? ' gnc-cal-today' : '' ?><?= $dayHasEvent ? ' gnc-cal-hasevent' : '' ?>"
                 <?= $dayHasEvent ? 'title="' . htmlspecialchars($dayTitles) . '"' : '' ?>>
                <span class="gnc-cal-daynum"><?= $day ?></span>
                <?php if ($dayHasEvent): ?>
                    <div class="gnc-cal-events">
                        <?php foreach ($visibleEvents as $e):
                            $c = evt_categoryColor($e['category_color'] ?? null, $e['category_name'] ?? null);
                        ?>
                            <span class="gnc-cal-event-chip" style="background:<?= $c ?>22; color:<?= $c ?>;">
                                <?= htmlspecialchars($e['title']) ?>
                            </span>
                        <?php endforeach; ?>
                        <?php if ($extraCount > 0): ?>
                            <span class="gnc-cal-event-more">+<?= $extraCount ?> more</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>

    <div class="gnc-cal-legend">
        <?php if (!isset($calendarCategory)): ?>
        <span><span class="gnc-cal-dot" style="background:#EABA3B;"></span> Holiday</span>
        <?php endif; ?>
        <span><span class="gnc-cal-dot" style="background:#1F5E2C;"></span> School Event</span>
        <?php if (!isset($calendarCategory)): ?>
        <span><span class="gnc-cal-dot" style="background:#B3312D;"></span> Emergency</span>
        <?php endif; ?>
    </div>
</div>
