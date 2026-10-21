<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';
initSession();
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Use POST.']); exit; }
if (!isLoggedIn() || !hasPermission('manage_page_content')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'You cannot manage student achievements.']); exit; }
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0)>0) { http_response_code(413); echo json_encode(['success'=>false,'error'=>'The upload exceeds the server request limit. Try fewer or smaller pictures.']); exit; }
if (!is_string($_POST['csrf_token'] ?? null) || !verifyCSRFToken($_POST['csrf_token'])) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Refresh the editor and try again.']); exit; }
$db=getDB(); $newImages=[]; $oldImages=[]; $saved=[];
try {
    $changes=json_decode($_POST['changes'] ?? '[]',true,32,JSON_THROW_ON_ERROR);
    if (!is_array($changes) || !array_is_list($changes) || !$changes || count($changes)>100) throw new InvalidArgumentException('Invalid achievement changes.');
    $db->beginTransaction(); $seen=[];
    foreach ($changes as $index=>$change) {
        if (!is_array($change)) throw new InvalidArgumentException('Invalid achievement.');
        $id=filter_var($change['achievement_id'] ?? 0,FILTER_VALIDATE_INT);
        if ($id===false || $id<0 || ($id && isset($seen[$id]))) throw new InvalidArgumentException('Invalid achievement ID.');
        if ($id) $seen[$id]=true;
        $old=null;
        if ($id) {
            $statement=$db->prepare('SELECT * FROM student_achievements WHERE achievement_id=? FOR UPDATE'); $statement->execute([$id]); $old=$statement->fetch(PDO::FETCH_ASSOC);
            if (!$old) throw new InvalidArgumentException('An achievement was removed. Reload the editor.');
            if (isset($change['updated_at']) && $change['updated_at']!==$old['updated_at']) throw new InvalidArgumentException('This achievement changed in another session. Reload before saving.');
        }
        if (!empty($change['remove'])) {
            if ($old) {
                $oldImages[]=$old['image_path']; $oldImages[]=$old['thumbnail_path'];
                foreach (studentAchievementPhotos($db,$id) as $photo) $oldImages[]=$photo['image_path'];
                $db->prepare('DELETE FROM student_achievements WHERE achievement_id=?')->execute([$id]);
                logActivity((int)$_SESSION['user_id'],'DELETE','student_achievements',$id,'Deleted student achievement');
            }
            continue;
        }
        $image=$old['image_path'] ?? null;
        if (isset($_FILES['image_'.$index]) && ($_FILES['image_'.$index]['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
            $image=uploadStudentAchievementImage($_FILES['image_'.$index]); $newImages[]=$image;
            if ($old) $oldImages[]=$old['image_path'];
        }
        $data=array_merge($old ?? [],$change);
        $thumbnail=$old['thumbnail_path'] ?? null;
        if (isset($_FILES['thumbnail_'.$index]) && ($_FILES['thumbnail_'.$index]['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
            $thumbnail=uploadStudentAchievementImage($_FILES['thumbnail_'.$index]); $newImages[]=$thumbnail;
            if ($old) $oldImages[]=$old['thumbnail_path'];
        }
        $savedId=saveStudentAchievement($db,$id,$data,$image,$thumbnail);
        $photos=$change['photos'] ?? [];
        if (!is_array($photos) || !array_is_list($photos) || count($photos)>40) throw new InvalidArgumentException('Invalid achievement photos.');
        $seenPhotos=[];
        foreach ($photos as $photoIndex=>$photo) {
            if (!is_array($photo)) throw new InvalidArgumentException('Invalid main photo.');
            $photoId=filter_var($photo['photo_id'] ?? 0,FILTER_VALIDATE_INT);
            if ($photoId===false || $photoId<0 || ($photoId && isset($seenPhotos[$photoId]))) throw new InvalidArgumentException('Invalid main photo.');
            $existingPhoto=null;
            if ($photoId) {
                $seenPhotos[$photoId]=true;
                $check=$db->prepare('SELECT * FROM student_achievement_photos WHERE photo_id=? AND achievement_id=?');$check->execute([$photoId,$savedId]);$existingPhoto=$check->fetch(PDO::FETCH_ASSOC);
                if (!$existingPhoto) throw new InvalidArgumentException('A main photo no longer exists. Reload the editor.');
            }
            if (!empty($photo['remove'])) {
                if ($existingPhoto) {$db->prepare('DELETE FROM student_achievement_photos WHERE photo_id=?')->execute([$photoId]);$oldImages[]=$existingPhoto['image_path'];}
                continue;
            }
            $caption=$photo['caption'] ?? '';
            if (!is_string($caption) || mb_strlen($caption)>255) throw new InvalidArgumentException('Photo captions must be at most 255 characters.');
            if ($existingPhoto) $db->prepare('UPDATE student_achievement_photos SET caption=?,sort_order=? WHERE photo_id=?')->execute([trim($caption),$photoIndex,$photoId]);
            else {
                $file=$_FILES['photos_'.$index.'_'.$photoIndex] ?? null;
                if (!$file) throw new InvalidArgumentException('Choose a picture for each new main photo.');
                $photoPath=uploadStudentAchievementImage($file);$newImages[]=$photoPath;
                $db->prepare('INSERT INTO student_achievement_photos (achievement_id,image_path,caption,sort_order) VALUES (?,?,?,?)')->execute([$savedId,$photoPath,trim($caption),$photoIndex]);
            }
        }
        $photoCount=$db->prepare('SELECT COUNT(*) FROM student_achievement_photos WHERE achievement_id=?');$photoCount->execute([$savedId]);
        if ((int)$photoCount->fetchColumn()>20) throw new InvalidArgumentException('Use up to 20 additional main photos per achievement.');
        logActivity((int)$_SESSION['user_id'],$old?'UPDATE':'CREATE','student_achievements',$savedId,'Saved student achievement in visual editor');
        $saved[]=['key'=>(string)($change['key'] ?? $index),'record'=>studentAchievementById($db,$savedId,false)];
    }
    $db->commit();
    foreach ($oldImages as $image) { try { removeStudentAchievementImage($db,$image); } catch (Throwable $e) { error_log('Achievement image cleanup failed.'); } }
    echo json_encode(['success'=>true,'records'=>$saved]);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    foreach ($newImages as $image) { try { removeStudentAchievementImage($db,$image); } catch (Throwable $cleanupError) { error_log('Achievement upload cleanup failed.'); } }
    http_response_code($e instanceof InvalidArgumentException || $e instanceof JsonException ? 422 : 500);
    if (!($e instanceof InvalidArgumentException) && !($e instanceof JsonException)) error_log('Achievement visual editor save: '.$e->getMessage());
    echo json_encode(['success'=>false,'error'=>$e instanceof InvalidArgumentException ? $e->getMessage() : 'Could not save the achievements. Please try again.']);
}
