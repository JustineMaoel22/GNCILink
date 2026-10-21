<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';
initSession(); header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Use POST.']); exit; }
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH']??0)>0) { http_response_code(413); echo json_encode(['success'=>false,'error'=>'The upload exceeds the server request limit. Upload fewer gallery images at once.']); exit; }
if (!isLoggedIn() || !hasPermission('manage_page_content')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'You do not have permission to manage organizations.']); exit; }
if (!verifyCSRFToken((string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Refresh the editor and try again.']); exit; }
try {
    $db=getDB(); $action=(string)($_POST['action'] ?? ''); $result=[];
    switch ($action) {
        case 'save':
            $row=saveStudentOrganization($_POST,$_FILES);
            logActivity((int)$_SESSION['user_id'],'UPDATE','student_organizations',(int)$row['org_id'],'Saved organization: '.$row['name']);
            $result=['organization'=>$row,'gallery'=>studentOrganizationGallery((int)$row['org_id'])]; break;
        case 'delete':
            $id=(int)($_POST['org_id']??0); deleteStudentOrganization($id);
            logActivity((int)$_SESSION['user_id'],'DELETE','student_organizations',$id,'Deleted student organization'); break;
        case 'move': moveStudentOrganization((int)($_POST['org_id']??0),(int)($_POST['direction']??0)); break;
        case 'save_page':
            $values=json_decode((string)($_POST['texts']??''),true,32,JSON_THROW_ON_ERROR);
            if (!is_array($values)) throw new InvalidArgumentException('Invalid page text.');
            $remove=json_decode((string)($_POST['remove_images']??'[]'),true,16,JSON_THROW_ON_ERROR);
            if(!is_array($remove))throw new InvalidArgumentException('Invalid page images.');
            saveStudentOrgPageWithImages($values,$_FILES,$remove);
            $result=['images'=>studentOrgPageImages()]; break;
        case 'save_category':
            $name=$_POST['name']??''; $id=(int)($_POST['category_id']??0);
            if (!is_string($name) || trim($name)==='' || mb_strlen($name)>100) throw new InvalidArgumentException('Enter a category name of up to 100 characters.');
            if ($id) {
                $stmt=$db->prepare('SELECT 1 FROM student_org_categories WHERE category_id=?'); $stmt->execute([$id]);
                if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Category not found.');
                $db->prepare('UPDATE student_org_categories SET name=? WHERE category_id=?')->execute([trim($name),$id]);
            } else $db->prepare('INSERT INTO student_org_categories (name) VALUES (?)')->execute([trim($name)]);
            $result=['categories'=>studentOrgCategories()]; break;
        case 'delete_category':
            $db->prepare('DELETE FROM student_org_categories WHERE category_id=?')->execute([(int)($_POST['category_id']??0)]); $result=['categories'=>studentOrgCategories()]; break;
        default: throw new InvalidArgumentException('Unknown organization action.');
    }
    echo json_encode(['success'=>true]+$result,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
} catch (InvalidArgumentException|JsonException $e) {
    http_response_code(422); echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('Student organizations: '.$e->getMessage()); http_response_code(422);
    echo json_encode(['success'=>false,'error'=>$e instanceof PDOException && $e->getCode()==='23000' ? 'That category name already exists or the record changed. Refresh and try again.' : 'Could not save the change. Check the uploaded files and try again.']);
}
