<?php
require_once __DIR__.'/../../config/config.php';require_once __DIR__.'/../admin-functions.php';initSession();requireLogin();requirePermission('manage_users');header('Location: /admin/users.php');exit;
