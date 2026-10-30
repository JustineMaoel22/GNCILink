# GNCILink AI Assistant

Floating chat widget + PHP endpoint that answers from the live GNCILink database (RAG) using Gemini.

## Install (3 steps)
1. Upload the `chatbot/` folder to the project root (next to `config/`, `index.php`).
2. Add to the existing `.env` (the one `config/config.php` already loads):
   ```
   GEMINI_API_KEY=your_key_from_aistudio.google.com
   # optional:
   # GEMINI_MODEL=gemini-3.6-flash   (default; run tests/list_models.php to see what YOUR key can use)
   # GEMINI_TIMEOUT=20
   # GNCBOT_IP_LIMIT=20        (requests per IP per 10 min)
   # GNCBOT_DAILY_LIMIT=3000   (requests per day, whole site)
   # CHATBOT_TRUSTED_IP_HEADER=HTTP_CF_CONNECTING_IP   (only if behind Cloudflare/a proxy)
   ```
3. Add ONE line at the end of `components/footer.php`:
   ```php
   <?php include_once __DIR__ . '/../chatbot/widget.php'; ?>
   ```

## IMPORTANT: make sure `.env` is not downloadable
`config/config.php` reads `../.env`, i.e. the project root, which is usually the web root.
Open `https://your-site/.env` in a browser - it must NOT show text. If it does, add to the root `.htaccess`:
```apache
<FilesMatch "^\.(env|git)">
    Require all denied
</FilesMatch>
```
(The DB password, Facebook token and the Gemini key all live in that file.)

## Program / department pages
On a program page set, before the footer include: `<?php $chatbotProgram = 'BSIT'; $chatbotDept = 'cba'; ?>`
(otherwise `?program=CODE` in the URL is used). The assistant then treats "What events are coming up?" as being about that program.

## Page links
Edit `inc/knowledge.php` -> `gncbot_page_urls()`. Entries set to `null` (vision-mission, about, contact, administration, student-services)
are never recommended until you fill in their real routes.

## Test
`php chatbot/tests/cli_test.php` shows the exact GNCILink data sent to Gemini for the 10 test questions (free).
`php chatbot/tests/cli_test.php --live "Your question"` also calls Gemini.

## Tuition and fee PDFs

Tuition questions now retrieve the same committed `tuition_fee_documents` used by
the public Tuition and Fees page. Uploading a private editor draft or submitting
for approval does not make a schedule available to the chatbot: publish/approve
it first. Deploy `chatbot.php`, `inc/retriever.php`, `inc/tuition.php` and
`inc/prompt.php` together. No database migration or PDF-converter installation is
required. The existing Gemini service reads PDF documents natively using
[inline PDF input](https://ai.google.dev/gemini-api/docs/document-processing).

The retriever ranks document titles by program and the requested school level.
It supplies source links and attaches up to three readable schedules, totaling at
most 8 MB per request, as server-side `application/pdf` parts. Only local files
under `uploads/tuition-fees/<existing-category>/` with a PDF signature are eligible.
It never fetches remote URLs or reads private/draft upload storage. If the tuition
table is absent, named legacy schedules use the public page's folder convention;
random upload filenames are excluded from that fallback. When the table exists,
only its committed rows are eligible, even if orphan files remain on disk.

Gemini is instructed to quote only legible amounts matching the program, school
year, semester, year level and payment basis; otherwise it asks for clarification
or points to the published document. Older schedules are not assumed current.
Scanned PDFs are passed to Gemini's document reader too, but unclear scans, larger
files and unmatched schedules may still require opening the linked document or
contacting the school. Published PDF bytes are sent to the existing Gemini provider
for matching tuition questions after the chatbot's existing AI consent and rate
limits. No extracted amounts or document copies are stored separately, so replacing
or removing a published PDF is reflected in the next query.

`php chatbot/tests/tuition_inspect.php` lists local published schedules and attachment
counts without sending any content externally. `php chatbot/tests/tuition_test.php`
uses a connection-local temporary table and synthetic PDF to test retrieval,
follow-up intent, attachment transport, source citations and unsafe-path rejection;
it removes the test file and does not call Gemini. Test the deployed site's actual
published schedules with questions such as “How much is the BSIT tuition for first
year, first semester, AY 2026–2027?” Exact amounts still depend on the uploaded
document and Gemini response; local automated checks do not prove model accuracy.

## If the chat says "trouble responding"
Look for `[gncbot]` lines in the PHP/container log. `http 404 NOT_FOUND model=...` means that model name is retired or unavailable:
`php chatbot/tests/list_models.php` lists valid names; set one as `GEMINI_MODEL` in `.env` and restart the container.
