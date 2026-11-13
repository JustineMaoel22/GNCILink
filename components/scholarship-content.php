<?php
require_once __DIR__ . '/../admin/admin-functions.php';
require_once __DIR__ . '/tuition-fee-documents.php';

function scholarshipDefaultCards(): array {
    $incomingGrade7 = [
        ['badge' => 'Rank 1', 'label' => 'Full Scholarship',    'pct' => 100, 'grade' => 'Grade 7', 'program' => 'Regular Program',    'type' => 'regular'],
        ['badge' => 'Rank 2', 'label' => 'Half Scholarship',    'pct' => 50,  'grade' => 'Grade 7', 'program' => 'Regular Program',    'type' => 'regular'],
        ['badge' => 'Rank 1', 'label' => 'Half Scholarship',    'pct' => 50,  'grade' => 'Grade 7', 'program' => 'Montessori Program', 'type' => 'montessori'],
        ['badge' => 'Rank 2', 'label' => 'Partial Scholarship', 'pct' => 25,  'grade' => 'Grade 7', 'program' => 'Montessori Program', 'type' => 'montessori'],
    ];
    $oldStudents = [
        ['badge' => '1st Honor', 'label' => 'Half Scholarship',    'pct' => 50, 'grade' => '', 'program' => 'Montessori Program', 'type' => 'montessori'],
        ['badge' => '2nd Honor', 'label' => 'Partial Scholarship', 'pct' => 25, 'grade' => '', 'program' => 'Montessori Program', 'type' => 'montessori'],
    ];
    $collegeIncoming = [
        ['badge' => 'Rank 1', 'label' => 'Full Scholarship', 'pct' => 100, 'grade' => 'First Year', 'program' => 'College Program', 'type' => 'regular'],
        ['badge' => 'Rank 2', 'label' => 'Half Scholarship', 'pct' => 50,  'grade' => 'First Year', 'program' => 'College Program', 'type' => 'regular'],
    ];
    $collegeOld = [
        ['badge' => "President's Lister", 'label' => 'Half Scholarship',    'pct' => 50, 'grade' => '', 'program' => 'College Program', 'type' => 'navy'],
        ['badge' => "Dean's Lister",      'label' => 'Partial Scholarship', 'pct' => 25, 'grade' => '', 'program' => 'College Program', 'type' => 'navy'],
    ];
    return compact('incomingGrade7','oldStudents','collegeIncoming','collegeOld');
}
function scholarshipDefaultRequirements(): array {
    $collegeRequirements = [
        'To be a regular student.',
        'Letter of Application addressed to GNCI President signed by the Dean.',
        'Grades from Last Semester. All course subject grades must be 1.8 and above.',
        'Submit all the requirements at Business Office Window 3.',
    ];
    return $collegeRequirements;
}
function scholarshipFieldDefaults(): array {
    $fields = [];
    $fields['copy_1'] = ['value'=>'Scholarship', 'label'=>'Scholarship', 'max'=>255, 'type'=>'text'];
    $fields['copy_2'] = ['value'=>'GNCI welcomes applicants whose academic credentials, character, and potential demonstrate their readiness to benefit from the school’s quality education and enriching intellectual, social, and spiritual environment.', 'label'=>'GNCI welcomes applicants whose academic credentials, character, and potential demonstrate their read', 'max'=>4000, 'type'=>'text'];
    $fields['copy_3'] = ['value'=>'GNC Scholarships', 'label'=>'GNC Scholarships', 'max'=>255, 'type'=>'text'];
    $fields['copy_4'] = ['value'=>'Government Scholarships', 'label'=>'Government Scholarships', 'max'=>255, 'type'=>'text'];
    $fields['copy_5'] = ['value'=>'The school is committed to providing quality education and offering various scholarship programs to support deserving and qualified students.', 'label'=>'The school is committed to providing quality education and offering various scholarship programs to ', 'max'=>4000, 'type'=>'text'];
    $fields['copy_6'] = ['value'=>'Academic Scholarship', 'label'=>'Academic Scholarship', 'max'=>255, 'type'=>'text'];
    $fields['copy_7'] = ['value'=>'Academic Scholarship', 'label'=>'Academic Scholarship', 'max'=>255, 'type'=>'text'];
    $fields['copy_8'] = ['value'=>'Athletic Scholarship', 'label'=>'Athletic Scholarship', 'max'=>255, 'type'=>'text'];
    $fields['copy_9'] = ['value'=>'Government scholarship information will be posted here.', 'label'=>'Government scholarship information will be posted here.', 'max'=>4000, 'type'=>'text'];
    $fields['copy_10'] = ['value'=>'Academic Scholarship for Basic Education Students', 'label'=>'Academic Scholarship for Basic Education Students', 'max'=>255, 'type'=>'text'];
    $fields['copy_11'] = ['value'=>'Incoming Grade 7 Students', 'label'=>'Incoming Grade 7 Students', 'max'=>255, 'type'=>'text'];
    $fields['copy_12'] = ['value'=>'Old Students (Advanced CASA - Grade 6 & Grade 8 - Grade 10)', 'label'=>'Old Students (Advanced CASA - Grade 6 & Grade 8 - Grade 10)', 'max'=>255, 'type'=>'text'];
    $fields['copy_13'] = ['value'=>'Academic Scholarship for College Students', 'label'=>'Academic Scholarship for College Students', 'max'=>255, 'type'=>'text'];
    $fields['copy_14'] = ['value'=>'Incoming Freshmen Students', 'label'=>'Incoming Freshmen Students', 'max'=>255, 'type'=>'text'];
    $fields['copy_15'] = ['value'=>'Old Students', 'label'=>'Old Students', 'max'=>255, 'type'=>'text'];
    $fields['copy_16'] = ['value'=>'Requirements for Application', 'label'=>'Requirements for Application', 'max'=>255, 'type'=>'text'];
    $fields['copy_17'] = ['value'=>'Students are required to submit the following documents to apply for academic scholarship.', 'label'=>'Students are required to submit the following documents to apply for academic scholarship.', 'max'=>4000, 'type'=>'text'];
    $fields['copy_18'] = ['value'=>'(Basic Education Department)', 'label'=>'(Basic Education Department)', 'max'=>255, 'type'=>'text'];
    $fields['copy_19'] = ['value'=>'(College Department)', 'label'=>'(College Department)', 'max'=>255, 'type'=>'text'];
    $copyLabels = ['Page title','Page introduction','School scholarships tab','Government scholarships tab','School scholarships introduction','Basic Education card title','College card title','Athletics card title','Government scholarship information','Basic Education dialog title','Incoming Grade 7 heading','Continuing Basic Education heading','College dialog title','Incoming college students heading','Continuing college students heading','Application requirements heading','Application requirements introduction','Basic Education card subtitle','College card subtitle'];
    foreach ($copyLabels as $i => $label) $fields['copy_' . ($i + 1)]['label'] = $label;
    $groupLabels = ['incomingGrade7'=>'Incoming Grade 7','oldStudents'=>'Continuing Basic Education','collegeIncoming'=>'Incoming college students','collegeOld'=>'Continuing college students'];
    $fieldLabels = ['badge'=>'Award or rank','label'=>'Scholarship name','pct'=>'Discount percentage','grade'=>'Year or grade level','program'=>'Program label'];
    foreach (scholarshipDefaultCards() as $group => $cards) foreach ($cards as $i => $card) {
        foreach (['badge','label','pct','grade','program'] as $key) {
            $fields[$group . '_' . $i . '_' . $key] = ['value'=>(string)$card[$key], 'label'=>$groupLabels[$group] . ' ' . ($i + 1) . ': ' . $fieldLabels[$key], 'max'=>$key === 'pct' ? 3 : 255, 'type'=>$key === 'pct' ? 'percent' : 'text'];
        }
    }
    foreach (scholarshipDefaultRequirements() as $i => $value) $fields['requirement_' . $i] = ['value'=>$value, 'label'=>'Application requirement ' . ($i+1), 'max'=>4000, 'type'=>'text'];
    return $fields;
}
function scholarshipValidateTexts(array $changes): array {
    $fields = scholarshipFieldDefaults();
    if (count($changes) > count($fields)) throw new InvalidArgumentException('Too many scholarship changes.');
    foreach ($changes as $key => &$value) {
        if (!isset($fields[$key]) || !is_string($value)) throw new InvalidArgumentException('Unknown scholarship field.');
        $value = trim(strip_tags($value));
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length > $fields[$key]['max']) throw new InvalidArgumentException($fields[$key]['label'] . ' is too long.');
        if ($fields[$key]['type'] === 'percent' && (!preg_match('/^(?:100|[0-9]{1,2})$/D', $value))) throw new InvalidArgumentException('Enter a scholarship percentage from 0 to 100.');
    }
    unset($value);
    return $changes;
}
function scholarshipStoredValues(string $prefix, ?PDO $db = null): array {
    try {
        $stmt = ($db ?? getDB())->prepare('SELECT field_key,content FROM tuition_fee_content WHERE field_key LIKE ?');
        $stmt->execute([$prefix . '%']);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $error) { if ($error->getCode() !== '42S02') throw $error; return []; }
}
function scholarshipFields(?PDO $db = null): array {
    $fields = scholarshipFieldDefaults();
    foreach (scholarshipStoredValues('scholarship_text_', $db) as $key => $value) {
        $key = substr($key, strlen('scholarship_text_'));
        if (isset($fields[$key])) $fields[$key]['value'] = $value;
    }
    return $fields;
}
function scholarshipCards(array $fields): array {
    $groups = scholarshipDefaultCards();
    foreach ($groups as $group => &$cards) foreach ($cards as $i => &$card) {
        foreach (['badge','label','pct','grade','program'] as $key) $card[$key] = $fields[$group . '_' . $i . '_' . $key]['value'];
    }
    unset($cards, $card);
    return $groups;
}
function scholarshipValidateEntries(array $entries, ?array $categories = null): array {
    $categories = $categories ?? scholarshipCategories();
    if (!array_is_list($entries) || count($entries) > 100) throw new InvalidArgumentException('Use up to 100 scholarship entries.');
    $seen = [];
    foreach ($entries as &$entry) {
        if (!is_array($entry) || array_diff(array_keys($entry), ['id','category','title','description','url','pdf','eligibility','benefits','application','information','legacy'])) throw new InvalidArgumentException('Invalid scholarship entry.');
        if (isset($entry['legacy']) && !in_array($entry['legacy'], ['basic','college','athletic'], true)) throw new InvalidArgumentException('Invalid scholarship information reference.');
        if (isset($entry['pdf']) && (!is_string($entry['pdf']) || ($entry['pdf'] !== '' && !preg_match('#^/uploads/scholarships/[a-f0-9]{32}\.pdf$#D', $entry['pdf'])))) throw new InvalidArgumentException('Invalid scholarship PDF.');
        foreach (['id','category','title','description','url'] as $key) if (!isset($entry[$key]) || !is_string($entry[$key])) throw new InvalidArgumentException('Invalid scholarship details.');
        if (!preg_match('/^[a-f0-9]{16}$/D', $entry['id']) || isset($seen[$entry['id']])) throw new InvalidArgumentException('Invalid or duplicate scholarship ID.');
        $seen[$entry['id']] = true;
        if (!isset($categories[$entry['category']])) throw new InvalidArgumentException('Choose a scholarship category.');
        foreach (['eligibility','benefits','application','information'] as $key) {
            if (!isset($entry[$key])) continue;
            if (!is_string($entry[$key])) throw new InvalidArgumentException('Invalid scholarship section.');
            $entry[$key] = trim(strip_tags($entry[$key]));
            if ((function_exists('mb_strlen') ? mb_strlen($entry[$key]) : strlen($entry[$key])) > 6000) throw new InvalidArgumentException('Scholarship sections must be within 6000 characters.');
        }
        foreach (['title'=>255,'description'=>6000] as $key => $max) {
            $entry[$key] = trim(strip_tags($entry[$key]));
            $length = function_exists('mb_strlen') ? mb_strlen($entry[$key]) : strlen($entry[$key]);
            if ($length > $max || ($key === 'title' && $entry[$key] === '')) throw new InvalidArgumentException('Enter a scholarship title and keep the details within the allowed length.');
        }
        $entry['url'] = trim($entry['url']);
        if (strlen($entry['url']) > 2048 || ($entry['url'] !== '' && (
            preg_match('/[\s\x00-\x1f\x7f<>"\\\\]/', $entry['url'])
            || !(str_starts_with($entry['url'], '/') && !str_starts_with($entry['url'], '//')
                || filter_var($entry['url'], FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($entry['url'], PHP_URL_SCHEME)), ['http','https'], true))
        ))) throw new InvalidArgumentException('Enter an HTTP or HTTPS link, or a local path starting with /.');
    }
    unset($entry);
    if (strlen(json_encode($entries, JSON_THROW_ON_ERROR)) > 60000) throw new InvalidArgumentException('Scholarship details are too large to save. Shorten the descriptions and try again.');
    return $entries;
}

