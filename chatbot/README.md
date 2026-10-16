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

## If the chat says "trouble responding"
Look for `[gncbot]` lines in the PHP/container log. `http 404 NOT_FOUND model=...` means that model name is retired or unavailable:
`php chatbot/tests/list_models.php` lists valid names; set one as `GEMINI_MODEL` in `.env` and restart the container.
