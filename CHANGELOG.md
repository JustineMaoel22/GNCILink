# Changelog

## [0.12.0-alpha] - 2026-10-25

### New Features
* Added smaller, bigger, and default text-size controls to the Visual Editor, saved separately for desktop, tablet, and mobile.
* College header logos can now be moved and resized in the Visual Editor, with proportional scaling and saved placement for each device size.
* College department headers use a program-style hero with an admin-editable background, department logo, and live title in the Visual Editor.
* College department landing headers now separate the “College of” label and use the admin-selected department color for the heading and breadcrumbs.
* Can now replace background images on the home page.
* Can now move text positions.
* Added snap-to-grid alignment.
* Added "Meet the Devs" page.
* Added FAQs page.
* Admin can now create users.
* Added forgot password functionality.

### Bug Fixes & Adjustments
* Fixed missing Basic Education admission tab-label editor records being left unregistered on older hosted databases.
* Added Cian's robot profile picture beside his name in the chat header.
* Named the chatbot Cian across its widget, welcome message, accessibility labels, and assistant identity.
* Replaced the chatbot's floating button icon with the green robot mascot, with smaller sizing on mobile.
* Reduced college department landing header height for a more compact desktop and mobile layout.
* Fixed the college header background crop preview using the small upload control instead of the full header proportions.
* Fixed college header titles wrapping inside a fixed inner width when admins resize the text box.
* Fixed admin panel bug where uploading a picture and then editing details causes the photo to disappear.
* Fixed optional program description in headers.
* Fixed non-functional program facilities details editing.
* Removed unnecessary "view details" button for basic education programs.
* Fixed non-functional pagination numbers in campus events.
* Removed unused "About Us" section under pages in the content editor.
* Fixed content editor responsiveness.
* Fixed transition screen finishing prematurely before reaching 100%.
* Fixed CNC map zoom-out and zoom button behaviors.
* Fixed transition animation firing repeatedly when clicking sorting and filter options (e.g., in Student Achievements and News & Announcements).
* Fixed header image reposition button getting blocked by the navbar hover state.
* Fixed missing save/publish buttons in college departments.
* Fixed missing uniform section across department pages.
* Fixed specific office map animations to restrict clicks only to target buildings.
* Fixed Facebook posts not displaying correctly on iOS devices.
