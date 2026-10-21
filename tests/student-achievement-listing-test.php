<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
function achievementCheck(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
foreach (['admin/student-achievements.php','admin/action/student-achievements-handler.php','admin/content-edit.php','database/migrate-student-achievements.php'] as $file) token_get_all(file_get_contents(__DIR__ . '/../' . $file), TOKEN_PARSE);
$db = getDB();
$modalPhotos=studentAchievementModalPhotos(['thumbnail_path'=>'/thumbnail.png','image_path'=>'/thumbnail.png','photos'=>[['image_path'=>'/thumbnail.png'],['image_path'=>'/achiever.png','caption'=>'Achiever']]]);
achievementCheck(count($modalPhotos)===1 && $modalPhotos[0]['image_path']==='/achiever.png','Legacy/shared thumbnail is excluded from modal');
achievementCheck(studentAchievementModalPhotos(['image_path'=>'/fallback-thumbnail.png','photos'=>[]])===[],'Fallback thumbnail never becomes an achiever slide');
achievementCheck(studentAchievementModalPhotos(['thumbnail_path'=>'/card.png','image_path'=>'/featured.png','photos'=>[]])===[],'Featured cover is not an achiever slide');
$mode = $argv[1] ?? 'test';
if ($mode === 'session') {
    initSession();
    $_SESSION['user_id'] = (int)$db->query('SELECT user_id FROM users ORDER BY user_id LIMIT 1')->fetchColumn();
    $_SESSION['user_role'] = ($argv[2] ?? '') === 'editor' ? 'Content Editor' : 'Super Admin';
    echo json_encode(['name'=>session_name(),'id'=>session_id(),'csrf'=>generateCSRFToken()]);
    session_write_close(); exit;
}
if ($mode === 'destroy_session') {
    $id = $argv[2] ?? '';
    if (!preg_match('/^[a-zA-Z0-9,-]{20,128}$/',$id)) throw new RuntimeException('Invalid test session');
    session_id($id); session_start(); session_destroy(); exit;
}
$db->beginTransaction();
try {
    $newsBefore = $db->query('SELECT COUNT(*) FROM news')->fetchColumn();
    $prefix = 'Achievement fixture ' . bin2hex(random_bytes(6));
    $used = $db->query('SELECT DISTINCT academic_year FROM student_achievements WHERE academic_year IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
    $first = 2080;
    while (in_array("$first-" . ($first + 1), $used, true) || in_array(($first + 1) . '-' . ($first + 2), $used, true)) $first += 2;
    $yearA = "$first-" . ($first + 1);
    $yearB = ($first + 1) . '-' . ($first + 2);
    $ids = [];
    $base = ['description'=>'Recognizing talent and dedication.','program_organization'=>'Bachelor of Science in Accountancy','category_label'=>'Academic Excellence','status'=>'published','is_featured'=>1,'academic_year'=>$yearA];
    for ($i = 0; $i < 15; $i++) {
        $ids[] = saveStudentAchievement($db,0,array_merge($base,['title'=>"$prefix $i",'academic_year'=>$i < 8 ? $yearA : $yearB,'is_featured'=>$i<3?1:0,'sort_order'=>15-$i]),'/assets/images/gnc-front-II.jpg');
    }
    $draft = saveStudentAchievement($db,0,['title'=>"$prefix draft",'status'=>'draft','academic_year'=>$yearA],null);
    $listing = studentAchievementListing($db,['academic_year'=>$yearA]);
    achievementCheck($listing['total']===8 && count($listing['items'])===6 && $listing['pages']===2,'Six cards per page');
    achievementCheck(array_map('intval',array_column($listing['items'],'achievement_id'))===array_slice($ids,0,6),'First created achievements appear first regardless of legacy display order');
    achievementCheck(!in_array($draft,array_column($listing['items'],'achievement_id')),'Drafts excluded from listing');
    achievementCheck(studentAchievementById($db,$draft)===null,'Draft detail is private');
    $pageTwo = studentAchievementListing($db,['academic_year'=>$yearA,'achievement_page'=>2]);
    achievementCheck(count($pageTwo['items'])===2,'Second page contains remaining cards');
    achievementCheck(array_map('intval',array_column($pageTwo['items'],'achievement_id'))===array_slice($ids,6,2),'Upload order continues across pages');
    achievementCheck(!array_intersect(array_column($listing['items'],'achievement_id'),array_column($pageTwo['items'],'achievement_id')),'No duplicate cards');
    achievementCheck(studentAchievementListing($db,['academic_year'=>$yearB])['total']===7,'Academic-year filter');
    $sports = $prefix . ' Sports';
    saveStudentAchievement($db,$ids[7],array_merge($base,['title'=>"$prefix sports",'category_label'=>$sports]),'/assets/images/gnc-front-II.jpg');
    $categoryListing = studentAchievementListing($db,['academic_year'=>$yearA,'category'=>'Academic Excellence']);
    achievementCheck($categoryListing['total']===7 && $categoryListing['pages']===2,'Year and category filters combine before pagination');
    achievementCheck(studentAchievementListing($db,['category'=>$sports])['total']===1,'Category can filter across all academic years');
    achievementCheck(studentAchievementListing($db,['academic_year'=>$yearB,'category'=>$sports])['total']===0,'A category with no matching year returns no cards');
    achievementCheck(in_array($sports,$categoryListing['categories'],true),'Category dropdown includes saved categories');
    achievementCheck(studentAchievementListing($db,['category'=>['invalid']])['category']===null,'Malformed category handled');
    achievementCheck(studentAchievementListing($db,['category'=>str_repeat('x',101)])['category']===null,'Overlong category handled');
    achievementCheck(studentAchievementListing($db,['category'=>"' OR 1=1 --"])['total']===0,'Category queries use bound values');
    $categoryUrl=studentAchievementPageUrl(2,$yearA,$sports);
    parse_str(parse_url($categoryUrl,PHP_URL_QUERY),$categoryParams);
    achievementCheck($categoryParams['category']===$sports && $categoryParams['academic_year']===$yearA,'Pagination preserves both filters');
    $privateCategory=$prefix.' Private category';
    saveStudentAchievement($db,$draft,['title'=>"$prefix draft",'status'=>'draft','academic_year'=>$yearA,'category_label'=>$privateCategory],null);
    achievementCheck(!in_array($privateCategory,studentAchievementListing($db,[])['categories'],true),'Public category options exclude draft-only categories');
    achievementCheck(in_array($privateCategory,studentAchievementListing($db,[],true)['categories'],true),'Editor can filter draft categories');
    achievementCheck(studentAchievementListing($db,['academic_year'=>$yearA,'achievement_page'=>999])['page']===2,'Page bounds');
    achievementCheck(studentAchievementListing($db,['academic_year'=>['invalid'],'achievement_page'=>['invalid']])['year']===null,'Malformed query handled');
    achievementCheck(str_contains(studentAchievementPageUrl(2,$yearA),'academic_year='.$yearA),'Pagination preserves year');
    try { normalizeAchievementAcademicYear('2026-2028'); throw new RuntimeException('Invalid year accepted'); } catch (InvalidArgumentException $expected) {}
    try { saveStudentAchievement($db,0,['title'=>'Missing image','status'=>'published','academic_year'=>$yearA],null); throw new RuntimeException('Missing image accepted'); } catch (InvalidArgumentException $expected) {}
    try { saveStudentAchievement($db,0,['title'=>'Missing year','status'=>'published'],'/assets/images/gnc-front-II.jpg'); throw new RuntimeException('Missing year accepted'); } catch (InvalidArgumentException $expected) {}
    saveStudentAchievement($db,$ids[0],array_merge($base,['title'=>"$prefix updated"]),'/assets/images/gnc-front-II.jpg');
    achievementCheck(studentAchievementById($db,$ids[0])['title']==="$prefix updated",'Dedicated update persists');
    achievementCheck((int)studentAchievementListing($db,['academic_year'=>$yearA])['items'][0]['achievement_id']===$ids[0],'Editing does not move the first achievement');
    $photoInsert=$db->prepare('INSERT INTO student_achievement_photos (achievement_id,image_path,caption,sort_order) VALUES (?,?,?,?)');
    $photoInsert->execute([$ids[0],'/first-photo.png','First uploaded',99]);
    $photoInsert->execute([$ids[0],'/second-photo.png','Second uploaded',0]);
    achievementCheck(array_column(studentAchievementPhotos($db,$ids[0]),'caption')===['First uploaded','Second uploaded'],'Photos display first uploaded first regardless of legacy display order');
    achievementCheck($db->query('SELECT COUNT(*) FROM news')->fetchColumn()===$newsBefore,'Achievement writes never create News posts');
    $coverOnly=saveStudentAchievement($db,0,['title'=>'Cover-only fixture','status'=>'published','is_featured'=>1,'academic_year'=>'2098-2099'],null,'/assets/images/gnc-front-II.jpg');
    achievementCheck(studentAchievementById($db,$coverOnly)['image_path']===null,'Separate featured photo is optional when a card cover exists');
    $_GET = ['academic_year'=>$yearA,'category'=>'Academic Excellence'];
    $_SERVER['REQUEST_URI']='/pages/student%20life/student-achievement.php';
    $_SERVER['DOCUMENT_ROOT']=dirname(__DIR__);
    ob_start(); include __DIR__.'/../pages/student life/student-achievement.php'; $html=ob_get_clean();
    achievementCheck(substr_count($html,'class="achievement-card"')===6,'Public template renders six cards');
    achievementCheck(str_contains($html,'?achievement=') && !str_contains($html,'class="achievement-card-link" href="/news-article.php'),'Cards use achievement details');
    achievementCheck(str_contains($html,'Academic Excellence'),'Admin category rendered');
    achievementCheck(str_contains($html,'id="achievementCategory"') && str_contains($html,'value="Academic Excellence" selected'),'Category dropdown renders selected filter');
    if ($mode==='render') echo $html;
    else echo "PASS: independent CRUD, no News writes, year/category filtering, category validation and privacy, pagination and public cards.\n";
} finally { $db->rollBack(); }
