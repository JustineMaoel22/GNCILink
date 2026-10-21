<?php
// Upcoming events (next 3 published, from today onward, any month;
// filtered to the selected department/program and optional category)
try {
    $upcomingCategorySql = isset($calendarCategory) ? ' AND c.category_name = ?' : '';
    $stmt=($db ?? getDB())->prepare("SELECT e.event_id,e.title,e.location,e.start_date,e.end_date,e.program,c.category_name,c.category_color
        FROM events e LEFT JOIN categories c ON e.category_id=c.category_id
        WHERE e.status='published' AND e.start_date >= ? {$calendarFilterSql} {$upcomingCategorySql}
        ORDER BY e.start_date ASC LIMIT 3");
    $upcomingParams = array_merge([date('Y-m-d 00:00:00')], $calendarFilters['params'] ?? []);
    if (isset($calendarCategory)) $upcomingParams[] = $calendarCategory;
    $stmt->execute($upcomingParams);
    $upcomingEvents = $stmt->fetchAll();
} catch (Exception $e) {
    $upcomingEvents = [];
}

