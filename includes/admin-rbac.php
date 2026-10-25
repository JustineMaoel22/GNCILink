<?php
/** Role policy and a single request boundary, shared by pages and action handlers. */
function rbacRole(?string $role = null): string {
    $role = $role ?? (string)($_SESSION['user_role'] ?? '');
    return ['Content Admin'=>'Content Editor','Office Staff'=>'The Mirror'][$role] ?? $role;
}
function rbacIsSuper(): bool { return rbacRole() === 'Super Admin'; }
function rbacHome(): string {
    if(!empty($_SESSION['must_change_password']))return '/admin/change-password.php';
    return rbacRole()==='The Mirror' ? '/admin/announcement.php' : '/admin/admin-dash.php';
}
function rbacFail(string $message, int $status = 403): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'error'=>$message]);
    exit;
}
function rbacRouteAllowed(string $role, string $route): bool {
    $role=rbacRole($role);
    if ($role==='Super Admin' || $role==='Content Editor') return true;
    if ($role!=='The Mirror') return false;
    return in_array($route, ['/admin/announcement.php','/admin/news.php','/admin/submissions.php',
        '/admin/action/create-announcement.php','/admin/action/create-news.php','/admin/auth/logout.php',
        '/admin/action/content-completeness-handler.php','/admin/change-password.php'],true);
}
function rbacRequestRoute(): string {
    $file=realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
    $root=realpath(__DIR__.'/../admin');
    if (!$file || !$root) return '';
    $file=str_replace('\\','/',$file);$root=str_replace('\\','/',$root);
    return str_starts_with($file,$root.'/') ? '/admin/'.substr($file,strlen($root)+1) : '';
}
function rbacRefreshIdentity(): void {
    static $checked=false;
    if ($checked || !isLoggedIn() || PHP_SAPI==='cli') return;
    $checked=true;
    try {
        try{$q=getDB()->prepare('SELECT role,status,must_change_password,credential_version FROM users WHERE user_id=?');$q->execute([(int)$_SESSION['user_id']]);$user=$q->fetch();}
        catch(PDOException $e){if($e->getCode()!=='42S22')throw $e;$q=getDB()->prepare('SELECT role,status FROM users WHERE user_id=?');$q->execute([(int)$_SESSION['user_id']]);$user=$q->fetch();}
        if (!$user || $user['status']!=='active') { $_SESSION=[]; rbacFail('Your account is inactive. Please sign in again.',401); }
        if(isset($user['credential_version'])&&(!isset($_SESSION['credential_version'])||(int)$user['credential_version']!==(int)$_SESSION['credential_version'])){authClearSession();rbacFail('Your session expired. Please sign in again.',401);}
        $_SESSION['must_change_password']=!empty($user['must_change_password']);
        if(isset($user['credential_version']))$_SESSION['credential_version']=(int)$user['credential_version'];
        $_SESSION['user_role']=rbacRole($user['role']);
        if (isset($_SESSION['user'])) $_SESSION['user']['role']=$_SESSION['user_role'];
    } catch (PDOException $e) { rbacFail('Account authorization is temporarily unavailable.',503); }
}
function rbacRequestBoundary(): void {
    static $checked=false;
    if ($checked || PHP_SAPI==='cli') return;
    $checked=true;
    rbacRefreshIdentity();
    $route=rbacRequestRoute();
    if ($route==='') return;
    if(in_array($route,['/admin/forgot-password.php','/admin/reset-password.php'],true))return;
    if(!empty($_SESSION['must_change_password'])&&!in_array($route,['/admin/change-password.php','/admin/auth/logout.php'],true)){
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'||str_starts_with($route,'/admin/action/'))rbacFail('Change your temporary password before using admin features.');
        header('Location: /admin/change-password.php');exit;
    }
    if (in_array($route,['/admin/login.php','/admin/auth/verify-otp.php','/admin/auth/auth_handler.php'],true)) return;
    if (!empty($_SESSION['_rbac_worker'])) {
        $worker=$_SESSION['_rbac_worker'];
        if (!rbacIsSuper() || ($worker['expires']??0)<time() || ($worker['route']??'')!==$route
            || !is_string($_SERVER['HTTP_X_GNCI_REVIEW']??null) || !hash_equals($worker['nonce']??'',$_SERVER['HTTP_X_GNCI_REVIEW'])
            || !empty($worker['used'])) rbacFail('Invalid approval execution.');
        $_SESSION['_rbac_worker']['used']=true;
    }
    if (!rbacRouteAllowed(rbacRole(),$route)) rbacFail('Your role cannot access this admin feature.');
    if (!rbacIsSuper() && (str_contains($route,'/users') || str_contains($route,'/logs') || $route==='/admin/backfill-fb-categorizer.php')) rbacFail('Only Super Admin can access this feature.');
    require_once __DIR__.'/content-submissions.php';
    if(!rbacIsSuper()&&($_SERVER['REQUEST_METHOD']??'GET')!=='POST'&&in_array($route,rbacContentRoutes(),true)&&isset($_GET['action'])&&!in_array($_GET['action'],['get','list','load','fetch_one','upload_environment','categories','get_content'],true))rbacFail('Content changes must be submitted by POST with a valid request token.',405);
    if (rbacIsSuper() || ($_SERVER['REQUEST_METHOD']??'GET')!=='POST') return;
    if (rbacShouldQueue($route,$_POST)) rbacQueueCurrentRequest($route);
}
function rbacContentOwnerId(): int { return (int)($_SESSION['_rbac_worker']['owner'] ?? $_SESSION['user_id'] ?? 0); }
function rbacCreatedRecord(string $kind, int $id): void {
    if (!empty($_SESSION['_rbac_worker'])) $_SESSION['_rbac_created']=['kind'=>$kind,'id'=>$id];
}
