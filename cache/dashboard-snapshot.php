<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config/config.php';
require __DIR__.'/../admin/admin-functions.php';
require __DIR__.'/../includes/role-dashboard.php';
$role=$argv[1]??'Super Admin';
$q=getDB()->prepare("SELECT user_id FROM users WHERE role=? AND status='active' AND must_change_password=0 LIMIT 1");$q->execute([$role]);$id=$q->fetchColumn();
if(!$id)throw new RuntimeException('No test account available.');
$_SESSION=['user_id'=>(int)$id,'user_role'=>$role];
$data=roleDashboardData();
$analytics=null;
if(hasPermission('view_analytics'))try{$analytics=dashboardAnalytics(getDB(),14);}catch(Throwable $e){}
echo json_encode(['stats'=>$data['stats'],'recent'=>$data['recent'],'submissions'=>$data['submissions'],'analytics'=>$analytics,'errors'=>$data['errors']]);
