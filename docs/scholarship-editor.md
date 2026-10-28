# Scholarship Opportunities editor

Open **Admin → Content Editor → Scholarship Opportunities** (`/admin/content-edit.php?page=scholarships`). Click text or photos to edit them. **Open scholarship details** offers Basic Education, College (including application requirements), and Government scholarships. Scholarship percentages accept whole numbers from 0 through 100. Photos accept PNG, JPG, or WEBP up to 5 MB and use the existing repositioning dialog. **Restore original photo** restores the built-in image.

The current headings, descriptions, tab labels, card captions, voucher text/percentages, requirements, and four images use the existing page as their defaults. The original photo cards and voucher slots retain their layout. **Add scholarship** appears in both tabs in the editor and creates a PDF scholarship entry with a title, a required PDF upload (up to 20 MB), optional details/requirements, and an optional HTTP(S) or local link. PDFs display in an embedded native browser viewer with Open PDF and Download PDF fallback links, matching Tuition and Fees. Edit scholarship lets you replace an existing PDF; old text-only entries remain available until you attach a PDF. Added entries can be edited in place or through their toolbar and removed before publishing. They use the same card template on the public page. The default Government Scholarships placeholder disappears publicly after a government entry is published; authored introductory text remains visible.

Changes use the shared `tuition_fee_content` table, under `scholarship_text_` and `scholarship_image_` keys; added entries are stored as a validated JSON collection at `scholarship_entries`. The collection supports up to 100 entries within the existing table's storage limit. The public template reads the same values shown in the editor. Save Draft stays private; Content Editors use the existing approval workflow, while Super Admin can publish. Requests enforce authentication, page-management permission, CSRF validation, field allowlists, and percentage/image/link validation, plus PDF extension, MIME, header, cross-reference, EOF, and size validation. PDF paths must belong to the existing entry or come from a validated upload. A failed batch rolls back its text, entry, and image changes. Previous uploaded photos and PDFs are retained when replaced or removed, preserving older links.

## Hosting

Deploy these files together:

- `pages/admission/scholarship.php`
- `components/scholarship-content.php`
- `components/scholarship-entry-list.php`
- `admin/content-edit.php`
- `admin/action/scholarship-handler.php`
- `admin/action/editor-draft-handler.php`
- `assets/js/content-edit.js`
- `assets/css/scholarship-style.css`
- `includes/content-submissions.php`

If `tuition_fee_content` is absent, import `sql/scholarship-content-migration.sql` with the hosting database selected. This creates only the shared content table and does not modify existing rows. No migration is needed where the Tuition and Fees or Online Payment Guide editor already uses this table. The existing content-editor draft and approval tables remain prerequisites for drafts/approvals. PHP must be able to create/write `uploads/scholarships` for new photos and PDFs. Deploy the existing `includes/image-upload.php` alongside the handler if the hosting copy lacks `uploadPdfSignatureValid()`.

The original requirements illustration path (`/assets/images/scholarship/requirements-illustration.png`) has no file in this checkout. An editor can replace it through the College details dialog. No substitute illustration was added.

## Verification

`php tests/scholarship-content-test.php` uses a temporary table for content/rendering/validation checks. `node tests/scholarship-editor-test.cjs` uses the local Docker services and Chrome; it snapshots and restores its affected content keys and removes its own draft/upload fixtures. It checks adding/editing/removing entries in both tabs, saved-entry drafts, staging, draft privacy, publishing, validation, CSRF, authorization, rollback, modal editing, and responsive public rendering. It stops if it finds an authored scholarship draft. Hosting and physical-device checks are still required after deployment.
