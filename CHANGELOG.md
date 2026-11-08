## [0.21.0] - 11-8-2026

### Added
- Super Admin setting for the suggested-pages popup delay (5–3600 seconds) within Website Preferences, retaining the existing 60-second default.
- Configurable Access Student Email URL in Settings, shared by desktop/mobile navigation with server-side URL validation and the existing approval workflow.
- Separated the Admin PWA into `/admin/` scope with its own network-only worker and `/admin/login` launcher; old admin bookmarks redirect to scoped routes, keeping panel navigation in the installed app.
- Separate installable GNCILink Admin app with login launch and a bottom-right install button; private/admin pages remain online-only.
- Installable GNCILink PWA with school-seal icons, iOS installation help, a branded offline fallback, public-only offline caching, and quiet updates without popups or forced reloads. No database migration is required; see `docs/pwa.md`.

### Minor Improvements/Additions
- Renamed visitor and admin installation controls to “Install web app,” including Cian’s installation bubble.
- Present the campus-map install prompt as a speech bubble above Cian, with a pointer toward the chatbot; once shown, it stays visible while scrolling or opening chat until the visitor dismisses it or chooses installation.
- Show a compact visitor install prompt after the campus map enters view, once per tab session, without interrupting open dialogs or typing. Dismissal resets in a new session; observed installations suppress future reminders.
- Added Office of Student Affairs Portal to Student Resources and updated OSA Student Grievance to the Google Form in navigation and footer.
- Removed the homepage President full-form panel; the message remains editable directly on the page.
- Hide the admin Install web app button after sign-in; installation remains available on the login screen.
- Change the chatbot icon.
- Made the chatbot icon follow the cursor.
- Made the chatbot react when clicked.

### Bug Fixes
- Fixed the campus-map install reminder being hidden when no native browser install event arrives or the map keeps keyboard focus; the existing installation instructions provide a fallback.
- Suggested program thumbnails now use current banner slideshow images instead of older program images, with video slides skipped.
- Kept Content Editor previews proportional within separate desktop, tablet, and mobile designs when the editor window changes size; visitor layouts retain their existing responsive behavior.
- Fixed the unecessary proccessing request.
- Fixed news and announcement articles time posted not the same with system time.
