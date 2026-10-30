<?php
function publicEventCategoryColor(?string $color, ?string $name = null): string {
    if($color && preg_match('/^#[0-9a-f]{6}$/iD',$color))return $color;
    if($color && preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/iD',$color,$m))return '#'.$m[1].$m[1].$m[2].$m[2].$m[3].$m[3];
    $name=strtolower($name??'');
    if(str_contains($name,'holiday'))return '#EABA3B';
    if(str_contains($name,'emergency')||str_contains($name,'suspension'))return '#B3312D';
    if(str_contains($name,'school'))return '#1F5E2C';
    return '#6c757d';
}

/** Read only published events that overlap a whole calendar day, including multi-day activities. */
function publicEventsForDate(PDO $db,string $date,array $filters,?string $category=null): array {
    $query=$db->prepare("SELECT e.*,c.category_name,c.category_color FROM events e LEFT JOIN categories c ON c.category_id=e.category_id
        WHERE e.status='published' AND e.start_date<=? AND COALESCE(e.end_date,e.start_date)>=? ". $filters['condition'] . ($category!==null?' AND c.category_name=?':'').' ORDER BY e.start_date,e.title,e.event_id');
    $params=array_merge([$date.' 23:59:59',$date.' 00:00:00'],$filters['params']);
    if($category!==null)$params[]=$category;
    $query->execute($params);$events=[];
    foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row)$events[]=[
        'id'=>(int)$row['event_id'],'title'=>$row['title'],'start_date'=>$row['start_date'],'end_date'=>$row['end_date'],
        'category'=>$row['category_name']?:'Uncategorized','color'=>publicEventCategoryColor($row['category_color'],$row['category_name']),
        'location'=>$row['location']??'','description'=>$row['description']??'',
    ];
    return $events;
}
