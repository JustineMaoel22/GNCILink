<?php
require_once __DIR__ . '/image-framing.php';
require_once __DIR__ . '/content-publish-policy.php';
function normalizeAchievementAcademicYear($value): ?string {
    if ($value === null || $value === '') return null;
    if (!is_string($value) || !preg_match('/^([1-9][0-9]{3})-([1-9][0-9]{3})$/D', $value, $matches)
        || (int)$matches[2] !== (int)$matches[1] + 1) {
        throw new InvalidArgumentException('Enter a consecutive academic year, such as 2026-2027.');
    }
    return $value;
}

function studentAchievementListing(PDO $db, array $query, bool $includeDrafts = false): array {
    $year = null;
    try { $year = normalizeAchievementAcademicYear($query['academic_year'] ?? null); }
    catch (InvalidArgumentException $e) { /* Invalid filters show all years. */ }
    $category = $query['category'] ?? null;
    $category = is_string($category) && mb_strlen(trim($category)) <= 100 ? trim($category) : '';
    $category = $category === '' ? null : $category;
    $visibility = $includeDrafts ? '1=1' : "status='published'";
    $years = $db->query("SELECT DISTINCT academic_year FROM student_achievements WHERE $visibility AND academic_year IS NOT NULL ORDER BY academic_year DESC")->fetchAll(PDO::FETCH_COLUMN);
    $categories = $db->query("SELECT DISTINCT category_label FROM student_achievements WHERE $visibility AND category_label<>'' ORDER BY category_label")->fetchAll(PDO::FETCH_COLUMN);
    $where = $includeDrafts ? '1=1' : "n.status='published'";
    $params = [];
    if ($year !== null) { $where .= ' AND n.academic_year = ?'; $params[] = $year; }
    if ($category !== null) { $where .= ' AND n.category_label = ?'; $params[] = $category; }
    $count = $db->prepare('SELECT COUNT(*) FROM student_achievements n WHERE ' . $where);
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $pages = max(1, (int)ceil($total / 6));
    $requestedPage = filter_var($query['achievement_page'] ?? 1, FILTER_VALIDATE_INT);
    $page = min($pages, max(1, $requestedPage === false ? 1 : $requestedPage));
    $statement = $db->prepare("SELECT n.* FROM student_achievements n
        WHERE $where ORDER BY n.created_at ASC,n.achievement_id ASC LIMIT 6 OFFSET " . (($page - 1) * 6));
    $statement->execute($params);
    return ['items'=>$statement->fetchAll(PDO::FETCH_ASSOC),'years'=>$years,'year'=>$year,'categories'=>$categories,'category'=>$category,'page'=>$page,'pages'=>$pages,'total'=>$total];
}

function studentAchievementPageUrl(int $page, ?string $year, ?string $category = null): string {
    return '?' . http_build_query(array_filter(['academic_year'=>$year,'category'=>$category,'achievement_page'=>$page], static fn($value) => $value !== null)) . '#more-achievements';
}

function studentAchievementById(PDO $db, int $id, bool $publishedOnly = true): ?array {
    $statement = $db->prepare('SELECT * FROM student_achievements WHERE achievement_id=?' . ($publishedOnly ? " AND status='published'" : ''));
    $statement->execute([$id]);
    $record=$statement->fetch(PDO::FETCH_ASSOC);
    if (!$record) return null;
    $record['photos']=studentAchievementPhotos($db,$id);
    return $record;
}

function studentAchievementPhotos(PDO $db, int $id): array {
    $statement=$db->prepare('SELECT * FROM student_achievement_photos WHERE achievement_id=? ORDER BY photo_id ASC');
    $statement->execute([$id]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function studentAchievementModalPhotos(array $record): array {
    $thumbnail=($record['thumbnail_path'] ?? null) ?: ($record['image_path'] ?? null);
    $photos=[]; $seen=[];
    foreach ($record['photos'] ?? [] as $photo) {
        $path=$photo['image_path'] ?? '';
        if (!$path || $path===$thumbnail || isset($seen[$path])) continue;
        $seen[$path]=true; $photos[]=$photo;
    }
    return $photos;
}

function saveStudentAchievement(PDO $db, int $id, array $data, ?string $imagePath, ?string $thumbnailPath = null): int {
    $existing=$id?studentAchievementById($db,$id,false):null;
    if ($id && !$existing) throw new InvalidArgumentException('This achievement no longer exists.');
    $thumbnailPath=$thumbnailPath ?? ($existing['thumbnail_path'] ?? null);
    $fields = [];
    foreach (['title'=>255,'description'=>12000,'program_organization'=>255,'category_label'=>100,'achievers'=>12000] as $key=>$limit) {
        $value = $data[$key] ?? '';
        if (!is_string($value) || mb_strlen(trim($value)) > $limit) throw new InvalidArgumentException('Check the ' . str_replace('_', ' ', $key) . ' field.');
        $fields[$key] = trim($value);
    }
    if ($fields['title'] === '' && !contentPublishAllowsIncomplete()) throw new InvalidArgumentException('Enter an achievement title.');
    $status = $data['status'] ?? 'draft';
    if (!in_array($status, ['draft','published'], true)) throw new InvalidArgumentException('Choose draft or published.');
    $year = normalizeAchievementAcademicYear($data['academic_year'] ?? null);
    if ($status === 'published' && (!$year || (!$thumbnailPath && !$imagePath)) && !contentPublishAllowsIncomplete()) throw new InvalidArgumentException('Choose an academic year and add a card cover photo before publishing.');
    $order = filter_var($data['sort_order'] ?? 0, FILTER_VALIDATE_INT);
    if ($order === false || $order < 0 || $order > 100000) throw new InvalidArgumentException('Display order must be between 0 and 100000.');
    $values = [$fields['title'],$fields['description'],$fields['program_organization'],$fields['category_label'],$year,$imagePath,$status,empty($data['is_featured'])?0:1,$order,$fields['achievers'],$thumbnailPath];
    if ($id) {
        $values[] = $id;
        $db->prepare('UPDATE student_achievements SET title=?,description=?,program_organization=?,category_label=?,academic_year=?,image_path=?,status=?,is_featured=?,sort_order=?,achievers=?,thumbnail_path=?,updated_at=NOW() WHERE achievement_id=?')->execute($values);
        return $id;
    }
    $db->prepare('INSERT INTO student_achievements (title,description,program_organization,category_label,academic_year,image_path,status,is_featured,sort_order,achievers,thumbnail_path) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute($values);
    return (int)$db->lastInsertId();
}

function uploadStudentAchievementImage(array $file): string {
    $file = imageUploadPrepare($file);
    if (!isset($file['error'],$file['tmp_name']) || !is_int($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('The picture upload failed. Please try again.');
    $size = filesize($file['tmp_name']);
    $types = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!$size || $size > 5*1024*1024 || !isset($types[$mime]) || !getimagesize($file['tmp_name'])) throw new InvalidArgumentException('Upload a JPG, PNG, WebP or GIF picture of up to 5 MB.');
    $folder = dirname(__DIR__) . '/uploads/student-achievements';
    if (!is_dir($folder) && !mkdir($folder,0755,true) && !is_dir($folder)) throw new RuntimeException('Could not create the achievement upload folder.');
    $path = '/uploads/student-achievements/' . bin2hex(random_bytes(16)) . '.' . $types[$mime];
    if (!move_uploaded_file($file['tmp_name'],dirname(__DIR__).$path)) throw new RuntimeException('Could not save the achievement picture.');
    imageFramingSaveUpload($path, dirname(__DIR__) . $path);
    return $path;
}

function removeStudentAchievementImage(PDO $db, ?string $path): void {
    if (!$path || !preg_match('#^/uploads/student-achievements/[a-f0-9]{32}\.(jpg|png|webp|gif)$#D',$path)) return;
    $statement = $db->prepare('SELECT 1 FROM student_achievements WHERE image_path=? OR thumbnail_path=? UNION ALL SELECT 1 FROM student_achievement_photos WHERE image_path=? LIMIT 1');
    $statement->execute([$path,$path,$path]);
    if (!$statement->fetchColumn()) {
        $target = dirname(__DIR__) . $path;
        if (is_file($target) && !unlink($target)) error_log('Unable to remove unused achievement picture.');
    }
}
