# Frequently Asked Questions

Public page: `/pages/faq.php`, linked under Student Life → Student Resources. Manage it through Content Editor → Frequently Asked Questions, or All content. The reference supplies the accordion pattern only; no example questions or answers are included.

Questions and answers are stored in the existing `faqs` table. The new `faq_page` row holds the editable page title, introduction and empty-state copy. Existing FAQs, IDs, category assignments and author metadata are preserved. The chatbot continues reading the same published table.

Use Add FAQ, edit the question and answer inline, and use the selection toolbar to move entries earlier/later or delete them. Answers accept plain text and line breaks; public output is escaped. Visitors expand/collapse questions using native keyboard-accessible details controls, which also work without JavaScript.

Save Draft uses private per-user Content Editor snapshots. Publish uses the shared confirmation and success messages, including Publish Anyway for missing content. Deletions and ordering remain private until publication. Publication is atomic and rejects stale revisions, malformed/duplicate IDs, incorrect types and excessive text lengths. Publishing and draft access require the existing page-management permission and CSRF verification. Limits are 200 questions, 1,000 characters per question and 20,000 per answer.

Deploy the PHP, CSS, editor and navigation changes together. Run this additive migration with the hosting database configuration:

```sh
php database/migrate-faqs.php
```

It creates missing tables and adds only page labels and empty-state copy. It never seeds questions or modifies existing FAQ records. Existing Content Editor draft tables must already be installed. No production migration is run automatically.

The FAQ migration has been applied to the local Docker database. Run it separately on hosting when deploying.

Verification: `tests/faqs-test.php` uses an isolated SQLite database; `tests/faqs-fixture.php` renders the real page with connection-local temporary tables. Browser tests exercise the editor, private draft restoration, shared confirmation, rejected requests and visitor accordion behavior without publishing fixture content.