function scholarshipEntries(?PDO $db = null): array {
    $values = scholarshipStoredValues('scholarship_entries', $db);
    if (!isset($values['scholarship_entries'])) return [];
    return scholarshipValidateEntries(json_decode($values['scholarship_entries'], true, 16, JSON_THROW_ON_ERROR), scholarshipCategories($db));
}

function scholarshipValidateCategories(array $categories): array {
    if (!array_is_list($categories) || !$categories || count($categories)>50) throw new InvalidArgumentException('Use between 1 and 50 scholarship categories.');
    $result=[]; $names=[];
    foreach ($categories as $category) {
        if (!is_array($category) || !is_string($category['id']??null) || !preg_match('/^[a-z][a-z0-9-]{0,39}$/D',$category['id']) || isset($result[$category['id']])) throw new InvalidArgumentException('Invalid or duplicate scholarship category.');
        $label=$category['label']??null;
        if (!is_string($label) || trim($label)==='' || strip_tags($label)!==$label || (function_exists('mb_strlen')?mb_strlen($label):strlen($label))>100) throw new InvalidArgumentException('Enter a category name of up to 100 characters.');
        $label=trim($label);$name=function_exists('mb_strtolower')?mb_strtolower($label):strtolower($label);
        if(isset($names[$name]))throw new InvalidArgumentException('Use unique scholarship category names.');
        $result[$category['id']]=['id'=>$category['id'],'label'=>$label];$names[$name]=true;
        if (isset($category['icon'])) {
            if (!is_string($category['icon']) || !isset(tuitionFeeCategoryIcons()[$category['icon']])) throw new InvalidArgumentException('Choose a category icon from the list.');
            $result[$category['id']]['icon']=$category['icon'];
        }
    }
    return $result;
}

