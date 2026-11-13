<?php
require_once __DIR__ . '/../includes/event-time.php';
/** Alumni content uses the existing page_sections draft/publish workflow. */
require_once __DIR__ . '/../includes/responsive-backgrounds.php';
require_once __DIR__ . '/../includes/event-categories.php';

function alumniPageValidate(string $key, string $value): string {
    $limits = ['title_start'=>100, 'title_accent'=>100, 'title_continues'=>100, 'title_end'=>100, 'intro'=>2000, 'actions'=>5000, 'hero_image'=>500, 'stories'=>100000, 'voices_label'=>100, 'voices_title'=>100, 'voices_subtitle'=>100, 'events_title'=>100, 'events_subtitle'=>100, 'events_intro'=>2000, 'events_image'=>500, 'recent_events_title'=>100, 'survey_label'=>100, 'survey_title'=>100, 'survey_subtitle'=>100, 'survey_scan_label'=>100, 'survey_caption'=>100, 'survey_image'=>500];
    if (!isset($limits[$key]) || mb_strlen($value) > $limits[$key]) {
        throw new InvalidArgumentException('Unknown alumni field or content exceeds its length limit.');
    }
    if (in_array($key, ['hero_image', 'events_image', 'survey_image'], true)) {
        $value = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value !== '' && !responsiveBackgroundPath($value)) {
            throw new InvalidArgumentException('Choose an existing image from /assets/images/ or /uploads/.');
        }
    }
    return $value;
}

/** Published upcoming/ongoing Alumni events only; never exposes drafts or pending entries. */
function alumniUpcomingEvents(PDO $db, string $today): array {
    $query = $db->prepare("SELECT " . eventAllDaySql($db) . ", e.event_id,e.title,e.location,e.start_date,e.end_date
        FROM events e JOIN " . eventCategoryTable($db) . " c ON c.category_id=e.category_id
        WHERE c.category_name=? AND e.status='published'
          AND COALESCE(e.end_date,e.start_date)>=?
        ORDER BY e.start_date ASC,e.event_id ASC LIMIT 3");
    $query->execute(['Alumni', $today . ' 00:00:00']);
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

function alumniVoicesRead(bool $lock = false): array {
    $query = getDB()->prepare("SELECT ps.content FROM page_sections ps JOIN pages p ON p.page_id=ps.page_id WHERE p.slug='alumni' AND ps.section_key='voices_data'" . ($lock ? ' FOR UPDATE' : ''));
    $query->execute();
    $raw = $query->fetchColumn();
    if ($raw === false) return ['items'=>[], 'revision'=>'', 'ready'=>false];
    return ['items'=>json_decode($raw, true, 16, JSON_THROW_ON_ERROR), 'revision'=>hash('sha256', $raw), 'ready'=>true];
}

function alumniVoicesValidate(array $items): array {
    if (!array_is_list($items) || count($items)>100) throw new InvalidArgumentException('Use up to 100 alumni voices.');
    $seen=[]; $out=[];
    foreach ($items as $item) {
        if (!is_array($item) || !is_string($item['id']??null) || !preg_match('/\A[a-z0-9-]{1,80}\z/D', $item['id']) || isset($seen[$item['id']])) throw new InvalidArgumentException('Invalid or duplicate alumni voice.');
        $seen[$item['id']]=true; $row=['id'=>$item['id']];
        foreach (['name'=>150,'quote'=>2000,'batch'=>100,'description'=>300,'image'=>500,'alt'=>300] as $key=>$limit) {
            $value=$item[$key]??'';
            if (!is_string($value) || mb_strlen($value)>$limit) throw new InvalidArgumentException('Check the alumni voice field lengths.');
            $row[$key]=trim($value);
        }
        if ($row['name']==='' || $row['quote']==='') throw new InvalidArgumentException('Each alumni voice needs a name and quote.');
        if ($row['image']!=='' && !responsiveBackgroundPath($row['image'])) throw new InvalidArgumentException('Choose an image stored on this website.');
        $out[]=$row;
    }
    return $out;
}

function alumniPageContent(bool $preview): array {
    $content = [];
    foreach (getPageSections('alumni') as $section) {
        if (!$preview && $section['status'] !== 'published') continue;
        $content[$section['section_key']] = (string)($preview && $section['draft_content'] !== null ? $section['draft_content'] : $section['content']);
    }
    return $content;
}
