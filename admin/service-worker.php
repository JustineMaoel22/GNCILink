<?php
/** Admin app worker: network-only, with no Cache Storage or request replay. */
require_once __DIR__.'/../includes/pwa.php';
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Service-Worker-Allowed: /admin/');
header('X-Content-Type-Options: nosniff');
echo '// GNCILink Admin '.gncPwaVersion()."\n";
?>
'use strict';
self.addEventListener('activate',event=>event.waitUntil(self.clients.claim()));
self.addEventListener('message',event=>{
    const source=event.source;
    if (!source?.url || event.data?.type!=='SKIP_WAITING') return;
    const url=new URL(source.url);
    if (url.origin===self.location.origin && url.pathname.startsWith('/admin/')) event.waitUntil(self.skipWaiting());
});
self.addEventListener('fetch',event=>{
    const url=new URL(event.request.url);
    if (url.origin!==self.location.origin || !url.pathname.startsWith('/admin/')) return;
    // POST, APIs, assets and authentication actions retain native network behavior.
    if (event.request.method!=='GET' || event.request.mode!=='navigate') return;
    event.respondWith(fetch(new Request(event.request,{cache:'no-store'})).catch(()=>new Response(
        '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#094024"><title>Connection needed | GNCILink Admin</title><style>body{margin:0;min-height:100dvh;display:grid;place-items:center;background:#094024;color:#173d2b;font:16px/1.6 system-ui,sans-serif}main{box-sizing:border-box;width:min(500px,calc(100% - 40px));padding:32px;background:#f9f6f1;border-radius:20px;border-top:4px solid #eaba3b}h1{font:700 30px/1.2 Georgia,serif}a{display:inline-block;margin-top:12px;padding:12px 18px;background:#094024;color:white;border-radius:8px;text-decoration:none}</style></head><body><main><h1>Reconnect to GNCILink Admin</h1><p>The admin panel needs an internet connection. Private pages and account information are never saved for offline access.</p><a href="/admin/login">Try again</a></main></body></html>',
        {status:503,headers:{'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store'}})));
});
