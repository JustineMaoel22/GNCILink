# Alumni page

The public page is `/alumni`. Student Life → Our Community → Alumni links to it.
Open **Content Editor → Alumni** to edit the four heading parts, introduction,
button text and links, and optional stories. Replace the hero photo through
the **Change background** controls.
The content lives in the existing `pages` and `page_sections` tables; the public
template contains no fallback marketing copy or alumni records.

**Alumni Voices** appears directly beneath the hero. Its label and two-line
heading are editable in place. Click **Add alumni voice** to insert an empty
card. Edit the quote, name, batch, and role directly in the public-style card
layout, without a separate form or boxed fields. Photo and entry controls use the shared Visual Editor floating toolbar
above the selected card. Icon buttons have accessible names and hover labels;
the toolbar scrolls horizontally on narrow screens. It follows the selected
card and hides when you click elsewhere. Small badges show entry and publishing
status. Select a quote, name, batch, or role to use the shared font family,
size, color, and alignment controls. Typography is specific to each entry and
device, and uses the existing draft, approval, and publish workflow.
Click **Add photo** or **Replace photo**
to choose an image, and **Reposition photo** for shared crop and zoom controls.
Expand **Photo description** to edit accessible image text. Remove and reorder
entries with the floating toolbar; **Set as featured** moves an entry to first position.
The entry badge identifies unpublished changes; the editor toolbar tracks
whether work still needs saving as a draft. Save Draft keeps work private;
Publish or Submit for approval uses the existing permissions and workflow.
In **Preview**, the first card uses the large photograph layout and subsequent
cards use the smaller public layout. No sample people or quotes are installed. The section
stays hidden publicly until it has published entries. Photos display without an
added gradient; upload a photo with suitable contrast for the featured quote.

Card edits and staged photo uploads are included in Save Draft and approval
snapshots. Publish validates the collection and uses a revision check to prevent
overwriting another editor's changes. Removed photos are retained rather than
deleting potentially shared media. Content is stored in the `voices_data` row
of `page_sections`; it uses the card manager rather than the rich-text editor.

Use **Add button** below the hero links or in their floating toolbar to add a
new button. Edit its label directly, then select the button and choose the
labeled **Add link** or **Edit link** action to enter its destination. Website
URLs, /page paths, mailto: addresses, and tel: numbers are supported. Select an existing button and choose **Remove button** to remove
it after confirmation. You can remove all buttons and add them again. These
changes follow the existing Save Draft, approval, and Publish workflow. The initial buttons lead to Contact Us and News & Announcements.
Once alumni stories have been published, the second button can point to
`/alumni#alumni-stories`. Empty stories stay hidden on the public page.

Click **Change background** on the hero, then **Replace desktop background**
(or the currently selected device) to choose an image. Save Draft or Publish
when ready. The Reposition shortcut opens the same controls.
Use the existing Background controls to upload and position desktop, tablet,
and mobile images. Device overrides use `responsive_background_settings` and
take priority over the default image path. The initial background is the
repository's campus bell-tower photo; the reference's sunset image was not
provided as a separate asset.

## Alumni events

The ?What?s happening?? section pairs an editable background photo and
introduction with a single white **Upcoming Events** panel. Its calendar heading,
divider, date badges, and compact event metadata match the shared calendar design.
The panel displays the next three published **Alumni** events, ordered by start
date. Ongoing events remain visible through their end date. Draft, pending,
archived, past, and other-category events are excluded. With no upcoming events,
the section shows a brief empty message without sample records.

In **Events**, choose the **Alumni** category when adding or editing a gathering.
Use the existing event approval and publishing controls. In **Content Editor ?
Alumni**, edit the two heading lines and introduction in place; **Change
background** uses the existing device-specific background and crop controls.
Select an event card to open its existing event editing controls if your account
has event permissions. Event titles, dates, and locations are managed in Events.

Deploy `components/alumni-events.php` with the updated page, helper, editor,
stylesheet, and migration. Rerun `php database/migrate-alumni-page.php` to register
the four editable event fields and the Alumni category. The migration preserves
existing categories, records, published content, and drafts, and can be rerun.
No table changes are required. Retain these rows if rolling back application code.

