# Rich text in the Visual Editor

Click a paragraph or description to show the shared formatting toolbar. It
supports headings, bold, italic, underline, ordered and unordered lists, links,
quotes, alignment, and clearing formatting. Titles, names, labels, contact
values, and addresses keep their existing plain-text behavior.

This applies to homepage introductions and the president's message,
administration descriptions, academic listings, department biographies and
introductions, program descriptions and item bodies, and organization pages
including leadership callouts. Existing section editors, news, and announcement
editors retain their rich-text tools. Changes use the existing Publish workflow.

Rich fields declare `rich => true` in `ceField()` metadata and use a block
container with class `cms-rich`. Render stored text with `renderSectionContent()`
and normalize incoming content with `richTextNormalize()`. Shared formatting
styles live in `assets/css/content-rich-text.css`, included by the public footer.
The server strips scripts, event handlers, unsafe URLs, and unsupported markup.
Existing plain text stays compatible; no database migration is required.

Organization callout and vision/mission icons use the existing searchable
Bootstrap and Material catalog. Click an icon, choose Change icon, and Publish.
Icon values are validated against the catalog and stored in the existing
organization page-content table.

Validation: `tests/visual-editor-rich-text-test.php` rolls back its database
writes; `tests/student-organizations-browser-test.cjs` checks formatting across
editor page types and removes temporary organization fixtures.
