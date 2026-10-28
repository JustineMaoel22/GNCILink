The Online Payment Guide uses eight locally hosted Storyset SVG illustrations in
`assets/images/payment-guide/`. `sources.json` records each original page, download
URL, and accent color. The original blue accent was changed to match each section.
The public page credits Storyset, as required for free use:
https://storyset.com/faqs

Admins with **Manage page content** permission can open **Content Editor → Online
Payment Guide**, click any illustration, and choose **Change illustration**. SVG,
PNG, JPG, and WEBP uploads up to 5 MB are supported. **Browse Storyset** opens the
Storyset website to choose and download another illustration. Uploaded SVGs pass
through the existing SVG sanitizer before storage.
An admin-only note and the Browse Storyset reminder instruct admins to choose
**Download SVG** and upload the downloaded `.svg` file.

Changes remain in the editor until **Publish**. **Restore Storyset illustration**
stages the original bundled artwork; publish to make the restoration live.
Admins can also click headings, descriptions, numbered instructions, account
details, captions, required verification details, and button labels to edit them
in place. Paragraphs support rich text. Shared account details update in both
payment options. Select a button and choose **Edit destination** to change its URL.
The Storyset credit and SVG download reminder remain fixed.

Text and link overrides use `payment_text_*` keys in `tuition_fee_content`. Text,
links, and illustrations publish together in one transaction. Invalid links and
unknown fields are rejected; formatted text is sanitized before saving.

Image overrides use `payment_image_*` keys in the existing `tuition_fee_content`
table. New installations must run `database/migrate-tuition-fees.php`, as required
for the tuition editor. Uploaded files live in `uploads/payment-guide/`; replacing
or restoring an image removes its unused prior upload after the database commits.
Default Storyset assets are preserved.

SVG replacements are validated and sanitized directly; PHP Fileinfo is not
required. The host must enable PHP DOM/XML for safe SVG and rich-text sanitization.
If it is unavailable, the editor asks for that extension to be enabled.
Raster images use server-side image detection and the shared 5 MB upload rules.
Deploy `admin/action/payment-guide-handler.php` for this compatibility fix; no
new migration is required for sites already using the payment guide editor.

Validation: `node tests/payment-guide-editor-test.cjs` uses the local Docker stack
and Chrome. It checks staging privacy, publishing, SVG sanitization, rollback,
permissions, CSRF, restoring defaults, file cleanup, and responsive image loading.
It requires the GCash illustration to be at its default and restores it afterward.
