# GNCILink PWA

PWA support is added to the existing PHP site. No database migration, Node build,
new production dependency, or login change is required. Deploy to the site's root
on HTTPS. `localhost:8080` is also a secure development context.

## Files and integration

- `manifest.json`: stable app identity `/`, standalone launch, school colors,
  192/512px regular icons, and a separate maskable icon.
- `admin-manifest.json`: separate **GNCILink Admin** identity `/admin/`, launching
  `/admin/login` in standalone mode, with `/admin/` scope. Signed-in users are
  redirected to `/admin/dashboard`; signed-out users see the normal login page.
  All administrative aliases now stay below `/admin/`, including the editor,
  approvals, logs, settings and account screens. Existing root aliases such as
  `/dashboard`, `/login` and `/content-editor` remain working redirects. Separate manifest IDs identify
  separate apps ([MDN manifest identity](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/id)).
- `assets/images/pwa/`: PNG icons generated from the existing school seal,
  including the 180px Apple touch icon. Regenerate with
  `docker compose exec -T web php scripts/generate-pwa-icons.php` (PHP GD).
  Separate `admin-*.png` launcher icons use a green background and gold **ADMIN**
  badge; regular, maskable and Apple touch variants are linked only by the admin
  manifest/metadata. Already installed icons may update on the browser/OS's own
  schedule; reinstall the admin app if the old icon persists.
- `includes/pwa.php`: adds manifest, Apple metadata and isolated PWA assets to
  public HTML through the existing `includes/browser-urls.php` output handler.
  Existing relative URLs, CMS templates and admin authorization are preserved.
  The shared `components/header-admin.php` and `components/auth-page-start.php`
  add the admin manifest/controls directly. Embedded editors and preview frames
  omit PWA controls. The shared admin header sends `Cache-Control: no-store, private`.
- `.htaccess`: maps `/service-worker.js` to `service-worker.php`, supplies the
  manifest MIME type, and revalidates manifest/offline files where mod_headers is enabled.
- `service-worker.php`: emits a root-scoped JavaScript worker with a deployment
  version and public-route list derived from the existing route registry.
- `admin/service-worker.php`: separate `/admin/service-worker.js` registration,
  limited to `/admin/`. It has no Cache Storage, IndexedDB or replay queue.
  Navigation uses `cache: no-store`; connection failure displays a generic,
  non-personal offline message. POST/API/asset requests keep normal network behavior.
  Each worker accepts update messages only from its own app's clients.
- `assets/js/pwa-worker.js`: cache policy and lifecycle.
- `assets/js/pwa.js`, `assets/css/pwa.css`: install help, dismissible install
  notices, connection state, safe-area spacing and offline styling.
- `offline.html`: self-contained branded fallback with local assets.

