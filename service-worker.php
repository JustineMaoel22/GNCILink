<?php
require_once __DIR__ . '/includes/pwa.php';
if (gncAdminRequestOrigin()!==null) {http_response_code(404);header('Cache-Control: no-store');exit;}
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Service-Worker-Allowed: /');
header('X-Content-Type-Options: nosniff');
echo 'self.GNCI_PWA = ' . json_encode(['version'=>gncPwaVersion(), 'paths'=>gncPwaPublicPaths()], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ";\n";
readfile(__DIR__.'/assets/js/pwa-worker.js');
