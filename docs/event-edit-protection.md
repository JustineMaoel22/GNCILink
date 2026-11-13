# Event edit protection and midnight times

The Events editor fetches a fingerprint of the event and its department/program
audience. Manual updates check that fingerprint under a row lock and keep the
transaction open through the event and audience writes. A stale or missing
fingerprint returns HTTP 409 without changing the saved event. The editor keeps
entered fields visible for copying and reconciliation; reopening fetches the
latest version. Approval payloads retain the submitted fingerprint.

Run `php database/migrate-event-all-day.php` on each deployment. This additive,
idempotent migration adds nullable `events.is_all_day` and changes no saved dates
or times. It is already applied to local Docker. New manual saves distinguish
an explicit midnight start from an all-day event. Existing NULL records retain
the legacy inference (all-day when both stored times are midnight/absent).
Old records with only midnight and no end time are inherently ambiguous; edit
and save with All day off to explicitly identify a timed midnight event.

Calendar imports retain their existing time inference and clear the explicit
flag when replacing a schedule, so an older manual flag cannot override the
imported times. Import conflict fingerprints include that flag too.

Deploy `admin/events.php`, `includes/event-edit-state.php`, and the updated
calendar-import helper together. Apply the migration to enable full midnight
support. Without the new column, old-schema saves remain compatible and the
conflict check still works, but midnight-only events retain legacy ambiguity.
Rollback application code without removing the additive column; the original
dates and times remain compatible. Back up explicit flag values before any
later schema removal.

Verification: run `php tests/event-edit-handler-test.php` with each mode:
`stale`, `audience`, `missing`, `fresh`, `create`, `all-day`, and `fetch`.
These tests shadow event, audience, activity and CSRF storage with temporary
tables and do not change real events. Calendar import compatibility is covered
by `php tests/calendar-import-audience-test.php`.
