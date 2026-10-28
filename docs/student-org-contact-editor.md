# Student organization contact editor

The contact section's “Stay Updated” eyebrow is now an editable `facebook_eyebrow` field in the existing `student_org_page_content` storage, alongside the shared contact heading and introduction. It retains the original default until edited. Like those existing contact settings, the eyebrow is shared across organization detail pages.

The contact section matches the College Departments pattern: a centered 500px compact Facebook box with a clickable editing overlay, or an **Add the Facebook page link** tile when no URL is set. The control accepts HTTPS Facebook addresses, updates the embedded preview and a public “Visit Facebook page” link, and stages the change through the existing organization draft/publish flow. Clear the address to remove the embed. The existing save handler continues to enforce authorization, CSRF and server-side URL validation. No schema migration is required.

Deploy together:

- `admin/student-organizations.php`
- `pages/student life/student organization and leadership program/dynamic-org.php`
- `assets/js/content-edit.js`
- `assets/css/dynamic-org-style.css`

Verification: PHP syntax and JavaScript syntax checks; the existing student-organization persistence test (transaction rolled back); and `tests/student-org-contact-browser-test.cjs` covering editable eyebrow, URL dialog, preview iframe/link, private pending changes, publication, invalid URL rejection and clearing the address. Browser fixtures and the shared eyebrow value are restored after testing. Hosting still requires a deployment check.
