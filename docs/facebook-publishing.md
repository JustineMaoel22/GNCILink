# Facebook announcement publishing

## For administrators: no tokens to enter

1. Sign in as **Super Admin → Settings → Manage Facebook Integration**.
2. Select **Connect Facebook**, sign into Facebook, and allow the requested Page permissions.
3. Back in GNCILink, choose your Page by name, check the authorization checkbox, and select **Connect selected Page**.
4. Create/edit an announcement, choose Website Only, Facebook Only, or Both, select **Publish now**, and confirm the destination.

Admins never copy tokens, type Page IDs, or edit configuration files. When access expires, select **Connect Facebook** again. If the button is disabled, ask the developer to finish the one-time setup below. Test mode only offers the configured test Page. Login, cancellation, connect, reconnect, disconnect, approval and Page switching never repost announcements.

## One-time developer setup

Keep the existing `FB_PAGE_ID` and `FB_ACCESS_TOKEN` unchanged: those belong to the imported feed. Configure these backend environment values or root `.env` entries:

```dotenv
FB_PUBLISH_ENVIRONMENT=test
FB_PUBLISH_PAGE_ID=YOUR_TEST_PAGE_NUMERIC_ID
FB_PUBLISH_APP_ID=YOUR_META_APP_ID
FB_PUBLISH_APP_SECRET=YOUR_META_APP_SECRET
FB_PUBLISH_GRAPH_VERSION=v26.0
FB_PUBLISH_REDIRECT_URI=http://localhost:8080/admin/action/facebook-oauth.php
FB_PUBLISH_TOKEN_KEY=YOUR_BASE64_ENCODED_32_BYTE_RANDOM_KEY
```

`FB_PUBLISH_PAGE_TOKEN` is **not required for Facebook sign-in**. Existing manual connections remain usable until replaced by a confirmed sign-in connection; manual-token setup is no longer offered in the UI. Process environment values take precedence over `.env`.

Generate the encryption key once, privately:

```sh
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Put the result in `FB_PUBLISH_TOKEN_KEY`, protect it, and back it up separately from the database. All PHP workers must share it. Losing/changing it requires reconnecting Facebook; do not routinely regenerate it.

1. Configure Facebook Login for the website in the Meta app dashboard. Enable Web OAuth Login, Client OAuth Login and strict redirect matching as applicable to the app's Login product.
2. Add the **exact** `FB_PUBLISH_REDIRECT_URI` to **Valid OAuth Redirect URIs**. Local development uses the localhost URI above; production must use your HTTPS domain, e.g. `https://your-domain.example/admin/action/facebook-oauth.php`. HTTP is accepted by the application only for localhost/127.0.0.1. Meta must also allow your development URL; use an HTTPS development hostname if required by its settings. Never derive the callback from incoming Host headers.
3. Configure `pages_show_list`, `pages_read_engagement`, and `pages_manage_posts`. During development, the Facebook account needs an app administrator/developer/tester role and permission to create content on the test Page. For wider use, complete the App Review, Advanced Access, verification, privacy policy and data deletion requirements applicable to the app. Scope names in PHP cannot grant Meta access.
4. Back up the database. New installation: import `sql/facebook-publishing-migration.sql`, which now includes encrypted-token storage. Existing installation: import **`sql/facebook-oauth-migration.sql`** after the original publishing migration. It adds one table without changing announcement content or publishing anything. No migration runs automatically.
5. Require PHP 8.2+, PDO MySQL, cURL/HTTPS, Fileinfo, and OpenSSL/AES-256-GCM. The Dockerfile installs PHP cURL; rebuild an older image if needed. Use HTTPS, protected sessions, and restricted server access in production. Apache denies `.env`; configure an equivalent rule on other servers.
6. Connect through Facebook sign-in. No Page token needs to be entered.

