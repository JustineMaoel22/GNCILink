<?php
require_once __DIR__.'/content-submissions.php';
/** Publication summaries reuse the audit store without exposing general activity logs. */
function websitePublicationMetadata(array $input): array {
    $page=$input['page']??null;$sub=$input['sub']??'';$route=$input['route']??null;$title=$input['title']??null;
    if(!is_string($page)||!preg_match('/\A[a-z0-9-]{1,100}\z/',$page)||!is_string($sub)||!preg_match('/\A[a-z0-9-]{0,150}\z/',$sub)
        ||!is_string($route)||strlen($route)>1000||!rbacSafePreviewRoute($route)||!is_string($title))throw new InvalidArgumentException('Invalid website publication.');
    $title=trim(strip_tags($title));
    if($title===''||mb_strlen($title)>500)throw new InvalidArgumentException('Invalid published page title.');
    // Preview flags, review IDs, fragments and unrelated query values are not audit data.
    $legacy=gncBrowserLegacyUrl($route);$parts=parse_url($legacy);$query=[];
    parse_str($parts['query']??'',$query);
    $route=$parts['path'];
    if(isset($query['slug'])&&is_string($query['slug'])&&preg_match('/\A[a-z0-9-]{1,150}\z/',$query['slug']))$route.='?'.http_build_query(['slug'=>$query['slug']]);
    return compact('page','sub','route','title');
}
function recordWebsitePublication(PDO $db,array $input): void {
    if(!rbacIsSuper()||!hasPermission('manage_page_content'))throw new RuntimeException('Only Super Admin can record a publication.');
    $data=websitePublicationMetadata($input);
    $q=$db->prepare("INSERT INTO activity_logs(user_id,action,table_name,record_id,description,created_at) VALUES(?,'PUBLISH','website_pages',NULL,?,NOW())");
    $q->execute([(int)$_SESSION['user_id'],json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
}
function dashboardPublishedPages(PDO $db): array {
    if(!hasPermission('manage_page_content')&&!hasPermission('edit_page_content'))return [];
    $manager=hasPermission('manage_page_content');$pages=[];
    try{
        foreach($db->query('SELECT slug,title,page_type FROM pages')->fetchAll(PDO::FETCH_ASSOC) as $page)$pages[$page['slug']]=$page;
    }catch(PDOException $e){if($e->getCode()!=='42S02')throw $e;}
    $managerOnly=['home','administration','college-departments','programs','graduate-school','basic-education','basic-education-departments','student-organizations','student-achievements','athletics-sports','community-activities','faqs','tuition-fees','online-payment-guide','scholarships'];
    $q=$db->query("SELECT l.log_id,l.user_id,l.table_name,l.description,l.created_at,u.user_id AS author_id,CONCAT_WS(' ',u.first_name,u.last_name) AS author FROM activity_logs l LEFT JOIN users u ON u.user_id=l.user_id WHERE l.action='PUBLISH' AND l.table_name IN ('website_pages','page_sections') ORDER BY l.created_at DESC,l.log_id DESC");
    $recent=[];
    while($row=$q->fetch(PDO::FETCH_ASSOC)){
        if($row['table_name']==='website_pages'){
            $data=json_decode($row['description']??'',true);
            if(!is_array($data))continue;
            try{$data=websitePublicationMetadata($data);}catch(InvalidArgumentException $e){continue;}
        }else{
            // The old PUBLISH event identifies its page; draft UPDATE events never enter here.
            if(!preg_match('~\APublished ([a-z0-9-]+)/[^\s]+\z~',(string)$row['description'],$match)||!isset($pages[$match[1]]))continue;
            $data=['page'=>$match[1],'sub'=>'','title'=>$pages[$match[1]]['title']];
        }
        if(!$manager&&(!isset($pages[$data['page']])||in_array($data['page'],$managerOnly,true)))continue;
        $key=$data['page'].':'.$data['sub'];
        if(isset($recent[$key]))continue;
        $recent[$key]=['type'=>'page','id'=>(int)$row['log_id'],'page'=>$data['page'],'sub'=>$data['sub'],'title'=>$data['title'],'status'=>'published','created_at'=>$row['created_at'],'updated_at'=>$row['created_at'],'author_id'=>$row['author_id'],'author'=>$row['author']];
        if(count($recent)===6)break;
    }
    return array_values($recent);
}
