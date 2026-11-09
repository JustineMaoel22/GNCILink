# Image optimization

Raster uploads passing through `imageUploadPrepare()` are fitted within 1920 × 1080 pixels without cropping or enlarging. This includes photos smaller than the 5 MB file limit. Formats, transparency and animation are preserved. JPEG/WebP quality starts at 82; saved images remain capped at 5 MB. AI checking, permissions, file validation and image-position metadata remain in place.

PHP **Imagick** is preferred (already installed in the project's Docker image). When unavailable, **GD** resizes static JPG, PNG and WebP images, including files already below 5 MB, to fit within 1920 × 1080. Formats, transparency and embedded provenance metadata are retained. With PHP EXIF enabled, JPEG sensor orientation is retained and used when calculating display dimensions. GIF and animated PNG/WebP files that need optimization require Imagick; the GD fallback refuses them with a clear message instead of flattening animation. Images already within the dimension/file limits are left untouched.

GD checks estimated decode/resize memory against PHP's memory limit before decoding. A 4016 × 6016 static JPEG was verified under a 128 MB limit; larger photos or requests with less available memory may require resizing before upload. Missing format support or insufficient memory produces an error rather than storing an oversized image. Browser upload limits and PHP request limits still apply. Uploaded originals are replaced by their optimized representation before storage.

Deploy `includes/image-upload.php` for future uploads and `scripts/optimize-administration-images.php` for existing Administration files. GD must be enabled in the PHP runtime serving the website, not just a separate PHP Selector configuration. No schema change is needed.

Existing Administration photos can be optimized separately from new uploads. Preview first:

```sh
php scripts/optimize-administration-images.php
php scripts/optimize-administration-images.php --apply
```

This command runs only from the CLI, processes only direct raster files in `uploads/administration`, and leaves existing URLs, record IDs and framing settings intact. It verifies a SHA-256 backup in `config/image-originals` before changing each source. The ignored backup folder must stay private; production already blocks the config directory. `manifest.jsonl` maps each image path to its backup. Keep these backups outside Git and retain a copy before deployment. To undo, restore the corresponding backup bytes to the original path and update its `media_library.file_size` to match. The command updates file-size metadata only; no schema migration is needed.

In Docker, prefix commands with `docker compose exec -T web`. Deploy code and optimized photos through the normal upload process: the cPanel Git deployment deliberately excludes the uploads directory. Original photos on the hosted site are unaffected until the maintenance command is run there or optimized files are uploaded.

Run the isolated image checks using `php tests/image-optimization-test.php` with Imagick enabled and `php -d memory_limit=128M tests/image-gd-optimization-test.php` with GD and EXIF enabled. The GD suite calls the fallback directly, even on hosts that have Imagick; when Imagick is absent it also checks automatic fallback selection. These tests do not use the database or modify site uploads.
