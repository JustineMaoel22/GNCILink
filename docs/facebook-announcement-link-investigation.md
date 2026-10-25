# Facebook announcement links on iOS

## Findings verified on 2026-10-23

- The reported error screen is inside the Facebook iPad app. It does not establish that Safari cannot load the post, or that the post is private.
- `index.php` requests `id` and `permalink_url` from the Page posts API and saves the returned URL in `fb_posts_cache.permalink_url`. It does not generate an iOS-specific URL.
- Homepage announcements, pinned announcements and the news listing render that cached URL in an ordinary external anchor. Clicking elsewhere on a card uses synchronous `window.open` with the same URL. There is no custom Facebook app scheme, delayed popup or intermediate site redirect.
- Read-only requests to the hosted homepage and `/pages/news-and-announcement.php` returned identical Facebook link lists for iPhone Safari, Android Chrome and desktop Chrome user-agent headers.
- All six Facebook post links on the hosted homepage returned HTTP 200, the matching post title and a canonical `gnc.edu.ph/posts/...` URL in unauthenticated requests with an iPhone Safari user-agent header. This verifies web resolution and public metadata, **not** full content visibility for every account or actual Safari/Facebook app execution.
- The cached numeric URL owner is different from the Page prefix in the Graph post ID. For example, the cache has `403969556396264_1106565838791435`, while its permalink is `https://www.facebook.com/1009975871783766/posts/1106565838791435`. Both numeric owner variants and the `permalink.php` variant returned the same post title and canonical URL in the web request. The identifier difference alone does not prove a broken URL.
- Desktop/Android user-agent requests to Facebook returned HTTP 400 in this diagnostic environment, even for the alternate forms. These responses cannot establish real device behavior; Facebook can respond differently to automated requests.

## Confirmed device behavior

The user's classmate tested these links on an affected iOS device:

1. Original URL: `https://www.facebook.com/1009975871783766/posts/1106565838791435`
2. Alternate story URL: `https://www.facebook.com/permalink.php?story_fbid=1106565838791435&id=403969556396264`
For the original URL, tapping the link opened Facebook with the unavailable-content error. Pasting it into Safari worked both when dismissing the Open in Facebook prompt (viewing in Safari) and when accepting it (viewing in Facebook).

The alternate story URL worked when tapped directly and when pasted into Safari, with either choice in the app-opening prompt.

This isolates a URL-format-dependent failure during direct handoff into the iOS Facebook app for the tested post. The original post is accessible through both Safari and Facebook via other entry paths. Its removal or general privacy restriction does not explain this reproduction. Facebook's internal routing and the precise role of the differing Page IDs remain outside the website's visibility.

## Implementation

`includes/facebook-post-url.php` supplies `gncFacebookPostUrl()` to the homepage, pinned feeds, news listing, campus-event feed and admin announcement preview. For numeric `/{page}/posts/{post}` paths, it returns the tested `permalink.php?story_fbid={post}&id={page}` form. It uses the Page prefix of the stored Graph post ID when that ID matches the URL's post ID. No post or Page identifier is hardcoded in production code.

Valid video, reel, photo, share, vanity and opaque `pfbid` permalinks retain their original target. An inconsistent Graph post suffix does not overwrite a valid URL. Missing/unsafe URLs fall back to the validated Graph Page/post pair, or the configured numeric Page URL when no post identity is available. External hosts, credentials, unexpected ports, scripts and control characters are rejected.

Resolution happens when reading/rendering cached posts. The fetched source `permalink_url` remains unchanged in the database, preserving diagnostics and automatically covering existing entries as well as future imports. No user-agent branching, custom app schemes, extra popups or UI changes are required.

## Verification

- `php tests/facebook-post-url-test.php`: 25 passing cases covering numeric paths, Graph identity selection, inconsistent IDs, missing/unsafe inputs and preservation of other link formats.
- PHP syntax checks passed for the helper and all four modified PHP pages.
- `node tests/facebook-post-links-browser-test.cjs`: passed on local desktop/mobile Chromium layouts; checked 48 rendered post links across the homepage, news and campus events, safe external-link attributes, and ordinary anchor/card click targets. This does not emulate the iOS Facebook app.
- The alternate format was verified on the affected iOS device by the classmate **before** implementation. After deployment, repeat direct announcement clicks on iOS, Android and desktop, including a second numeric post and a reel/video. Full coverage of every Facebook app version/account cannot be guaranteed by local tests.

If a post remains unavailable through both formats and browser access, the Page owner must check audience, Page visibility and age/country restrictions. The website cannot detect a cross-origin Facebook error after navigation or grant a viewer access using the API token. A manual fallback is to copy the link and paste it into Safari, as verified by the classmate. Do not automatically retry URLs or expose the token.

## Production considerations

Deploy these files together:

- `includes/facebook-post-url.php` (new)
- `index.php`
- `pages/news-and-announcement.php`
- `pages/student life/campus-event.php`
- `admin/announcement.php`

No migration, new dependency, API credential change or Facebook cache refresh is needed. The live site has not been changed by this local implementation. Clear hosting HTML/page caches if enabled; follow the host's normal PHP opcode-cache refresh procedure if deployments do not invalidate cached scripts. Roll back these PHP changes together if necessary; no stored records were altered.

Apple documents that an HTTPS universal link to a different domain can open its associated app: [Allowing apps and websites to link to your content](https://developer.apple.com/documentation/xcode/allowing-apps-and-websites-to-link-to-your-content). The device test above, rather than this general documentation alone, established the URL-dependent reproduction.
