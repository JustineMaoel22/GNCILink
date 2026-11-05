<?php
/** Additive, rerunnable registration; existing alumni content and drafts are preserved. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
$db = getDB();
$db->beginTransaction();
try {
    $query = $db->prepare('SELECT page_id FROM pages WHERE slug = ?');
    $query->execute(['alumni']);
    $id = $query->fetchColumn();
    if (!$id) {
        $db->prepare("INSERT INTO pages (title,slug,description,page_type,display_order,status) VALUES ('Alumni','alumni','Alumni community and stories','sections',20,'active')")->execute();
        $id = $db->lastInsertId();
    }
    $fields = [
        ['title_start', 'Heading opening', 'Your'],
        ['title_accent', 'Heading gold word', 'story'],
        ['title_continues', 'Second line gold word', 'continues'],
        ['title_end', 'Heading ending', 'here.'],
        ['intro', 'Alumni introduction', 'Wherever life takes you, GNCI remains a place to reconnect, contribute, and keep growing together.'],
        ['actions', 'Buttons (edit text and destination links)', '<a href="/pages/contact-us.php">Connect with Our Alumni Community</a> <a href="/pages/news-and-announcement.php">Explore Stories</a>'],
        ['hero_image', 'Default hero image path (or use Background controls for each device)', '/assets/images/gnc-church.jpg'],
        ['stories', 'Alumni stories (optional; hidden until content is published)', ''],
        ['voices_label', 'Alumni voices section label', 'ALUMNI VOICES'],
        ['voices_title', 'Alumni voices heading', 'Remarkable Paths.'],
        ['voices_subtitle', 'Alumni voices heading second line', 'Shared Beginnings.'],
        ['voices_data', 'Alumni voices collection', '[]'],
        ['events_title', 'Alumni events heading', 'What’s'],
        ['events_subtitle', 'Alumni events heading second line', 'happening?'],
        ['events_intro', 'Alumni events introduction', 'From class reunions to conversations with leaders, there is always a reason to come back.'],
        ['events_image', 'Default alumni events image (or use Background controls)', '/assets/images/gnc-church.jpg'],
        ['recent_events_title', 'Recent alumni events heading', 'Recent Events'],
        ['survey_label', 'Survey banner label', 'THE DOOR IS ALWAYS OPEN'],
        ['survey_title', 'Survey banner heading', 'Stay part of'],
        ['survey_subtitle', 'Survey banner second line', 'the story.'],
        ['survey_scan_label', 'Survey QR heading', 'SCAN ME'],
        ['survey_caption', 'Survey QR caption', 'ALUMNI SURVEY'],
        ['survey_image', 'Default survey banner image (or use Background controls)', '/assets/images/gnc-church.jpg'],
    ];
    $check = $db->prepare('SELECT 1 FROM page_sections WHERE page_id = ? AND section_key = ?');
    $insert = $db->prepare("INSERT INTO page_sections (page_id,section_key,title,content,status,display_order) VALUES (?,?,?,?,'published',?)");
    foreach ($fields as $order => [$key, $title, $content]) {
        $check->execute([$id, $key]);
        if (!$check->fetchColumn()) $insert->execute([$id, $key, $title, $content, $order + 1]);
    }
    $category = $db->prepare('SELECT category_id FROM categories WHERE category_name=? LIMIT 1');
    $category->execute(['Alumni']);
    if (!$category->fetchColumn()) {
        $db->prepare('INSERT INTO categories (category_name,category_color,description) VALUES (?,?,?)')
            ->execute(['Alumni', '#226437', 'Alumni reunions, gatherings, and community events']);
    }
    $db->commit();
    echo "Alumni page registered. Existing content and drafts preserved.\n";
} catch (Throwable $error) { $db->rollBack(); throw $error; }
