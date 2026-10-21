# Student achievements

Before deploying, apply the idempotent migration:

```sh
php database/migrate-student-achievements.php
```

For the local Docker setup:

```sh
docker compose exec -T web php database/migrate-student-achievements.php
```

Admins use **Content Editor → Student life → Student Achievements** (`/admin/content-edit.php?page=student-achievements`). The old `/admin/student-achievements.php` URL redirects to this visual editor. The module requires the `manage_page_content` permission. It has its own table, `student_achievements`, and its own upload folder, `/uploads/student-achievements/`. It does not read or write News posts.

Click **Add Achievement**, an empty image slot, or an existing card. Edit its title, program/organization and year directly on the page. Set its category in **Achievement settings**; categories appear in the filter beside Academic Year. Click its picture to upload a JPG, PNG, WebP, or GIF up to 8 MB. **Achievement settings** also contains the description, visibility and slideshow switch. **Save draft** keeps the entry unpublished; **Publish** applies staged changes. Publishing a visible entry requires a picture and academic year. Achievements appear in creation order, earliest first, and achiever photos appear in upload order, first uploaded first. Editing an achievement or replacing a photo does not move it. Drafts are shown only to authorized managers in editor preview.

The canvas uses the public three-column design, with compact program/year captions. Empty image slots fill the first six positions in the editor when fewer entries exist. Settings and draft controls stay in the floating toolbar. These slots are not shown to public visitors, and no sample achievements or images are inserted into storage.

The editor uses three plain labels, with a preview and explanation for each cover choice:

- **Card cover photo**: the small picture under More Achievements. **Change card cover** replaces it.
- **Featured slideshow photo (optional)**: the picture in the large slideshow at the top. **Change featured photo** replaces it. If it is empty, the card cover is used. A card cover and academic year are sufficient to publish; admins do not need to choose a separate featured photo.
- **Achiever photos**: the pictures visitors browse after opening an achievement. Both cover pictures are excluded from this viewer. **Add achiever photos** supports selecting or dropping a batch of up to 20 pictures, at 8 MB each. Captions are optional. Save draft or Publish saves the entire batch together.

Enter achiever names one per line. Existing cover choices are preserved when upgrading.

Public card and featured-image clicks open the reference-style dialog: a green institutional header, white photo area, one achiever image at a time, previous/next arrows, pagination dots, and a green bottom border. It uses only the achiever gallery; card covers and featured slideshow photos are not automatically inserted. Images are contained without cropping. A record with no achiever photos shows an empty message. Captions and achiever names remain accessible to screen readers without adding visible text blocks to the design. Escape, the close button, and backdrop clicks dismiss the modal; focus returns to its trigger. Arrow keys change slides. Direct links also open the modal. The visual editor's **View achievers** action uses the same slideshow for staged photos.

The slideshow shows up to 12 published featured entries. More Achievements lists all published entries, six per page. Its dropdown uses explicitly assigned years; filtering resets pagination, and page links retain the selected year. Drafts are inaccessible through public modal links.

The migration creates the independent table without modifying existing records in other modules. It does not import News posts. An unused `news.academic_year` column from the earlier implementation may remain in existing installations; this module does not use it.

Verification:

```sh
docker compose exec -T web php tests/student-achievement-listing-test.php
node tests/student-achievements-browser-test.cjs
```

The database tests roll back their records. The browser checks also upload and publish a temporary achievement, verify permissions and CSRF protection, unpublish and delete it, and remove the test image and sessions. Their grid-layout fixtures are rendered from a rolled-back transaction.
