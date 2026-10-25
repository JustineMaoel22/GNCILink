<?php
require_once __DIR__.'/../includes/content-publish-policy.php';
/** Basic Education content uses the existing section-based Content Editor. */
function basicEnrollmentDefaults(): array {
    return [
        'basic_education_title' => ['title' => 'Basic Education heading', 'content' => 'Basic Education Enrollment Procedures'],
        'basic_education_procedures' => ['title' => 'Basic Education enrollment procedures', 'content' => '<p>Please contact the school office for the enrollment procedures for your Basic Education department.</p><p><a href="/pages/admission/basic-education-admission-req.php">View Basic Education admission requirements</a></p>'],
    ];
}

/** The illustrations already used by the enrollment guide are available for reuse. */
function basicEnrollmentIllustrations(): array {
    $images = [];
    foreach (glob(__DIR__ . '/../assets/images/svg/enrollment-procedures/*.svg') as $file) {
        $name = basename($file);
        $images['/assets/images/svg/enrollment-procedures/' . $name] = ucfirst(str_replace(['fresh-', 'trans-', 'old-', '.svg'], ['Freshmen ', 'Transferee ', 'Old student ', ''], $name));
    }
    return $images;
}

function basicEnrollmentSteps(bool $lock = false): array {
    try {
        return getDB()->query('SELECT step_id, title, location, instruction, illustration FROM basic_enrollment_steps ORDER BY display_order, step_id' . ($lock ? ' FOR UPDATE' : ''))->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if (!$lock && $e->getCode() === '42S02') return [];
        throw $e;
    }
}

function basicEnrollmentRevision(array $steps): string {
    return hash('sha256', json_encode($steps, JSON_THROW_ON_ERROR));
}

