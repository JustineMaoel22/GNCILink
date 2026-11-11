# API review and request protection

Implemented 2026-11-09. No schema migration, production data edits, or new dependencies.
Existing uncommitted chatbot allowance work was retained.

## Enforcement and limits

`config/config.php` applies IP limits before database work; `initSession()` applies
authenticated user limits. `includes/request-protection.php` selects a policy from
the actual PHP entry point, method and upload presence. Changing query strings or
action names does not create new buckets. IP and user budgets both apply; sessions
do not reset the IP budget. Existing RBAC, ownership, CSRF, account lockout and
editor conflict checks remain in force. A limiter is not an authorization check.

These are initial operational defaults, per endpoint, in fixed windows starting
at first use. A boundary can permit two windows' traffic close together; this is
not a sliding-window guarantee. Tune after observing legitimate school traffic,
especially shared campus networks and multiple editor tabs.

| Requests | IP/min | User/min | IP/hour | User/hour |
|---|---:|---:|---:|---:|
| Recommendation catalog | 120 | — | 2400 | — |
| Calendar details | 180 | — | 3600 | — |
| Analytics; privacy preference clearing (each) | 120 | — | 2400 | — |
| Chatbot transport, including status/local replies | 120 | — | 1800 | — |
| Search, calendar/filter/pagination query pages | 180 | 120* | 3600 | 2400* |
| Login/OTP/recovery/reset POST | 20 | 20* | 100 | 100* |
| Contact form POST | 5 | — | 20 | — |
| Editor presence | 600 | 180 | 12000 | 5000 |
| Photo checking / image optimization | 120 | 40 | 1200 | 400 |
| Other multipart admin uploads | 180 | 60 | 1800 | 600 |
| Editor drafts (without files) | 300 | 120 | 6000 | 2400 |
| Facebook integration actions / recategorization | 60 | 20 | 600 | 120 |
| Other admin writes | 600 | 240 | 12000 | 6000 |
| Other admin action reads | 600 | 240 | 12000 | 6000 |

`*` User limits apply when the endpoint initializes an authenticated admin session.
Public pages do not start sessions just to rate-limit visitors. Photo and upload
policies additionally share a total budget of 2400/IP/hour and 800/user/hour.
Ordinary unfiltered public navigation and static assets are not throttled here.

Existing authentication limits remain: 30 login attempts/IP/15 minutes, account
lockout for 15 minutes after ten failed password attempts, and OTP send/resend/verification
controls in `includes/auth-security.php` and `admin/auth/auth_handler.php`.
The chatbot retains its rolling `GNCBOT_IP_LIMIT` allowance (default 20/10 minutes)
and `GNCBOT_DAILY_LIMIT` (default 3000); greetings/status do not consume that
allowance. Its file limiter now refuses work on storage/lock/write failure.

Provider quotas count actual attempts, including retries, across entry points:

| Integration | Global/hour | Global/day |
|---|---:|---:|
| Chatbot Gemini | 1000 | 2 × configured chatbot daily message limit |
| Photo Gemini | 300 | 1500 |
| Facebook publishing/OAuth Graph requests | 600 | 3000 |
| Homepage Facebook feed | 30 | 720 |
| Facebook image downloads | 180 | 4320 |
| Password breach-range checks | 300 | 1500 |
| Contact email | 200 | 1000 |

## Endpoint inventory and findings

- Public `/api/`: `calendar-day.php`, `recommendations.php`, `track-visit.php`,
  `privacy-preferences.php`. Added budgets to all four. Analytics rejects
  cross-site fetches and malformed/oversized path/title input. Consent checks are
  preserved. Catalog rebuilding no longer blocks a queue of PHP workers: a
  contender can use a validated snapshot up to five minutes old or receive 503.
- `/chatbot/chatbot.php`: status, local shortcuts and Gemini messages; preserved
  JSON/header/origin validation, message/history bounds, published-data retrieval
  and allowance display. Added transport and provider-attempt budgets, trusted
  proxy verification, fail-closed throttling, `Retry-After`, and sanitized logs.
- All 31 `/admin/action/` PHP handlers were inventoried for session, role/permission,
  method and CSRF boundaries and tested to reject unauthenticated POSTs:
  `admission-category-handler`, `alumni-voices-handler`, `athletics-sports-handler`,
  `community-activities-handler`, `content-completeness-handler`,
  `create-announcement`, `create-news`, `editor-draft-handler`,
  `editor-presence-handler`, `enrollment-canvas-handler`,
  `enrollment-illustrations-handler`, `facebook-oauth`, `facebook-posts-handler`,
  `faqs-handler`, `image-framing-handler`, `onboarding-handler`, `optimize-image`,
  `page-content-handler`, `page-review-handler`, `payment-guide-handler`,
  `photo-check-handler`, `profile-picture-handler`, `program-page-handler`,
  `publish-confirmation-handler`, `responsive-background-handler`,
  `scholarship-handler`, `student-achievements-handler`,
  `student-organizations-handler`, `text-layout-handler`, `tuition-fees-handler`,
  `unpin-content`. File suffixes are `.php`. Policies distinguish presence,
  image processing, uploads, drafts, integrations, reads and writes.
- Embedded form/action routes: `admin/events.php` (including calendar import),
  `enrollment-steps.php`, `settings.php`, `users.php`, `personnel.php`,
  `submissions.php`, `facebook-integration.php`, password-change pages and
  `backfill-fb-categorizer.php` receive the shared POST/upload protections.
  Recategorization now presents a CSRF-protected POST form instead of mutating on
  GET. Photo diagnostics require authenticated POST `test=1` plus CSRF instead of
  performing paid AI work/resetting circuit-breaker state on GET.
