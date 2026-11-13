<?php
/** Compatible projection for deployments predating the optional all-day flag. */
function eventAllDaySql(PDO $db, string $alias = 'e'): string {
    if (!preg_match('/^[a-z_]+$/iD', $alias)) throw new InvalidArgumentException('Invalid event alias.');
    try { $ready = (bool)$db->query("SHOW COLUMNS FROM events LIKE 'is_all_day'")->fetch(); }
    catch (PDOException $e) { $ready = false; }
    return ($ready ? $alias . '.is_all_day' : 'NULL') . ' AS is_all_day';
}

function eventTimeLabel(?string $date, $allDay = null): string {
    if (!$date || ($allDay !== null && (int)$allDay === 1)) return '';
    $timestamp = strtotime($date);
    if ($timestamp === false || ($allDay === null && date('H:i:s', $timestamp) === '00:00:00')) return '';
    return date('g:i A', $timestamp);
}

function eventTimeRange(array $event): string {
    $from = eventTimeLabel($event['start_date'] ?? null, $event['is_all_day'] ?? null);
    $to = eventTimeLabel($event['end_date'] ?? null, $event['is_all_day'] ?? null);
    return $from !== '' ? $from . ($to !== '' ? ' - ' . $to : '') : ($to !== '' ? 'Until ' . $to : 'All Day');
}
