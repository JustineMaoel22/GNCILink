<?php $e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <?php require_once __DIR__.'/../includes/pwa.php'; echo gncPwaHead(true); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($authTitle) ?> — GNCILink Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/login-style.css">
    <link rel="stylesheet" href="/assets/css/auth-security.css">
</head>
<body class="auth-page">
<main class="login-wrapper auth-wrapper">
    <section class="login-card" aria-labelledby="auth-title">
        <header class="login-header">
            <div class="brand-row"><img class="login-logo" src="/assets/images/logos/gnc-logo-v1.svg" alt="Guagua National Colleges logo" width="48" height="48"><span>GNCILink <small>ADMIN PANEL</small></span></div>
            <h1 id="auth-title"><?= $e($authTitle) ?></h1>
            <p class="auth-intro"><?= $e($authIntro) ?></p>
        </header>
        <div class="login-body">
