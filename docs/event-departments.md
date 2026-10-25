# Event departments

Events now supports an optional department and optional College/Graduate School program. Leave Program on **All programs in this department** for a department-wide event. Changing Department resets Program to this option. Existing event audiences are preserved.

Before deploying the feature, run the additive migration against the intended database:

```sh
php database/migrate-event-departments.php
```

For hosting with only phpMyAdmin, check whether `events.dept_id` already exists. If absent, run:

```sql
ALTER TABLE events
  ADD COLUMN dept_id INT DEFAULT NULL,
  ADD INDEX idx_events_department (dept_id);
```

Existing rows retain a NULL department and their existing program. No content is deleted or reseeded. Before migration, existing event operations and public filters continue to work, and the new department control is disabled. Deploy `admin/events.php`, `includes/event-audience.php`, `components/calendar-filters.php` and `components/bulletin-board.php` together.

Department calendar filters include department-wide events and existing events assigned to programs in that department. Program filters also include events for the program's whole department. Program bulletin boards include global events while excluding department-wide events for other departments.

Local integration verification (creates and cleans up temporary draft events; public filter fixtures are rolled back):

```sh
docker compose exec -T --user www-data web php tests/event-departments-test.php
```