function scholarshipCategoriesEnabled(PDO $db): bool {
    try {
        $query = $db->prepare('SELECT 1 FROM content_migrations WHERE migration_key = ?');
        $query->execute(['scholarship-categories-v1']);
        return (bool)$query->fetchColumn();
    } catch (PDOException $error) { if ($error->getCode() !== '42S02') throw $error; return false; }
}

/** Compatibility reader used before migration and to seed existing category IDs. */
function scholarshipLegacyCategories(?PDO $db = null): array {
    $values=scholarshipStoredValues('scholarship_categories',$db);
    if(isset($values['scholarship_categories'])) return scholarshipValidateCategories(json_decode($values['scholarship_categories'],true,16,JSON_THROW_ON_ERROR));
    $fields=scholarshipFields($db);
    return ['gnc'=>['id'=>'gnc','label'=>$fields['copy_3']['value']], 'government'=>['id'=>'government','label'=>$fields['copy_4']['value']]];
}

function scholarshipCategories(?PDO $db = null): array {
    $db = $db ?? getDB();
    if (!scholarshipCategoriesEnabled($db)) return scholarshipLegacyCategories($db);
    $rows = $db->query('SELECT category_id AS id,category_name AS label FROM scholarship_categories ORDER BY display_order,category_id')->fetchAll(PDO::FETCH_ASSOC);
    // Names/order stay authoritative in normalized storage; optional icons use
    // the existing compatibility record, so no schema migration is required.
    $values=scholarshipStoredValues('scholarship_categories',$db);
    $stored=json_decode($values['scholarship_categories']??'[]',true);
    $icons=[];
    foreach (is_array($stored)?$stored:[] as $category) {
        if (is_array($category) && is_string($category['id']??null) && is_string($category['icon']??null) && isset(tuitionFeeCategoryIcons()[$category['icon']])) $icons[$category['id']]=$category['icon'];
    }
    foreach ($rows as &$row) if (isset($icons[$row['id']])) $row['icon']=$icons[$row['id']];
    unset($row);
    return scholarshipValidateCategories($rows);
}

