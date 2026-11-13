# Content Editor publishing

All Content Editor pages share the completeness warning. **Keep Editing** cancels publication. **Publish Anyway** explicitly allows missing authored text or media; page-specific saves must not reject missing content after that decision.

The visual editor carries `allow_incomplete=1` through the confirmed publish operation and clears the decision afterward, including failed saves. Embedded forms include the same field, and multipart AJAX requests issued while replaying a confirmed save receive it as well. Native required-field constraints defer to the shared warning for designated publishing forms.

`includes/content-publish-policy.php` centralizes the server-side completeness decision. It does not grant permission: handlers still require login, authorization and CSRF verification. Field types and length limits, record IDs, allowed statuses, nonempty invalid URLs/email addresses, upload signatures and sizes, revision conflicts and database integrity checks remain enforced. Creating new internal identifiers still follows each editor's existing rules.

Deploy the changed PHP helpers/handlers and editor scripts together. This change requires no database migration and does not publish any existing drafts automatically.

Home Page slides stage selected photos/videos in the existing private editor snapshot, including their media files. Choosing media and Save draft do not call the live slide handler or request a publishing password. New slides are written only during explicit publication or approved snapshot publication. The selected existing draft can also be published without requiring unrelated edits; other hidden slides remain unchanged. Publication retains Super Admin password confirmation, and Content Editor submits a snapshot for approval.

Permanent slide deletion uses the current-password dialog and the shared server deletion-password boundary. Content Editor deletion requests are verified before entering the approval queue; the password is stripped before staging. The one-use approval worker retains reviewer verification. Discarding a new, unpersisted slide only changes the private canvas. Successful publication/deletion records include the acting user and timestamp; the database write and activity log commit together, before old media is removed. Failed slide uploads retain pending edits and successful new slide IDs for safe retries.

Home slide verification: `tests/home-slides-workflow-test.php` uses temporary tables for image/video persistence, draft visibility, passwords, role routing, publication/deletion audits and rollback. `tests/home-slides-browser-test.cjs` covers both roles, staged media, private draft restoration, publishing, partial retries, deletion dialogs and reviewer completion with content/password/approval responses mocked. No migration is required.

Regression checks:

- `tests/content-publish-policy-test.php`: missing content and retained validation using isolated data.
- `tests/community-activities-test.php`: actual atomic saves of incomplete activities in an isolated database.
- `tests/community-image-preview-browser-test.cjs`: actual activity Publish Anyway request, cancellation and retained edits after failure; save request intercepted.
- `tests/content-editor-workflow-browser-test.cjs`: shared confirmation across 18 editor pages; no publication.
- `tests/content-completeness-browser-test.cjs`: warning controls, native required fields, multipart AJAX override and decision lifetime; requests intercepted.
