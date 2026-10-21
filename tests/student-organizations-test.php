<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
function orgCheck(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
function organizationFixture():array{
    $db=getDB();$prefix='ORGTEST-'.bin2hex(random_bytes(5));$categories=[];
    foreach(['Academic test','Community test','Unused test'] as $label){$db->prepare('INSERT INTO student_org_categories (name) VALUES (?)')->execute([$prefix.' '.$label]);$categories[]=(int)$db->lastInsertId();}
    $db->prepare("INSERT INTO media_library (file_name,file_type,file_size,file_path,created_by) VALUES ('Organization test image','png',0,'/assets/images/admission-header.png',NULL)")->execute();$media=(int)$db->lastInsertId();
    $records=[];
    for($i=0;$i<9;$i++){
        $row=saveStudentOrganization(['name'=>$prefix.' Organization '.$i,'category_id'=>$i<7?$categories[0]:$categories[1],
            'description'=>'A database-backed organization description.','about_text'=>'Organization overview from the database.',
            'vision'=>'A shared vision.','mission'=>'A shared mission.','status'=>$i===8?'draft':'published']);
        $db->prepare('UPDATE student_organizations SET logo_media_id=?,cover_media_id=? WHERE org_id=?')->execute([$media,$media,$row['org_id']]);
        $records[]=$row;
    }
    $db->prepare('INSERT INTO student_org_gallery (org_id,media_id) VALUES (?,?)')->execute([$records[0]['org_id'],$media]);
    return ['prefix'=>$prefix,'categories'=>$categories,'media'=>$media,'records'=>$records,'url'=>studentOrganizationsUrl(),'detail'=>studentOrganizationsUrl($records[0]['slug'])];
}
$db=getDB();$mode=$argv[1]??'test';
if($mode==='verify_cleanup'){
    $fixtures=(int)$db->query("SELECT COUNT(*) FROM student_organizations WHERE name LIKE 'ORGTEST-%'")->fetchColumn();
    $media=(int)$db->query("SELECT COUNT(*) FROM media_library WHERE file_name IN ('organization-test.png','Organization test image')")->fetchColumn();
    echo json_encode(['temporary_organizations'=>$fixtures,'temporary_media'=>$media]);
    if($fixtures||$media)exit(1);exit;
}
if($mode==='session'){
    initSession();$_SESSION['user_id']=(int)$db->query('SELECT user_id FROM users ORDER BY user_id LIMIT 1')->fetchColumn();$_SESSION['user_role']='Super Admin';
    echo json_encode(['name'=>session_name(),'id'=>session_id(),'csrf'=>generateCSRFToken()]);session_write_close();exit;
}
if($mode==='destroy_session'){
    $id=$argv[2]??'';if(!preg_match('/^[a-zA-Z0-9,-]{20,128}$/',$id))throw new RuntimeException('Invalid test session');
    session_id($id);session_start();session_destroy();exit;
}
if($mode==='seed'){echo json_encode(organizationFixture(),JSON_THROW_ON_ERROR);exit;}
if($mode==='cleanup'){
    $prefix=$argv[2]??'';if(!preg_match('/^ORGTEST-[a-f0-9]{10}$/',$prefix))throw new RuntimeException('Invalid test fixture prefix');
    $media=$db->prepare('SELECT o.logo_media_id AS media_id FROM student_organizations o WHERE o.name LIKE ? UNION SELECT o.cover_media_id FROM student_organizations o WHERE o.name LIKE ? UNION SELECT g.media_id FROM student_org_gallery g JOIN student_organizations o ON o.org_id=g.org_id WHERE o.name LIKE ?');$media->execute([$prefix.'%',$prefix.'%',$prefix.'%']);$ids=$media->fetchAll(PDO::FETCH_COLUMN);
    $db->prepare('DELETE FROM student_organizations WHERE name LIKE ?')->execute([$prefix.'%']);
    $db->prepare('DELETE FROM student_org_categories WHERE name LIKE ?')->execute([$prefix.'%']);
    foreach(array_filter($ids) as $id)deleteMediaIfUnreferenced((int)$id);
    echo "Organization test fixtures removed.\n";exit;
}
$db->beginTransaction();
try{
    $fixture=organizationFixture();$rows=$fixture['records'];$first=$rows[0];
    orgCheck(deleteMediaIfUnreferenced((int)$fixture['media'])===false,'Shared organization/gallery images cannot be deleted while referenced');
    $published=array_filter(studentOrganizations(),fn($row)=>str_starts_with($row['name'],$fixture['prefix']));
    orgCheck(count($published)===8,'Only published organizations are public');
    $draft=array_filter(studentOrganizations(true),fn($row)=>$row['org_id']===$rows[8]['org_id']);orgCheck(count($draft)===1,'Managers can preview unpublished organizations');
    $saved=saveStudentOrganization(['org_id'=>$first['org_id'],'name'=>'<Updated> '.$fixture['prefix'],'status'=>'draft']);
    orgCheck($saved['slug']===$first['slug']&&$saved['description']===$first['description'],'Partial edits preserve stable routes and existing content');
    orgCheck(!in_array($first['org_id'],array_column(studentOrganizations(),'org_id')),'Unpublished organizations disappear publicly');
    $duplicate=saveStudentOrganization(['name'=>$rows[1]['name']]);orgCheck($duplicate['slug']!==$rows[1]['slug'],'Duplicate names receive unique routes');
    moveStudentOrganization((int)$rows[1]['org_id'],-1);
    $ordered=array_column(studentOrganizations(true),'org_id');orgCheck(array_search($rows[1]['org_id'],$ordered)<array_search($first['org_id'],$ordered),'Reordering is persisted');
    $db->prepare('DELETE FROM student_org_categories WHERE category_id=?')->execute([$fixture['categories'][0]]);
    $saved=saveStudentOrganization(['org_id'=>$rows[2]['org_id'],'name'=>$rows[2]['name'],'category_id'=>'']);orgCheck($saved['category_id']===null,'Deleting a category preserves its organizations as Uncategorized');
    foreach([['name'=>''],['name'=>str_repeat('a',256)],['name'=>'Invalid status','status'=>'bad'],['name'=>'Invalid category','category_id'=>2147483647],['name'=>'Invalid URL','facebook_url'=>'javascript:alert(1)']] as $invalid){
        $failed=false;try{saveStudentOrganization($invalid);}catch(InvalidArgumentException $e){$failed=true;}orgCheck($failed,'Invalid organization input rejected');
    }
    $gallery=studentOrganizationGallery((int)$first['org_id']);orgCheck(count($gallery)===1,'Gallery image references are persisted');
    deleteStudentOrganization((int)$first['org_id']);orgCheck(count(studentOrganizationGallery((int)$first['org_id']))===0,'Deleting an organization cascades its gallery');
    saveStudentOrgPage(['section_title'=>'Custom organizations heading']);orgCheck(studentOrgPageContent()['section_title']['value']==='Custom organizations heading','Listing text is editable and persistent');
    saveStudentOrgPageWithImages(['leadership_title'=>'Custom leadership heading','training_topic_1'=>'Team building'],[],[]);
    orgCheck(studentOrgPageContent()['leadership_title']['value']==='Custom leadership heading','Leadership text uses existing page storage');
    $db->prepare('INSERT INTO student_org_page_content (field_key,content) VALUES (?,?) ON DUPLICATE KEY UPDATE content=VALUES(content)')->execute(['retreat_photo_media_id',(string)$fixture['media']]);
    orgCheck(studentOrgPageImages()['retreat_photo']['media_id']===(int)$fixture['media'],'Leadership photos resolve through the media library');
    orgCheck(deleteMediaIfUnreferenced((int)$fixture['media'])===false,'Leadership media references are retained');
    saveStudentOrgPageWithImages([],[],['retreat_photo']);orgCheck(studentOrgPageImages()['retreat_photo']['media_id']===null,'Resetting a leadership photo restores the default');
    $failed=false;try{saveStudentOrgPageWithImages([],[],['unknown_image']);}catch(InvalidArgumentException $e){$failed=true;}orgCheck($failed,'Unknown page image fields are rejected');
}finally{$db->rollBack();}
echo "PASS: organizations, drafts, categories, stable slugs, ordering, gallery relationships and page content; writes rolled back.\n";
