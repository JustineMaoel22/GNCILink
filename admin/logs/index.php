<?php
require_once __DIR__.'/../../config/config.php';require_once __DIR__.'/../admin-functions.php';initSession();requireLogin();requirePermission('view_logs');header('Location: /admin/logs.php');exit;
