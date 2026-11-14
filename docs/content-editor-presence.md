# Active viewers and editors

The Content Editor toolbar shows the number of people on the current preview
page. Open **Active** to see their names, profile pictures (or initials) and **Viewing**/**Editing**
status. Multiple tabs for one account appear once; any editing tab makes that
account Editing. Program/department/organization detail pages have separate
lists. Directory filters, pagination and device sizes share the same list.
Administration's four section tabs also share one list and save target, matching
their shared collection draft.

Opening a page joins as **Viewing**, with editing controls read-only. **Start
editing** explicitly acquires the server reservation. Other tabs see "Currently
being edited by [name]." Reservations belong to a random, session-bound tab token,
not an account ID: another tab of the same account cannot write through it.

One authenticated heartbeat every five seconds renews an existing, unexpired
reservation. The reservation expires after 45 seconds without renewal (a publishing
batch may extend it to 120 seconds). A heartbeat never reacquires an expired or
revoked reservation. An untouched tab stops renewing after five minutes. Finish
editing, Preview, successful publication/submission, page switching and page exit
release access. Exit beacons are best effort; expiry recovers disconnected tabs.
Other authorized admins can continue viewing. No draft content is shared in presence.

A Super Admin can explicitly confirm **Take over editing**. The same database lock
serializes takeover, renewal, release and writes. Takeover records an
`EDITOR_TAKEOVER` activity before changing ownership; audit failure refuses it.
The old tab becomes read-only on its next heartbeat (immediately on an offline
signal); its next server write is rejected even before the heartbeat. Its local
pending work and private checkpoint remain available. The warning offers a private
**Save recovery draft**; this is the only write allowed without current editing
access, preserves the old baseline and uses the existing same-account draft revision
check. It cannot submit or publish. Unsaved browser work is retained in memory,
not automatically persisted; save a checkpoint before closing an offline tab.

## Draft recovery and version protection

Reopening loads published content first, then offers a saved checkpoint. Merely
loading a checkpoint does not change the preview baseline or apply its content.
The dialog identifies an older page version and offers **Review differences**,
**Keep draft and view current**, **Resume saved draft**, and confirmed **Discard
saved draft**. Keeping a checkpoint blocks replacement saves until it is resumed
or explicitly discarded. Legacy section-specific checkpoints are offered in place,
without silently copying or deleting them. Approval review retains its explicit
proposal-loading behavior.

Differences include changed text, current/saved settings, media references and
layout snapshots; this is a technical comparison, not an automatic merge. Resuming
an older draft keeps its original version, so saving/submitting/publishing remains
blocked. After reviewing differences, **Use reviewed draft with current version**
requires explicit confirmation. The server checks the draft revision, reservation,
and the exact published versions shown during review, retains the former baseline
in `reconciled_from`, and saves the reconciled checkpoint before applying it.
If published content changed during review, reconciliation is rejected. Refreshing
again offers the draft instead of silently restoring stale content.

The backend checks both the active reservation and preview version for normal
visual-editor writes. Submitting also checks the saved checkpoint's own version;
a fresh viewing token cannot submit an old checkpoint. Existing approval-worker
version checks and publishing-password/role boundaries remain in force.

Each preview establishes its baseline **before** loading the website page.
Private checkpoints and approval snapshots retain that baseline when restored.
Existing checkpoints created before this feature have a zero baseline, requiring
review when versioned website changes have occurred. Saving a private checkpoint
does not change the live-content revision. Queued visual-editor requests also
retain their baseline; approval execution checks it before changing content.

MySQL named locks serialize version checks with actual handler execution without
wrapping or replacing the handlers' existing transactions. The editing reservation also serves as the publishing
lease, preventing another tab from interleaving writes into the same content family
while a multi-request publication is running. It releases at publication completion or expires
after at most 120 seconds without a write/heartbeat. Revisions are reserved before a write:
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

Deploy the changed PHP/JavaScript together, including
`includes/editor-presence.php`, `admin/action/editor-presence-handler.php` and
`assets/js/editor-presence.js`. Install the two additive metadata tables using
`php database/migrate-editor-presence.php` or import
`sql/editor-presence-migration.sql` in the hosting control panel. The migration is
idempotent and does not modify existing content tables. Run it only if these tables are missing. MySQL/MariaDB named-lock support and
InnoDB are required, using the existing database account/connection.

Without the tables, the new editor allows viewing but reports that
presence/protection is unavailable and refuses its saves. Conventional handlers
continue their previous behavior until the migration is installed. Do not remove
the tables while the new UI is active. For rollback, restore the previous runtime
files together; metadata tables may remain. No content data rollback is needed.

Submitting a saved visual-editor snapshot is idempotent per author, page route
and saved content. A per-draft database lock serializes retries; matching existing
approval records are returned instead of inserted again. Repeated toolbar clicks
share a single confirmation/submission, and unchanged drafts are not rewritten by the toolbar before submission.
Changed snapshots and explicit review revisions can still be submitted normally.
No schema migration or removal of existing approval records is required.

## Verification

Current reservation verification (2026-11-14): domain checks, real multi-account/
same-account browser sessions, takeover fencing, expiry/reconnection, explicit draft
recovery/reconciliation and idempotent-submission tests. Browser tests use invalid
publication payloads to advance metadata without changing published content.
Takeover tests create normal audit entries locally.


- `docker compose exec -T web php tests/editor-presence-test.php` uses temporary
  tables to check account deduplication, page/alias identity, expiry, credentials,
  session binding, stale versions, old drafts, approval versions and leases.
- `node tests/editor-presence-browser-test.cjs` uses isolated local browser
  sessions for Super Admin and Content Editor. It checks activity, page switches,
  responsive panels, CSRF/session rejection, actual stale-draft rejection and
  simultaneous acquisition requests, offline/reconnection, takeover fencing,
  and confirmed draft reconciliation. Invalid write payloads exercise the real
  concurrency boundary without modifying published content. Its CLI fixture is
  local-only and removes its temporary login/presence records on completion.

The existing handler validation, publish-password confirmation, account/role
checks and approval flow remain in effect. Hosted publication and long-running
network-disconnection behavior should also be checked after deployment.

No new migration is required when the existing presence and private-draft tables
are installed. Deploy all changed PHP/JavaScript together; close/reload existing
editor tabs so older clients do not continue using advisory-presence behavior.
Back up the database and code before deployment. If metadata tables are absent,
run the existing additive presence and draft migrations first. Roll back runtime
files together; retained checkpoints, reconciliation metadata and approval records
need no destructive rollback.

Remaining boundaries: related homepage/academic/Administration pages deliberately
share one conservative reservation because saves can update shared personnel and
academic records. Independent feature families remain concurrent. Conventional
unscoped managers/approval workers are conservatively blocked while any publishing
or editing lease exists, then retain their external-version invalidation behavior.
This avoids bypasses but may temporarily block an unrelated conventional save.
The difference viewer does not perform semantic merges; the admin must reconcile
all proposed fields/settings before confirming. Multi-request publication retains
the existing partial-success behavior, not an atomic whole-page transaction.
Test network timing and actual hosted role/approval flows again after deployment.

Verification commands/results for this change:

- `docker compose exec -T web php tests/editor-presence-test.php`: 56 checks passed.
- `docker compose exec -T web php tests/editor-draft-submission-test.php`: 18 checks passed, including rejection of a stale saved checkpoint with a fresh preview.
- `docker compose exec -T web php tests/administration-office-http-test.php`: 28 checks passed in a disposable schema.
- `node tests/editor-draft-regressions-browser-test.cjs`: Administration encoding and undo/checkpoint regression checks passed.
- Run browser suites sequentially: they use real local reservations and intentionally contend on the shared personnel family.
- `node tests/editor-presence-browser-test.cjs`: passed, including simultaneous acquisition, same-session tabs, token omission, browser offline/reconnection, takeover, saved-draft fencing, explicit recovery/reconciliation and panels at 1440/768/390px.
- `node tests/administration-drafts-browser-test.cjs`: passed all four sections for both roles, approval review, photo restoration, failed-save retry and partial-publication retry.
- PHP lint, JavaScript syntax checks and `git diff --check`: passed. Desktop/mobile screenshots were inspected; the existing toolbar/button styles are reused.

No production migration or deployment was performed. Production network/proxy behavior
and hosted publication should be verified during deployment.
