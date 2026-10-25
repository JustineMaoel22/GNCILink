<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
function categoryCheck(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function categoryPage(string $query = ''): DOMXPath {
    $curl = curl_init('http://localhost/pages/news-and-announcement.php' . ($query ? '?' . $query : ''));
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]);
    $html = curl_exec($curl); $status = curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    categoryCheck($status===200 && is_string($html), 'Public page loads');
    $dom = new DOMDocument(); libxml_use_internal_errors(true); $dom->loadHTML($html); libxml_clear_errors();
    return new DOMXPath($dom);
}
$db=getDB(); $page=categoryPage(); $checked=0;
categoryCheck($page->query('//*[@id="na-category-event"]')->length===0,'Events category group removed');
categoryCheck($page->query('//a[contains(@class,"na-tab") and contains(@href,"type=event")]')->length===0,'Events tab removed');
categoryCheck($page->query('//div[contains(concat(" ",normalize-space(@class)," ")," na-events-card ")]')->length===0,'Upcoming events removed from this page');
foreach (['announcement','news'] as $type) {
    $links=$page->query('//div[@aria-labelledby="na-category-'.$type.'"]/a');
    categoryCheck($links->length>0, $type . ' has a separate group');
    $colors=[];
    foreach ($links as $link) {
        $colors[]=$page->evaluate('string(.//span[contains(@class,"gnc-cat-dot")]/@style)',$link);
        parse_str(parse_url(html_entity_decode($link->getAttribute('href')),PHP_URL_QUERY),$query);
        categoryCheck($query['type']===$type && isset($query['category']), 'Category link selects its content type');
        $label=trim($page->evaluate('string(.//span[contains(@class,"gnc-cat-name")])',$link));
        $count=(int)$page->evaluate('string(.//span[contains(@class,"gnc-cat-count")])',$link);
        if ($type==='news') {
            categoryCheck(!in_array($label,['Updates','Job Opportunities','Scholarship','Student Life'],true),'Removed news types do not appear');
            $stmt=$db->prepare("SELECT COUNT(*) FROM news WHERE status='published' AND category=?");
            $stmt->execute([$query['category']]); $expected=(int)$stmt->fetchColumn();
        } else {
            $stmt=$db->prepare("SELECT (SELECT COUNT(*) FROM announcements WHERE status='published' AND category_id=?) + (SELECT COUNT(*) FROM fb_posts_cache WHERE category_id=?)");
            $stmt->execute([$query['category'],$query['category']]); $expected=(int)$stmt->fetchColumn();
        }
        categoryCheck($count===$expected, $type . ': ' . $label . ' count matches database');
        $destination=categoryPage(http_build_query($query));
        categoryCheck($destination->query('//div[@aria-labelledby="na-category-'.$type.'"]/a[contains(@class,"gnc-cat-item-active")]')->length===1,'Active category shown in its group');
        $cards=$destination->query('//div[contains(concat(" ",normalize-space(@class)," ")," na-card-body ")]');
        categoryCheck($cards->length===min(12,$expected),'Filtered category shows the correct number of published cards');
        foreach ($cards as $card) {
            $badge=trim($destination->evaluate('string(.//span[contains(@class,"na-badge")])',$card));
            categoryCheck(in_array($badge,$type==='announcement'?['Announcement','From Facebook']:[$type==='news'?'News':'Event'],true),'Filtered cards match the selected content type');
        }
        if ($type==='news') categoryCheck($destination->evaluate('string(//input[@type="hidden" and @name="category"]/@value)')===$query['category'],'Search/sort preserve string news type');
        $checked++;
    }
    categoryCheck(count(array_unique($colors))===count($colors),'Each '.$type.' category has its own color');
}
foreach ($page->query('//div[contains(concat(" ",normalize-space(@class)," ")," na-card-body ")]//span[contains(@class,"na-badge")]') as $badge) categoryCheck(trim($badge->textContent)!=='Event','Events removed from All feed');
categoryCheck($page->query('//span[contains(@class,"gnc-cat-name") and normalize-space(.)="Student Organization"]')->length===1,'Student Organization label is used');
$empty=categoryPage('type=news&category=Academic&q=gnci-no-results-59a7');
categoryCheck($empty->query('//div[contains(@class,"na-empty")]')->length===1,'Search/category empty state renders');
echo "PASS: $checked grouped category links, database counts, published-only results, type isolation, active states, renamed/excluded types, search/sort category preservation and empty state. No content changed.\n";
