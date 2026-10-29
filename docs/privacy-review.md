# Website privacy review — 28 October 2026

This is a code and local-schema review, not a legal compliance certification or
a penetration test. No production accounts, personal records, secrets, tokens or
database content exports were read. The local schema was checked read-only using
`tests/privacy-schema-inventory.php`; its table/column inventory is recorded in
`privacy-schema-inventory.json`. Production schema/configuration, server logs,
TLS, backups, vendor agreements, school records and actual institutional practices
remain unverified. The JSON keys identify the local tables; no inference about
the number of people is made from the schema.

## Which pages apply

| Page | Finding |
| --- | --- |
| Privacy Policy / privacy notice | Necessary to explain the implemented personal-data processing. A notice is required across lawful processing grounds, not just consent. |
| Terms of Use | Useful for school-information accuracy, public use, AI limits, external systems and content permissions. It is not established here as a general DPA requirement. |
| Cookie Policy | A separate page is useful because the site has several cookies/storage purposes. A separate document title is not itself the legal requirement; meaningful disclosure and a lawful basis are. |
| Optional-feature choices | Implemented as a conservative opt-in approach for pseudonymous statistics, behavioral personalization and third-party embeds because no approved alternative ground/legitimate-interest assessment was supplied. Necessary sessions do not by themselves establish a universal cookie-banner requirement. |

The prior footer linked Privacy Policy and Terms of Use to `coming-soon.php`.
That is not a substantive privacy notice. No prior complete policies or approved
retention schedule/DPO contact were found. Organizational documents may exist
outside the repository; absence here is not proof that the school has none.

## Actual processing inventory

| Feature / evidence | Data and storage | Recipient / purpose / limits |
| --- | --- | --- |
| `pages/contact-us.php` | Name, email, optional subject, message. Session CSRF token and flash values/errors; email delivery. No form-handler insert into a contact-message table. | Published contact mailbox through configured SMTP/PHPMailer or PHP mail. Hosting and mailbox retention unknown. The default contact address is not evidence of a DPO appointment. |
| `api/track-visit.php`, `components/visitor-counter.php` | Random `gnc_vid`, SHA-256 identifier hash, visit date, first_seen in `site_visitors`. | First-party visitor counting. Hash is pseudonymous, not guaranteed anonymous. Cookie lifetime one year; stored rows previously had no automatic purge. No IP/UA column in this table; UA is checked to exclude bots. |
| `components/page-analytics.php` | Normalized page URL, title, aggregate total/daily counts, dates in `page_visits` / `page_visits_daily`. | No foreign key or per-visitor page-history linkage in these tables. Selected public entity identifiers retained in page keys. Query strings otherwise dropped. No automatic retention purge found. |
| `assets/js/recommendations.js` | Local `gnc_recommendations_v1`: page interests, counts, active reading seconds, recent impressions. | Profile stays in browser; catalog request returns a shared public snapshot. History has a 30-day rolling expiry on use, impressions seven days. Generic suggestions work without the profile. |
| `components/visitor-feedback.php`, `assets/js/visitor-feedback.js`, `api/visitor-feedback.php`, `integrations/visitor-feedback/Code.gs` | Six required ratings sent through PHP to Google Forms; no local answer persistence. Signed HttpOnly identity cookie (180 days), local reminder timing, server rate-limit hashes, private Google receipt sheet with browser-derived hash, state, response ID and time. | Owner-deployed Apps Script confirms a saved response and prevents duplicates for the same browser identity. No respondent sign-in/email is requested. Activation requires owner setup; live writes are not yet verified. Revocation clears the identity cookie and UI answers but does not delete saved Google records. Cookie clearing, other browsers and the original unrestricted responder URL can bypass browser identity. Receipt/response retention remains the school's responsibility; see `docs/visitor-feedback.md`. |
| `chatbot/chatbot.php`, `chatbot/chatbot.js` | Message (500-char input limit), up to six recent turns, page/program/department context; up to 40 turns in tab session storage. | Server retrieves published site context and can send it plus messages/history to Google Gemini. No transcript-table writes found. Provider handling/retention not established. Greeting/off-topic shortcuts can answer locally. A message can still contain personal data even though the site does not ask for it. |
| `chatbot/inc/ratelimit.php` | Hashed IP-derived filenames and request timestamps in `sys_get_temp_dir()/gncbot_rl`. | Abuse/quota limits. Window pruning occurs on use; expired files are not generally deleted. This is not the same as deleting all IP-derived artifacts. |
| `admin/photo-check.php`, `admin/action/photo-check-handler.php` | Eligible staff-uploaded portrait bytes resized/encoded and sent to Gemini when configured and enabled. | Real-person/AI-image checking, not an audited identity verification system. Permission to publish an image is not automatically permission for external AI processing; approve this separately or disable the existing setting. No claim of facial recognition is made. |
| `admin/auth/auth_handler.php`, `includes/auth-security.php` | Admin names/email/roles/status, password hashes, OTP and expiry, remembered-device token hash, credential version/login times. Session state, recovery token hashes, IP/security logs and rate-limit buckets. | Staff authentication, email OTP/recovery, permissions and security. OTP is stored temporarily in the account record; expiry enforcement does not necessarily delete unused expired credentials. Session default 30-minute inactivity, OTP default 300 seconds, remembered device 30 days, reset token 15 minutes. |
| `includes/auth-security.php` password check | First five SHA-1 hash characters sent to Pwned Passwords with response padding requested. | Compromised-password check. Full password is not sent by this code. Security provider still receives the server connection. |
| `activity_logs`, drafts and approval tables | Staff IDs, record changes/actions, timestamps; draft/approval payload chunks can contain uploaded files and unpublished personal information. | Authorized review/editing. No general lifecycle purge found. Do not describe every logged or published item as automatically erased on account deletion. |
| People, organizations, achievements, news/events and uploads | Names, roles, office contacts, bios, photographs, achievement/education descriptions, academic year and JSON achiever lists; media files and identifiers. | Public publishing controlled by staff. Educational information may be sensitive personal information; minors may appear. Existing permission/notice/guardian processes and takedown procedures are not proven by CMS authentication. |
| Facebook integration (`index.php`, `includes/facebook-*.php`) | Cached school posts/images/permalinks; administrator/Page connection metadata; publishing requests/delivery state and configured token storage. | Server imports posts; authorized publishing sends selected announcement text/media to Meta. Not evidence that ordinary visitors log in with Facebook. Provider account contracts/permissions unverified. |
| Supported embeds | Google Maps footer; Facebook plugins on department/sports/organization pages; Google Forms feedback. | Browser IP/headers and provider cookie context can be exposed when loaded. Before this change, several loaded automatically. Local campus map is a local HTML resource, not a geolocation collection endpoint; it also references Google Fonts. |
| Fonts/CDNs/PDF support | Google Fonts, jsDelivr, cdnjs, external PDF.js library when used. | Browser connection metadata reaches delivery providers. These requests remain independent of optional-embed settings; do not claim all third-party traffic is blocked. No Google Analytics, Meta Pixel, ad SDK, newsletter collection or public account registration flow was found in the reviewed implementation. |
| Portal, payment and application links | Links to Microsoft sign-in, bank/GCash guidance and school/social contacts. | No implemented tuition transaction processor or admissions-document intake was identified on this public site. External systems need their own review; do not invent payment-card collection here. |

