Tuition and fees uses the site's shared navigation/footer and a category sidebar beside a native browser PDF viewer. No sample fees or documents are published.

Open **Content Editor → Tuition and Fees**. Click the page text to edit it. Choose a fee category and click **Manage PDFs** to add, rename, replace or remove schedules. PDFs must be valid PDF files up to 20 MB (the server request limit also applies). Multiple documents are supported per category.

Changes remain in the editor until **Publish**. Cancel discards the staged changes. The public PDF viewer and download action use the published database records. Page introductions support the shared rich text toolbar.

Run `php database/migrate-tuition-fees.php` once on a new installation to create `tuition_fee_content` and `tuition_fee_documents`. The first migration imports schedules previously stored in these folders under `uploads/tuition-fees/`:

- `college/`
- `graduate-school/`
- `senior-high-school/`
- `basic-education/`
- `other-fees/`

Search covers category names and document titles. Empty categories show a contact link and no download action. Uploads use generated filenames; document titles and category names are stored in the database. Repeated migrations preserve the current records and do not reimport removed schedules.
