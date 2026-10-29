# Anonymous visitor feedback with verified submission

The popup keeps the six rating questions managed in the existing Google Form.
Questions and section headings are read through Apps Script; they are not copied
into PHP or JavaScript. The website renders the rating controls so its server can
validate the complete response and confirm Google's saved response ID. Visitors
are not asked for a name, email address, or Google sign-in.

**Activation requires the form owner to deploy the supplied Apps Script and
configure the two hosting secrets below. Until configured, the popup displays
verification unavailable and does not offer an unverified fallback.**

## One-time owner setup

1. Open the existing Google Form in its editor. Use its Apps Script editor (or
   create an Apps Script project owned by the same Google account). Copy
   `integrations/visitor-feedback/Code.gs` into that project.
2. Create a new private Google spreadsheet for verification receipts. Do not
   publish it or share it with respondents. Existing form responses are untouched.
3. In Apps Script **Project Settings > Script Properties**, add:
   - `FORM_ID`: the ID in the form EDITOR URL `/forms/d/FORM_ID/edit`, not the
     public responder ID following `/forms/d/e/`.
   - `RECEIPTS_SHEET_ID`: the ID in the new spreadsheet's URL.
   - `FEEDBACK_SCRIPT_SECRET`: a random secret of at least 32 characters. Generate
     it privately, for example `php -r "echo bin2hex(random_bytes(32));"` in your
     own terminal. Never put the secret in website markup, JavaScript, Git or chat.
4. Run `setupFeedbackReceipts` manually once and authorize the project. It adds
   a `Website receipts` sheet and refuses to overwrite an existing one. Its
   columns are visitor hash, pending/confirmed state, Google response ID and time.
5. **Deploy > New deployment > Web app**. Execute as the form owner and allow
   access by **Anyone**. Anonymous visitors never call this URL directly; every
   request must carry a signature from the website. Copy the deployment `/exec`
   URL. Changes to the script require updating the deployed version.
6. Add these entries to the EXISTING private `config/secrets.php` on hosting,
   preserving all existing database and other credentials:

   ```php
   'FEEDBACK_SCRIPT_URL' => 'https://script.google.com/macros/s/YOUR_DEPLOYMENT_ID/exec',
   'FEEDBACK_SCRIPT_SECRET' => 'THE_SAME_PRIVATE_SECRET_AS_SCRIPT_PROPERTIES',
   ```

   Environment variables with those names also work. Do not upload the example
   configuration over your real credentials.
7. Upload the changed PHP, JavaScript, CSS and component files. Hosting requires
   PHP 8+, cURL with HTTPS support and outbound access to `script.google.com` and
   `script.googleusercontent.com`. The existing `auth_rate_limits` table is used
   for throttling. On installations missing the existing authentication schema,
   review `sql/auth-security-migration.sql`; no new feedback database table is
   needed. No production database or Google configuration was changed by Codex.
8. Perform an owner-approved live test: five unanswered/partial ratings must
   block saving; six ratings save one response; reopening shows **You already
   submitted**; repeated submission returns the same receipt. Check both the
   original Form Responses and the private receipts sheet.

The Form must accept responses and contain exactly six Rating or Linear Scale
questions with values 1 through 5. Every question is required by both servers,
even if its Google required switch was accidentally turned off. Unsupported
question types or a changed question count fail closed instead of losing answers.
Existing form response storage and linked-sheet configuration are not changed.

## Popup behavior

- **Submit ratings** validates all six answers and sends them to the PHP gateway.
  Apps Script independently checks every rating, obtains a script lock, reserves
  the browser's receipt row and saves the response with FormApp.
- **I've submitted** checks the server receipt. Without a confirmed response it
  shows that no completed response was found and leaves the questions available.
  It never records a browser claim as proof of completion.
- A confirmed response hides the rating controls and shows **You already
  submitted**. The same browser cannot save another response through the site.
  Local reminder storage is only a convenience; clearing it does not remove the
  server lock while the identity cookie remains.
- Double-clicks, concurrent requests and retries return the existing receipt.
  If a write fails after reservation, the row stays pending and automatic retries
  cannot create a duplicate. The owner must reconcile pending rows against Form
  Responses: record the known saved response ID and mark confirmed, or remove a
  pending row only after establishing that no response was saved. Do not blindly
  retry or erase uncertain records.
- Existing active-time prompting, focus handling, manual opening, seven-day
  dismissal and privacy choices remain. Confirmed responses pause invitations
  for 30 days; server verification continues to prevent repeat responses even
  after that reminder pause expires.

## Privacy and limits

A signed HttpOnly, SameSite browser cookie lasts 180 days; it uses Secure on HTTPS.
The Google receipts sheet stores only a hash derived from the random browser
identity, receipt ID, state and timestamp. No IP or browser identity is attached
to the six Google answers. IP-derived keyed hashes and browser hashes are used
in the existing server rate-limit table; raw IPs are not sent to Google by this
integration. Existing host request logs remain subject to host configuration.
Answers stay in browser controls until saved; the site does not persist answer
values in localStorage, sessions or its database. Google stores the submitted
answers under the form owner's existing configuration.

Both personalization and external-service consent are required. Revoking either
clears the identity cookie through the privacy endpoint, aborts pending UI
requests and clears the displayed answers. It does not delete an already saved
Google response or receipt. School staff must manage Google response/receipt
access, retention and any deletion request under their approved practices.

This prevents repeats for the same browser identity, not one response per human.
Clearing cookies, revoking consent, using another browser/device or visiting the
original unrestricted Google responder URL can bypass browser identity. The
popup no longer exposes that responder URL as an alternative submission path.
Rate limits reduce abuse but are not proof of identity. Secret rotation also
invalidates existing signed browser identities. Anonymous operation cannot
promise permanent one-person-one-response protection.

## Verification

- `php tests/visitor-feedback-verification-test.php`: PHP validation, signed
  identity, CSRF binding, malformed/missing ratings, forged/mismatched receipts.
- `node tests/visitor-feedback-script-test.cjs`: Apps Script with mocked Google
  services; validation, signatures, locks, idempotency and uncertain writes.
- `node tests/visitor-feedback-http-test.cjs`: real local HTTP negative paths;
  method, origin, privacy, payload limits and invalid requests.
- `node tests/visitor-feedback-browser-test.cjs`: mocked verification service;
  required ratings, restored button, duplicates, server lock across navigation,
  unavailable service, consent, timing, focus and responsive layout.
- `node tests/visitor-feedback-coordination-test.cjs`: recommendation coordination.

Automated tests do not deploy Google services or send live ratings. Real Google
writes and the hosted connection remain unverified until the owner's setup.

References: [Apps Script web apps](https://developers.google.com/apps-script/guides/web),
[Form responses](https://developers.google.com/apps-script/reference/forms/form-response),
[Rating items](https://developers.google.com/apps-script/reference/forms/rating-item).
