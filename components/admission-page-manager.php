<?php
require_once __DIR__.'/../includes/content-publish-policy.php';
require_once __DIR__ . '/basic-admission-requirements.php';
require_once __DIR__ . '/college-admission-requirements.php';

function admissionCategoryLabel(string $content, string $fallback): string
{
    $label = preg_match('/<h2\b[^>]*>(.*?)<\/h2>/is',$content,$match)
        ? trim(html_entity_decode(strip_tags($match[1]),ENT_QUOTES|ENT_HTML5,'UTF-8')) : '';
    return $label !== '' ? $label : $fallback;
}

function admissionCategoryScope(string $page, string $key): ?string
{
    if ($page === 'college-admission-requirements') {
        return in_array($key,['freshmen','transferees'],true) || preg_match('/^custom_[a-f0-9]{16}$/D',$key) ? 'college' : null;
    }
    if ($page !== 'basic-education-admission-requirements') return null;
    foreach (basicAdmissionRequirements() as $school => $data) {
        if (isset(basicAdmissionSectionDefaults()[$key]) && str_starts_with($key,$school.'_')) return $school;
        if (preg_match('/^'.preg_quote($school,'/').'_custom_[a-f0-9]{16}$/D',$key)) return $school;
    }
    return null;
}

function admissionPresentationField(string $page, string $key): bool
{
    return ($page === 'college-admission-requirements' && in_array($key, ['hero_title', 'hero_intro'], true))
        || ($page === 'basic-education-admission-requirements' && isset(basicAdmissionPresentationDefaults()[$key]));
}

/** Save the admission canvas in one transaction, including category order and removal. */
function publishAdmissionPage(string $page, array $changes, array $orders): array
{
    $scopes = $page === 'college-admission-requirements' ? ['college']
        : ($page === 'basic-education-admission-requirements' ? array_keys(basicAdmissionRequirements()) : []);
    if (!$scopes || count($changes)>200 || array_diff(array_keys($orders),$scopes) || array_diff($scopes,array_keys($orders))) throw new InvalidArgumentException('Invalid admission page layout.');
    $clean = [];
    foreach ($changes as $change) {
        if (!is_array($change) || !is_string($change['key'] ?? null) || isset($clean[$change['key']])) throw new InvalidArgumentException('Invalid category changes.');
        $key = $change['key'];
        if (admissionCategoryScope($page,$key) === null && !admissionPresentationField($page,$key)) throw new InvalidArgumentException('Invalid admission category.');
        if (!is_string($change['title'] ?? null) || (trim($change['title'])==='' && !contentPublishAllowsIncomplete()) || mb_strlen($change['title'])>150) throw new InvalidArgumentException('Enter a category name of up to 150 characters.');
        if (!is_string($change['content'] ?? null) || strlen($change['content'])>100000 || !is_bool($change['removed'] ?? null)) throw new InvalidArgumentException('Invalid category content.');
        if (admissionPresentationField($page,$key) && $change['removed']) throw new InvalidArgumentException('Page headings, descriptions and tab labels cannot be removed as categories.');
        if (($page === 'basic-education-admission-requirements' && admissionPresentationField($page,$key))
            || ($page === 'college-admission-requirements' && $key === 'hero_title')) {
            $text = trim(html_entity_decode(strip_tags($change['content']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (($text === '' && !contentPublishAllowsIncomplete()) || mb_strlen($text) > ($key === 'hero_intro' ? 2000 : 150)) throw new InvalidArgumentException('Enter a valid heading, description or tab label.');
            $change['content'] = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
        $clean[$key] = ['title'=>trim($change['title']),'content'=>richTextToStorage($change['content']),'removed'=>$change['removed']];
    }
    $db = getDB(); $db->beginTransaction();
    try {
        $query=$db->prepare("SELECT page_id FROM pages WHERE slug=? AND status='active' FOR UPDATE");$query->execute([$page]);$id=$query->fetchColumn();
        if (!$id) throw new InvalidArgumentException('Admission page not found.');
        $query=$db->prepare('SELECT * FROM page_sections WHERE page_id=? FOR UPDATE');$query->execute([$id]);
        $rows=[];foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row)$rows[$row['section_key']]=$row;
        $expected=[];
        foreach($rows as $key=>$row)if(admissionCategoryScope($page,$key)!==null && $row['status']==='published')$expected[$key]=true;
        foreach($clean as $key=>$change) {
            if(admissionPresentationField($page,$key))continue;
            if($change['removed'])unset($expected[$key]);else $expected[$key]=true;
        }
        $seen=[];
        foreach($orders as $scope=>$keys) {
            if(!is_array($keys) || !array_is_list($keys) || count($keys)>200)throw new InvalidArgumentException('Invalid category order.');
            foreach($keys as $key) {
                if(!is_string($key) || admissionCategoryScope($page,$key)!==$scope || isset($seen[$key]))throw new InvalidArgumentException('Categories must stay within their school program.');
                $seen[$key]=true;
            }
        }
        if(array_diff_key($expected,$seen) || array_diff_key($seen,$expected))throw new InvalidArgumentException('The categories changed. Reload the editor before publishing.');
        $updated=[];
        foreach($clean as $key=>$change) {
            if(!isset($rows[$key]) && !preg_match('/(?:^|_)custom_[a-f0-9]{16}$/D',$key))throw new InvalidArgumentException('Category not found.');
            $status=$change['removed']?'draft':'published';
            if(isset($rows[$key])) {
                $db->prepare("UPDATE page_sections SET title=?,content=?,draft_content=NULL,status=?,review_status='none',review_note=NULL,updated_by=? WHERE section_id=?")
                    ->execute([$change['title'],$change['content'],$status,$_SESSION['user_id'],$rows[$key]['section_id']]);
            } elseif(!$change['removed']) {
                $db->prepare('INSERT INTO page_sections (page_id,section_key,title,content,status,display_order,updated_by) VALUES (?,?,?,?,?,0,?)')
                    ->execute([$id,$key,$change['title'],$change['content'],$status,$_SESSION['user_id']]);
            }
            $updated[$key]=['success'=>true,'has_draft'=>false,'review_status'=>'none','editor_html'=>sectionToEditorHtml($change['content'])];
        }
        $position=0;
        foreach($orders as $keys)foreach($keys as $key)$db->prepare('UPDATE page_sections SET display_order=? WHERE page_id=? AND section_key=?')->execute([++$position,$id,$key]);
        logActivity($_SESSION['user_id'],'PUBLISH','page_sections',null,'Published admission requirements layout: '.$page);
        $db->commit();return $updated;
    } catch(Throwable $error) {$db->rollBack();throw $error;}
}
