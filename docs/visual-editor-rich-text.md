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

Select editable text and use **Google Font** to choose Inter, Lora, Montserrat,
Noto Serif, Open Sans, Poppins, Roboto, or Rubik. **Default font** restores the
page's original font. The choice applies to the whole selected field for the
current desktop, tablet, or mobile view, like the existing text-size controls.
Font-only changes keep the text in normal document flow. They use the existing
layout draft and publish workflow and `text_layout_settings` JSON storage; no
additional migration is needed. Only selected Google Fonts are requested, using
the CSS2 API with `display=swap`; system serif/sans-serif fonts provide fallbacks.
Font identifiers are validated against the shared server-side catalog. This does
not change fonts within uploaded PDFs or images.

College department headers expose their prefix (such as “College of”) and title
as separate editable fields. Header wording is saved in department page content;
the shared department name, navigation and URLs remain unchanged. Click either
title part and choose **Add header text box**, or use that option in **Page
options**, to add one optional extra line. Select the extra line to change its
font, size or placement, or choose **Remove text box**. These changes follow the
existing Save draft / Publish workflow. Existing whole-title layout settings
remain supported. No database migration is required.

College Departments uses the visual editor without the old **More settings**
department form. Click the final breadcrumb to edit the department name used
in navigation and list cards. Click page text and media to edit them in place;
the overview also supplies the list-card description. **Page options** keeps
the card/header colour picker, default-colour reset, visibility and header
controls. Changes use Save draft / Publish. Other More settings panels remain
available for their respective pages.

In a college department's **Page options**, enable **Hide background image on
mobile** to hide only the header image below 768px. The logo and text remain
visible, and tablet/desktop views keep the image. The editor device preview
reflects the setting immediately; Save draft or Publish persists it. The
existing department content table stores this option without a migration.

**Snap to center** in the text/logo toolbar centers the selected box horizontally
within its section, preserving its size, vertical position and typography. Like
placement edits, it applies to the current device view and is saved through the
existing draft/publish workflow. Exact centering takes precedence over the 8px grid.
The box remains centered when the section width changes within that device view;
dragging or resizing it returns to manual placement.

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
