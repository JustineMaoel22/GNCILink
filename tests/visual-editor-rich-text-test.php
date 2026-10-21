<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
function editorRichCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$html='<p style="text-align:center"><strong>Bold</strong> <em>Italic</em> <u>Underline</u> <a href="/contact">Contact</a></p><ul><li>One</li></ul><script>unsafe()</script><a href="javascript:unsafe()">Bad link</a>';
$clean=richTextNormalize($html);
editorRichCheck(str_contains($clean,'<strong>')&&str_contains($clean,'<ul>')&&str_contains($clean,'text-align:center'),'Formatting survives normalization');
editorRichCheck(!preg_match('/script|javascript:/i',$clean),'Unsafe content is removed');
editorRichCheck(richTextNormalize("Plain text\n\nSecond paragraph")==="Plain text\n\nSecond paragraph",'Legacy plain text stays unchanged');
$db=getDB();initSession();$_SESSION['user_id']=(int)$db->query('SELECT user_id FROM users LIMIT 1')->fetchColumn();homeContentEnsureTable();$db->beginTransaction();
try{
    $basic=validateBasicEducationChanges('index',['text_02'=>$html],[]);
    editorRichCheck($basic['text_02']===$clean,'Basic Education uses the shared sanitizer');
    $college=validateBasicEducationChanges('college-index',['text_02'=>$html],[]);
    editorRichCheck($college['text_02']===$clean,'College listing uses the shared sanitizer');
    $dept=$db->query('SELECT * FROM departments ORDER BY dept_id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    saveDepartmentPageContent((int)$dept['dept_id'],['leadership_intro'=>$html]);
    $query=$db->prepare('SELECT content FROM department_page_content WHERE dept_id=? AND field_key=?');$query->execute([$dept['dept_id'],'leadership_intro']);
    editorRichCheck($query->fetchColumn()===$clean,'Shared department details store clean formatting');
    $section=$db->query('SELECT section_key,title FROM admin_sections LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    saveAdministrationSection($section['section_key'],$section['title'],$html);
    $query=$db->prepare('SELECT description FROM admin_sections WHERE section_key=?');$query->execute([$section['section_key']]);editorRichCheck($query->fetchColumn()===$clean,'Administration formatting is stored safely');
    saveHomeContent(['news_intro'=>'<strong>Home introduction</strong>'],[]);
    editorRichCheck(str_contains(homeContentStored()['news_intro']['value'],'<strong>Home introduction</strong>'),'Homepage formatting persists');
    saveStudentOrgPage(['retreat_benefit'=>$html,'retreat_benefit_icon'=>'bi:book','retreat_location_icon'=>'material:school']);
    editorRichCheck(studentOrgPageContent()['retreat_benefit']['value']===$clean,'Leadership callout formatting persists');
    editorRichCheck(str_contains(studentOrgIcon('retreat_benefit_icon','test'),'bi-book'),'Bootstrap icon is rendered');
    editorRichCheck(str_contains(studentOrgIcon('retreat_location_icon','test'),'school'),'Material icon is rendered');
    $bad=false;try{saveStudentOrgPage(['retreat_benefit_icon'=>'<script>']);}catch(InvalidArgumentException $e){$bad=true;}editorRichCheck($bad,'Icons must come from the existing catalog');
    $organization=saveStudentOrganization(['name'=>'Rich text rollback test','description'=>$html,'about_text'=>$html,'vision'=>$html,'mission'=>$html]);
    editorRichCheck($organization['description']===$clean&&$organization['vision']===$clean,'Organization paragraphs store safe formatting');
}finally{$db->rollBack();}
echo "PASS: shared formatting, unsafe HTML stripping, legacy text, homepage, administration, academic and organization storage, Bootstrap and Material icons; database writes rolled back.\n";
