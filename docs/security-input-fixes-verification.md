# Input fixes verification

All ten requested fix groups are implemented. No application schema changes or production-content writes were performed. Secrets were neither printed nor modified.

## Changed runtime files

- `admin/announcement.php`: JSON_HEX_TAG for create/edit modal hydration.
- `announcement-article.php`, `news-article.php`: control-character/whitespace normalization before URL scheme validation; missing Announcement category hydration is skipped.
- `chatbot/tests/allowance_test.php`: CLI-only guard after strict_types.
- `config/config.php`: corrected rate-limit default precedence.
- `admin/action/create-news.php`: scalar gallery error guard.
- `pages/about/administration.php`: string-only section lookup.
- `admin/action/page-content-handler.php`, `admin/action/create-announcement.php`, `admin/action/page-review-handler.php`, `admin/action/editor-draft-handler.php`, `admin/action/community-activities-handler.php`, `admin/action/text-layout-handler.php`: type-safe CSRF validation. Array tokens return 403. Ordinary Announcement invalid-token form submissions retain their redirect.
- `admin/action/page-content-handler.php`: string-only draft content, sanitized through richTextToStorage.
- `admin/events.php`: creation/audience/audit transaction; audit failures abort creation; bounded calendar year and validated/escaped category colors.
- `includes/multi-audience.php`: audience save errors propagate within transactions using a generic client error so the caller can roll back.
- `pages/contact-us.php`: single-leading-slash redirect validation, also rejecting backslashes/control characters.

## Commands run

Run from the repository root in PowerShell. PHP runs inside Docker.

```powershell
$taskPhpFiles = @('admin/announcement.php','announcement-article.php','news-article.php','chatbot/tests/allowance_test.php','config/config.php','admin/action/create-news.php','pages/about/administration.php','admin/action/page-content-handler.php','admin/action/create-announcement.php','admin/action/page-review-handler.php','admin/action/editor-draft-handler.php','admin/action/community-activities-handler.php','admin/action/text-layout-handler.php','admin/events.php','pages/contact-us.php','includes/multi-audience.php','tests/security-input-regressions-test.php','tests/csrf-array-regressions-test.php','tests/event-edit-handler-test.php','tests/news-view-test.php')
foreach ($taskPhpFile in $taskPhpFiles) { docker compose exec -T web php -l $taskPhpFile; if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE } }
docker compose exec -T web php tests/security-input-regressions-test.php
docker compose exec -T web php tests/announcement-view-test.php
docker compose exec -T web php tests/news-view-test.php all
docker compose exec -T web php tests/news-view-test.php scalar-gallery
docker compose exec -T web php chatbot/tests/allowance_test.php
docker compose exec -T web php tests/admin-rich-text-test.php
docker compose exec -T web php tests/admin-rich-text-storage-test.php
$taskModes = @('page-content','create-announcement','page-review','editor-draft','community-activities','text-layout','draft-array')
foreach ($taskMode in $taskModes) { docker compose exec -T web php tests/csrf-array-regressions-test.php $taskMode; if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE } }
docker compose exec -T web php tests/csrf-array-regressions-test.php draft-sanitize
$eventModes = @('stale','audience','missing','fresh','create','all-day','fetch')
foreach ($eventMode in $eventModes) { docker compose exec -T web php tests/event-edit-handler-test.php $eventMode; if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE } }
docker compose exec -T web php tests/event-edit-handler-test.php create-audit-failure
docker compose exec -T web php tests/event-edit-handler-test.php create-audience-failure
```

All 20 modified PHP files passed syntax checks. Security assertions: 60 passed. Announcement list: 16 passed. News list: 15 passed, plus ten handler modes and scalar-gallery. Rich-text: 42 assertions and storage lifecycle integration passed. Chatbot allowance lifecycle passed. All seven malformed-token/content modes and actual sanitized-draft persistence passed. All seven original event modes and both forced-failure rollback modes passed. Fault-injection SQL log messages are expected; clients receive generic errors.

New tests: `tests/security-input-regressions-test.php`, `tests/csrf-array-regressions-test.php`. Extended tests: `tests/event-edit-handler-test.php`, `tests/news-view-test.php`. Database tests shadow application tables with connection-local temporary tables; fault injection alters only those temporary tables.

## HTTP checks and limitations

Read-only Node fetch checks against localhost confirmed missing Announcement and News IDs return HTTP 404 with the `.notfound` UI, and `/administration?section[]=bad` returns HTTP 200 without a fatal error.

`/chatbot/tests/allowance_test.php` returns HTTP 403 because Apache denies the directory before PHP runs. The guard's own HTTP 404 could not be directly exercised through Apache; its syntax and CLI behavior passed. Hosting was not tested. No migration is required.
