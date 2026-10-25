Admin design consistency audit — October 22, 2026

The reference is the existing admin shell in `assets/css/header-admin-style.css`, used by the Dashboard, News, Announcements, Events, Settings, and Content Editor. This pass reuses that design and does not change public-page layouts, content, database schema, or publishing actions.

| Existing reference | Shared treatment |
| --- | --- |
| Typography | Inter; existing page heading sizes; 0.875rem table text and standard controls |
| Brand actions | Existing `#094024` green and `#EABA3B` gold, with existing hover colors |
| Controls | Existing 8px radius; compact controls keep their smaller dimensions |
| Cards | Existing 12px radius and `0 2px 8px rgba(0,0,0,0.06)` shadow |
| Status | Shared published, draft, pending, rejected, and archived colors |
| Motion | Existing `--transition: 0.2s ease`; reduced-motion handling for affected interactions |

Confirmed differences and adjustments:

- Events used Bootstrap success-green buttons, while News and Announcements used the existing gold action class. Events now reuses that class, retaining its calendar and event forms.
- The visual editor used `.btn-gnc` without a shared definition outside popovers. Apply and Publish now reuse the existing green button appearance, including hover, focus and disabled states.
- News, Announcements and Events overrode the shared status colors inside their tables. Those overrides were removed; Content Editor statuses now use the same shared definitions.
- News, Announcements and Events used separate filled notification styles; the editor had another notification style. They now share the existing white admin notification, colored status accent, typography, radius and shadow. Notification placement and dismissal behavior remain specific to their workflows.
- Settings placed its subtitle alongside the heading and used a larger, lighter card heading. Its heading markup and card title now follow the existing page-header and data-card-title patterns.
- The Content Editor list had a separate cream table header, hover background and body typography. These now match the existing admin tables.
- Confirmation and publishing dialogs reuse admin radius, shadow, title and action tokens. Shared dialogs retain their previous fallback appearance on public pages.
- Common neutral buttons reuse the existing control radius, font sizing and transition timing. The shared stylesheet is versioned by modification time to refresh browser caches.

Intentionally retained:

- The dashboard's welcome heading, quick-action cards and charts.
- The Events calendar, category colors and date chips.
- Fullscreen creation dialogs on phones and compact table actions.
- The visual editor's full-screen canvas, device controls, drawers and public-page iframe styling.
- Existing manager layouts and the separate sign-in/OTP layout, including monospace OTP digits.

Verification:

- Before/after browser audit at 1440px and 390px: Dashboard, News, Announcements, Events, Settings, content hub, homepage editor, History editor, College Departments editor, Programs editor, review entry, Administration editor, Graduate School editor, Basic Education editor, Tuition editor, Payment Guide editor, Organizations editor, Achievements editor and Sports editor.
- All 38 view/viewport checks rendered without PHP errors or page-level horizontal overflow. The review entry follows the application's existing routing/permissions.
- Opened News, Announcement and Event creation dialogs without submitting; checked action colors, heading typography and existing mobile fullscreen behavior.
- Compared shared button, badge and notification computed styles; checked reduced-motion behavior and confirmation dialog styling/cancellation.
- Existing dialog browser checks passed: Cancel, Escape, backdrop dismissal, focus restoration, mobile fit, safe messages and queued dialogs.
- Settings regression test passed with database changes rolled back; publishing, feedback, delete confirmation, department Facebook preview and History background tests passed.
- PHP syntax checks passed for the affected templates. No application JavaScript or save handlers were changed by this consistency pass.

Local browser artifacts are written to `cache/admin-design-audit/`. Run the audit against the local Docker site with:

```powershell
$env:AUDIT_LABEL = 'after'
$env:AUDIT_SCREENSHOTS = '1'
node tests/admin-design-browser-test.cjs
```

The browser audit uses a temporary local admin session, opens pages and dialogs, and removes the session afterward. It does not submit content changes.
