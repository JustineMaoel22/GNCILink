# Visual Editor text placement

Select an editable text element and choose **Move and resize text**. Drag the text or its grip to move it inside its section. Eight handles resize its box. Typing and formatting remain available when layout mode is off. **Reset text placement** restores the current device's original layout.

**Snap to grid** starts enabled. The section displays an 8 CSS-pixel grid while layout mode is active. Dragged positions and resized edges align with that grid; turn snapping off for fine adjustment. Keyboard arrows on the grip move by 8 pixels with snapping or 1 pixel without it; Shift increases the step. Escape exits positioning. The grid and handles never appear on the public website.

Desktop (above 1024px), tablet (768–1024px), and mobile (below 768px) have independent settings. Changing a desktop box does not reposition it on smaller devices. Use the existing device buttons to customize each size. Horizontal position and width are relative to the containing section. Dragging is constrained to that section. Text can wrap and grow beyond its requested minimum height rather than be clipped. Original footprints are reserved so resizing does not push surrounding content. Free positioning can intentionally overlap other content; check each preview before publishing.

Save Draft stores private placement changes alongside text and media. Publish uses the authenticated, CSRF-protected `admin/action/text-layout-handler.php`; only accounts with `manage_page_content` can publish placement. Failed saves retain pending changes. Unconfigured text keeps its original layout. Existing CMS handlers still save content, media, and other page settings. Newly created content obtains its persistent display identity when it renders through its normal template.

Public templates expose opaque `data-gnc-text` identifiers, not editor fields or draft text. Existing `ceMark`, `ceManageMark`, and `ceField` helpers generate these identifiers, and entity scopes keep repeated text distinct. Program cards use their stored item IDs. Text with nested marked text is controlled through its individual child fields.

## Installation

Deploy the PHP and JavaScript changes together and run once with the hosting database configuration:

```sh
php database/migrate-text-layout.php
```

This additive migration creates `text_layout_settings` and does not modify content. It was run in local Docker; the hosted database still needs the migration. Without it, public pages retain their default layout and publishing placement reports a storage error. Roll back the feature files together if necessary; retain the table to preserve saved placements.

## Verification

- `php tests/text-layout-test.php`: numeric and route validation, stored round trip, page isolation, breakpoint merge, and reset. The isolated test row is removed.
- `node tests/text-layout-browser-test.cjs`: real pointer drag/resize, 8px snapping, fine adjustment, inline editing, neighboring layout, draft restoration, reset, failed/successful intercepted publish, unauthorized/missing-CSRF rejection, shared controls on Home/History/Contact, and public behavior at 1440/820/390px. Publication responses are intercepted, so published page metadata is unchanged.
- Existing program media and shared publish-workflow browser checks cover image replacement, inline names, PDFs, drafts, and the publish confirmation across editor pages.

The 390px public program page has a pre-existing small horizontal overflow. The layout check compares against the same unmodified page and verifies text placement adds no overflow. Physical touch devices and production hosting still require a deployment check.