Meta references: [manual Login flow](https://developers.facebook.com/docs/facebook-login/guides/advanced/manual-flow/), [Pages getting started](https://developers.facebook.com/docs/pages-api/getting-started/), and [publishing](https://developers.facebook.com/docs/pages-api/posts/). These endpoints returned HTTP 429 during implementation. Verify your app's Login product, permission access and Graph version in its dashboard. Live Facebook consent/login/posting has not been tested.

## Security and connection behavior

Only `manage_settings` users (Super Admin) can start sign-in, use the callback, confirm a Page or disconnect. Authenticated POSTs require CSRF verification. OAuth state is random, session/user-bound, single-use and expires after ten minutes. Authorization/state are checked before exchanging the code. Sign-in errors and cancellation leave the current connection unchanged.

Code exchange, long-lived User-token exchange and authorized Page retrieval happen on the backend. Pagination uses a fixed Graph endpoint and cursor, never an arbitrary returned next URL. Only Pages with publishing tasks are offered. Pending Page tokens are encrypted in the server session for ten minutes. The confirmed token is encrypted with AES-256-GCM in `facebook_publishing_tokens`; the key stays outside the database. Tokens never appear in HTML, hidden inputs, JavaScript, browser URLs, notices or raw error responses.

Confirmation rechecks token type, app, expiry, scopes, Page grants and identity before atomically saving the Page and encrypted token. Pending choices are discarded on confirmation, cancellation, expiry, or user/environment changes. The token is revalidated before publishing. Connection changes share the publishing lock. Disconnect removes local OAuth credentials and disables publishing; it does not delete posts or revoke the app in Facebook. Revoke app access separately in Facebook if desired.

A stored OAuth token takes precedence over legacy backend credentials. Legacy fallback is used only without an OAuth row. Changing the allowed test Page/environment invalidates stored OAuth credentials until an explicitly confirmed reconnect.

## Publishing, approval and retry safety

Website Only remains the default whenever the form opens. Editors and Office Staff retain the existing approval workflow and cannot publish to Facebook. Approval alone never sends a post. Authorized publishers explicitly choose Facebook with Publish now and confirm the Page.

Facebook Only saves an editable local website draft and removes its homepage pin. Both publishes the website first; a Facebook failure does not undo website publication. Status, destination Page, sanitized errors and saved post ID/URL appear in the list; Integration shows the latest 50 deliveries.

Headline/body become plain text with inline HTTP(S) URLs retained. The main picture and gallery are uploaded from validated local announcement storage as unpublished photos, then attached to one feed post (10 photos total). An optional link becomes a link attachment without photos, or message text with photos. Meta controls previews/rendering. Preparation failures may leave unpublished photos; the feed request runs only after all photos are ready.

Each announcement creates at most one confirmed Facebook post through the UI, across edits and Page switches. Editing a delivered announcement does not update or duplicate its Facebook post. Create a distinct new announcement deliberately for another post.

Request receipts prevent replayed forms from creating another announcement. Delivery primary keys and the MySQL named lock serialize sends. `sending` is persisted before the final feed call; success stores `published`, PageID_PostID and URL. Explicit rejection is `failed`; ambiguous timeouts/server/transient/malformed responses become `unknown`. A crash/database error can leave `sending`. Both uncertain states block repeat sends, with no automatic ambiguous-write retries.

Retry ordinary failures by editing the saved announcement and explicitly choosing Facebook again. For `unknown`/`sending`, a Super Admin checks the original Page and records a verified existing full post ID, or explicitly confirms no matching post exists to unlock a manual retry. Recovery actions never publish. Incorrect absence confirmation can cause duplicates. An attempt for another Page cannot be retried to a new destination.

## Official Page and rollback

Until authorized, keep test mode and the test Page ID configured. When explicitly authorized, the developer sets `FB_PUBLISH_ENVIRONMENT=official`; a Super Admin signs in, chooses the official Page and confirms it. Official mode offers eligible authorized Pages; test mode offers only the configured test Page. No source change or automatic repost is involved.

Disconnect before a code rollback. Retain delivery/request tables for audit and duplicate protection; never delete them to force retries. OAuth ciphertext cannot be read by the original manual-token version, so that version needs deliberate manual configuration if reverting. No destructive rollback SQL is necessary.

## Verification

- `docker compose exec -T web php tests/facebook-oauth-test.php`: encryption/tampering, OAuth state/expiry/replay/user binding, URL validation, server-only exchanges, pagination, test-only selection, authorization, failed-connection preservation, credential use, no reposts and disconnect. Simulated Meta; temporary tables.
- `docker compose exec -T web php tests/facebook-publishing-test.php`: token validation, delivery states, duplicates, Page switching, photos/links and request uniqueness. Simulated Meta; temporary tables.
- `docker compose exec -T web php tests/facebook-publishing-handler-test.php <scenario>`: real handler, temporary tables, Meta disabled. Scenarios: website, facebook, both, update, editor, csrf, confirmation, replay.
- `node tests/facebook-publishing-browser-test.cjs`: token-free UI, publishing dialogs/cancellation, reset behavior, mobile sizing, authentication, CSRF and `.env` protection. Requires local Docker/Chrome and an active Super Admin for a temporary session. Actual form submission is disabled.
- Developer diagnostic: `docker compose exec -T web php tests/facebook-connection-check.php` checks active backend access without publishing, changing settings or printing credentials.

These checks do not replace a live consent/login test with your configured Meta app and test Page.

## Facebook post links

Both outbound admin views use the public site's shared `gncFacebookPostUrl()` handler through `facebookPublishingViewPostUrl()`. Matching numeric post paths render as an explicit story/Page URL using the Graph Page/post identity, the format previously tested for the public site's iOS handoff issue. The original Meta permalink stays unchanged in storage; opaque pfbid/video/photo links are preserved. This is a URL-handling consistency fix, not proof that the new test post opens on every iOS device/account. A real-device retest remains necessary, particularly with the Meta app in Development mode.

The publisher reads and stores Meta's `permalink_url` after the feed succeeds. A Graph ID such as `PageID_PostID` is an API identifier, not a browser permalink; Meta's returned link can use a different Page/profile identifier. The returned permalink is preserved after validating its Facebook host. If its lookup fails, the confirmed delivery remains published and uses an explicit story/Page fallback, so retrying a link lookup never creates another post. Admin views also repair old composite-ID URL formats when rendering them.

Developer diagnostic: `docker compose exec -T web php tests/facebook-post-check.php` reads the latest five saved post IDs and asks Meta for published status/permalinks, without changing records. Adding `--refresh-links` explicitly updates only their verified local delivery URLs; it never publishes/edits/deletes Facebook posts or changes tokens/delivery states. A correct permalink cannot override Facebook audience, Page visibility, deletion, age/country restrictions, or account access.

Use `--visibility` for read-only Page name/link, API visibility flags, post author and presence in the latest 25 published posts. Unavailable fields/list checks are reported as unavailable or null, never as proof of deletion. A Graph response of published/public does not prove that a browser viewer can access the post. For a Meta app in Development mode, first test with the personal Facebook account assigned an accepted admin/developer/tester role on that same app, as well as Page access. The GNCILink test/official setting is separate from Meta's Development/Live mode. Do not repost merely because a browser cannot view a confirmed API post.
