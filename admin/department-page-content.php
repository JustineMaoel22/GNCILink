<?php
require_once __DIR__.'/../includes/content-publish-policy.php';

/** Banner media uses the existing per-department content storage, separate from editable text. */
function departmentBanner(array $dept): ?array {
    try {
        $stmt = getDB()->prepare("SELECT m.media_id, m.file_path FROM department_page_content c JOIN media_library m ON m.media_id = c.content WHERE c.dept_id = ? AND c.field_key = 'banner_media_id'");
        $stmt->execute([(int)$dept['dept_id']]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        return $media && departmentFileExists($media['file_path']) ? $media : null;
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S02') throw $e;
        return null;
    }
}

function saveDepartmentBanner(int $id, ?array $file, bool $remove): array {
    $dept = getDepartmentById($id);
    if (!$dept) throw new InvalidArgumentException('Department not found.');
    $old = departmentBanner($dept);
    $upload = $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ? storeDepartmentUpload($file, 'department_banner') : null;
    if (!$upload && !$remove) return $dept;
    try {
        $stmt = getDB()->prepare("INSERT INTO department_page_content (dept_id, field_key, content) VALUES (?, 'banner_media_id', ?) ON DUPLICATE KEY UPDATE content = VALUES(content)");
        $stmt->execute([$id, $upload ? (string)$upload['media_id'] : '']);
    } catch (Throwable $e) {
        discardStoredDepartmentUpload($upload);
        throw new RuntimeException('Could not save the department banner. Please try again.');
    }
    if ($old) deleteMediaIfUnreferenced((int)$old['media_id']);
    logActivity($_SESSION['user_id'], 'UPDATE', 'departments', $id, 'Updated department header banner');
    return $dept;
}
/** Editable section text shared by every department detail page. */
function departmentPageContent(array $dept, bool $refresh = false): array {
    static $cache = [];
    $id = (int)$dept['dept_id'];
    if (!$refresh && isset($cache[$id])) return $cache[$id];
    $name = (string)$dept['dept_name'];
    $defaults = [
        'hero_title' => [$name, 'Page heading', 255],
        'overview_label' => ['Overview', 'Overview label', 100],
        'overview_title' => ['About the ' . $name, 'Overview heading', 255],
        'overview_empty' => ['Information about this department will be published soon. Please check back later.', 'Empty overview message', 2000],
        'vision_title' => ['Our Vision', 'Vision heading', 100],
        'vision_text' => ['', 'Department vision', 10000],
        'vision_icon' => ['bi:eye', 'Vision icon', 60],
        'mission_title' => ['Our Mission', 'Mission heading', 100],
        'mission_text' => ['', 'Department mission', 10000],
        'mission_icon' => ['bi:eye', 'Mission icon', 60],
        'programs_label' => ['Our Programs', 'Programs label', 100],
        'programs_title' => ['Programs Offered', 'Programs heading', 255],
        'programs_empty' => ['Programs for this department will be listed here soon.', 'Empty programs message', 2000],
        'program_link' => ['View Details', 'Program link label', 100],
        'leadership_label' => ['Leadership & Organization', 'Leadership label', 100],
        'leadership_title' => ['Head of Office', 'Leadership heading', 255],
        'leadership_intro' => ['Meet the visionary leader guiding the ' . $name . ' in advancing academic excellence, innovation, and service.', 'Leadership introduction', 2000],
        'profile_label' => ['Meet the ' . (trim((string)($dept['dean_title'] ?? '')) ?: 'Head of Office'), 'Profile label', 255],
        'profile_department' => [$name, 'Department on photo', 255],
        'contacts_label' => ['Get Connected', 'Contacts heading', 100],
        'phone_label' => ['Contact Information', 'Phone label', 100],
        'email_label' => ['Email Address', 'Email label', 100],
        'hours_label' => ['Operating Hours', 'Hours label', 100],
        'facebook_label' => ['Stay Updated', 'Facebook label', 100],
        'facebook_title' => ['Connect with Us', 'Facebook heading', 255],
        'facebook_intro' => ['If you have concerns or need assistance feel free to message our page.', 'Facebook introduction', 2000],
    ];
    if (($dept['department_type'] ?? '') === 'basic-education') {
        foreach (['overview_label', 'overview_title', 'leadership_label', 'leadership_title', 'leadership_intro', 'profile_label', 'contacts_label', 'overview_empty', 'programs_empty'] as $key) $defaults[$key][0] = '';
    }
    $fields = [];
    foreach ($defaults as $key => [$value, $label, $max]) $fields[$key] = ['value' => $value, 'label' => $label, 'max' => $max];
    try {
        $stmt = getDB()->prepare('SELECT field_key, content FROM department_page_content WHERE dept_id = ?');
        $stmt->execute([$id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($fields[$row['field_key']])) $fields[$row['field_key']]['value'] = (string)$row['content'];
        }
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S02') throw $e;
    }
    return $cache[$id] = $fields;
}

function departmentPageField(array $dept, string $key): string {
    $field = departmentPageContent($dept)[$key];
    return ceField('dept-page-text', ['key' => $key, 'label' => $field['label'], 'max' => $field['max'],
        'multiline' => $field['max'] > 255, 'rich'=>$field['max']>255, 'ph' => 'Add ' . strtolower($field['label']),
        'optional' => str_ends_with($key, '_empty') || str_starts_with($key, 'facebook_') || in_array($key, ['vision_title', 'vision_text', 'mission_title', 'mission_text'], true)]);
}

function departmentPageText(array $dept, string $key): string {
    $value = departmentPageContent($dept)[$key]['value'];
    return departmentPageContent($dept)[$key]['max']>255?renderSectionContent($value):nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
}

/** Render only library icons; stored values never become arbitrary markup. */
function departmentStatementIcon(array $dept, string $key): string {
    $spec = departmentPageContent($dept)[$key]['value'];
    $choices = academicFeatureIconChoices();
    $choice = $choices[$spec] ?? $choices['bi:eye'];
    $h = static fn($value) => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    if (isset($choice['material'])) return '<span class="material-symbols-outlined dept-statement-icon-glyph" aria-hidden="true">' . $h($choice['material']) . '</span>';
    return '<i class="bi bi-' . $h($choice['glyph'] ?? 'eye') . ' dept-statement-icon-glyph" aria-hidden="true"></i>';
}

function saveDepartmentPageContent(int $id, array $changes): array {
    $dept = getDepartmentById($id);
    if (!$dept) throw new InvalidArgumentException('Department not found.');
    $fields = departmentPageContent($dept);
    foreach ($changes as $key => &$text) {
        if (!isset($fields[$key]) || !is_string($text)) throw new InvalidArgumentException('Unknown department text field.');
        if (in_array($key, ['vision_icon', 'mission_icon'], true)) {
            $choice = academicFeatureIconChoices()[$text] ?? null;
            if (!$choice || isset($choice['path'])) throw new InvalidArgumentException('Choose an icon from the icon library.');
        }
        if($fields[$key]['max']>255)$text=richTextNormalize($text);
        if (mb_strlen($text) > $fields[$key]['max']) throw new InvalidArgumentException($fields[$key]['label'] . ' is too long.');
    }unset($text);
    $db = getDB();
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO department_page_content (dept_id, field_key, content) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content)');
        foreach ($changes as $key => $text) $stmt->execute([$id, $key, trim($text)]);
        if ($ownsTransaction) $db->commit();
    } catch (Throwable $e) {
        if ($ownsTransaction) $db->rollBack();
        throw $e;
    }
    return departmentPageContent($dept, true);
}

