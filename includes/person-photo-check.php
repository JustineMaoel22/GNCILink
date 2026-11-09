<?php
require_once __DIR__.'/../admin/settings-functions.php';
/** Local-only personnel comparison. No embeddings are persisted or returned to browsers. */
function personPhotoName(string $name): string {
    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)), 'UTF-8');
}

function personPhotoPath(?string $path): ?string {
    if (!$path) return null;
    $root = realpath(__DIR__ . '/..');
    $file = realpath($root . '/' . ltrim($path, '/'));
    if (!$file || !is_file($file)) return null;
    $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
    if (strpos($file, $root . DIRECTORY_SEPARATOR) !== 0
        || !preg_match('~^(uploads/|assets/images/).+\.(jpe?g|png|webp)$~i', $relative)) return null;
    return $file;
}

function personPhotoInventory(PDO $db): array {
    // Include archived profiles too: their images can return through restoration.
    $sql = "SELECT CONCAT('person:',p.person_id) AS profile_key,p.name,COALESCE(m.file_path,p.image_path) AS image
        FROM admin_people p LEFT JOIN media_library m ON m.media_id=p.media_id
        UNION ALL SELECT CONCAT('office:',o.office_id),o.head_name,COALESCE(m.file_path,o.image_path)
        FROM admin_offices o LEFT JOIN media_library m ON m.media_id=o.media_id
        UNION ALL SELECT CONCAT('dean:',d.dept_id),d.dean_name,m.file_path
        FROM departments d LEFT JOIN media_library m ON m.media_id=d.dean_media_id
        UNION ALL SELECT 'president:1',p.name,m.file_path
        FROM homepage_president p LEFT JOIN media_library m ON m.media_id=p.media_id
        ORDER BY profile_key";
    $rows=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('personnelEnabled') && personnelEnabled($db)) {
        $identities=personnelProfiles($db);
        foreach($rows as &$row) {
            $identity=$identities[$row['profile_key']]??null;
            $row['personnel_id']=$identity['personnel_id']??null;
            if($row['personnel_id']!==null)$row['name']=$identity['full_name'];
        }
        unset($row);
    }
    return $rows;
}

function personPhotoUnavailable(): array {
    return ['checked'=>false,'ok'=>false,'reason'=>'unavailable',
        'message'=>'Face comparison is unavailable. Please try again later or contact your administrator.'];
}

function personPhotoWorker(string $path, array $profiles): array {
    $python = function_exists('env') ? env('FACE_CHECK_PYTHON', '') : getenv('FACE_CHECK_PYTHON');
    $models = function_exists('env') ? env('FACE_CHECK_MODELS', '') : getenv('FACE_CHECK_MODELS');
    if (!$python || !$models || !function_exists('proc_open')) throw new RuntimeException('Face runtime not configured');
    $process = proc_open([$python, __DIR__.'/../scripts/person-face-check.py', '--models', $models],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, null, null, ['bypass_shell'=>true]);
    if (!is_resource($process)) throw new RuntimeException('Face runtime unavailable');
    try {
        fwrite($pipes[0], json_encode(['image'=>$path,'profiles'=>$profiles], JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = ''; $deadline = microtime(true) + 45;
        do {
            $out .= stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]); // Drain private diagnostics, never expose them.
            if (strlen($out) > 1048576 || microtime(true) > $deadline) throw new RuntimeException('Face runtime limit');
            $status = proc_get_status($process);
            if (!$status['running']) break;
            usleep(10000);
        } while (true);
        $out .= stream_get_contents($pipes[1]);
        if ($out === '') throw new RuntimeException('Face worker exited without output (exit ' . (int)($status['exitcode'] ?? -1) . ', signal ' . (int)($status['termsig'] ?? 0) . ')');
        $result = json_decode($out, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($result) || ($result['complete'] ?? false) !== true) throw new RuntimeException('Incomplete comparison');
        return $result;
    } finally {
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        if (proc_get_status($process)['running']) proc_terminate($process);
        proc_close($process);
    }
}

/** Thresholds are cosine similarities, never probabilities or proof of identity. */
function personPhotoDecision(array $scores, array $profiles): array {
    $matches = [];
    foreach ($profiles as $profile) {
        $score = $scores[$profile['profile_key']] ?? null;
        if (!is_numeric($score) || !is_finite((float)$score) || $score < -1 || $score > 1) throw new RuntimeException('Invalid comparison');
        if ($score >= 0.363) {
            $matches[] = ['key'=>$profile['profile_key'],'name'=>$profile['name'],'image'=>$profile['image'],
                'similarity'=>round((float)$score, 3),'high_confidence'=>$score >= 0.55];
        }
    }
    usort($matches, static fn($a,$b) => $b['similarity'] <=> $a['similarity']);
    $blocked = count(array_filter($matches, static fn($m) => $m['high_confidence'])) > 0;
    return ['checked'=>true,'ok'=>!$blocked,'reason'=>$matches ? 'duplicate_face' : 'clear',
        'matches'=>$matches,'message'=>$matches
            ? "Possible Duplicate Photo Detected: This person's face appears to be already assigned to another profile (".$matches[0]['name']."). Please verify that you uploaded the correct photo."
            : ''];
}

