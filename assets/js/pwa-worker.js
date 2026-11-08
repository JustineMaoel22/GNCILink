/* Served through /service-worker.js. Only explicitly public documents enter Cache Storage. */
'use strict';
const PREFIX = 'gncilink-pwa-';
const VERSION = self.GNCI_PWA.version;
const SHELL = PREFIX + VERSION + '-shell';
const PAGES = PREFIX + VERSION + '-pages';
const ASSETS = PREFIX + VERSION + '-assets';
const PUBLIC_PATHS = new Set(self.GNCI_PWA.paths);
const ESSENTIAL = ['/offline.html','/assets/css/pwa.css','/assets/js/pwa.js',
    '/assets/images/pwa/icon-192.png','/assets/images/pwa/icon-512.png',
    '/assets/images/pwa/maskable-512.png','/assets/images/pwa/apple-touch-icon.png',
    '/assets/images/pwa/admin-icon-192.png','/assets/images/pwa/admin-icon-512.png',
    '/assets/images/pwa/admin-maskable-512.png','/assets/images/pwa/admin-apple-touch-icon.png',
    '/assets/css/base-style.css','/assets/css/bootstrap.min.css',
    '/assets/css/navbar-style.css','/assets/css/footer-style.css','/assets/js/bootstrap.bundle.min.js'];
const MAX_AGE = 7 * 24 * 60 * 60 * 1000;

function publicPage(url) {
    if (url.origin !== self.location.origin) return false;
    const path = url.pathname.replace(/\/$/, '') || '/';
    if (!PUBLIC_PATHS.has(path) && !/^\/(programs|departments)\/[a-z0-9-]+$/.test(path)) return false;
    // Search results, previews, tokens and unrecognized query variants stay online-only.
    return [...url.searchParams].every(([key,value]) =>
        /^(slug|id|dept|program|section|category|page|event_page)$/.test(key) && /^[a-z0-9_-]{1,160}$/i.test(value));
}
function staticAsset(url, request) {
    if (url.origin !== self.location.origin) {
        return url.hostname === 'fonts.gstatic.com' && request.destination === 'font';
    }
    if (![...url.searchParams.keys()].every(key => key === 'v')) return false;
    let path;
    try { path = decodeURIComponent(url.pathname); } catch (_) { return false; }
    return !path.split('/').includes('..') && /^\/assets\/(?:css\/[\w.-]+\.css|js\/[\w.-]+\.js|images\/[\w/ .-]+\.(?:png|jpg|jpeg|webp|svg|ico)|fonts\/[\w/.-]+\.(?:woff2?|ttf))$/i.test(path)
        && !/admin|editor|content-edit|responsive-canvas/i.test(path);
}
function safeResponse(response, page) {
    return response.status === 200 && !response.redirected
        && !/private|no-store/i.test(response.headers.get('Cache-Control') || '')
        && (!page || (response.headers.get('X-GNCI-PWA-Cache') === 'public'
            && /text\/html/i.test(response.headers.get('Content-Type') || '')));
}
async function remember(name, request, response, limit) {
    try {
        const maximum = (name === PAGES ? 3 : 2) * 1024 * 1024;
        if (Number(response.headers.get('Content-Length')) > maximum) return;
        const bytes = await response.clone().arrayBuffer();
        if (bytes.byteLength > maximum) return;
        const headers = new Headers(response.headers);
        headers.delete('Content-Encoding'); headers.delete('Content-Length');
        headers.set('X-GNCI-PWA-Cached-At', String(Date.now()));
        const cache = await caches.open(name);
        await cache.delete(request);
        await cache.put(request, new Response(bytes,{status:200,headers}));
        const keys = await cache.keys();
        await Promise.all(keys.slice(0, Math.max(0,keys.length-limit)).map(key => cache.delete(key)));
    } catch (_) { /* Full/disabled storage never breaks an online page. */ }
}
async function stored(name, request) {
    const cache = await caches.open(name), response = await cache.match(request);
    if (response && Date.now() - Number(response.headers.get('X-GNCI-PWA-Cached-At') || 0) < MAX_AGE) return response;
    if (response) await cache.delete(request);
    return null;
}
async function network(request) {
    const controller = new AbortController(), timer = setTimeout(() => controller.abort(), 8000);
    try { return await fetch(new Request(request,{cache:'no-store',signal:controller.signal})); }
    finally { clearTimeout(timer); }
}
async function offlineDocument(request) {
    const saved = await stored(PAGES, request);
    if (saved) {
        const html = (await saved.text()).replace(/<head\b[^>]*>/i, '$&<meta name="gnci-offline" content="true">');
        return new Response(html,{status:200,headers:{'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store'}});
    }
    return await (await caches.open(SHELL)).match('/offline.html') || Response.error();
}
async function navigate(request) {
    try {
        const response = await network(request);
        if (response.status >= 500) return offlineDocument(request);
        if (safeResponse(response, true)) await remember(PAGES,request,response,30);
        else await (await caches.open(PAGES)).delete(request);
        return response;
    } catch (_) { return offlineDocument(request); }
}
async function asset(request) {
    const url = new URL(request.url);
    // Versioned URLs (and immutable Google font files) are safe to reuse exactly.
    // Unversioned media still checks the network first so replaced photos stay fresh.
    if (request.cache !== 'reload' && request.cache !== 'no-store'
        && (url.searchParams.has('v') || url.hostname === 'fonts.gstatic.com')) {
        const saved = await stored(ASSETS,request);
        if (saved) return saved;
    }
    try {
        const response = await network(request);
        if (safeResponse(response, false)) await remember(ASSETS,request,response,120);
        return response;
    } catch (_) {
        const saved = await stored(ASSETS,request);
        if (saved) return saved;
        // Essential shell URLs have no version query; serve those only offline.
        return await (await caches.open(SHELL)).match(url.pathname) || Response.error();
    }
}
self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(SHELL);
        await cache.addAll(ESSENTIAL.map(url => new Request(url,{cache:'reload',credentials:'omit'})));
        // An update waits until a visitor explicitly accepts it or old tabs close.
    })());
});
self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        await Promise.all((await caches.keys()).filter(name => name.startsWith(PREFIX)
            && ![SHELL,PAGES,ASSETS].includes(name)).map(name => caches.delete(name)));
        await self.clients.claim();
    })());
});
self.addEventListener('fetch', event => {
    const request = event.request, url = new URL(request.url);
    if (request.method !== 'GET' || request.headers.has('Range')) return;
    const page = request.mode === 'navigate' && publicPage(url);
    const resource = request.mode !== 'navigate' && staticAsset(url,request);
    if (!page && !resource) return; // Admin, auth, APIs, uploads, external embeds and POST are untouched.
    event.respondWith((async () => {
        // Even shared static files bypass this worker when loaded by the admin/editor.
        const client = event.clientId && await self.clients.get(event.clientId);
        if (client && !publicPage(new URL(client.url))) return fetch(request);
        return page ? navigate(request) : asset(request);
    })());
});
self.addEventListener('message', event => {
    const source = event.source;
    if (!source || !source.url) return;
    const url = new URL(source.url);
    if (!publicPage(url)) return;
    if (event.data?.type === 'SKIP_WAITING') event.waitUntil(self.skipWaiting());
    if (event.data?.type === 'CACHE_CURRENT_PAGE' && publicPage(url)) {
        event.waitUntil((async () => {
            const request = new Request(source.url,{credentials:'omit',cache:'no-store'});
            try {
                const response = await network(request);
                if (safeResponse(response,true)) await remember(PAGES,request,response,30);
            } catch (_) { /* Offline first launch still has the precached fallback. */ }
        })());
    }
});
