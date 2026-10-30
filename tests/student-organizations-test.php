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
function cleanupOrganizationTestDrafts(PDO $db): void {
    foreach ($db->query('SELECT draft_id FROM content_editor_drafts')->fetchAll(PDO::FETCH_COLUMN) as $draftId) {
        $q=$db->prepare('SELECT payload FROM content_editor_draft_chunks WHERE draft_id=? ORDER BY chunk_no');$q->execute([$draftId]);
        $snapshot=json_decode(implode('',$q->fetchAll(PDO::FETCH_COLUMN)),true);
        $records=$snapshot['state']['records']??[];
        if (!$records) continue;
        foreach ($records as $record) {
            if (!preg_match('/^ORGTEST-[a-f0-9]{10} Organization /',$record['base']['name']??'')) continue 2;
            $q=$db->prepare('SELECT 1 FROM student_organizations WHERE org_id=?');$q->execute([$record['base']['org_id']??0]);
            if ($q->fetchColumn()) continue 2;
        }
        $db->prepare('DELETE FROM content_editor_drafts WHERE draft_id=?')->execute([$draftId]);
    }
}
if($mode==='cleanup_test_drafts'){cleanupOrganizationTestDrafts($db);echo "Orphaned organization test drafts removed.\n";exit;}
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
if($mode==='seed_pages'){
    $fixture=organizationFixture();
    for($i=9;$i<16;$i++)$fixture['records'][]=saveStudentOrganization(['name'=>$fixture['prefix'].' Organization '.$i,'category_id'=>$fixture['categories'][0],'description'=>'Cross-page order fixture','status'=>'published']);
    echo json_encode($fixture,JSON_THROW_ON_ERROR);exit;
}
if($mode==='cleanup'){
    $prefix=$argv[2]??'';if(!preg_match('/^ORGTEST-[a-f0-9]{10}$/',$prefix))throw new RuntimeException('Invalid test fixture prefix');
    $media=$db->prepare('SELECT o.logo_media_id AS media_id FROM student_organizations o WHERE o.name LIKE ? UNION SELECT o.cover_media_id FROM student_organizations o WHERE o.name LIKE ? UNION SELECT g.media_id FROM student_org_gallery g JOIN student_organizations o ON o.org_id=g.org_id WHERE o.name LIKE ?');$media->execute([$prefix.'%',$prefix.'%',$prefix.'%']);$ids=$media->fetchAll(PDO::FETCH_COLUMN);
    $db->prepare('DELETE FROM student_organizations WHERE name LIKE ?')->execute([$prefix.'%']);
    $db->prepare('DELETE FROM student_org_categories WHERE name LIKE ?')->execute([$prefix.'%']);
    foreach(array_filter($ids) as $id)deleteMediaIfUnreferenced((int)$id);
    cleanupOrganizationTestDrafts($db);
    echo "Organization test fixtures removed.\n";exit;
}
$db->beginTransaction();
try{
    $fixture=organizationFixture();$rows=$fixture['records'];$first=$rows[0];
    orgCheck(deleteMediaIfUnreferenced((int)$fixture['media'])===false,'Shared organization/gallery images cannot be deleted while referenced');
    $published=array_filter(studentOrganizations(),fn($row)=>str_starts_with($row['name'],$fixture['prefix']));
    orgCheck(count($published)===8,'Only published organizations are public');
    $archived=saveStudentOrganization(['org_id'=>$first['org_id'],'archived'=>'1']);
    orgCheck(!empty($archived['archived_at'])&&$archived['status']==='published','Archive preserves publication status');
    orgCheck(!in_array($first['org_id'],array_column(studentOrganizations(),'org_id')),'Archived organizations are hidden publicly');
    orgCheck(count(studentOrganizationGallery((int)$first['org_id']))===1&&$archived['logo_media_id']===$fixture['media'],'Archive preserves gallery and media references');
    $edited=saveStudentOrganization(['org_id'=>$first['org_id'],'about_text'=>'Edited while archived']);
    orgCheck($edited['archived_at']===$archived['archived_at'],'Editing archived content does not restore it');
    $restored=saveStudentOrganization(['org_id'=>$first['org_id'],'archived'=>'0']);
    orgCheck($restored['archived_at']===null&&$restored['slug']===$first['slug']&&in_array($first['org_id'],array_column(studentOrganizations(),'org_id')),'Restore retains the URL and restores published visibility');
    saveStudentOrganization(['org_id'=>$rows[8]['org_id'],'archived'=>'1']);
    $restored=saveStudentOrganization(['org_id'=>$rows[8]['org_id'],'archived'=>'0']);
    orgCheck($restored['status']==='draft'&&!in_array($rows[8]['org_id'],array_column(studentOrganizations(),'org_id')),'Restoring an unpublished organization keeps it unpublished');
    foreach (['bad',2,[],null] as $archiveState) {
        $failed=false;try{saveStudentOrganization(['org_id'=>$first['org_id'],'archived'=>$archiveState]);}catch(InvalidArgumentException $e){$failed=true;}
        orgCheck($failed,'Malformed archive states are rejected');
    }
    $draft=array_filter(studentOrganizations(true),fn($row)=>$row['org_id']===$rows[8]['org_id']);orgCheck(count($draft)===1,'Managers can preview unpublished organizations');
    $saved=saveStudentOrganization(['org_id'=>$first['org_id'],'name'=>'<Updated> '.$fixture['prefix'],'status'=>'draft']);
    orgCheck($saved['slug']===$first['slug']&&$saved['description']===$first['description'],'Partial edits preserve stable routes and existing content');
    orgCheck(!in_array($first['org_id'],array_column(studentOrganizations(),'org_id')),'Unpublished organizations disappear publicly');
    $duplicate=saveStudentOrganization(['name'=>$rows[1]['name']]);orgCheck($duplicate['slug']!==$rows[1]['slug'],'Duplicate names receive unique routes');
    moveStudentOrganization((int)$rows[1]['org_id'],-1);
    $ordered=array_column(studentOrganizations(true),'org_id');orgCheck(array_search($rows[1]['org_id'],$ordered)<array_search($first['org_id'],$ordered),'Reordering is persisted');
    $beforeSwap=array_column(studentOrganizations(true),'org_id');
    swapStudentOrganizations((int)$rows[1]['org_id'],(int)$rows[5]['org_id']);
    $expected=$beforeSwap;$a=array_search($rows[1]['org_id'],$expected);$b=array_search($rows[5]['org_id'],$expected);
    [$expected[$a],$expected[$b]]=[$expected[$b],$expected[$a]];
    orgCheck(array_column(studentOrganizations(true),'org_id')===$expected,'Swapping changes only the two selected positions');
    $subset=[(int)$rows[4]['org_id'],(int)$rows[2]['org_id'],(int)$rows[3]['org_id']];
    $selected=array_fill_keys($subset,true);$next=0;
    foreach ($expected as &$id) if(isset($selected[$id]))$id=$subset[$next++];
    unset($id);
    reorderStudentOrganizations($subset);
    orgCheck(array_column(studentOrganizations(true),'org_id')===$expected,'Publishing a subset order preserves other organization slots');
    foreach ([[],[$subset[0],$subset[0]],['1'],[0],[2147483647]] as $invalidOrder) {
        $failed=false;try{reorderStudentOrganizations($invalidOrder);}catch(InvalidArgumentException $e){$failed=true;}
        orgCheck($failed&&array_column(studentOrganizations(true),'org_id')===$expected,'Invalid published orders leave all positions unchanged');
    }
    foreach ([[0,$rows[1]['org_id']],[$rows[1]['org_id'],$rows[1]['org_id']],[$rows[1]['org_id'],2147483647]] as [$source,$target]) {
        $failed=false;try{swapStudentOrganizations((int)$source,(int)$target);}catch(InvalidArgumentException $e){$failed=true;}
        orgCheck($failed&&array_column(studentOrganizations(true),'org_id')===$expected,'Invalid swaps preserve the order');
    }
    $db->prepare('DELETE FROM student_org_categories WHERE category_id=?')->execute([$fixture['categories'][0]]);
    $saved=saveStudentOrganization(['org_id'=>$rows[2]['org_id'],'name'=>$rows[2]['name'],'category_id'=>'']);orgCheck($saved['category_id']===null,'Deleting a category preserves its organizations as Uncategorized');
    foreach([['name'=>''],['name'=>str_repeat('a',256)],['name'=>'Invalid status','status'=>'bad'],['name'=>'Invalid category','category_id'=>2147483647],['name'=>'Invalid URL','facebook_url'=>'javascript:alert(1)']] as $invalid){
        $failed=false;try{saveStudentOrganization($invalid);}catch(InvalidArgumentException $e){$failed=true;}orgCheck($failed,'Invalid organization input rejected');
    }
    $gallery=studentOrganizationGallery((int)$first['org_id']);orgCheck(count($gallery)===1,'Gallery image references are persisted');
    deleteStudentOrganization((int)$first['org_id']);orgCheck(count(studentOrganizationGallery((int)$first['org_id']))===0,'Deleting an organization cascades its gallery');
    saveStudentOrgPage(['facebook_eyebrow'=>'Contact our organization']);orgCheck(studentOrgPageContent()['facebook_eyebrow']['value']==='Contact our organization','Contact eyebrow persists through page settings');
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
