<?php

// Drafts are private checkpoints. Only the existing publish handlers write live content.
const GNC_EDITOR_DRAFT_LIMIT = 96 * 1024 * 1024;

/** Read the legacy text snapshot or a private multipart snapshot upload. */
function editorDraftRequestPayload(array $post,array $files): string {
    if(!array_key_exists('snapshot_file',$files)){
        if(!is_string($post['snapshot']??null))throw new InvalidArgumentException('The draft was not received. Check the hosting request-size limit.');
        return $post['snapshot'];
    }
    if(array_key_exists('snapshot',$post))throw new InvalidArgumentException('Send one draft snapshot at a time.');
    $file=$files['snapshot_file'];
    if(!is_array($file)||!is_int($file['error']??null))throw new InvalidArgumentException('The draft upload was not received correctly.');
    if(in_array($file['error'],[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true))throw new LengthException('The draft exceeds the hosting upload limit (upload_max_filesize: '.ini_get('upload_max_filesize').'). Ask your hosting provider to allow larger draft uploads.');
    if($file['error']!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('The draft upload was not received correctly. Please try again.');
    $size=filesize($file['tmp_name']);
    if($size===false||$size===0)throw new InvalidArgumentException('The draft upload is empty or unreadable.');
    if($size>GNC_EDITOR_DRAFT_LIMIT)throw new LengthException('This draft is too large. Save fewer media changes at a time.');
    $payload=file_get_contents($file['tmp_name']);
    if(!is_string($payload)||strlen($payload)!==$size)throw new InvalidArgumentException('The draft upload could not be read. Please try again.');
    return $payload;
}

/** One approval per saved snapshot, including concurrent clicks and network retries. */
function editorDraftSubmitForReview(PDO $db,int $userId,string $page,string $route,int $revision,int $reviewId=0): array {
    if($userId!==(int)($_SESSION['user_id']??0)||(!hasPermission('manage_page_content')&&!hasPermission('edit_page_content')))throw new RuntimeException('You cannot submit this draft.',403);
    $key=$page.':'.$route;$lock='gnci-draft-submit:'.substr(hash('sha256',$userId.':'.$key),0,40);
    $q=$db->prepare('SELECT GET_LOCK(?,5)');$q->execute([$lock]);
    if((int)$q->fetchColumn()!==1)throw new RuntimeException('Another submission is finishing. Please try again.',409);
    try {
        $draft=editorDraftLoad($db,$userId,$key);
        if(!$draft||(int)$draft['revision']!==$revision)throw new RuntimeException('Save the latest draft before submitting it.',409);
        require_once __DIR__.'/editor-presence.php';
        if(editorPresenceReady($db)) {
            $identity=editorPresencePage($page,$route);
            editorPresenceCheckSavedVersion($db,$draft['snapshot']['content_version']??['scope'=>$identity['scope'],'revision'=>0,'external'=>0]);
        }
        $resubmit=0;
        if($reviewId){
            $old=rbacGetSubmission($reviewId);
            if(!$old||(int)$old['user_id']!==$userId||$old['kind']!=='snapshot'||$old['route']!=='/admin/content-edit.php')throw new RuntimeException('You cannot revise this submission.',403);
            $previous=rbacReadPayload($reviewId);
            if(($previous['page']??'')!==$page||($previous['route']??'')!==$route)throw new RuntimeException('Open the page stored with this submission.',409);
            if(in_array($old['status'],['draft','rejected','needs_revision','failed'],true))$resubmit=$reviewId;
        }
        $fingerprint=hash('sha256',json_encode($draft['snapshot'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        $q=$db->prepare("SELECT submission_id FROM rbac_submissions WHERE user_id=? AND kind='snapshot' AND route='/admin/content-edit.php' AND status<>'draft' AND title=? ORDER BY (status='pending') DESC,submission_id DESC");
        $q->execute([$userId,'Page changes: '.$page]);
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $candidate){
            if((int)$candidate===$resubmit)continue;
            $payload=rbacReadPayload((int)$candidate);
            if(($payload['page']??'')!==$page||($payload['route']??'')!==$route)continue;
            // Comparing saved content also recognizes submissions made before this fix.
            if(isset($payload['snapshot'])&&hash_equals($fingerprint,hash('sha256',json_encode($payload['snapshot'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))))return ['submission_id'=>(int)$candidate,'already_submitted'=>true];
        }
        $id=rbacSaveSubmission('snapshot','/admin/content-edit.php','Page changes: '.$page,['page'=>$page,'route'=>$route,'snapshot'=>$draft['snapshot']],0,$resubmit);
        return ['submission_id'=>$id,'already_submitted'=>false];
    }finally{
        $db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
    }
}

function editorDraftLoad(PDO $db, int $userId, string $key): ?array {
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT draft_id, revision FROM content_editor_drafts WHERE user_id=? AND page_key=?' . ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
        $stmt->execute([$userId, hash('sha256', $key)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { $db->commit(); return null; }
        $chunks = $db->prepare('SELECT payload FROM content_editor_draft_chunks WHERE draft_id=? ORDER BY chunk_no');
        $chunks->execute([$row['draft_id']]);
        $payload = '';
        while (($chunk = $chunks->fetchColumn()) !== false) $payload .= $chunk;
        $db->commit();
        return ['revision' => (int)$row['revision'], 'snapshot' => json_decode($payload, true, 64, JSON_THROW_ON_ERROR)];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function editorDraftSave(PDO $db, int $userId, string $key, int $revision, string $payload): int {
    if (strlen($payload) > GNC_EDITOR_DRAFT_LIMIT) throw new LengthException('This draft is too large. Save fewer media changes at a time.');
    $decoded = json_decode($payload, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || ($decoded['version'] ?? null) !== 1 || !is_array($decoded['state'] ?? null) || !is_array($decoded['preview'] ?? null)) {
        throw new InvalidArgumentException('Invalid draft. Reload the editor and try again.');
    }
    unset($decoded);
    $hash = hash('sha256', $key);
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT draft_id, revision FROM content_editor_drafts WHERE user_id=? AND page_key=?' . ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
        $stmt->execute([$userId, $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ((int)($row['revision'] ?? 0) !== $revision) throw new RuntimeException('This draft was changed in another tab. Reopen the editor before saving again.', 409);
        if ($row) {
            $id = (int)$row['draft_id'];
            $db->prepare('UPDATE content_editor_drafts SET revision=?, updated_at=CURRENT_TIMESTAMP WHERE draft_id=?')->execute([$revision + 1, $id]);
            $db->prepare('DELETE FROM content_editor_draft_chunks WHERE draft_id=?')->execute([$id]);
        } else {
            $db->prepare('INSERT INTO content_editor_drafts (user_id,page_key,revision) VALUES (?,?,?)')->execute([$userId, $hash, 1]);
            $id = (int)$db->lastInsertId();
        }
        // Small chunks also work on hosts with a small MySQL max_allowed_packet.
        $insert = $db->prepare('INSERT INTO content_editor_draft_chunks (draft_id,chunk_no,payload) VALUES (?,?,?)');
        $length = strlen($payload);
        for ($offset = 0, $index = 0; $offset < $length; $offset += 262144, $index++) $insert->execute([$id, $index, substr($payload, $offset, 262144)]);
        $db->commit();
        return $revision + 1;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function editorDraftDelete(PDO $db, int $userId, string $key, int $revision): void {
    // Revision protects a newer draft saved from a different tab.
    $db->prepare('DELETE FROM content_editor_drafts WHERE user_id=? AND page_key=? AND revision=?')->execute([$userId, hash('sha256', $key), $revision]);
}
