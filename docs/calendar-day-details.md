# Calendar date details

Every date in the shared public calendar opens a Bootstrap modal. The homepage and Campus Events page use the same component, including after asynchronous month/filter navigation. Dates are accessible by keyboard (Enter/Space); Escape, the close button and outside clicks dismiss the modal and restore focus.

The read-only `/api/calendar-day.php?date=YYYY-MM-DD` endpoint returns only published events overlapping the selected date, including multi-day activities. Optional `department`, `program` and `category` parameters use the existing public calendar scopes. Responses contain only public title, dates/times, category/color, venue and description fields. Plain text is inserted using `textContent`; drafts, pending and archived records are excluded. Empty dates, loading failures and retry are handled in the modal.

Deploy these files together; no database migration is required:

- `components/calendar-card.php` and `components/calendar-month.php`
- `includes/event-display.php` and `api/calendar-day.php`
- `assets/js/calendar-day.js` and `assets/css/calendar-day.css`

Verification:

```sh
docker compose exec -T web php tests/calendar-day-test.php
docker compose exec -T web php tests/calendar-day-http-test.php
node tests/calendar-day-browser-test.cjs
node tests/campus-event-calendar-test.cjs
```

The database test uses an in-memory SQLite fixture. The HTTP test reads the local site without changing events. The Chrome test checks the real page layout and scripts with controlled endpoint responses at desktop, tablet and mobile widths.
