# Publishing completeness warnings

The shared admin header loads `content-completeness.js` and its dialog styles.
Visual Editor publishing inspects the current preview DOM before the existing
publish confirmation. Save requests still use the existing handlers and CSRF
and permission checks. The advisory backend endpoint checks the submitted field
snapshot; it does not save content or grant permission to publish.

The inspector reads existing `data-ve-field`, `data-ve-section`, editable media,
office address, and program-name markers. It also reads native CMS fields and
Quill editors. It uses current field values and preview images, including unsaved
edits. Empty Add Program placeholders, draft slides, disabled/inactive controls,
optional links, fallback messages, and optional program sections are excluded.

For new CMS components:

- Add `data-content-publish` to a content form or a non-submit Save button.
  Dynamically inserted controls are supported by delegated event listeners.
- Wrap a button's editable controls in `data-warning-scope` when the form/card
  boundaries do not describe the save operation. Only that scope is inspected.
- Use `data-warning-section="Head of Office"` and
  `data-warning-label="Biography"` to provide clear names.
- Mark intentionally optional content with `data-warning-optional="true"`.
  `ceField()` supports `optional`, `warning_required`, and `section` options.
- Use `data-warning-active="false"` for inactive content groups. Update it when
  their visibility setting changes.
- Add `data-warning-field` to custom text editors, and `data-warning-image` to
  custom media slots. Native fields can use `data-warning-required` to opt in.
- Read-only approval previews can supply `data-warning-value` so display text
  such as “(empty)” does not hide an empty stored value.

`ContentCompleteness.review({root, csrf})` returns a promise resolving to whether
the admin chose to proceed. `inspect(root)` returns the current descriptors and
focus targets. Keep Editing, Escape, and outside clicks cancel without changing
content. Missing-field links close the dialog and navigate to the field.
The dialog and publishing controls guard duplicate clicks. An advisory check
failure falls back to the local inspection, without bypassing save validation.

Warnings are advisory. Existing structural requirements (for example a record's
name, slug, or an event's date) and permission/upload validation still apply.
News and announcement descriptions may be empty after explicit Publish Anyway;
their existing title and authorization checks remain in force.
