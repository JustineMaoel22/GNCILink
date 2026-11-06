# Personnel image validation

Personnel portraits now have two independent checks: local face comparison and the existing optional AI-generated-image checker. Face comparison runs even when the optional AI checker is disabled. It uses OpenCV YuNet landmarks and SFace features, rather than filenames or whole-image hashes. It never sends images to an external face service. The pre-existing optional AI checker still uses its configured Gemini service.

## Setup

For the repository's Docker development environment, the web image installs Python, OpenCV and checksum-verified model files in `/opt/gnci-face`, and sets both configuration variables automatically. Apply image changes with `docker compose up -d --build --no-deps web`. This recreates only the web container; the database service and its data remain in place. Verify with `docker compose exec -T --user www-data web php tests/person-photo-runtime-test.php`. The first build downloads Python packages and the two model binaries plus their licenses; later builds reuse the image layers.

For other hosting environments, follow the manual setup below.

No database migration is needed. PHP 8.2, mbstring, PDO MySQL, and `proc_open` must be available. The web-server account needs permission to launch a local Python interpreter. Install Python 3.10 or later and create a private environment outside the web root:

```sh
python3 -m venv /opt/gnci-face
/opt/gnci-face/bin/pip install -r scripts/requirements-face-check.txt
```

Download the following actual binary ONNX files from the official [OpenCV Zoo](https://github.com/opencv/opencv_zoo) into a private, read-only model directory, e.g. `/opt/gnci-face/models`. Git LFS pointer files will not work. Review and retain each model's license with the deployment.

- [face_detection_yunet_2023mar.onnx](https://github.com/opencv/opencv_zoo/tree/main/models/face_detection_yunet)
- [face_recognition_sface_2021dec.onnx](https://github.com/opencv/opencv_zoo/tree/main/models/face_recognition_sface)

Configure server environment variables (or the existing private `.env`):

```ini
FACE_CHECK_PYTHON=/opt/gnci-face/bin/python
FACE_CHECK_MODELS=/opt/gnci-face/models
```

Use absolute paths. For Windows, set these to the environment's `Scripts/python.exe` and model directory. These paths belong to the PHP host: Windows paths do not work inside the Linux Docker container. Shared hosts that disable local processes cannot run this implementation without a hosting change.

The feature fails closed if the runtime/models are missing, a present photo cannot be decoded, inference is incomplete, or processing exceeds 45 seconds. Missing legacy files that public pages already render as having no photograph are omitted with an explicit coverage notice. Replace them to restore full coverage; they cannot be compared until the images exist. Configure and smoke-test the runtime before deploying the PHP changes. There is no silent fallback to file hashes or AI guesses. The installed development runtime/models under `cache/face-*` are ignored by Git and are test artifacts, not production configuration.

## Editing and review

Enter a full name, then choose a single-face JPG, PNG or WebP portrait. Administration people (including faculty entered as personnel cards), office heads, department heads and the homepage president share the same comparison inventory. Both conventional settings and visual-editor saves pass through server enforcement. Retained images are checked on profile saves, so changing a name cannot bypass the check. Restoring archived personnel/department records also checks their portraits. Existing published records are not automatically modified or retrospectively blocked by this change.

Possible matches show the uploaded portrait and matching profile images with both names. Cosine similarity >= 0.363 warns and requires acknowledgement in the upload UI. Scores >= 0.55 block saving; a Super Admin can review the comparison and confirm the assignment, or replace the photo. Content Editors must ask a Super Admin to open that profile and review it in their own session. Saving a draft does not publish blocked personnel changes.

The comparison dialog shows the upload once and groups matches by the same normalized full-name rule used for cross-section identity reuse. Each matching person appears once, with a profile count when used in multiple sections. The highest-scoring image represents each group; server-side checks and review binding still cover every matching record.

Reviews are recorded in the existing activity log. A review lasts 15 minutes in that Super Admin's session and is bound to the image's content hash, target record, entered name and current comparison inventory. Changing any of these requires a fresh review; losing the Super Admin role also invalidates it. A review does not bypass image-type, upload, single-face or existing AI-image validation. To review a retained photo after renaming a record, reselect that image so the comparison dialog can open. Personnel writes hold a MySQL advisory lock through the request to serialize comparisons and saves.

## Identity and accuracy limits

When the shared personnel migration is enabled, identity reuse is determined by personnel ID across sections. Equal names alone do not suppress a potential duplicate: same-name individuals remain distinct. Existing profiles start with separate identities and must be explicitly reviewed and linked by a Super Admin through `/admin/personnel.php`. See [personnel normalization](personnel-normalization.md). On databases that have not enabled that migration, the compatibility policy still treats matching full names (case and whitespace normalized) as the same individual, with the previous homonym/spelling limitations. No fuzzy name matching is used.

The lower threshold comes from the [OpenCV SFace example](https://docs.opencv.org/4.13.0/d0/dd4/tutorial_dnn_face.html). The stricter 0.55 blocking threshold is a screening default, not a calibrated identity probability. Validate thresholds against representative, consented school portraits and false matches before production rollout. Lighting, pose, age, occlusion and extreme crops can produce false negatives or positives. The UI deliberately describes a *possible* duplicate and never treats similarity as proof of identity. Existing images with multiple faces are compared against each face; new portraits must contain exactly one detected face. Legacy photos with no detected face produce no match and should be replaced with clear portraits.

All existing candidate photos are processed locally on each check, including archived profiles; reused image paths are decoded once per invocation. This avoids a persistent biometric index but costs processing time as the inventory grows. Benchmark the full inventory on production hardware. A 45-second limit is enforced; an oversized inventory needs a separately designed local worker/index with appropriate retention controls.

## Privacy and verification

Face features exist only in the child process's memory and are discarded at exit. Browser responses contain names, existing image URLs, screening scores and review capability, never embeddings. Session reviews retain only opaque hashes and expiry times; audit records retain the review confirmation and comparison hash. Use HTTPS, restrict server/model access,. The AJAX endpoint requires page-content permission, an authenticated session and CSRF, and sends `Cache-Control: no-store`. Standard server/OS process-memory and swap protections still apply.

Run:

```sh
php tests/person-photo-check-test.php
php tests/person-photo-runtime-test.php
/opt/gnci-face/bin/python tests/person-face-worker-test.py --models /opt/gnci-face/models --portrait /path/to/local/portrait.jpg
node tests/person-photo-browser-test.cjs
node tests/person-photo-endpoint-test.cjs
```

The policy test uses isolated SQLite tables and injected comparison results, including cross-section detection, same-name reuse, target exclusion, threshold boundaries, malformed output, unavailable service, review expiry and invalidation. The worker test uses real inference on resized/cropped copies and a changed canvas, plus no-face and multi-face images. The runtime test uses the configured environment variables to verify PHP-to-Python inference. Browser tests mock API responses to verify UI gating and review behavior. The endpoint test uses the existing local Docker session helper (`cache/activity-log-session.php`) and active local test accounts in each role; it verifies authentication, CSRF, review authorization, private responses and successful runtime availability, without saving profiles. For an intentional runtime-outage test, set `FACE_CHECK_ALLOW_UNAVAILABLE=1` to permit the expected blocked response. These tests do not establish the model's real-world error rate. Perform authenticated upload/save tests with the deployed runtime before enabling production publishing.
