## [0.16.0-alpha] - 2026-10-28

### New Features & Improvements
- Added a matching thumbnail tray to the Athletics & Sports Content Editor for sports photos, team logos, and facility slides, using the existing draft and Publish workflow.
- Connected the visitor feedback popup directly to the supplied Google Form after 60 seconds of active browsing, with dismissal and visitor-confirmed completion cooldowns.
- Added a Settings switch to turn the suggested pages popup on or off across the website.
- Added matching Storyset illustration credits to Basic Education and College Admission Requirements, consistent with Enrollment Procedures.
- Student organizations now support Archive and Restore, with a private Archived view. Changes stay in drafts until Publish.
- Hold and drag an organization card's Move handle onto another card to swap positions in the editor draft. Publish applies the new order to the public site.
- You can now edit the scholarship opportunities.

### Bug Fixes
- Restored the Community Extension Activities link under Student life in the Content Editor sidebar.
- Payment guide SVG illustrations now go directly through SVG sanitization, allowing publishing on hosts without PHP Fileinfo. Raster illustrations also use server-side image detection without requiring Fileinfo.
- The image optimizer script now uses a content-based cache version so hosted Facilities and Uniform uploads load updated compression code even when deployment preserves file timestamps.
- Large static PNG uploads can use the browser's regular image decoder for compression when ImageBitmap is unavailable, avoiding the hosted optimizer for supported photos.
- Career opportunities can now be added, moved, and deleted in place in the Content Editor. Changes stay in drafts until Publish.
- Uniform photo repositioning now uses each image's natural aspect ratio for uploads and replacements, and applies saved zoom on program pages.
- Fixed event security tokens expiring immediately when PHP and MySQL use different time zones, preventing events from opening in the admin panel.
- Adding a program badge now updates the editor in place. Badge additions, moves, and removals stay in drafts until Publish.
- Student organization pagination updates results without replaying page transitions for visitors or the admin editor.
- Organization card dragging and move arrows now stay private until Publish; Save draft preserves the pending order.
- Fixed student organization logo zoom not appearing after accepting the image position, including on organization detail pages.
- Fixed an issue where Facebook page iframes were not visible in student organizations.
- Fixed the large photo optimization tool.
- Fixed an issue where zooming an organization's logo in the admin panel was not reflecting live.
- Fixed the responsiveness of Head of Student Services and college programs.

### UI Improvements
- Improved the design of the footer.