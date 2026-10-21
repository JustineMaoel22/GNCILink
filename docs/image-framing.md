Image framing stores display position and zoom separately from uploaded files.
Run `php database/migrate-image-framing.php` once on each deployment. The migration
only creates `image_display_settings` and is safe to repeat.

Admins can drag a photo in the upload preview, zoom from 100% to 400%, reset, or
cancel. The preview keeps the selected container's aspect ratio. Arrow keys also
move the photo. Accepting the preview stages metadata; it is sent with the normal
upload when the admin saves or publishes the content. Canceling a new upload
clears that selection. Neither the file input nor the request receives a cropped
or recompressed image.

Existing cropped images have a Reposition control on hover in admin forms. In the
Visual Editor, select a cropped image (or its card) and choose **Reposition image**.
**Save position** explicitly saves the image's display settings immediately,
independently of other unsaved page changes. The dialog explains this behavior.
Cancel restores the existing preview without changing the database.

Settings are shared by the image file's URL wherever that photo is cropped. Each
container retains its own dimensions and aspect ratio; contain/full-image views
are unaffected. PNG, JPEG, WEBP and GIF uploads are supported. Existing local SVGs
can also be positioned in cover containers; contain illustrations are left alone.
Videos are excluded.

`components/image-framing-assets.php` supplies the shared runtime and saved settings
through the public navbar/footer and admin header. It exposes no editing controls
to anonymous visitors. `image-framing.js` handles both object-fit images and CSS
backgrounds, including lazy-loaded backgrounds and gradient overlays. A clipped
CSS transform preserves the image's original layout box and rounded corners.
Resize observers update cover backgrounds when their container changes size.

Multipart uploads carry `image_framing`, keyed by SHA-256 of the original file.
The shared announcement/news, slideshow, department/program/person/homepage,
history, organization, achievement, and banner upload helpers associate that
metadata with the newly stored file. Native forms, fetch, XMLHttpRequest, blob
previews, and FileReader previews use the same metadata. The facility uploader's
automatic in-place optimization was removed so it also preserves original bytes.

Existing-image saves use `admin/action/image-framing-handler.php`, with login,
CSRF, path validation, permission checks, numeric bounds and transactional writes.
Page managers can adjust site images. Other eligible content editors can adjust
their own media. Metadata changes never invoke a pixel editing operation.

Checks:

- `php tests/image-framing-test.php`: normalization, safe paths, database storage,
  upload matching, unchanged image hash; database writes rolled back.
- `node tests/image-framing-browser-test.cjs`: pointer dragging, zoom, aspect ratio,
  cancel, persistence, public image/background rendering at desktop/mobile sizes,
  Visual Editor controls, native-form metadata and a real announcement upload.
  Temporary records, metadata and files are removed afterward. The browser fixture
  isolates unrelated Bootstrap/Quill CDN dependencies.
