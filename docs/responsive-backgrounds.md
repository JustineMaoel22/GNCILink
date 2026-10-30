# Device-specific Content Editor backgrounds

Select a background in the Content Editor, choose Desktop, Tablet, or Mobile, then use **Background (device)**. Replacement images, horizontal/vertical alignment, Cover/Contain sizing and crop zoom apply only to that device. Switching device closes the previous controls. **Remove override** restores the original background for that device. Ordinary content photos keep their existing editing controls.

Existing published images remain the fallback; installing this feature does not change them. A saved device override is independent of the original image and other device overrides. The homepage's legacy separate phone image also remains its fallback.

Save Draft stores pending background settings and uploaded file bytes privately in the existing editor snapshot. Content Editors submit the snapshot for Super Admin approval. Only explicit publishing writes the shared background endpoint, which uses existing authentication, permissions, CSRF, image validation and activity logging. Public pages load published settings through the shared image-framing assets. Breakpoints are mobile up to 767px, tablet 768–1024px and desktop from 1025px; editor device buttons explicitly select the corresponding settings.

## Hosting setup

Back up the database, then run `php database/migrate-responsive-backgrounds.php`, or import `database/responsive-backgrounds-migration.sql` using phpMyAdmin on hosts without shell access. This additive migration creates one table and can be run repeatedly. Deploy the new shared PHP/JavaScript files together with the editor changes. Until the migration is installed, existing pages retain their backgrounds and publishing a device background shows a setup message.

For rollback, restore the previous application files and retain the optional table and uploaded images. Existing legacy content is never rewritten by device overrides. Do not delete media or drop tables as part of an application rollback.

## Verification

Run `node tests/responsive-backgrounds-test.cjs`, `node tests/responsive-backgrounds-editor-test.cjs`, and `php tests/responsive-backgrounds-test.php`. The browser integration test `node tests/responsive-backgrounds-browser-test.cjs` requires local Docker and Chrome. It discovers editor pages from the content hub, checks their device previews and public routes, and uses an isolated query-string route to check private draft restoration, explicit publishing, upload, removal and invalid requests. Its temporary published overrides are cleared afterward.

The browser suite should also be run against representative program/department/organization detail pages and on the hosting environment before production rollout. Reordering or removing the underlying section can change a generated DOM selector; device overrides do not maintain separate page layouts or duplicate content records.