Verification: `php tests/alumni-events-test.php` checks category/status/date
filtering, ongoing events, ordering, the three-event limit, editable field
validation, and unsafe background rejection inside a rolled-back transaction.

## Recent Events gallery

Below Upcoming Events, **Recent Events** displays six cards per page with a
three-column desktop, two-column tablet, and single-column mobile layout. It
shares the Campus Events card stylesheet and feed helper. The gallery includes
all published calendar events assigned to **Alumni**, including past dates, and
Alumni-tagged published news/announcements and cached public Facebook posts.
Pending, draft, archived, and other-category calendar events are excluded.
The existing Upcoming Events panel keeps its separate three-event date filter.

Events are managed through the existing **Events** editor; choose **Alumni** in
its category dropdown and publish using the existing permissions/approval flow.
News, announcements, and Facebook posts use their existing category controls.
Attached media appears on the cards; entries without media get a calendar
placeholder. News and announcement cards open their existing public articles,
and Facebook cards preserve their validated Facebook destinations.

Pagination makes every matching entry reachable and clamps invalid page values.
The gallery heading is editable in place. Pagination shares the Alumni page?s
font, background, and draft settings, and navigating in the editor uses the
existing unsaved-change confirmation. No event content is hardcoded.

Deploy `components/alumni-recent-events.php`, `assets/css/recent-events.css`, the
updated Alumni and Campus Events styles/templates, shared feed helper, editor
scripts, and layout routing helper. Rerun `php database/migrate-alumni-page.php`
to add the heading if absent and ensure the Alumni category exists. No existing
records or category assignments are replaced.

Run `php tests/alumni-recent-events-test.php` to verify category/status filtering,
past event visibility, six-card pagination, source coverage, invalid-page handling,
and the unchanged default Campus Events feed. All test records are rolled back.

## Alumni survey banner

The closing banner uses editable ?THE DOOR IS ALWAYS OPEN?, ?Stay part of?,
and ?the story.? text, a configurable campus background, and decorative gold
circles. Select text for the existing font controls; use **Change background**
for device-specific banner imagery.

In the Content Editor, click **Replace QR code** and upload the actual survey
QR as JPG, PNG, or WebP (up to 5 MB). The full image is displayed with a white
quiet border, without crop or zoom. One QR upload is shared across desktop,
tablet, and mobile using the existing background snapshot and publishing
workflow. It remains private until Publish or approval. Existing uploaded files
are retained through the shared media workflow.

No sample QR or destination URL is installed. The public banner shows its text
without a QR block until a QR image is published. The QR itself contains the
survey destination; replacing it changes where visitors scan. Its heading and
caption are editable in place.

Deploy `components/alumni-survey.php` along with the updated Alumni page,
content helper, stylesheet, editor and responsive-background scripts, then rerun
`php database/migrate-alumni-page.php` to register the six banner fields.
This uses existing tables and preserves previous content and drafts.

## Installation

Deploy the page, stylesheet, content helper, route/navbar/editor changes, and
`database/migrate-alumni-page.php`. Back up the hosting database, then run:

```sh
php database/migrate-alumni-page.php
```

For Alumni Voices, deploy `components/alumni-voices.php`,
`assets/js/alumni-voices-editor.js`, and `admin/action/alumni-voices-handler.php`
along with the updated page, stylesheet, content helper, and editor files.
Rerun the migration on installations that already have the alumni hero;
it adds the section headings and empty collection without replacing existing data.

This additive migration registers the page and its editable fields. Running
it again preserves existing values and drafts. It uses the existing CMS schema
and requires no new tables. Background uploads use the existing responsive
background feature; its storage must already be installed (see
`docs/responsive-backgrounds.md`).

Save Draft, Publish, discard, and content-editor approval use the current CMS
workflow and permissions. Roll back application files if needed and retain the
alumni rows and uploaded images to preserve editor work.
