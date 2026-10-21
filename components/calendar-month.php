<?php
// The caller supplies an optional category and department/program scope.
$calendarFilterSql = $calendarFilterSql ?? "";
$evtMonth = isset($_GET['evt_month']) && is_scalar($_GET['evt_month']) ? max(1, min(12, (int)$_GET['evt_month'])) : (int)date('n');
$evtYear  = min(2050, isset($_GET['evt_year']) && is_scalar($_GET['evt_year']) ? (int)$_GET['evt_year'] : (int)date('Y'));

$evtFirstOfMonth = mktime(0, 0, 0, $evtMonth, 1, $evtYear);
$evtDaysInMonth  = (int)date('t', $evtFirstOfMonth);
$evtStartWeekday = (int)date('w', $evtFirstOfMonth); // 0 = Sunday
$evtMonthLabel   = date('F Y', $evtFirstOfMonth);

$evtPrevMonth = $evtMonth - 1; $evtPrevYear = $evtYear;
if ($evtPrevMonth < 1) { $evtPrevMonth = 12; $evtPrevYear--; }
$evtNextMonth = $evtMonth + 1; $evtNextYear = $evtYear;
if ($evtNextMonth > 12) { $evtNextMonth = 1; $evtNextYear++; }

// ---------------------------------------------------------------
// Category colors
// Holiday = yellow, School Event = green, Emergency = red
// ---------------------------------------------------------------
function evt_categoryColor(?string $categoryColor, ?string $categoryName = null): string {
    if (!empty($categoryColor)) return $categoryColor;
    $name = strtolower(trim($categoryName ?? ''));
    if (strpos($name, 'holiday') !== false)   return '#EABA3B';
    if (strpos($name, 'emergency') !== false || strpos($name, 'suspension') !== false) return '#B3312D';
    if (strpos($name, 'school') !== false)    return '#1F5E2C';
    return '#6c757d';
}

$evtRangeStart = sprintf('%04d-%02d-01 00:00:00', $evtYear, $evtMonth);
$evtRangeEnd   = date('Y-m-d 23:59:59', mktime(0, 0, 0, $evtMonth, $evtDaysInMonth, $evtYear));

try {
    // Overlap condition: include any event that touches this month at all,
    // not just ones that *start* in it — so multi-day events that started
    // last month but run into this one still show up.
    $evtCategorySql = isset($calendarCategory) ? ' AND c.category_name = ?' : '';
    $stmt = ($db ?? getDB())->prepare("
        SELECT e.event_id, e.title, e.start_date, e.end_date, e.status, c.category_name, c.category_color
        FROM events e
        LEFT JOIN categories c ON e.category_id = c.category_id
        WHERE e.status = 'published'
          AND e.start_date <= ?
          AND COALESCE(e.end_date, e.start_date) >= ?
          {$calendarFilterSql}
          {$evtCategorySql}
        ORDER BY e.start_date ASC
    ");
    $evtParams = array_merge([$evtRangeEnd, $evtRangeStart], $calendarFilters['params'] ?? []);
    if (isset($calendarCategory)) $evtParams[] = $calendarCategory;
    $stmt->execute($evtParams);
    $publicMonthEvents = $stmt->fetchAll();
} catch (Exception $e) {
    $publicMonthEvents = [];
}

// Expand each event across every day it spans (clamped to this month),
// so a "Jul 18 - Jul 20" event gets a chip on the 18th, 19th, and 20th —
// matching how the admin calendar renders multi-day events.
$publicEventsByDay = [];
$evtMonthStartObj = new DateTime(date('Y-m-d', strtotime($evtRangeStart)));
$evtMonthEndObj   = new DateTime(date('Y-m-d', strtotime($evtRangeEnd)));

foreach ($publicMonthEvents as $ev) {
    $evStartObj = new DateTime(date('Y-m-d', strtotime($ev['start_date'])));
    $evEndObj   = new DateTime(date('Y-m-d', strtotime($ev['end_date'] ?: $ev['start_date'])));
    if ($evEndObj < $evStartObj) $evEndObj = clone $evStartObj; // guard against bad data

    // Clamp the event's span to the days that actually fall within this calendar month
    $cursor  = $evStartObj > $evtMonthStartObj ? clone $evStartObj : clone $evtMonthStartObj;
    $lastDay = $evEndObj   < $evtMonthEndObj   ? clone $evEndObj   : clone $evtMonthEndObj;

    while ($cursor <= $lastDay) {
        $day = (int)$cursor->format('j');
        $publicEventsByDay[$day][] = $ev;
        $cursor->modify('+1 day');
    }
}

// ---------------------------------------------------------------
// Shared optional event time label
// ---------------------------------------------------------------
// Time is optional on events; 00:00:00 means "no time set".
function evt_timeLabel($dt) {
    if (empty($dt)) return '';
    $ts = strtotime($dt);
    return date('H:i:s', $ts) !== '00:00:00' ? date('g:i A', $ts) : '';
}