function personPhotoCheck(string $path, string $name, string $key = '', ?callable $compare = null): array {
    try {
        // The shared switch covers browser prechecks and server-side saves,
        // including retained portraits checked after a rename or restoration.
        if (!aiImageCheckerEnabled()) return ['checked'=>false,'ok'=>true,'reason'=>'disabled','message'=>''];
        if (personPhotoName($name) === '') return ['checked'=>false,'ok'=>false,'reason'=>'name_required','message'=>'Enter the person’s full name before checking their photo.'];
        $profiles = []; $fingerprints = []; $missing = 0;
        $normalized=function_exists('personnelEnabled') && personnelEnabled(getDB());
        $identities=$normalized?personnelProfiles(getDB()):[];
        $identity=(int)($GLOBALS['personnel_write_identity'][$key]??($identities[$key]['personnel_id']??0));
        foreach (personPhotoInventory(getDB()) as $profile) {
            if ($profile['profile_key'] === $key) continue;
            if ($normalized ? ($identity>0 && (int)($profile['personnel_id']??0)===$identity) : personPhotoName((string)$profile['name']) === personPhotoName($name)) continue;
            if (empty($profile['image'])) continue;
            $local = personPhotoPath($profile['image']);
            if (!$local) {
                // The public renderer already treats these legacy records as having no photo.
                $missing++;
                $fingerprints[] = [$profile['profile_key'],$profile['name'],'missing'];
                continue;
            }
            $profile['path'] = $local;
            $profiles[] = $profile;
            $fingerprints[] = [$profile['profile_key'],$profile['name'],hash_file('sha256',$local),$profile['personnel_id']??null];
        }
        $worker = ($compare ?? 'personPhotoWorker')($path, $profiles);
        if (($worker['faces'] ?? 0) !== 1) return ['checked'=>true,'ok'=>false,'reason'=>'invalid_face',
            'message'=>'Please choose a clear portrait containing exactly one visible face.'];
        $result = personPhotoDecision($worker['scores'] ?? [], $profiles);
        $result['coverage_notice'] = $missing ? $missing . ' existing profile photo(s) are missing and could not be compared. Replace those photos to restore full coverage.' : '';
        $hash = hash_file('sha256',$path);
        $hash = $GLOBALS['imageUploadOriginalHashes'][$hash] ?? $hash;
        $reviewKey = hash('sha256', json_encode([$hash,personPhotoName($name),$key,$fingerprints]));
        $result['review_key'] = $reviewKey;
        $result['can_review'] = rbacIsSuper();
        if (rbacIsSuper() && ($_SESSION['person_photo_reviews'][$reviewKey] ?? 0) > time()) {
            $result['ok'] = true;
            $result['reviewed'] = true;
        }
        return $result;
    } catch (Throwable $e) {
        // Log only known internal categories, never raw paths, SQL or user data.
        $reason = $e->getMessage();
        $safeReasons = ['Face runtime not configured', 'Face runtime unavailable', 'Face runtime limit', 'Incomplete comparison', 'Invalid comparison'];
        if (!in_array($reason, $safeReasons, true)
            && !preg_match('/\AFace worker exited without output \(exit -?\d+, signal \d+\)\z/', $reason)) {
            $reason = $e instanceof JsonException ? 'Invalid worker JSON' : 'Comparison preparation failed';
        }
        error_log('Personnel face comparison unavailable: ' . $reason);
        return personPhotoUnavailable();
    }
}

/** Called before any personnel write, including renames without a new upload. */
function personPhotoEnforce(?array $upload, bool $remove, ?string $existing, string $name, string $key): void {
    // Serialize personnel writes across sessions; hold the DB lock through the subsequent save.
    static $locked = false;
    if (!$locked) {
        if ((int)getDB()->query("SELECT GET_LOCK('gnci_person_photo_save', 5)")->fetchColumn() !== 1) {
            throw new RuntimeException('Another personnel photo is being saved. Please try again.');
        }
        $locked = true;
        register_shutdown_function(static function () { getDB()->query("SELECT RELEASE_LOCK('gnci_person_photo_save')"); });
    }
    $hasUpload = $upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($hasUpload) $upload = imageUploadPrepare($upload);
    if (!$hasUpload && $remove) return;
    if (!$hasUpload && !$existing) return;
    $path = $hasUpload ? ($upload['tmp_name'] ?? '') : personPhotoPath($existing);
    if (!$path || ($hasUpload && (($upload['error'] ?? -1) !== UPLOAD_ERR_OK || !is_uploaded_file($path)))) {
        throw new RuntimeException('The personnel photo could not be read. Please upload a replacement.');
    }
    $info = @getimagesize($path);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP], true)
        || filesize($path) > 5*1024*1024 || $info[0]*$info[1] > 40000000) throw new RuntimeException('Please choose a valid portrait up to 5 MB and 40 megapixels.');
    $result = personPhotoCheck($path, $name, $key);
    if (!$result['ok']) throw new RuntimeException($result['message']);
}
