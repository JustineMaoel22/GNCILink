# Hosted Administration photo uploads

The browser console's HTTP 500 identifies a failed server request, not the underlying PHP error. The `getComputedStyle` error is a separate preview problem caused by scanning an iframe document after it has been unloaded. The framing code now ignores those documents and disconnects their observers.

## Deploy and check

Deploy the matching project code, including:

- `assets/js/image-framing.js`: stale-document guards and observer cleanup.
- `includes/image-upload.php`: Fileinfo validation and safe environment reporting.
- `admin/admin-functions.php`: explicit mbstring checks and shared raster-image validation before photo processing.
- `admin/action/page-content-handler.php`: runtime-error JSON responses, private error-log references, and an authenticated environment report.

No database migration is needed for this fix. The hosted server has not been changed by local development or tests.

While signed in as a content administrator, open:

`https://gncipampanga.com/admin/action/page-content-handler.php?action=upload_environment`

Check the report:

- `extensions.mbstring` must be `true`. Raster pictures use Fileinfo if available, with server-side image detection as a fallback. PDFs and videos still require Fileinfo. Enable missing extensions using the hosting panel's PHP selector, or ask hosting support. [PHP mbstring installation](https://www.php.net/manual/en/mbstring.installation.php), [PHP Fileinfo installation](https://www.php.net/manual/en/fileinfo.installation.php).
- `administration_upload_writable` must be `true`. Hosting support should check the ownership, write access and storage quota for `uploads/administration`.
- Imagick is required to optimize originals larger than 5 MB. Only images at or below 5 MB are saved; images already within the limit do not require Imagick.
- cURL is used for the configured person-photo validation service; GD is used to reduce large photos before validation. Review a reported validation-service error separately from a PHP runtime error.
- Review `upload_max_filesize`, `post_max_size`, `memory_limit` and `max_execution_time` against the source image and hosting constraints. `uploads.ini` is installed by this project's Dockerfile; copying it to shared hosting does not automatically apply its settings. Use the host's supported PHP configuration mechanism.

Retry the photo change. If the save still returns 500, copy the **PHP error-log entry from the hosting dashboard**, matching the displayed reference when available. Browser DevTools console output does not contain that entry. The response hides internal exception details; the private PHP log records the exception class, message and source location.

An out-of-memory error, execution timeout, web-server rejection, or bootstrap failure may still occur before a JSON response can be produced. Those cases also require the hosting error log.

### Image size and PHP limits

Oversized originals are sent to the optimization endpoint before staging. The existing modal explains the 5 MB saved-image limit if optimization fails. See [Hosted image uploads](hosted-image-uploads.md) for PHP-FPM configuration, deployment files and regression coverage.

## Local verification

The regression tests exercise an unloaded image document and a removed iframe; missing mbstring functions; a PHP engine error; diagnostic authentication; and existing photo-preview/crop/upload preparation behavior. They do not replace a check of the actual hosted PHP environment.
