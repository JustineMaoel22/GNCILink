# Active viewers and editors

The Content Editor toolbar shows the number of people on the current preview
page. Open **Active** to see their names, profile pictures (or initials) and **Viewing**/**Editing**
status. Multiple tabs for one account appear once; any editing tab makes that
account Editing. Program/department/organization detail pages have separate
lists. Directory filters, pagination and device sizes share the same list.
Administration's four section tabs also share one list and save target, matching
their shared collection draft.

Updates use one small authenticated POST every five seconds, with no WebSocket
service or additional dependency. Pending changes or recent input mark Editing;
Preview marks Viewing. The list contains no draft content, email addresses,
session identifiers or publicly accessible user information. Saved profile pictures
use the existing validated avatar storage and one batch lookup for the people on
this page. Missing or unreadable pictures fall back to initials.

Leaving or switching pages sends a best-effort removal. Disconnected entries
stop appearing after 45 seconds; an untouched tab stops heartbeats after five
minutes. Expired logins and revoked accounts cannot refresh presence. Heartbeats
do not extend the existing login inactivity timeout. Old presence records are
removed during later joins using an indexed expiry lookup.

## Saving and conflicts

An amber message identifies other people editing the same page. It is advisory;
people can continue making local changes. The server checks a preview's content
version before saving a private checkpoint, submitting, approving or publishing
through the visual editor. A stale operation returns HTTP 409 and preserves the
pending work in the browser. There is no automatic merge or overwrite option.
Review/reconcile those changes against the current page before trying again.
The warning offers **Save recovery draft** for pending work. This explicitly
saves a private checkpoint while retaining its stale baseline; restoring it
cannot bypass the publishing conflict check. It does not publish or submit a
proposal. Existing same-account draft revision checks still prevent two tabs
from overwriting each other's private checkpoint.

Each preview establishes its baseline **before** loading the website page.
Private checkpoints and approval snapshots retain that baseline when restored.
Existing checkpoints created before this feature have a zero baseline, requiring
review when versioned website changes have occurred. Saving a private checkpoint
does not change the live-content revision. Queued visual-editor requests also
retain their baseline; approval execution checks it before changing content.

MySQL named locks serialize version checks with actual handler execution without
wrapping or replacing the handlers' existing transactions. A short publishing
lease prevents another tab from interleaving writes into the same content family
while a multi-request publication is running. It releases at completion or expires
after 120 seconds without another write. Revisions are reserved before a write:
a rejected/failed operation can conservatively require other previews to reload,
so partially committed or interrupted requests never silently look current.

Presence lists are specific to the visible page; conflict versions deliberately
include shared content. Homepage/academic/administration pages share a version
family because they reuse personnel and academic data. Other features have their
own families. Conventional managers, embedded post forms and approval workers
without a visual-editor token advance an external revision, conservatively
invalidating existing previews. Shared image-framing changes do the same.
Independent managers keep their existing editing/approval behavior; this feature
does not add collaborative text editing or merge their form changes.

## Deployment

Deploy the changed PHP/JavaScript/CSS together, including
`includes/editor-presence.php`, `admin/action/editor-presence-handler.php` and
`assets/js/editor-presence.js`. Install the two additive metadata tables using
`php database/migrate-editor-presence.php` or import
`sql/editor-presence-migration.sql` in the hosting control panel. The migration is
idempotent and does not modify existing content tables. It was installed locally;
hosted deployment still needs this step. MySQL/MariaDB named-lock support and
InnoDB are required, using the existing database account/connection.

Without the tables, the new editor allows viewing/local changes but reports that
presence/protection is unavailable and refuses its saves. Conventional handlers
continue their previous behavior until the migration is installed. Do not remove
the tables while the new UI is active. For rollback, restore the previous runtime
files together; metadata tables may remain. No content data rollback is needed.

## Verification

- `docker compose exec -T web php tests/editor-presence-test.php` uses temporary
  tables to check account deduplication, page/alias identity, expiry, credentials,
  session binding, stale versions, old drafts, approval versions and leases.
- `node tests/editor-presence-browser-test.cjs` uses isolated local browser
  sessions for Super Admin and Content Editor. It checks activity, page switches,
  responsive panels, CSRF/session rejection, actual stale-draft rejection and
  simultaneous administrator requests. Invalid write payloads exercise the real
  concurrency boundary without modifying published content. Its CLI fixture is
  local-only and removes its temporary login/presence records on completion.

The existing handler validation, publish-password confirmation, account/role
checks and approval flow remain in effect. Hosted publication and long-running
network-disconnection behavior should also be checked after deployment.

Local verification passed: 22 domain checks, the multi-session browser suite
(including explicit recovery checkpoints and simultaneous writes), 27 existing
publishing-confirmation checks, and undo/redo/image regression checks on home,
program and history previews. PHP/JavaScript syntax and whitespace checks passed.
Desktop/tablet/mobile presence panels were inspected at 1440/768/390px. Tests did
not publish website content; the conflict tests reserve metadata versions only.
