<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/user-deletion.php';

// Exercise the real deletion function with an in-memory PDO double. No accounts,
// database tables, audit records or sessions outside this test can be changed.
class ProtectedAdminDeletionPDO extends PDO {
    public array $executed = [];
    public array $target = [];
    public string $passwordHash;
    private bool $active = false;
    public function __construct() { $this->passwordHash=password_hash('test-confirmation-only',PASSWORD_DEFAULT); }
    public function beginTransaction(): bool { $this->active=true;return true; }
    public function inTransaction(): bool { return $this->active; }
    public function commit(): bool { $this->active=false;return true; }
    public function rollBack(): bool { $this->active=false;return true; }
    public function query(string $query, ?int $fetchMode=null, mixed ...$args): PDOStatement|false {
        return new ProtectedAdminDeletionStatement($this,$query);
    }
    public function prepare(string $query,array $options=[]): PDOStatement|false { return new ProtectedAdminDeletionStatement($this,$query); }
}
class ProtectedAdminDeletionStatement extends PDOStatement {
    public function __construct(private ProtectedAdminDeletionPDO $db,private string $sql) {}
    public function execute(?array $params=null): bool { $this->db->executed[]=[$this->sql,$params];return true; }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed {
        if(str_starts_with($this->sql,'SELECT password,')) return ['password'=>$this->db->passwordHash,'role'=>'Super Admin','status'=>'active','credential_version'=>1,'locked_until'=>null];
        return $this->db->target;
    }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array { return str_contains($this->sql,'information_schema')?[]:[1,41]; }
}
$db=new ProtectedAdminDeletionPDO();$authorized=true;
function getDB(): PDO { global $db;return $db; }
function hasPermission(string $permission): bool { global $authorized;return $authorized; }
function rbacIsSuper(): bool { return true; }
function authRateAllowed(...$args): bool { return true; }
function authAudit(...$args): void {}
function logActivity(...$args): bool { return true; }
function protectedCheck(bool $condition,string $message): void { if(!$condition)throw new RuntimeException($message); }
function deletionRefused(callable $call,string $expected): void {
    try { $call(); } catch (RuntimeException|InvalidArgumentException $e) {
        protectedCheck($e->getMessage()===$expected,'Unexpected refusal: '.$e->getMessage());return;
    }
    throw new RuntimeException('Deletion unexpectedly accepted.');
}
$_SESSION=['user_id'=>1,'credential_version'=>1];
foreach ([41,987] as $id) {
    $db->target=['user_id'=>$id,'email'=>'GNCIWEB.ADMIN@gmail.com','first_name'=>'Renamed','last_name'=>'Account','role'=>'Content Editor','status'=>'active'];
    $db->executed=[];
    deletionRefused(fn()=>deleteAdminUser($id,'test-confirmation-only'),'The Default Admin account is protected and cannot be deleted.');
    protectedCheck(!$db->inTransaction(),'A rejected deletion must close its transaction.');
    foreach($db->executed as [$sql]) protectedCheck(!preg_match('/^(DELETE|UPDATE) /',$sql),'Protected deletion attempted to modify related records.');
}
$db->target=['user_id'=>42,'email'=>'ordinary@example.test','first_name'=>'Default','last_name'=>'Admin','role'=>'Content Editor','status'=>'active'];
$db->executed=[];
deleteAdminUser(42,'test-confirmation-only');
protectedCheck(count(array_filter($db->executed,static fn($query)=>$query[0]==='DELETE FROM users WHERE user_id=?'&&$query[1]===[42]))===1,'Ordinary account deletion must remain available.');
deletionRefused(fn()=>deleteAdminUser(1,'test-confirmation-only'),'You cannot delete your own account.');
$authorized=false;
deletionRefused(fn()=>deleteAdminUser(42,'test-confirmation-only'),'Only Super Admin can delete users.');
echo "PASS: Default Admin deletion blocked before any cleanup, independent of ID/name/role/email case; ordinary deletion, self-protection and authorization retained. No database accessed.\n";
