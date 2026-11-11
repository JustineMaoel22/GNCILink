# Event categories

Events use the dedicated `event_categories` catalog after migration. New selections
are School Event, School Activities, Holiday, Suspension and Alumni. News, Announcements
and Facebook posts retain their existing category storage.

The migration copies existing category IDs, names and colors without modifying
`categories` or event rows. Other copied topics remain inactive legacy records:
existing assignments still render correctly and can be retained when editing,
but cannot be selected for new events. Calendar imports validate against the same
catalog; new imports and pending submissions using an inactive topic must choose
an available event type before confirmation.

## Deployment

Back up the database and pause event/category writes, imports and approvals during
the migration. Deploy the updated PHP files together, then run from the project root:

```sh
php database/migrate-event-categories.php --check
php database/migrate-event-categories.php --apply
php database/add-alumni-event-category.php
```

Apply also writes a private JSON snapshot of both source tables and their schema
to the system temporary directory and reports its path. Copy that snapshot to
durable private backup storage; it is not a replacement for a full database backup.
MySQL DDL commits implicitly. If activation fails after seeding, readers keep using
the original catalog; inspect the populated table before retrying. The script
refuses to overwrite an unactivated populated catalog.

The Events foreign key is the activation boundary. Readers use `categories`
before activation and `event_categories` afterward. Public calendars, day details,
upcoming lists, Alumni feeds, Events and calendar import/approval summaries all
use that boundary. No event IDs or approval snapshot IDs are remapped.

## Rollback and verification

```sh
php database/migrate-event-categories.php --rollback
php tests/event-categories-test.php
php tests/calendar-import-audience-test.php
php tests/calendar-import-workspace-test.php
php tests/calendar-categories-test.php
php tests/alumni-events-test.php
node tests/event-categories-browser-test.cjs
```

Rollback redirects the foreign key to the shared catalog and retains the dedicated
table. It refuses missing IDs or conflicting category names rather than changing
event meanings. New event-only IDs must be reviewed and mapped before rollback.
An already active migration is a no-op; reactivation after rollback requires
review of the retained catalog. The migration test uses and removes its own random
test database; the other database tests use temporary tables or roll back fixtures.