/** Update only the card fields; full program-page content and settings stay intact. */
function saveDepartmentProgramCard(array $data, ?array $image = null): array {
    $deptId = (int)($data['dept_id'] ?? 0);
    if ($deptId <= 0 || !getDepartmentById($deptId)) throw new InvalidArgumentException('Department not found.');
    assertProgramDepartmentAllowed($deptId);
    $id = (int)($data['program_id'] ?? 0);
    if ($id < 0) throw new InvalidArgumentException('Invalid program.');
    if (!is_string($data['program_name'] ?? null) || !is_string($data['description'] ?? '')) throw new InvalidArgumentException('Invalid program card text.');
    $name = trim($data['program_name']);
    $description = richTextNormalize($data['description'] ?? '');
    if (($name === '' && !contentPublishAllowsIncomplete()) || mb_strlen($name) > 255) throw new InvalidArgumentException('Enter a program heading of up to 255 characters.');
    if (mb_strlen($description) > 5000) throw new InvalidArgumentException('The program description is too long (maximum 5000 characters).');
    $db = getDB();
    $current = null;
    if ($id > 0) {
        $stmt = $db->prepare('SELECT * FROM programs WHERE program_id = ? AND dept_id = ?');
        $stmt->execute([$id, $deptId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$current) throw new InvalidArgumentException('Program not found in this department.');
    }
    $upload = $image && ($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? storeProgramImageUpload($image) : null;
    $imageId = $upload['media_id'] ?? (!empty($data['remove_image']) ? null : ($current['image_media_id'] ?? null));
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) $db->beginTransaction();
    try {
        if (!$current) {
            // Program cards need no extra form: derive an unused internal code from the heading.
            $words = preg_split('/[^A-Za-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY);
            $base = strtoupper(substr(implode('', array_map(fn($word) => $word[0], $words)), 0, 6));
            if (strlen($base) < 2) $base = 'PRG';
            $code = $base;
            $check = $db->prepare('SELECT 1 FROM programs WHERE program_code = ?');
            for ($suffix = 2; ; $suffix++) {
                $check->execute([$code]);
                if (!$check->fetchColumn()) break;
                if ($suffix > 9999) throw new RuntimeException('Could not assign a program code.');
                $code = $base . $suffix;
            }
            $id = createProgramPage(['dept_id' => $deptId, 'program_name' => $name, 'program_code' => $code, 'theme' => 'green']);
        }
        $stmt = $db->prepare('UPDATE programs SET program_name = ?, description = ?, image_media_id = ? WHERE program_id = ? AND dept_id = ?');
        $stmt->execute([$name, $description, $imageId, $id, $deptId]);
        if ($ownsTransaction) $db->commit();
    } catch (Throwable $e) {
        if ($ownsTransaction) $db->rollBack();
        discardStoredDepartmentUpload($upload);
        throw $e;
    }
    if (!empty($current['image_media_id']) && (int)$current['image_media_id'] !== (int)$imageId) deleteMediaIfUnreferenced((int)$current['image_media_id']);
    return ['program_id' => $id, 'program_name' => $name, 'description' => $description];
}
