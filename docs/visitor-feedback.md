# Visitor feedback: simple Google Form embed

The website now displays your existing Google Form directly inside the feedback
popup. **No Apps Script deployment, shared secret, private configuration file,
Docker volume or database migration is required.** This also works on localhost.

## Already connected

Your public form:
https://docs.google.com/forms/d/e/1FAIpQLSextXO7NUBv8LnF-PArXycrebe1ViNF9YsdQGP23yRRTaYsrQ/viewform

The URL is set in `components/visitor-feedback.php`. The popup loads it with
`?embedded=true` only when the popup opens. Visitors use **Google's Submit button
inside the form** to save their responses. The website does not submit responses
through a PHP endpoint.

The supplied form currently contains six required rating questions. Its contents
and validation are managed in Google Forms. This supersedes the earlier single
custom star-rating implementation; no extra questions are added by the website.

## Testing on localhost

The fixed vertical **RATE US** tab on the left opens the same dialog immediately
and can reopen it after dismissal or acknowledgment without refreshing. Closing
a manually opened dialog leaves the automatic timer and its cooldowns unchanged.
After **I've submitted**, reopening the tab displays **You already submitted**
instead of the form for the existing 30-day reminder pause. This acknowledgment
persists in the same browser across page visits; clearing storage or revoking
personalization clears it. Earlier cooldown records do not distinguish a
dismissal from a submission, so only new acknowledgments show this message.
**I've submitted** still pauses automatic reminders for 30 days. The automatic
popup retains its existing 60-active-second timing and dismissal behavior.
Both entry points respect existing personalization and embedded-service privacy
choices; the tab opens privacy settings when those permissions are missing.

1. Open http://localhost:8080/ in a fresh browser profile or private window.
2. Browse public pages for a total of 60 active seconds. Time in background tabs
   does not count. The popup waits until other dialogs have closed.
3. Fill out the embedded Google Form and press its **Submit** button.
4. Check the **Responses** tab in your Google Form to see the submission.
5. Select **I've submitted** in the website popup to close it and pause reminders
   for 30 days. X, Escape and **Not Now** pause reminders for seven days.

Google's iframe is cross-origin: the website cannot verify whether the response
was saved. **I've submitted** is an explicit visitor acknowledgment, not another
Submit button or a verified success message. An iframe load never marks a rating
as submitted. Simply using Google's Submit without the acknowledgment does not
set the website's 30-day cooldown.

The separate 30-minute offer marker prevents refreshes while a prompt is open
from repeatedly showing it. Cooldowns and active browsing time use localStorage;
clearing it resets those preferences. If storage is blocked, preferences only
last for the current page. A browser lock/short lease prevents simultaneous offers
in multiple tabs. Admin pages and Visual Editor previews exclude the popup.

## Google Form settings

Keep the form published/accepting responses and accessible to your visitors.
Turn off email collection, sign-in restrictions and **Limit to 1 response** if you
want visitors to rate anonymously without a Google account. The **Open form in a
new tab** link is available if the embedded form is blocked by browser settings.

To view results in Sheets, open your Google Form's **Responses** tab and select
**Link to Sheets**. Google handles saving and validation; access to the results
remains controlled by the Google account that owns the form.

The iframe uses Google's own form design. The surrounding popup uses the existing
GNCILink design, animations, focus handling and responsive layout. Google receives
normal browser requests when the iframe loads; no site-side proxy is involved.

## Verification

Run `node tests/visitor-feedback-browser-test.cjs` and
`node tests/visitor-feedback-coordination-test.cjs` from the project root. The
browser test needs PHP CLI, local web hosting and Chrome and intercepts Google Form loading; it
never submits a response. Its checks cover timing, lazy embedding, cooldowns,
focus, mobile layout and admin exclusions. A live browser GET can separately
verify the actual form renders without submitting test ratings.