/** Never accept an arbitrary client-supplied file path or reuse another step's upload. */
function basicEnrollmentValidateSteps(array $steps, array $existing): array {
    if (!array_is_list($steps) || count($steps) > 40) throw new InvalidArgumentException('You can publish up to 40 steps.');
    $known = array_column($existing, null, 'step_id');
    $illustrations = basicEnrollmentIllustrations();
    $clean = []; $seen = [];
    foreach ($steps as $step) {
        if (!is_array($step) || !is_string($step['step_id'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D', $step['step_id']) || isset($seen[$step['step_id']])) throw new InvalidArgumentException('Invalid or duplicate step. Reload the page.');
        $seen[$step['step_id']] = true;
        $row = ['step_id' => $step['step_id']];
        foreach (['title' => 150, 'location' => 150, 'instruction' => 2000] as $key => $max) {
            if (!is_string($step[$key] ?? null)) throw new InvalidArgumentException('Invalid step text.');
            $row[$key] = trim($step[$key]);
            if (mb_strlen($row[$key]) > $max || ($key !== 'location' && $row[$key] === '' && !contentPublishAllowsIncomplete())) throw new InvalidArgumentException('Each step needs an office/title and instructions within the field limits.');
        }
        $image = $step['illustration'] ?? '';
        if (!is_string($image) || ($image !== '' && !isset($illustrations[$image]) && $image !== ($known[$row['step_id']]['illustration'] ?? null))) throw new InvalidArgumentException('Choose an existing illustration or upload a new one.');
        $row['illustration'] = $image;
        $clean[] = $row;
    }
    return $clean;
}

/** Publish the ordered collection atomically; failed uploads never change live steps. */
function publishBasicEnrollmentSteps(array $steps, string $revision, array $files = [], ?array &$transactionUploads = null): void {
    if (!hasPermission('manage_page_content')) throw new InvalidArgumentException('You cannot publish enrollment steps.');
    $db = getDB(); $stored = [];
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) $db->beginTransaction();
    try {
        $existing = basicEnrollmentSteps(true);
        if (!hash_equals(basicEnrollmentRevision($existing), $revision)) throw new InvalidArgumentException('The steps changed in another window. Reload before publishing.');
        $steps = basicEnrollmentValidateSteps($steps, $existing);
        $known = array_column($existing, null, 'step_id');
        $keep = [];
        foreach ($steps as $order => $step) {
            $id = $step['step_id']; $keep[$id] = true;
            $file = $files['illustration_' . $id] ?? null;
            if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try { $upload = storeDepartmentUpload($file, 'enrollment_illustration'); }
                catch (Exception $e) { throw new InvalidArgumentException($e->getMessage(), 0, $e); }
                $stored[] = $upload;
                $transactionUploads[] = $upload;
                $step['illustration'] = $upload['path'];
            }
            if (isset($known[$id]) && $step['illustration'] !== $known[$id]['illustration']) $db->prepare('DELETE FROM enrollment_illustrations WHERE image_key=?')->execute(['basic-'.$id]);
            if (isset($known[$id])) {
                $db->prepare('UPDATE basic_enrollment_steps SET title=?,location=?,instruction=?,illustration=?,display_order=?,updated_by=? WHERE step_id=?')
                    ->execute([$step['title'], $step['location'], $step['instruction'], $step['illustration'], $order, $_SESSION['user_id'], $id]);
            } else {
                $db->prepare('INSERT INTO basic_enrollment_steps (title,location,instruction,illustration,display_order,updated_by,step_id) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$step['title'], $step['location'], $step['instruction'], $step['illustration'], $order, $_SESSION['user_id'], $id]);
            }
        }
        foreach ($known as $id => $step) if (!isset($keep[$id])) {
            $db->prepare('DELETE FROM basic_enrollment_steps WHERE step_id=?')->execute([$id]);
            $db->prepare('DELETE FROM enrollment_illustrations WHERE image_key=?')->execute(['basic-'.$id]);
        }
        logActivity((int)$_SESSION['user_id'], 'PUBLISH', 'basic_enrollment_steps', null, 'Published Basic Education enrollment steps');
        if ($ownsTransaction) $db->commit();
    } catch (Throwable $e) {
        if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
        // Media records were part of the transaction. Remove only this failed request's new files.
        foreach ($stored as $upload) if (!empty($upload['abs'])) @unlink($upload['abs']);
        throw $e;
    }
}

/** College defaults and Basic Education's managed images share the inline illustration tools. */
function enrollmentIllustrations(?array $basicSteps = null, bool $lock = false): array {
    $images = []; $overrides = [];
    foreach (['fresh'=>['Incoming Freshmen',10], 'trans'=>['Transferees',9], 'old'=>['Old Students',7]] as $prefix => [$label,$count]) {
        for ($i=1; $i<=$count; $i++) {
            $key = $prefix . '-' . $i;
            $url = '/assets/images/svg/enrollment-procedures/' . $key . '.svg';
            $images[$key] = ['label'=>$label . ' - Step ' . $i, 'url'=>$url, 'default'=>$url];
        }
    }
    try {
        $rows = getDB()->query('SELECT image_key, image_path FROM enrollment_illustrations' . ($lock ? ' FOR UPDATE' : ''))->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $overrides[$row['image_key']] = $row['image_path'];
            if (isset($images[$row['image_key']]) && preg_match('#^/uploads/enrollment-procedures/[a-f0-9]{32}\.(svg|png|jpg|webp)$#D', $row['image_path'])) $images[$row['image_key']]['url'] = $row['image_path'];
        }
    } catch (PDOException $e) { if ($lock || $e->getCode() !== '42S02') throw $e; }
    foreach ($basicSteps ?? basicEnrollmentSteps() as $index => $step) {
        $fallback = isset(basicEnrollmentIllustrations()[$step['illustration']]) ? $step['illustration'] : '/assets/images/svg/enrollment-procedures/fresh-1.svg';
        $savedDefault = $overrides['basic-' . $step['step_id']] ?? '';
        if (isset(basicEnrollmentIllustrations()[$savedDefault])) $fallback = $savedDefault;
        $images['basic-' . $step['step_id']] = ['label'=>'Basic Education - Step ' . ($index+1), 'url'=>$step['illustration'], 'default'=>$fallback];
    }
    return $images;
}

function enrollmentIllustration(array $images, string $key, string $alt): string {
    $image = $images[$key];
    if ($image['url'] === '' && !cePreviewCanManage()) return '';
    $url = $image['url'] ?: $image['default'];
    return '<div class="enrollment-step-icon skeleton-wrap"' . ceManageMark('data-ve-img="enrollment-illustration" data-ve-key="' . ceAttr($key) . '" data-ve-label="' . ceAttr($image['label']) . '"') . '><img src="' . ceAttr($url) . '" alt="' . ceAttr($alt) . '" width="100" height="100" loading="lazy"></div>';
}

function publishEnrollmentIllustrations(array $changes, array $files, ?array &$transactionUploads = null): array {
    if (!hasPermission('manage_page_content')) throw new InvalidArgumentException('You cannot publish enrollment illustrations.');
    if (count($changes)>66) throw new InvalidArgumentException('Too many illustration changes.');
    $db=getDB(); $uploads=[];
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) $db->beginTransaction();
    try {
        $basic=basicEnrollmentSteps(true);
        $images=enrollmentIllustrations($basic,true);
        foreach ($changes as $key=>$change) {
            if (!isset($images[$key]) || !is_array($change) || !is_bool($change['reset'] ?? null) || !is_string($change['expected'] ?? null)) throw new InvalidArgumentException('Invalid enrollment illustration.');
            if ($change['expected'] !== $images[$key]['url']) throw new InvalidArgumentException('This illustration changed in another window. Reload before publishing.');
        }
        foreach ($changes as $key=>$change) {
            $url=$images[$key]['default'];
            if (!$change['reset']) {
                $file=$files['image_'.$key] ?? null;
                if (!is_array($file)) throw new InvalidArgumentException('Choose an illustration to upload.');
                try { $upload=storeDepartmentUpload($file,'enrollment_illustration'); }
                catch (Exception $e) { throw new InvalidArgumentException($e->getMessage(),0,$e); }
                $uploads[]=$upload; $transactionUploads[]=$upload; $url=$upload['path'];
            }
            if (str_starts_with($key,'basic-')) {
                if ($change['reset']) $db->prepare('DELETE FROM enrollment_illustrations WHERE image_key=?')->execute([$key]);
                else $db->prepare('INSERT INTO enrollment_illustrations (image_key,image_path) VALUES (?,?) ON DUPLICATE KEY UPDATE image_path=VALUES(image_path)')->execute([$key,$images[$key]['default']]);
                $db->prepare('UPDATE basic_enrollment_steps SET illustration=?,updated_by=? WHERE step_id=?')->execute([$url,$_SESSION['user_id'],substr($key,6)]);
            } elseif ($change['reset']) {
                $db->prepare('DELETE FROM enrollment_illustrations WHERE image_key=?')->execute([$key]);
            } else {
                $db->prepare('INSERT INTO enrollment_illustrations (image_key,image_path) VALUES (?,?) ON DUPLICATE KEY UPDATE image_path=VALUES(image_path)')->execute([$key,$url]);
            }
            $images[$key]['url']=$url;
        }
        if ($changes) logActivity((int)$_SESSION['user_id'],'PUBLISH','enrollment_illustrations',null,'Published enrollment illustrations');
        if ($ownsTransaction) $db->commit(); return $images;
    } catch (Throwable $e) {
        if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
        foreach ($uploads as $upload) if (!empty($upload['abs'])) @unlink($upload['abs']);
        throw $e;
    }
}

/** Publish canvas step edits and illustration replacements as a single snapshot. */
function publishEnrollmentCanvas(?array $steps, string $revision, array $changes, array $files): array {
    if (!hasPermission('manage_page_content')) throw new InvalidArgumentException('You cannot publish enrollment steps.');
    $db = getDB(); $uploads = [];
    $db->beginTransaction();
    try {
        if ($steps !== null) publishBasicEnrollmentSteps($steps, $revision, [], $uploads);
        $images = publishEnrollmentIllustrations($changes, $files, $uploads);
        $published = basicEnrollmentSteps();
        $result = ['steps'=>$published, 'revision'=>basicEnrollmentRevision($published), 'images'=>$images];
        $db->commit(); return $result;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        foreach ($uploads as $upload) if (!empty($upload['abs'])) @unlink($upload['abs']);
        throw $e;
    }
}