- Public search/filter reads: news/announcements, homepage/campus calendars,
  student organizations and community impact. Existing 500 ms admin search
  debouncing and cancellation in directory/campus/community filters were retained.
  Homepage calendar requests now cancel superseded work and reject stale results.
  Failed homepage/organization AJAX requests no longer trigger a second full-page
  navigation request. Feedback uses an external Google Form; its submission limits
  belong to Google, not a local API.
- Outbound integrations: homepage Facebook feed/image downloads, Facebook
  publishing/OAuth, Gemini chat/photo checking, password breach checks, contact
  email, and internal approval replay. Existing approval worker nonce, single-use
  route validation, no redirects and 90-second timeout remain unchanged.

## Request reduction and user experience

- Footer suggestions and the recommendations dialog share one promise and a
  30-second in-memory snapshot: two catalog calls become one per page load.
- Chat replies already carry allowance data; their redundant follow-up status call
  was removed. Status calls have a 10-second deadline, cannot overlap, pause while
  hidden and back off after errors, stopping after five consecutive failures.
- Presence polling has one in-flight ping, exponential backoff honoring
  `Retry-After`, and stops after five failures. Reopening the page reconnects;
  successful explicit conflict checks also restore polling eligibility. Recovery
  no longer sends the same check twice. Active presence cadence stays at five
  seconds to preserve existing leases.
- Shared browser fetch handling adds 30-second read and 120-second mutation
  deadlines while preserving shorter caller signals. It honors server cooldowns
  across query/action variations and shows an accessible notice. It never retries
  mutations or caches admin payloads. Third-party fetches are untouched. The guard
  is delivered through the existing HTML transformation and admin/auth templates;
  editor preview frames retain their existing request handling.
- Chatbot generation has a 28-second total provider deadline and at most three
  attempts, within the existing 35-second browser deadline. Photo checks keep their
  45-second provider deadline and stop model fallback on 429. The duplicate-face
  review request now times out after 60 seconds.
- A stale Facebook feed permits one refresh attempt per two minutes, even after
  an empty/error response. Other visitors use cached records immediately. Refresh
  networking is bounded to approximately 20 seconds; at most two image candidates
  per post are attempted. Image downloads accept HTTPS Facebook CDN hosts only,
  do not follow redirects, and stop at 5 MiB. Unsupported/redirected images retain
  the existing no-image fallback. Feed credentials moved from the URL to an
  Authorization header; raw provider errors are no longer logged.

## Deployment and monitoring

No migration is required. The PHP worker needs a writable private local directory
with working cross-process `flock`. Default: an installation-specific directory
under the system temp directory. Set `REQUEST_LIMIT_DIR` to an absolute private
path outside the document root to retain counters across temporary-directory
cleanup. Store permissions should restrict access to the PHP service account.
The new limiter has 256 shards, at most 4096 live entries/shard and a 1 MiB read
ceiling/shard; expired entries are removed under the same lock. Lock waits are
bounded to 100 ms. Corruption, unavailable storage or full shards return 503 for
protected requests; they do not permit paid work. Legacy chatbot allowance files
remain in their existing `gncbot_rl` location for compatibility.

By default only `REMOTE_ADDR` is trusted. Behind a reverse proxy, set BOTH
`REQUEST_TRUSTED_PROXIES` (comma-separated exact proxy IPs) and
`REQUEST_CLIENT_IP_HEADER` (e.g. `HTTP_CF_CONNECTING_IP`). The edge must overwrite
that header and direct origin access must be restricted. The old chatbot-only
trusted-header setting no longer trusts arbitrary clients. Without correct proxy
configuration, visitors behind that proxy share its IP budget.

This implementation targets the existing single PHP host. Multiple application
hosts need a shared atomic limiter before scaling; separate temp directories do
not enforce a cluster-wide quota. PHP application limits also cannot prevent
upload bandwidth exhaustion before PHP receives a request or volumetric denial
of service; hosting/proxy request-size and connection controls remain necessary.

Private PHP logs use `[request-protection]` entries for the first violation per
bucket/window, provider limits, storage failures, and sampled failed HTTP requests
(one per endpoint/status/identity/minute). Records contain policy/status and hashed
identities, not bodies, passwords, tokens, email addresses or raw IP addresses.
Use existing host log rotation/retention and alert on repeated storage failures or
large changes in 429/503 rates. No new monitoring service or dashboard is required.
No throughput/latency benchmark was performed; improvements above describe request
counts and enforced bounds, not measured production speedups.

## Verification

- `php tests/request-protection-test.php`: 48 checks (expiry, isolated identities,
  corrupt storage, spoofed/trusted proxy headers, all action/API policy coverage).
- `node tests/request-protection-http-test.cjs`: 30 simultaneous PHP processes
  accept exactly seven requests for a quota of seven; HTTP 429, Retry-After,
  no-store, endpoint/user isolation and storage failure 503.
- `node tests/request-guard-test.cjs`: cooldown, cancellation, Request objects,
  no mutation retries, recommendation promise deduplication/cache.
- `node tests/request-protection-site-test.cjs`: all 31 admin action handlers reject
  anonymous POST; public calendar, invalid date, catalog, privacy CSRF, chatbot
  status (no provider call), and login script delivery pass on local Apache.
- Existing tests: chatbot allowance; 249 browser route checks; public/admin PWA
  worker tests; 25 personnel photo policy checks; Docker-backed editor presence
  (22), Facebook management (13), publishing confirmation (27). Database-backed
  tests report no persistent data changes.
- Changed PHP files linted and changed JavaScript syntax-checked. Local PHP photo
  tests required enabling installed `mbstring` and `pdo_sqlite` for the test command.

Live provider requests, successful real account login/upload/publishing workflows,
multi-host deployment, and sustained production load were not exercised. Verify
those on staging with intended proxy topology before production rollout.
