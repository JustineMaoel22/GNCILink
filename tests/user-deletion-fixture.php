<?php
if (PHP_SAPI !== 'cli' || !preg_match('/^gnci_rbac_test_[a-f0-9]{12}$/D',$argv[1]??'')) exit(1);
error_reporting(E_ERROR|E_PARSE);define('DB_NAME',$argv[1]);
require __DIR__.'/../config/config.php';$db=getDB();$action=$argv[2]??'';
if ($action==='setup') {
    $db->prepare('UPDATE users SET password=? WHERE user_id=1')->execute([password_hash('Synthetic-admin-confirmation-2026!',PASSWORD_DEFAULT)]);
    foreach ([4,5] as $id) $db->prepare("INSERT INTO users(user_id,first_name,last_name,email,password,role,status) VALUES(?,'Delete','Fixture',?,?,'The Mirror','active')")->execute([$id,'delete'.$id.'@example.invalid',password_hash('Synthetic-target-password-2026!',PASSWORD_DEFAULT)]);
    $db->exec("INSERT INTO announcements(user_id,title,slug,content,status,program) VALUES(4,'Preserved authored announcement','preserve-delete-fixture','Published content retained','published','ALL')");
    $db->exec("INSERT INTO rbac_submissions(user_id,kind,route,title,status) VALUES(4,'request','/admin/action/create-announcement.php','Pending deletion fixture','pending')");
    $db->prepare('INSERT INTO auth_password_resets(user_id,token_hash,expires_at) VALUES(4,?,DATE_ADD(NOW(),INTERVAL 1 DAY))')->execute([hash('sha256','synthetic-delete-reset')]);
    $db->exec('CREATE TABLE deletion_guard_fixture(user_id INT NOT NULL,CONSTRAINT deletion_guard_fk FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE) ENGINE=InnoDB');
    $db->exec('INSERT INTO deletion_guard_fixture VALUES(5)');
} elseif ($action==='clear-limits') $db->exec('DELETE FROM auth_rate_limits');
elseif ($action==='inspect') echo json_encode([
    'users'=>$db->query('SELECT user_id FROM users ORDER BY user_id')->fetchAll(PDO::FETCH_COLUMN),
    'posts'=>$db->query("SELECT title,content,user_id FROM announcements WHERE slug='preserve-delete-fixture'")->fetchAll(),
    'submissions'=>$db->query('SELECT user_id,status FROM rbac_submissions WHERE user_id=4')->fetchAll(),
    'reset_count'=>(int)$db->query('SELECT COUNT(*) FROM auth_password_resets WHERE user_id=4')->fetchColumn(),
    'deletion_logs'=>(int)$db->query("SELECT COUNT(*) FROM activity_logs WHERE action='DELETE_USER'")->fetchColumn()
]);else exit(1);
