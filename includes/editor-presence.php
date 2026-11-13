<?php
/** Ephemeral, page-scoped presence. Existing handlers retain all authorization/validation. */
const EDITOR_PRESENCE_TTL = 45;
const EDITOR_WRITE_LEASE = 120;

function editorPresenceReady(PDO $db): bool {
    try { $db->query('SELECT scope_key FROM content_editor_versions LIMIT 0');$db->query('SELECT peer_key FROM content_editor_presence LIMIT 0');return true; }
    catch (PDOException $e) { if ($e->getCode()==='42S02') return false;throw $e; }
}
function editorPresenceLock(PDO $db): void {
    if ((int)$db->query("SELECT GET_LOCK('gnci-editor-content',5)")->fetchColumn()!==1) throw new RuntimeException('Another save is finishing. Try again in a moment.',409);
}
function editorPresenceUnlock(PDO $db): void { $db->query("SELECT RELEASE_LOCK('gnci-editor-content')"); }
function editorPresenceVersion(PDO $db,string $scope): array {
    $db->prepare('INSERT IGNORE INTO content_editor_versions(scope_key) VALUES(?)')->execute([$scope]);
    $q=$db->prepare('SELECT * FROM content_editor_versions WHERE scope_key=?');$q->execute([$scope]);return $q->fetch(PDO::FETCH_ASSOC);
}
function editorPresenceSession(): string { return hash('sha256',session_id()); }
function editorPresencePeer(PDO $db,string $token): array {
    if (!preg_match('/^[a-f0-9]{64}$/D',$token)) throw new InvalidArgumentException('Invalid editor presence.');
    $q=$db->prepare('SELECT * FROM content_editor_presence WHERE peer_key=? AND session_key=? AND user_id=? AND credential_version=?');
    $q->execute([hash('sha256',$token),editorPresenceSession(),(int)$_SESSION['user_id'],(int)($_SESSION['credential_version']??0)]);
    $peer=$q->fetch(PDO::FETCH_ASSOC);
    if (!$peer) throw new RuntimeException('Reconnect to the page before saving. Your pending changes are still in the editor.',409);
    return $peer;
}
/** Cosmetic filters/device choices and shared Administration drafts share presence; entity routes do not. */
function editorPresencePage(string $page,string $route): array {
    if (!preg_match('/^[a-z0-9-]{1,100}$/D',$page)||strlen($route)>1000||!rbacSafePreviewRoute($route)) throw new InvalidArgumentException('Invalid editor page.');
    $allowed=array_column(getEditablePages(),'slug');
    $custom=['home','basic-education','graduate-school','programs','college-departments','administration','student-organizations','student-achievements','athletics-sports','community-activities','faqs','tuition-fees','online-payment-guide','scholarships'];
    if (!hasPermission('manage_page_content')&&!hasPermission('edit_page_content')) throw new RuntimeException('You cannot access this editor.',403);
    if (!in_array($page,array_merge($allowed,$custom),true)) throw new RuntimeException('This page is not available to you.',403);
    if (!hasPermission('manage_page_content')&&in_array($page,$custom,true)) throw new RuntimeException('You cannot edit this page.',403);
    $legacy=gncBrowserLegacyUrl($route);$path=rawurldecode((string)parse_url($legacy,PHP_URL_PATH));
    if ($path==='/index.php') $path='/';
    parse_str((string)parse_url($legacy,PHP_URL_QUERY),$params);
    $identity=[];
    foreach (['slug','id','dept','department','program','section','org'] as $key) {
        if (isset($params[$key])) {
            if (!is_string($params[$key])||strlen($params[$key])>120) throw new InvalidArgumentException('Invalid subpage.');
            $identity[$key]=$params[$key];
        }
    }
    // Canonicalize pretty entity paths and query-string routes to one page identity.
    if (preg_match('~^/pages/academics/college/(program|college-department)/([a-z0-9-]+)/?$~D',$path,$m)) {
        $path='/pages/academics/college/'.$m[1].'.php';$identity['slug']=$m[2];
    }
    if(preg_match('~^/pages/academics/college/programs/([a-z0-9-]+)\.php$~D',$path,$m)){
        $path='/pages/academics/college/program.php';$identity['slug']=$m[1];
    }
    // All Administration tabs save one collection snapshot without a section route.
    // Match that draft identity so a tab's peer can save or recover the shared draft.
    if ($page==='administration') unset($identity['section']);
    ksort($identity);
    // Linked academic lists/details and shared personnel can update each other. Use one
    // conservative revision family while keeping the visible list specific to the page.
    $scope=in_array($page,['programs','college-departments','basic-education','basic-education-departments','graduate-school','administration','home'],true)?'shared-academic-personnel':$page;
    return ['page_key'=>hash('sha256',$path.'?'.http_build_query($identity)),'scope'=>$scope];
}
function editorPresenceJoin(PDO $db,string $page,string $route): array {
    $identity=editorPresencePage($page,$route);$token=bin2hex(random_bytes(32));
    editorPresenceLock($db);
    try {
        $version=editorPresenceVersion($db,$identity['scope']);$external=editorPresenceVersion($db,'external');
        $q=$db->prepare('INSERT INTO content_editor_presence VALUES(?,?,?,?,?,?,?,?,?,?)');
        $q->execute([hash('sha256',$token),editorPresenceSession(),(int)$_SESSION['user_id'],(int)($_SESSION['credential_version']??0),$identity['page_key'],$identity['scope'],$version['revision'],$external['revision'],'viewing',time()]);
    } finally { editorPresenceUnlock($db); }
    return ['token'=>$token]+editorPresenceSnapshot($db,editorPresencePeer($db,$token));
}
function editorPresenceSnapshot(PDO $db,array $peer): array {
    $version=editorPresenceVersion($db,$peer['scope_key']);$external=editorPresenceVersion($db,'external');
    $stale=(int)$peer['base_revision']!==(int)$version['revision']||(int)$peer['external_revision']!==(int)$external['revision'];
    $q=$db->prepare("SELECT p.user_id,u.first_name,u.last_name,MAX(p.activity='editing') AS editing FROM content_editor_presence p JOIN users u ON u.user_id=p.user_id AND u.status='active' AND u.credential_version=p.credential_version WHERE p.page_key=? AND p.seen_at>? GROUP BY p.user_id,u.first_name,u.last_name ORDER BY editing DESC,u.first_name,u.last_name LIMIT 50");
    $q->execute([$peer['page_key'],time()-EDITOR_PRESENCE_TTL]);$users=[];
    $rows=$q->fetchAll(PDO::FETCH_ASSOC);
    require_once __DIR__.'/profile-picture.php';
    $pictures=profilePictureUrls(array_column($rows,'user_id'));
    foreach($rows as $row) {
        $name=trim($row['first_name'].' '.$row['last_name']);
        $users[]=['id'=>(int)$row['user_id'],'name'=>$name?:'Administrator','activity'=>$row['editing']?'editing':'viewing','self'=>(int)$row['user_id']===(int)$_SESSION['user_id'],'avatar'=>$pictures[(int)$row['user_id']]??null];
    }
    return ['users'=>$users,'stale'=>$stale,'revision'=>(int)$version['revision'],'external'=>(int)$external['revision']];
}
function editorPresenceCheck(PDO $db,array $peer): void {
    $version=editorPresenceVersion($db,$peer['scope_key']);$external=editorPresenceVersion($db,'external');
    if ((int)$peer['base_revision']!==(int)$version['revision']||(int)$peer['external_revision']!==(int)$external['revision']) throw new RuntimeException('Newer website changes were saved by another editor. Your pending work is kept here. Reopen the page and review the newer content before saving or publishing.',409);
    if ($version['lease_owner']!==null&&$version['lease_owner']!==$peer['peer_key']&&(int)$version['lease_until']>time()) throw new RuntimeException('Another editor is saving this content. Your pending work is kept. Try again after they finish.',409);
}
function editorPresenceCheckSavedVersion(PDO $db,array $saved): void {
    if(!is_string($saved['scope']??null)||!preg_match('/^[a-z0-9-]{1,120}$/D',$saved['scope']))throw new RuntimeException('Invalid saved content version. Reopen the proposal.',409);
    $version=editorPresenceVersion($db,$saved['scope']);$external=editorPresenceVersion($db,'external');
    if((int)($saved['revision']??-1)!==(int)$version['revision']||(int)($saved['external']??-1)!==(int)$external['revision'])throw new RuntimeException('Newer website content was saved after this proposal. Review and resubmit the changes before approving.',409);
}
function editorPresenceRelease(PDO $db,array $peer): void {
    $db->prepare('UPDATE content_editor_versions SET lease_owner=NULL,lease_until=0 WHERE lease_owner=?')->execute([$peer['peer_key']]);
}
function editorPresenceValidateTarget(array $peer,string $route,array $post): void {
    $features=['athletics-sports'=>'athletics-sports','community-activities'=>'community-activities','enrollment-canvas'=>'enrollment-procedures','enrollment-illustrations'=>'enrollment-procedures','faqs'=>'faqs','payment-guide'=>'online-payment-guide','scholarship'=>'scholarships','student-achievements'=>'student-achievements','student-organizations'=>'student-organizations','tuition-fees'=>'tuition-fees'];
    $target=null;
    foreach($features as $handler=>$scope)if($route==='/admin/action/'.$handler.'-handler.php')$target=$scope;
    if(in_array($route,['/admin/action/page-content-handler.php','/admin/action/program-page-handler.php'],true))$target='shared-academic-personnel';
    if($route==='/admin/action/page-content-handler.php'&&in_array($post['action']??'',['save_section_image','save_draft','publish','discard_draft'],true))$target=$post['page']??null;
    if(in_array($route,['/admin/action/page-review-handler.php','/admin/action/admission-category-handler.php'],true))$target=$post['page']??null;
    if($route==='/admin/action/editor-draft-handler.php') {
        $identity=editorPresencePage((string)($post['page']??''),(string)($post['route']??''));
        if($identity['page_key']!==$peer['page_key'])throw new RuntimeException('The editor switched pages. Reopen the target page before saving.',409);
        $target=$identity['scope'];
    }
    if($target!==null&&$target!==$peer['scope_key'])throw new RuntimeException('The editor switched pages. Reopen the target page before saving.',409);
}
/** Central gate covers visual writes and legacy tools/approval workers sharing these handlers. */
function editorPresenceWriteBoundary(string $route): void {
    if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') return;
    $action=$_POST['action']??$_POST['ajax_action']??'';
    $draft=$route==='/admin/action/editor-draft-handler.php';
    $mutation=$draft?in_array($action,['save','save_recovery','submit_review','complete_review'],true):rbacShouldQueue($route,$_POST);
    if (!$mutation) return;
    $token=$_SERVER['HTTP_X_GNCI_EDITOR_PEER']??'';
    $db=getDB();
    if (!editorPresenceReady($db)) {
        if ($token!=='') throw new RuntimeException('Editor conflict protection is unavailable. Ask your developer to install database/migrate-editor-presence.php.',503);
        return; // Old deployments keep existing handlers until the additive migration is installed.
    }
    if (!verifyCSRFToken(is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:'')) return; // Existing handler reports its own CSRF error.
    editorPresenceLock($db);
    $release=true;
    try {
        $peer=$token!==''?editorPresencePeer($db,$token):null;
        if ($peer) { editorPresenceValidateTarget($peer,$route,$_POST);if(!($draft&&$action==='save_recovery'))editorPresenceCheck($db,$peer); }
        if(!$peer&&isset($_SESSION['_rbac_worker']['editor_version']))editorPresenceCheckSavedVersion($db,$_SESSION['_rbac_worker']['editor_version']);
        $live=!$draft && (rbacIsSuper()||!empty($_SESSION['_rbac_worker']));
        if ($live) {
            $q=$db->prepare('SELECT scope_key FROM content_editor_versions WHERE lease_owner IS NOT NULL AND lease_until>?'.($peer?' AND lease_owner<>?':''));
            $q->execute($peer?[time(),$peer['peer_key']]:[time()]);
            // Unscoped legacy writes cannot race a publishing batch. Scoped writes can
            // proceed on independent content families.
            $leases=$q->fetchAll(PDO::FETCH_COLUMN);
            if (!$peer&&$leases) throw new RuntimeException('Another editor is publishing. Try again in a moment.',409);
            $global=in_array($route,['/admin/action/image-framing-handler.php','/admin/settings.php'],true);
            $scope=$peer&&!$global?$peer['scope_key']:'external';
            editorPresenceVersion($db,$scope);
            // Reserve the revision before executing. Invalid/failed writes may conservatively
            // invalidate other previews; fatal/partially committed requests can never look current.
            $db->prepare('UPDATE content_editor_versions SET revision=revision+1 WHERE scope_key=?')->execute([$scope]);
            if ($peer) {
                $column=$global?'external_revision':'base_revision';
                $db->prepare("UPDATE content_editor_presence SET $column=$column+1,seen_at=? WHERE peer_key=?")->execute([time(),$peer['peer_key']]);
                if (($_SERVER['HTTP_X_GNCI_EDITOR_BATCH']??'')==='1') $db->prepare('UPDATE content_editor_versions SET lease_owner=?,lease_until=? WHERE scope_key=?')->execute([$peer['peer_key'],time()+EDITOR_WRITE_LEASE,$peer['scope_key']]);
            }
        }
        // Keep the named lock through the actual handler (which may use its own transactions).
        register_shutdown_function(static function()use($db){try{editorPresenceUnlock($db);}catch(Throwable $e){}});
        $release=false;
    } finally { if($release)editorPresenceUnlock($db); }
}
function editorPresenceDraftVersion(PDO $db,string $token): array {
    $peer=editorPresencePeer($db,$token);
    return ['scope'=>$peer['scope_key'],'revision'=>(int)$peer['base_revision'],'external'=>(int)$peer['external_revision']];
}
function editorPresenceRestoreDraft(PDO $db,string $token,array $snapshot): void {
    $peer=editorPresencePeer($db,$token);$saved=$snapshot['content_version']??null;
    // Older drafts have no concurrency baseline. Force review if content has changed since setup.
    $base=is_array($saved)&&($saved['scope']??'')===$peer['scope_key']?$saved:['revision'=>0,'external'=>0];
    $db->prepare('UPDATE content_editor_presence SET base_revision=?,external_revision=? WHERE peer_key=?')->execute([(int)$base['revision'],(int)$base['external'],$peer['peer_key']]);
}
