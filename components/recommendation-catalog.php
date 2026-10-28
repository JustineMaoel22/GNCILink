<?php
/** Public catalog only. No visitor identifiers or browsing history enter the database. */
function gncRecText(?string $value, int $max = 180): string {
    return mb_substr(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value ?? ''), ENT_QUOTES, 'UTF-8'))), 0, $max);
}
function gncRecImage(?string $path): string {
    $path = '/' . ltrim($path ?? '', '/');
    return preg_match('#^/(assets|uploads)/[a-zA-Z0-9/_ .%()-]+\.(png|jpe?g|webp|gif|svg)$#i', $path)
        ? $path : '/assets/images/logos/gnc-logo-v1.svg';
}
function gncRecCatalog(PDO $db): array {
    $items = [];
    $add = function (string $id, string $url, string $title, string $category, ?string $description, ?string $image, array $tags = [], ?string $date = null, array $aliases = []) use (&$items) {
        $items[$id] = ['id'=>$id, 'url'=>$url, 'title'=>gncRecText($title, 180), 'category'=>$category,
            'description'=>gncRecText($description), 'image'=>gncRecImage($image),
            'tags'=>array_values(array_filter($tags)), 'date'=>$date ? strtotime($date) * 1000 : 0,
            'aliases'=>array_values(array_unique(array_merge([$url], $aliases))), 'popular'=>0];
    };
    $programs = $db->query("SELECT p.*, m.file_path AS image FROM programs p LEFT JOIN departments d ON d.dept_id=p.dept_id
        LEFT JOIN media_library m ON m.media_id=COALESCE(p.hero_media_id,p.image_media_id)
        WHERE p.is_published=1 AND (d.dept_id IS NULL OR d.archived_at IS NULL) ORDER BY p.program_id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
    $programDepartments = [];
    foreach ($programs as $p) {
        if (!$p['slug']) continue;
        $code = strtolower($p['program_code']);
        $programDepartments[$code] = $p['dept_id'];
        $aliases = [];
        $aliases[] = '/pages/academics/college/program.php?slug=' . rawurlencode($p['slug']);
        foreach (['college','graduate-school'] as $level) {
            $legacy = '/pages/academics/' . $level . '/programs/' . $p['slug'] . '.php';
            if (is_file(__DIR__ . '/..' . $legacy)) $aliases[] = $legacy;
        }
        $add('program:'.$p['program_id'], '/pages/academics/college/program/'.rawurlencode($p['slug']),
            $p['program_name'] ?: $p['program_code'], 'Programs', $p['description'], $p['image'],
            ['academics','program:'.$code, $p['dept_id'] ? 'dept:'.$p['dept_id'] : ''], null, $aliases);
    }
    foreach ($db->query("SELECT d.*,m.file_path AS image FROM departments d LEFT JOIN media_library m ON m.media_id=d.media_id WHERE d.archived_at IS NULL ORDER BY d.dept_id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC) as $d) {
        if (!$d['slug']) continue;
        $legacy = '/pages/academics/basic-edu/basic-edu-department/'.$d['slug'].'.php';
        $url = $d['department_type'] === 'basic-education' && is_file(__DIR__.'/..'.$legacy)
            ? $legacy : '/pages/academics/college/college-department.php?slug='.rawurlencode($d['slug']);
        $aliases = ['/pages/academics/college/departments/'.$d['slug'].'.php'];
        if ($d['slug'] === 'graduate-school') $aliases[] = '/pages/academics/graduate-school/graduate-school.php';
        $add('department:'.$d['dept_id'], $url, $d['dept_name'], 'Departments', $d['about_text'] ?: $d['description'], $d['image'], ['academics','dept:'.$d['dept_id']], null, $aliases);
    }
    foreach (['news'=>'news_id','announcements'=>'announcement_id'] as $table=>$pk) {
        $route = $table === 'news' ? '/news-article.php' : '/announcement-article.php';
        $add('feed:'.$table, $table === 'news' ? '/news.php' : '/announcement.php',
            $table === 'news' ? 'School News' : 'School Announcements', ucfirst($table), 'Browse the latest updates from GNC.', null, ['updates']);
        $rows = $db->query("SELECT a.*, m.file_path AS image FROM $table a LEFT JOIN media_library m ON m.media_id=a.featured_image
            WHERE a.status='published' AND (a.published_at IS NULL OR a.published_at<=NOW()) ORDER BY COALESCE(a.published_at,a.created_at) DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $a) {
            $tags = ['updates', 'category:'.($a['category_id'] ?? '')];
            $code = strtolower($a['program'] ?? 'all');
            if ($code !== 'all') { $tags[]='program:'.$code; if (!empty($programDepartments[$code])) $tags[]='dept:'.$programDepartments[$code]; }
            if (!empty($a['dept_id'])) $tags[]='dept:'.$a['dept_id'];
            $add($table.':'.$a[$pk], $route.'?id='.$a[$pk], $a['title'], ucfirst($table), $a['content'], $a['image'], $tags,
                $a['published_at'] ?: $a['created_at'], [$route.'?slug='.rawurlencode($a['slug'])]);
        }
    }
    foreach ($db->query("SELECT e.*,m.file_path AS image FROM events e LEFT JOIN media_library m ON m.media_id=e.media_id
        WHERE e.status='published' AND COALESCE(e.end_date,e.start_date)>=NOW() ORDER BY e.start_date LIMIT 200")->fetchAll(PDO::FETCH_ASSOC) as $e) {
        $code = strtolower($e['program']);
        $tags = ['updates','category:'.$e['category_id']];
        if ($code !== 'all') { $tags[]='program:'.$code; if (!empty($programDepartments[$code])) $tags[]='dept:'.$programDepartments[$code]; }
        $add('event:'.$e['event_id'], '/event.php?id='.$e['event_id'], $e['title'], 'Events', $e['location'], $e['image'], $tags, $e['created_at']);
    }
    // Route mappings describe existing CMS pages; recommendations themselves are ranked dynamically.
    $routes = ['home'=>'/', 'history'=>'/pages/about/gnc-history.php', 'college-departments'=>'/pages/academics/college/college-dept.php',
        'programs'=>'/pages/academics/college/college-dept.php', 'basic-education-departments'=>'/pages/academics/basic-edu/basic-edu-dept.php', 'contact-us'=>'/pages/contact-us.php'];
    foreach ($db->query("SELECT * FROM pages WHERE status='active' ORDER BY display_order LIMIT 200")->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $url = $routes[$p['slug']] ?? '/pages/about/'.$p['slug'].'.php';
        if ($url === '/' || !is_file(__DIR__.'/..'.$url)) continue;
        $academic = str_contains($url, '/academics/');
        $add('page:'.$p['page_id'], $url, $p['title'], $academic ? 'Academics' : 'Campus', $p['description'], null, $academic ? ['academics'] : [], $p['updated_at']);
    }
    // Existing admissions pages have no CMS rows. Discover their routes, rather than hardcoding recommendations.
    foreach (glob(__DIR__.'/../pages/admission/*.php') as $file) {
        $source = file_get_contents($file);
        if (!preg_match('/<title>([^<]+)<\/title>/i', $source, $m)) continue;
        $url='/pages/admission/'.basename($file);
        $add('admission:'.basename($file), $url, $m[1], 'Admissions', 'Explore admission requirements and information for prospective students.', null, ['academics','admissions']);
    }
    try {
        $popular = $db->query('SELECT page_url,visit_count FROM page_visits ORDER BY visit_count DESC LIMIT 2000')->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($items as &$item) foreach ($item['aliases'] as $alias) $item['popular'] += (int)($popular[$alias] ?? 0);
        unset($item);
    } catch (PDOException $e) { /* New sites may not have aggregate analytics yet. */ }
    $unique=[];
    foreach ($items as $item) if (!isset($unique[$item['url']])) $unique[$item['url']]=$item;
    return array_values($unique);
}