The public footer has an **Install web app** button directly under its social media links. Supported Chromium browsers
can present their native installation dialog; iOS gets Safari → Share → Add to
Home Screen instructions. Other browsers receive menu-based instructions.
Installed mode hides install controls. A native install promotion may be offered
after the homepage campus map is at least 15% visible for two seconds. The compact
speech bubble above Cian appears once per tab session. Once shown, it stays visible
when scrolling away from the map or opening chat, until the visitor dismisses it
or chooses installation. Before appearing, it yields to privacy dialogs, open
modals and typing without taking focus from the map.
The card also works when the native install event is unavailable; its button then
opens platform-specific installation instructions. Dismissal lasts only for the
current tab's browsing session, including reloads and navigation between pages.
A new tab session can show the reminder again; browser session restore may preserve
session storage. There is no seven-day install cooldown. The visitor app records
observed installation events and standalone launches in `gncilink-visitor-installed`
to hide installation controls on later visits in the same browser. A new native
install offer clears that marker after uninstall. Browsers do not universally
expose apps installed previously or through a different browser/profile.
Installation availability depends on the browser/platform;
see [web.dev installation guidance](https://web.dev/learn/pwa/installation).

For the admin app, use the fixed **Install web app** button at the bottom right of
the login screen. It is hidden after authentication, including on the dashboard
and Content Editor. Admin pages never show automatic install
promotion while someone works. The app requires an online connection: dashboards,
editor content, authentication and API responses are never stored offline.
The existing account, OTP, session expiry, CSRF and role checks still apply.
PWA installation grants no account permissions and does not extend session life.
Browsers/apps on the same origin may share cookies/storage; app identity is not
account isolation. Updates are checked quietly, with no update popup, activation
request or forced reload while someone works. An already open admin screen shows an offline warning; a disconnected
cold launch shows the generic connection-required page, never cached admin data.

## Cache and security boundaries

- Public navigations use the network first, with HTTP cache bypassed. Published
  news, announcements and other current content are fetched normally online.
- Only successful, non-redirected HTML explicitly carrying
  `X-GNCI-PWA-Cache: public` may be stored. The server does not issue that marker
  for active sessions, session cookies, previews, token-bearing forms, private or
  no-store responses, contact forms, errors or privileged routes.
- The root worker has a strict public path/query allowlist, including dynamic
  `/departments/{slug}` and `/programs/{slug}` routes. Unknown query keys, editor
  previews, search queries, APIs, chatbot requests, auth routes/aliases, admin
  routes/aliases, uploads, external embeds and all non-GET requests bypass it.
  Shared static assets requested by an admin/editor client bypass it too.
- The initial public page may be fetched again anonymously to warm its offline
  copy. This request omits credentials, and still requires the public marker.
  It cannot store an authenticated preview. Sessions and cookies are not cleared.
  Admin clients never request an offline warmup and register only the scoped admin
  worker. They cannot activate the visitor worker, and visitor clients cannot
  activate the admin worker.
- A previously visited eligible page is available offline for up to seven days.
  Its offline copy displays a connection/freshness notice. Unvisited pages get
  the branded fallback. A 404/410 or non-cacheable online response removes an old
  copy; server errors may use the clearly identified offline copy.
- Contact forms and article detail pages currently start PHP sessions, so they
  remain online-only. Other public templates that start sessions are also
  excluded automatically. No CSRF-bearing page, draft preview, form submission
  or API response is saved/replayed for offline use.
- Successful public CSS/JS/images/local fonts are retained as visited. Public
  resources with a `v` version query and immutable Google font files reuse exact
  cached URLs for faster loads; unversioned resources check the network first.
  Reload/no-store requests bypass that reuse. Public
  Google font files may be retained when CORS permits. Cross-origin opaque
  responses, large assets, videos, PDFs, admin/editor scripts and uploads are
  excluded. External font stylesheets/embeds require a connection; system fonts
  remain available offline. Some lazy images not viewed online may be absent.
- There are at most 30 page copies (3 MiB each) and 120 runtime resources (2 MiB
  each), plus the small essential shell. Browser quota may evict data earlier.
  Optional cache failures never block the website. Only `gncilink-pwa-*` caches
  are managed; cookies, local storage, other applications' caches and IndexedDB
  are left alone.

The policy follows the explicit-storage guidance in
[MDN's PWA caching documentation](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Caching).
Offline content is a convenience snapshot, not a guarantee that information is
still current or still published. The seven-day limit and online revalidation
reduce stale copies; removal cannot reach a disconnected device immediately.

## Deployment and updates

1. Upload all files together, including `.htaccess` and the PNG icons. Preserve
   existing secrets/configuration. No production database action is needed.
   The explicit `/admin/` rewrite rules retain the original executing PHP files
   so RBAC still checks the original entry points. A narrowly targeted
   `DirectorySlash Off` handles the existing `admin/dashboard/` compatibility
   directory without changing other directory URLs.
2. Increment `version.txt` with releases. Worker/cache versions also include the
   PWA source contents and CSS/JS modification times/sizes, so deploying those
   changes produces a new worker even if the version number was missed.
3. Check `/manifest.json` (manifest JSON), `/service-worker.js` (JavaScript,
   `Cache-Control: no-cache, no-store, must-revalidate`) and `/offline.html` (HTML).
   Exclude the worker URL from CDN/page optimization caching and JS rewriting.
   Do not cache authenticated responses at a proxy/CDN.
4. Updates are checked quietly. New workers wait for normal browser activation
   after all app tabs/windows controlled by the old worker close. Reopen the app
   to use the new version. There is no update popup or forced page refresh.
5. Activation removes old GNCILink caches. On reconnection, an offline screen
   fetches the latest document in the background and offers **Refresh**; it does
   not replace current form or reading state automatically.

If an existing host CSP is configured, allow same-origin manifest, scripts,
styles, images, connections and `worker-src 'self'`. No new external PWA service
is required. HTTPS must work without certificate errors. Keep the root worker
URL/scope stable across releases. The admin worker has a separate stable URL
`/admin/service-worker.js` and scope `/admin/`.

### Migrating the earlier admin install

The visitor ID `/` and admin ID `/admin/` are unchanged, preserving their separate
identities. Earlier development builds gave the admin app root scope and a
`/dashboard` start URL. Browsers update installed manifest metadata on their own
schedule. **Reinstall only GNCILink Admin from `/admin/login`** to apply the new
narrow scope/start immediately. Do not clear site cookies/storage or uninstall
the visitor app. Confirm the install dialog says **GNCILink Admin** and uses the
green ADMIN-badged icon. Install the visitor app separately from `/`.

Because the visitor homepage is `/`, its scope necessarily contains the nested
`/admin/` scope. Supported browsers can distinguish the manifests by ID and use
the more specific admin scope; platform-specific install prompts/link capture can
still vary. Test both installed apps on real target devices. PWAs cannot force
installation or suppress browser UI for intentional external/public-site links.
See [multiple-app scope considerations](https://web.dev/articles/building-multiple-pwas-on-the-same-domain).

To remove the feature later, first deploy a retirement worker at the same URL
that deletes only `gncilink-pwa-*` caches and unregisters itself. Removing only
the manifest/script does not remove an already installed worker. Do not use
`Clear-Site-Data: "*"`, which would also destroy authentication/preferences.

## Verification

Automated checks (Node 24, local Docker site and Chrome):

```text
node tests/pwa-worker-test.cjs
node tests/admin-pwa-worker-test.cjs
node tests/pwa-browser-test.cjs
node tests/pwa-ui-browser-test.cjs
node tests/admin-pwa-browser-test.cjs
docker compose exec -T web php -l includes/pwa.php
docker compose exec -T web php -l includes/browser-urls.php
docker compose exec -T web php -l service-worker.php
docker compose exec -T web php -l admin/service-worker.php
docker compose exec -T web php tests/browser-routes-test.php
```

The worker test covers route/query boundaries, private/no-store enforcement,
network/offline behavior, version cleanup and update consent. The Chrome browser
test checks the real manifest/installability through DevTools protocol,
registration, first-page warmup, visited/unvisited pages offline with both page
and worker network disabled, recovery, installation help, and cache exclusions.
The UI test verifies that a waiting update creates no popup, sends no forced
activation request, and never reloads the current page. It also covers
iOS help and hiding install controls in emulated standalone mode.
These are equivalent targeted DevTools checks, not a fabricated Lighthouse score.

The admin browser check verifies a distinct installable manifest, scoped login redirects,
no-store responses, responsive install controls, recovery without reloading,
editor/embed exclusions, and the absence of administrative HTML/API responses
in Cache Storage. It traverses the main administrative pages and verifies that
their manifest and controlling worker stay scoped; it tests the successful login
JavaScript redirect with a stubbed response/test session, without sending OTP
email or submitting credentials. Routing tests cover every existing alias and
original CMS/RBAC identity. The admin worker test checks offline safety and update
isolation. UI checks verify the absence of update interruptions after editing
the login form without submitting any credentials.

Before production sign-off, run these **physical-device/manual** checks:

- Android Chrome: install, launch from the icon, verify standalone navigation and
  the icon/splash; visit school information, switch to airplane mode and revisit.
- iPhone/iPad Safari: Share → Add to Home Screen → Add (enable Open as Web App if
  offered). Launch from the icon; check safe areas, orientation, scrolling,
  touch navigation and offline recovery. An emulated user agent is not Safari.
- Desktop Chrome/Edge: install from the address bar/menu or footer control,
  launch in its app window and follow internal/external links.
- Install **GNCILink Admin** from `/admin/login` using the bottom-right button. Launch it,
  verify normal sign-in/OTP, session expiry, role restrictions and logout. Check
  account editing/publishing online and verify no private page becomes available
  after disconnecting. Confirm that public and admin app names/start destinations
  are distinct on each supported browser; handling of overlapping app scopes is
  browser-dependent.
- Keep two public tabs open, deploy a version bump, call the registration's
  `update()` in DevTools and verify there is no popup or automatic reload. Close
  all old app tabs/windows and reopen the app. The new worker should activate
  and old GNCILink caches should disappear after activation.
- Inspect DevTools → Application → Manifest / Service Workers / Cache Storage.
  Confirm no private/admin/API entries. Log in, open the editor, save a private
  draft and log out; verify these requests retain their existing authorization
  and are never returned from Cache Storage.
- Online: check homepage navigation, search, chatbot, news/announcements/events,
  programs, feedback, contact submission and editor preview. Offline: connection
  notices explain that search/chat/forms need the network; nothing is queued.

The implementation does not deploy to the live host or claim native iOS,
Android, desktop OS installation, or physical-device splash testing from this
Windows/headless test environment.
