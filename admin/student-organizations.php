<?php
function studentOrganizationsUrl(?string $slug = null): string {
    $base = '/pages/student%20life/student%20organization%20and%20leadership%20program/';
    return $base . ($slug === null ? 'student-org.php' : 'dynamic-org.php?slug=' . rawurlencode($slug));
}
function studentOrganizationFields(): array {
    return ['name' => ['Organization name', 255], 'description' => ['Short description', 1500],
        'about_text' => ['About the organization', 12000], 'vision' => ['Our vision', 6000], 'mission' => ['Our mission', 6000],
        'facebook_url' => ['Facebook page URL (optional)', 500]];
}
function studentOrgPageFields(): array {
    return ['hero_title' => ['Page heading', 'Student Organizations and Leadership Programs', 255],
        'hero_description' => ['Page description', 'Find your community on campus, and grow into the kind of leader Guagua National Colleges, Inc. is proud to send out.', 1500],
        'section_title' => ['Section heading', 'Student Organizations', 255],
        'section_description' => ['Section description', 'A place for every interest, every course, and every GNCian.', 1500],
        'gallery_title' => ['Gallery heading', 'Through the Lens', 255],
        'facebook_title' => ['Facebook heading', 'Follow Us on Facebook', 255],
        'leadership_title' => ['Leadership section heading', 'Leadership and Capacity Building', 255],
        'leadership_description' => ['Leadership introduction', 'Empowering student leaders with the skills, values, and confidence to make a greater impact.', 1500],
        'training_title' => ['Training heading', 'Student Leaders’ Training', 255],
        'training_description' => ['Training description', 'The OSA conducts annual leadership training for all officers of recognized student organizations, covering:', 1500],
        'training_topic_1' => ['Training topic 1', 'Leadership Roles and Values', 255],
        'training_topic_2' => ['Training topic 2', 'Project Management', 255],
        'training_topic_3' => ['Training topic 3', 'Conflict Resolution and Mediation', 255],
        'training_topic_4' => ['Training topic 4', 'Financial Transparency', 255],
        'training_topic_5' => ['Training topic 5', 'Event Planning and Logistics', 255],
        'retreat_title' => ['Retreat heading', 'Leadership Formation Retreats', 255],
        'retreat_description' => ['Retreat introduction', 'The OSA organizes annual leadership retreats and reflective workshops to promote values-based leadership and emotional intelligence.', 2000],
        'retreat_benefit' => ['Retreat purpose', 'Retreats and reflective workshops are conducted yearly to promote values-based leadership and emotional intelligence.', 1500],
        'retreat_location' => ['Retreat arrangements', 'May be held off-campus, subject to proper approvals.', 1500],
        'retreat_benefit_icon' => ['Retreat purpose icon', 'bi:trophy', 100],
        'retreat_location_icon' => ['Retreat arrangements icon', 'bi:geo-alt', 100],
        'vision_icon' => ['Vision icon', 'bi:bullseye', 100],
        'mission_icon' => ['Mission icon', 'bi:lightbulb', 100],
        'leadership_closing' => ['Leadership closing message', "Building Better\nLeaders for Tomorrow", 500]];
}
function studentOrgPageIsRich(string $key): bool {
    return str_ends_with($key,'_description')||in_array($key,['retreat_benefit','retreat_location'],true);
}
function studentOrgIcon(string $key,string $class): string {
    $field=studentOrgPageContent()[$key];$choices=academicFeatureIconChoices();$choice=$choices[$field['value']]??$choices[studentOrgPageFields()[$key][1]];
    $h=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
    $icon=isset($choice['path'])?'<img src="'.$h($choice['path']).'" alt="" aria-hidden="true">':(isset($choice['material'])?'<span class="material-symbols-outlined" aria-hidden="true">'.$h($choice['material']).'</span>':'<i class="bi bi-'.$h($choice['glyph']).'" aria-hidden="true"></i>');
    return '<span class="'.$h($class).'"'.ceManageMark('data-ve-org-icon="'.$h($key).'" role="button" tabindex="0" aria-label="Change '.$h(strtolower($field['label'])).'"').'>'.$icon.'</span>';
}
function studentOrgPageImageFields(): array {
    return ['training_background'=>['Training background','/assets/images/gnc-front-II.jpg'],
        'retreat_photo'=>['Leadership formation photo','/assets/images/DSC_2965.JPG'],
        'closing_background'=>['Leadership closing background','/assets/images/DSC_2965.JPG']];
}
function studentOrgPageImages(): array {
    $images=[];
    foreach(studentOrgPageImageFields() as $key=>[$label,$fallback])$images[$key]=['label'=>$label,'media_id'=>null,'path'=>$fallback,'fallback'=>$fallback];
    try {
        $rows=getDB()->query("SELECT c.field_key,m.media_id,m.file_path FROM student_org_page_content c JOIN media_library m ON m.media_id=CAST(c.content AS UNSIGNED) WHERE c.field_key IN ('training_background_media_id','retreat_photo_media_id','closing_background_media_id')")->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $row){$key=substr($row['field_key'],0,-9);if(isset($images[$key])){$images[$key]['media_id']=(int)$row['media_id'];$images[$key]['path']=$row['file_path'];}}
    } catch(PDOException $e){if($e->getCode()!=='42S02')throw $e;}
    return $images;
}
function saveStudentOrgPageWithImages(array $texts,array $files,array $remove): void {
    $allowed=studentOrgPageImageFields();
    foreach(array_merge(array_keys($files),$remove) as $key)if(!is_string($key)||!isset($allowed[$key]))throw new InvalidArgumentException('Invalid page image.');
    $db=getDB();$owns=!$db->inTransaction();$uploads=[];$old=studentOrgPageImages();$updates=[];
    try {
        foreach($remove as $key)$updates[$key]=null;
        foreach($files as $key=>$file)if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$uploads[]=$upload=storeDepartmentUpload($file,'homepage_image');$updates[$key]=$upload['media_id'];}
        if($owns)$db->beginTransaction();
        saveStudentOrgPage($texts);
        $stmt=$db->prepare('INSERT INTO student_org_page_content (field_key,content) VALUES (?,?) ON DUPLICATE KEY UPDATE content=VALUES(content)');
        foreach($updates as $key=>$media)$stmt->execute([$key.'_media_id',$media===null?'':(string)$media]);
        if($owns)$db->commit();
    } catch(Throwable $e){if($owns&&$db->inTransaction())$db->rollBack();foreach($uploads as $upload)discardStoredDepartmentUpload($upload);throw $e;}
    if($owns)foreach($updates as $key=>$media)if($old[$key]['media_id']&&$old[$key]['media_id']!=$media)deleteMediaIfUnreferenced($old[$key]['media_id']);
}
function studentOrgPageContent(): array {
    $fields = [];
    foreach (studentOrgPageFields() as $key => [$label, $default, $max]) $fields[$key] = ['label' => $label, 'value' => $default, 'max' => $max];
    try {
        foreach (getDB()->query('SELECT field_key,content FROM student_org_page_content')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($fields[$row['field_key']])) $fields[$row['field_key']]['value'] = $row['content'];
        }
    } catch (PDOException $e) { if ($e->getCode() !== '42S02') throw $e; }
    return $fields;
}
function studentOrgCategories(): array {
    try { return getDB()->query('SELECT * FROM student_org_categories ORDER BY display_order,category_id')->fetchAll(PDO::FETCH_ASSOC); }
    catch (PDOException $e) { if ($e->getCode() !== '42S02') throw $e; return []; }
}
function studentOrganizations(bool $includeDrafts = false): array {
    try {
        return getDB()->query("SELECT o.*, c.name AS category_name,l.file_path AS logo_url,b.file_path AS cover_url
            FROM student_organizations o LEFT JOIN student_org_categories c ON c.category_id=o.category_id
            LEFT JOIN media_library l ON l.media_id=o.logo_media_id LEFT JOIN media_library b ON b.media_id=o.cover_media_id
            " . ($includeDrafts ? '' : "WHERE o.status='published'") . ' ORDER BY o.display_order,o.org_id')->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { if ($e->getCode() !== '42S02') throw $e; return []; }
}
function studentOrganizationGallery(int $id): array {
    $stmt = getDB()->prepare('SELECT g.*,m.file_path AS image_url FROM student_org_gallery g JOIN media_library m ON m.media_id=g.media_id WHERE g.org_id=? ORDER BY g.display_order,g.image_id');
    $stmt->execute([$id]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function saveStudentOrgPage(array $changes): void {
    $fields = studentOrgPageFields();
    foreach ($changes as $key => $value) if (!isset($fields[$key]) || !is_string($value) || mb_strlen($value) > $fields[$key][2]) throw new InvalidArgumentException('Invalid page text.');
    foreach($changes as $key=>&$value){
        if(str_ends_with($key,'_icon')){if(!isset(academicFeatureIconChoices()[$value]))throw new InvalidArgumentException('Choose an icon from the icon picker.');}
        elseif(studentOrgPageIsRich($key))$value=richTextNormalize($value);
    }unset($value);
    $db = getDB(); $owns = !$db->inTransaction(); if ($owns) $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO student_org_page_content (field_key,content) VALUES (?,?) ON DUPLICATE KEY UPDATE content=VALUES(content)');
        foreach ($changes as $key => $value) $stmt->execute([$key, trim($value)]);
        if ($owns) $db->commit();
    } catch (Throwable $e) { if ($owns) $db->rollBack(); throw $e; }
}
function saveStudentOrganization(array $input, array $files = []): array {
    foreach (['org_id','category_id'] as $key) {
        if (isset($input[$key]) && (!is_scalar($input[$key]) || !preg_match('/^\d*$/',(string)$input[$key]))) throw new InvalidArgumentException('Invalid organization or category ID.');
    }
    $db = getDB(); $id = (int)($input['org_id'] ?? 0); $current = null;
    foreach (studentOrganizations(true) as $row) if ((int)$row['org_id'] === $id) $current = $row;
    if ($id < 0 || ($id && !$current)) throw new InvalidArgumentException('Organization not found.');
    $data = [];
    foreach (studentOrganizationFields() as $key => [$label,$max]) {
        $value = $input[$key] ?? $current[$key] ?? '';
        if (!is_string($value) || mb_strlen($value) > $max) throw new InvalidArgumentException($label . ' is invalid or too long.');
        $data[$key] = trim($value);
        if(in_array($key,['description','about_text','vision','mission'],true))$data[$key]=richTextNormalize($data[$key]);
    }
    if ($data['name'] === '') throw new InvalidArgumentException('Enter an organization name.');
    $status = $input['status'] ?? $current['status'] ?? 'draft';
    if (!in_array($status, ['draft','published'], true)) throw new InvalidArgumentException('Choose Published or Unpublished.');
    $category = (int)($input['category_id'] ?? $current['category_id'] ?? 0);
    if ($category && !in_array($category, array_map('intval',array_column(studentOrgCategories(),'category_id')),true)) throw new InvalidArgumentException('Category not found.');
    if ($data['facebook_url'] !== '') {
        $host = strtolower((string)parse_url($data['facebook_url'], PHP_URL_HOST));
        if (!filter_var($data['facebook_url'], FILTER_VALIDATE_URL) || parse_url($data['facebook_url'], PHP_URL_SCHEME) !== 'https' || !in_array($host,['facebook.com','www.facebook.com','m.facebook.com'],true)) throw new InvalidArgumentException('Enter an HTTPS Facebook page URL.');
    }
    $gallery = $current ? studentOrganizationGallery($id) : [];
    $remove = $input['remove_gallery'] ?? [];
    if (!is_array($remove)) throw new InvalidArgumentException('Invalid gallery selection.');
    foreach ($remove as $image) if (!is_scalar($image) || !ctype_digit((string)$image)) throw new InvalidArgumentException('Invalid gallery image.');
    $remove = array_map('intval',$remove);
    foreach ($remove as $image) if (!in_array($image,array_map('intval',array_column($gallery,'image_id')),true)) throw new InvalidArgumentException('Gallery image not found in this organization.');
    $galleryFiles = [];
    if (isset($files['gallery']['name']) && is_array($files['gallery']['name'])) {
        foreach ($files['gallery']['name'] as $i => $unused) {
            $file = []; foreach (['name','type','tmp_name','error','size'] as $key) $file[$key]=$files['gallery'][$key][$i];
            if ($file['error'] !== UPLOAD_ERR_NO_FILE) $galleryFiles[]=$file;
        }
    }
    if (count($gallery) - count(array_unique($remove)) + count($galleryFiles) > 10) throw new InvalidArgumentException('Use up to ten gallery images.');
    $uploads=[]; $retired=[]; $newGallery=[];
    $owns = !$db->inTransaction();
    try {
        foreach (['logo','cover'] as $key) {
            $media = $current[$key.'_media_id'] ?? null;
            if (!empty($input['remove_'.$key])) $media=null;
            if (isset($files[$key]) && ($files[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $upload=storeDepartmentUpload($files[$key],'homepage_image'); $uploads[]=$upload; $media=$upload['media_id'];
            }
            if (!empty($current[$key.'_media_id']) && (int)$current[$key.'_media_id'] !== (int)$media) $retired[]=(int)$current[$key.'_media_id'];
            $data[$key.'_media_id']=$media;
        }
        foreach ($galleryFiles as $file) { $uploads[]=$upload=storeDepartmentUpload($file,'homepage_image'); $newGallery[]=$upload['media_id']; }
        if ($owns) $db->beginTransaction();
        if (!$current) {
            $base=mb_substr(generateSlug($data['name']) ?: 'organization',0,155); $slug=$base;
            $check=$db->prepare('SELECT 1 FROM student_organizations WHERE slug=?');
            for ($n=2;;$n++) { $check->execute([$slug]); if (!$check->fetchColumn()) break; $slug=$base.'-'.$n; }
            $stmt=$db->prepare('INSERT INTO student_organizations (slug,name,display_order) VALUES (?,?,(SELECT COALESCE(MAX(display_order),0)+1 FROM (SELECT display_order FROM student_organizations) ranked))');
            $stmt->execute([$slug,$data['name']]); $id=(int)$db->lastInsertId();
        }
        $stmt=$db->prepare('UPDATE student_organizations SET name=?,category_id=?,description=?,about_text=?,vision=?,mission=?,facebook_url=?,logo_media_id=?,cover_media_id=?,status=? WHERE org_id=?');
        $stmt->execute([$data['name'],$category?:null,$data['description'],$data['about_text'],$data['vision'],$data['mission'],$data['facebook_url'],$data['logo_media_id'],$data['cover_media_id'],$status,$id]);
        foreach ($gallery as $image) if (in_array((int)$image['image_id'],$remove,true)) {
            $db->prepare('DELETE FROM student_org_gallery WHERE image_id=? AND org_id=?')->execute([$image['image_id'],$id]); $retired[]=(int)$image['media_id'];
        }
        $stmt=$db->prepare('INSERT INTO student_org_gallery (org_id,media_id,display_order) VALUES (?,?,?)');
        foreach ($newGallery as $i=>$media) $stmt->execute([$id,$media,count($gallery)+$i]);
        if ($owns) $db->commit();
    } catch (Throwable $e) {
        if ($owns && $db->inTransaction()) $db->rollBack();
        foreach ($uploads as $upload) discardStoredDepartmentUpload($upload);
        throw $e;
    }
    if ($owns) foreach (array_unique($retired) as $media) deleteMediaIfUnreferenced($media);
    foreach (studentOrganizations(true) as $row) if ((int)$row['org_id']===$id) return $row;
    throw new RuntimeException('Could not read the saved organization.');
}
function deleteStudentOrganization(int $id): void {
    $current=null; foreach (studentOrganizations(true) as $row) if ((int)$row['org_id']===$id) $current=$row;
    if (!$current) throw new InvalidArgumentException('Organization not found.');
    $media=array_merge([$current['logo_media_id'],$current['cover_media_id']],array_column(studentOrganizationGallery($id),'media_id'));
    getDB()->prepare('DELETE FROM student_organizations WHERE org_id=?')->execute([$id]);
    if (!getDB()->inTransaction()) foreach (array_unique(array_filter($media)) as $item) deleteMediaIfUnreferenced((int)$item);
}
function moveStudentOrganization(int $id, int $direction): void {
    if (!in_array($direction,[-1,1],true)) throw new InvalidArgumentException('Invalid order.');
    $db=getDB(); $owns=!$db->inTransaction(); if ($owns) $db->beginTransaction();
    try {
        $ids=array_map('intval',$db->query('SELECT org_id FROM student_organizations ORDER BY display_order,org_id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN));
        $index=array_search($id,$ids,true); if ($index===false) throw new InvalidArgumentException('Organization not found.');
        $next=$index+$direction;
        if (isset($ids[$next])) { [$ids[$index],$ids[$next]]=[$ids[$next],$ids[$index]]; }
        $stmt=$db->prepare('UPDATE student_organizations SET display_order=? WHERE org_id=?');
        foreach ($ids as $i=>$org) $stmt->execute([$i,$org]);
        if ($owns) $db->commit();
    } catch (Throwable $e) { if ($owns) $db->rollBack(); throw $e; }
}