## Philippine privacy assessment

RA 10173 Sections 11–13 require a defined purpose, proportionality, retention
limits and an applicable lawful processing condition. Education is included in
the statutory definition of sensitive personal information. The school must
evaluate the specific data and Section 13 condition for educational content and
children’s material; being publicly viewable is not a blanket exemption.
[RA 10173](https://privacy.gov.ph/data-privacy-act/)

The IRR’s transparency/data-subject provisions call for information about data,
purposes, scope/recipients, controller contact, storage period and applicable
rights. A notice cannot substitute for actual security, retention and governance.
Security obligations also include organizational/physical safeguards that cannot
be established by inspecting PHP alone.
[DPA IRR](https://privacy.gov.ph/implementing-rules-regulations-data-privacy-act-2012/)

NPC Circular 2023-04 requires valid, specific and informed consent when consent
is the chosen ground; multiple optional purposes need meaningful separate
choices and withdrawal. Privacy notices apply beyond consent. The website’s
new optional-feature settings are not consent to unrelated school processing,
and declining optional features does not block public information or contact.
[NPC 2023 compendium, Circular 2023-04](https://privacy.gov.ph/wp-content/uploads/2024/05/2023-compendium-2.pdf)

NPC registration depends on the institution’s actual circumstances and applicable
thresholds/risk, not the number of website tables. Assess DPO/DPS registration
under Circular 2022-04 with the school. Do not claim registration, exemption,
certification or NPC approval from this review.
[NPC registration guidance](https://privacy.gov.ph/pips-and-pics/faqs/)

The NPC also issued Advisory Opinion 2017-047 on cookie popups. Its official
index was verified but the PDF could not be downloaded; no unsupported quotation
or rule is inferred from its title. No conclusion about GDPR/ePrivacy applicability
is made merely because the site can be reached from abroad.
[NPC advisory-opinion index](https://privacy.gov.ph/pips-and-pics/advisory-opinions/)

## Changes prepared in this task

- Three responsive policy routes share the existing navigation/footer. They are
  **private review drafts by default** (authenticated page managers only), use
  `noindex` and `no-store`, and return 404 to anonymous visitors until published.
- Footer policy links appear only when publication is approved and required
  institutional statements are supplied. Broken “Coming soon” policy links were
  removed. Privacy settings remain publicly accessible independently.
- Equal-access Accept/Reject/Manage controls with separate statistics,
  personalization/reminder and embedded-service choices; 180-day first-party
  preference cookie; browser privacy signals keep optional processing off.
- Visitor tracking is gated in the browser **and PHP endpoint**, before setting
  an identifier or writing statistics. No historic rows were deleted.
- Known supported public Maps/Facebook frames use deferred sources; Google
  feedback needs permission before its invitation/storage and frame load.
  Withdrawing removes supported frames and optional local keys. Providers’ own
  cookies and previously received data cannot be erased by this site.
- Existing personalization controls use the shared choices. Anonymous generic
  suggestions remain available. The assistant has a specific Google-processing
  acknowledgment before messages are submitted, enforced by its endpoint.
- Contact-form point-of-collection explanation and hardened session-cookie
  options. No mail was sent by the audit/tests.

## Required decisions before publication

The owner confirmed in this review that no privacy contact is designated and
the listed retention/permission/provider arrangements are not yet approved.
Policy routes therefore remain private drafts; no public compliance assertion
or invented DPO address has been added.

1. Confirm the controller identity, accountable privacy office/DPO, monitored
   contact and rights-request procedure (verification, access/correction/erasure,
   complaints and escalation). Do not substitute a developer’s default email.
2. Approve a purpose-by-purpose lawful-ground matrix. Assess sensitive/student
   data, children, guardians and the existing photo-checking AI feature. Obtain
   and retain any necessary permissions; minimize published material.
3. Approve and implement retention/deletion, including visitor dates/hashes,
   security/activity logs, inactive accounts, unused OTP/reset/rate-limit rows,
   drafts/chunks/uploads, contact email, Google Forms responses, chatbot limiter
   files and backups. Cookie expiry alone does not delete those stores. A
   maintenance schedule/job is missing for several of these; no destructive
   purge is authorized or run here.
4. Verify mail/hosting/Google/Meta contracts, account types, subprocessors,
   locations, cross-border safeguards, service purposes and data-use/retention
   terms. Inspect the actual Google Form and its access permissions/notice.
   API-key presence does not prove a paid account or a provider’s no-training rule.
5. Confirm incident/breach procedures, backup/access practices, public media
   review and school-wide privacy management/impact assessments. Verify live
   HTTPS/cookies, protected files and upload execution restrictions on the actual
   host and subdomains; repository configuration is not a hosted verification.
6. Test real hosted first-visit/reject/accept/revoke behavior, vendor network
   requests and actual cookie lifetimes. Supported embeds are covered, not
   arbitrary external HTML a future editor might introduce. Review consent
   evidence requirements: choices live in a browser cookie, not a central audit
   ledger. Decide whether additional lawful, proportionate evidence is needed.

## Publication settings

After the school resolves the above and approves the text, set these in the
existing server environment or private deployment configuration (not public HTML):

```
LEGAL_POLICIES_APPROVED=1
PRIVACY_CONTACT_OFFICE=<approved accountable office>
PRIVACY_CONTACT_EMAIL=<monitored privacy address>
PRIVACY_RETENTION_STATEMENT=<approved, implemented schedule and deletion process>
PRIVACY_LAWFUL_BASES_STATEMENT=<approved purpose-by-purpose lawful grounds>
PRIVACY_PROVIDERS_STATEMENT=<verified recipients, providers and transfer arrangements>
```

Approval plus all values are required by `gncLegalPublished()`. Supplying strings
does not implement retention or prove legal compliance. Review the full page
text before enabling publication; adapt it if school practices differ. Policy
content is code-managed because no existing legal-policy CMS was found. No
database migration is required. No production configuration was changed.

## Verification

PHP syntax checks passed for the changed PHP paths; JavaScript syntax checks
passed for the consent, recommendation, feedback and chat scripts. The focused
`node tests/privacy-consent-browser-test.cjs` exercises first visit,
reject/reload, category choices, withdrawal and cookie/local-storage cleanup,
server-side tracker enforcement, iframe sources, GPC/DNT, unavailable storage,
anonymous publication gating and responsive settings. This is not a test of the
hosted deployment, approved publication configuration, provider contracts or
institutional retention. Existing analytics/feedback tests that assume automatic
tracking will need consent-aware fixtures when those suites are next run.
