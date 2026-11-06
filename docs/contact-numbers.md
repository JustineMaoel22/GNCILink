# Contact number controls

The shared implementation is `includes/contact-number.php`, `assets/js/contact-number.js`, and `assets/css/contact-number.css`. It covers Administration office phones, department-head phones, the main Contact Us phone, direct directory numbers, and extension-only local directory entries. Both the conventional settings forms and visual-editor popovers use these controls. Other contact fields, such as email addresses and business hours, retain their existing validation.

## Formats and editing

- Mobile: `+63-912-345-6789`. The country prefix is fixed in the control; enter 10 national digits. Local `09123456789`, `639123456789` and international forms normalize to the same display. The domestic leading zero is removed.
- Landline: `045-900-4473`. The requested `045` area prefix is fixed; enter seven subscriber digits.
- Optional landline extension: 1–6 digits, stored/displayed as `045-900-4473 loc. 123`. Leading zeroes are preserved. The extension is hidden for mobile numbers, and switching types retains both in-progress values until the editor is closed.
- Local directory entries are extension-only numeric controls. They do not require a main number in every row.

The controls filter unsupported characters, cap digits, format while typing, accept full-number pastes, and handle deletion across separators. Partial numbers show an inline message. Optional empty personnel phone fields remain allowed; Contact Us fields keep their existing completeness policy. Nonempty malformed numbers are rejected even when an administrator allows incomplete page content.

## Storage and compatibility

No migration or dependency installation is required. The existing `admin_offices.phone`, `departments.dean_phone`, and Contact Us `page_sections` content fields store canonical strings. The `+63-` or `045-` prefix preserves the type, and `loc.` preserves the optional extension without changing editor payloads or schema. PHP parses this representation into type, national number and extension when needed. An empty number has no persisted type or extension.

Recognizable old values, including `(045) 900-4473 Loc. 106` and seven-digit local direct numbers, are normalized for display and when saved. Existing unrecognized text, such as multiple numbers in one field or unsupported area codes, is preserved verbatim when unchanged so unrelated edits cannot erase it. The editor shows that saved value as a notice until the administrator replaces it. New or changed values must follow the new rules. Nothing bulk-rewrites existing records.

Public Administration and department templates use the shared formatter. Contact Us formats the main number and direct directory entries. The shared footer reads the published Contact Us phone, with the previous school number as its fallback when no published value is available. Dialing URLs use `;ext=123` for extensions, rather than appending extension digits to the main number. Unrecognized legacy text is never turned into a guessed dialing URL.

Server checks run in the shared office/department save functions and Contact Us section validator, covering direct handler calls and section publication/approval. The Content Editor approval queue also validates and normalizes these values before storing a proposal, so it cannot bypass the normal handlers. Existing authentication, authorization, CSRF, review locks and publication-password requirements remain in force.

## Verification

```sh
php tests/contact-number-test.php
docker compose exec -T --user www-data web php tests/contact-number-domain-test.php
node tests/contact-number-browser-test.cjs
```

The policy tests cover normalization, invalid values, extensions, legacy preservation and dialing URLs. Domain tests read local records and attempt invalid office, department and Contact Us saves; validation rejects them before any writes. Browser tests cover type switching, formatting, limits, paste, separator deletion, extension handling, legacy values, the real Contact Us editor, draft rejection, publication authorization and invalid Content Editor proposals. They capture responsive layouts under `cache/contact-tests/`. The domain/browser tests require the local Docker services, existing local records and the existing `cache/activity-log-session.php` test-session helper. They do not publish or rewrite contact records.
