# Hosted Administration photo uploads

The browser console's HTTP 500 identifies a failed server request, not the underlying PHP error. The `getComputedStyle` error is a separate preview problem caused by scanning an iframe document after it has been unloaded. The framing code now ignores those documents and disconnects their observers.

## Deploy and check

Deploy the matching project code, including:

- `assets/js/image-framing.js`: stale-document guards and observer cleanup.
- `includes/image-upload.php`: Fileinfo validation and safe environment reporting.
- `admin/admin-functions.php`: explicit mbstring/Fileinfo requirements before photo processing.
- `admin/action/page-content-handler.php`: runtime-error JSON responses, private error-log references, and an authenticated environment report.

No database migration is needed for this fix. The hosted server has not been changed by local development or tests.

While signed in as a content administrator, open:

`https://gncipampanga.com/admin/action/page-content-handler.php?action=upload_environment`

Check the report:

- `extensions.mbstring` and `extensions.fileinfo` must be `true` for this upload path. Enable missing extensions using the hosting panel's PHP selector, or ask hosting support. [PHP mbstring installation](https://www.php.net/manual/en/mbstring.installation.php), [PHP Fileinfo installation](https://www.php.net/manual/en/fileinfo.installation.php).
- `administration_upload_writable` must be `true`. Hosting support should check the ownership, write access and storage quota for `uploads/administration`.
- Imagick is needed to automatically optimize source photos larger than 5 MB. An image already within 5 MB does not need automatic optimization.
- cURL is used for the configured person-photo validation service; GD is used to reduce large photos before validation. Review a reported validation-service error separately from a PHP runtime error.
- Review `upload_max_filesize`, `post_max_size`, `memory_limit` and `max_execution_time` against the source image and hosting constraints. `uploads.ini` is installed by this project's Dockerfile; copying it to shared hosting does not automatically apply its settings. Use the host's supported PHP configuration mechanism.

Retry the photo change. If the save still returns 500, copy the **PHP error-log entry from the hosting dashboard**, matching the displayed reference when available. Browser DevTools console output does not contain that entry. The response hides internal exception details; the private PHP log records the exception class, message and source location.

An out-of-memory error, execution timeout, web-server rejection, or bootstrap failure may still occur before a JSON response can be produced. Those cases also require the hosting error log.

### Slow image optimization

Photos over 5 MB are uploaded to `/admin/action/optimize-image.php` before the editor displays their preview. Transfer time and server processing both contribute to the wait. PNG/GIF images are now resized before encoding, avoiding repeated full-resolution compression attempts. Further retries use the measured output size, with at most six encodes. The existing format, transparency, animation and provenance metadata are retained; images already within 5 MB are left unchanged.

The optimizer releases its PHP session lock after login/CSRF validation so other editor requests can continue. Browser requests have a 60-second timeout with loading-state cleanup and a retry message. This is not a guarantee that large uploads finish within a particular time on shared hosting.

A local benchmark with a generated 20.6 MB PNG improved server processing from approximately 3.14 seconds to 0.38 seconds. JPEG/PNG/WebP/GIF, transparency, animation, metadata, the exact 5 MB boundary, authenticated multipart uploads, timeout cleanup and retry were verified with the image-upload tests. Deploy `includes/image-upload.php`, `admin/action/optimize-image.php`, and `assets/js/image-upload.js` together; no database migration is required.

## Local verification

The regression tests exercise an unloaded image document and a removed iframe; missing mbstring functions; a PHP engine error; diagnostic authentication; and existing photo-preview/crop/upload preparation behavior. They do not replace a check of the actual hosted PHP environment.