/** Caller owns the transaction shared with entries, PDF references and page text. */
function scholarshipSaveCategories(PDO $db, array $categories): void {
    if (!$db->inTransaction()) throw new LogicException('Save scholarship categories inside a transaction.');
    $categories = scholarshipValidateCategories(array_values($categories));
    if (scholarshipCategoriesEnabled($db)) {
        $save = $db->prepare('INSERT INTO scholarship_categories (category_id,category_name,display_order) VALUES (?,?,?) ON DUPLICATE KEY UPDATE category_name=VALUES(category_name),display_order=VALUES(display_order)');
        foreach (array_values($categories) as $order=>$category) $save->execute([$category['id'],$category['label'],$order]);
        $delete = $db->prepare('DELETE FROM scholarship_categories WHERE category_id NOT IN (' . implode(',',array_fill(0,count($categories),'?')) . ')');
        $delete->execute(array_keys($categories));
    }
    // Keep old readers and rollback compatible without making JSON authoritative.
    $mirror = $db->prepare('INSERT INTO tuition_fee_content (field_key,content) VALUES (?,?) ON DUPLICATE KEY UPDATE content=VALUES(content)');
    $mirror->execute(['scholarship_categories',json_encode(array_values($categories),JSON_THROW_ON_ERROR)]);
}

/** Bring existing editable scholarship information into the catalog without rewriting it. */
function scholarshipCatalog(?PDO $db = null): array {
    $entries=scholarshipEntries($db);
    $version=scholarshipStoredValues('scholarship_catalog_version',$db);
    if(isset($version['scholarship_catalog_version']))return $entries;
    $fields=scholarshipFields($db);$legacy=[];
    foreach(['basic'=>['0000000000000001','copy_10'], 'college'=>['0000000000000002','copy_13'], 'athletic'=>['0000000000000003','copy_8']] as $kind=>[$id,$key]) {
        if(in_array($id,array_column($entries,'id'),true))continue;
        $legacy[]=['id'=>$id,'category'=>'gnc','title'=>$fields[$key]['value'],'description'=>'','url'=>'','legacy'=>$kind];
    }
    return array_merge($legacy,$entries);
}
