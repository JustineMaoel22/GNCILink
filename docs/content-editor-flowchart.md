# Content Editor module — detailed flowchart

These diagrams describe the current repository implementation, including the visual editor, private checkpoints, publication, approval, and conflict handling. They document existing behavior; they do not introduce a new workflow. Open this file in a Markdown preview that supports Mermaid.

## 1. Access, page selection, and editing

```mermaid
flowchart TD
    A([Open Content Editor]) --> B[Initialize session and refresh account identity]
    B --> C{Authenticated and active account?}
    C -->|No| D[Require sign-in or deny access]
    C -->|Yes| E{Mandatory password change?}
    E -->|Yes| F[Complete password change before using editor]
    F --> B
    E -->|No| G{Role permits website editor?}
    G -->|No: The Mirror or unauthorized role| H[Reject protected route]
    G -->|Yes: Super Admin or Content Editor| I[Load permitted pages and existing content]
    I --> J{Requested panel}
    J -->|All content| K[Search, filter, and sort available content]
    K --> L[Select page or content item]
    J -->|Review, Super Admin| M[Redirect to combined pending Approvals queue]
    J -->|Page, or default landing| L
    L --> N{Page and preview route allowed?}
    N -->|No| O[Return to available content hub or reject request]
    N -->|Yes| P[Establish page identity and live-content version baseline]
    P --> Q[Load actual website page in editor preview]
    Q --> R[Mount page-specific editing adapter]
    R --> S{Opening an approval snapshot?}
    S -->|Yes, authorized reviewer or owner| T[Load saved proposal and its original content baseline]
    S -->|No| U{Private draft exists for this account and route?}
    U -->|Yes| V[Restore pending text, layout, media, and original baseline]
    U -->|No| W[Start from current published content]
    T --> X[Display editable canvas and permitted controls]
    V --> X
    W --> X
    X --> Y{User action}
    Y -->|Edit| Z[Change text, formatting, images, PDFs, ordering, or layout]
    Z --> AA[Keep pending changes in working preview]
    AA --> Y
    Y -->|Undo or redo| AB[Restore local preview history, up to 50 changes]
    AB --> Y
    Y -->|Preview or change device| AC[Inspect desktop, tablet, or mobile rendering]
    AC --> Y
    Y -->|Save draft| AD[[Private draft flow: Diagram 2]]
    Y -->|Publish or submit| AE[[Publication and approval flow: Diagram 3]]
    Y -->|Embedded form or manager| AF[[Independent manager flow: Diagram 4]]
    Y -->|Cancel pending edits| AG[Return to last saved checkpoint, or published baseline]
    AG --> Y
    Y -->|Discard draft| AH[Confirm, delete own checkpoint with revision check, reload published page]
    AH --> Q
    Y -->|Switch page or leave| AI[Run existing pending-change guard and best-effort presence removal]
    AI --> AJ([Navigate or close editor])

    X -.-> AK[Authenticated presence heartbeat every 5 seconds while active]
    AK -.-> AL[Show active viewers and editors for this page]
    AL -.-> AM[Other editors warning is advisory; local editing remains available]
```

Content comes from existing database-backed page sections and feature tables, not a separate hardcoded preview. The editor uses different adapters for homepage content, section pages, administration, departments/programs, student life, admissions/PDFs, and other supported pages. News, announcements, and events reuse their existing editors.

Both Super Admin and Content Editor currently receive `manage_page_content` in the permission map. Direct publication is additionally restricted by the role-aware submission boundary; that permission alone does not mean a Content Editor can publish directly.

## 2. Private draft, validation, and conflict recovery

```mermaid
flowchart TD
    A([Save draft or Ctrl/Cmd+S]) --> B{Draft adapter and storage available?}
    B -->|No| C[Show unavailable or disabled draft control]
    B -->|Yes| D[Serialize pending state, preview, selected media, and content baseline]
    D --> E[POST checkpoint with CSRF, page route, peer identity, and draft revision]
    E --> F{Valid login, role, CSRF, and permitted page?}
    F -->|No| G[Reject request; retain pending browser work]
    F -->|Yes| H{Normal save or explicit recovery save?}
    H -->|Normal| I[Acquire MySQL named lock and check live-content baseline and lease]
    I --> J{Baseline current and no conflicting publishing lease?}
    J -->|No| K[Return HTTP 409; preserve local work]
    K --> L{Conflict cause}
    L -->|Another publish in progress| M[Wait for it to finish and retry]
    M --> A
    L -->|Newer live content| N{Save recovery draft?}
    N -->|Yes| O[Explicitly request recovery checkpoint]
    N -->|No| P[Keep work in browser; review newer content before retrying]
    O --> H
    H -->|Recovery| Q[Require editor peer; retain stale content baseline]
    J -->|Yes| R[Validate snapshot structure, size, and own-checkpoint revision]
    Q --> R
    R --> S{Snapshot and checkpoint revision valid?}
    S -->|No| T[Reject invalid or oversized data, or return 409 for stale own draft]
    T --> G
    S -->|Yes| U[Store private snapshot and media in chunked draft tables]
    U --> V[Return next checkpoint revision and saved feedback]
    V --> W([Continue editing; public page unchanged])
    P --> W
```

- Checkpoints are scoped to the signed-in account and page/subpage route. There is no automatic save.
- Incomplete content can be saved privately. Draft media stays encoded in private checkpoint storage until the normal upload/publish handlers process it.
- A recovery draft preserves work but retains its stale baseline. It cannot override a newer publication. Reopen current content, reconcile changes, and then save or resubmit.
- Live-content version checks and same-account checkpoint revision checks solve different conflicts. Neither provides automatic merging.
- Draft storage uses `content_editor_drafts` and `content_editor_draft_chunks`; presence/version protection requires its separate metadata tables. Missing protection storage prevents visual-editor saves.

## 3. Submission, review, and direct publication

```mermaid
flowchart TD
    A([Choose primary action]) --> B{Content Editor or Super Admin?}
    B -->|Content Editor| C[Confirm Submit for approval]
    C --> D{Confirmed?}
    D -->|No| E([Continue editing])
    D -->|Yes| F[Save current private checkpoint using Diagram 2]
    F --> G{Checkpoint saved successfully?}
    G -->|No| H[Show error; retain working changes]
    H --> E
    G -->|Yes| I[Submit checkpoint revision for review]
    I --> J[Recheck authorization, CSRF, content baseline, and checkpoint revision]
    J --> K{Checks pass?}
    K -->|No| H
    K -->|Yes| L[Store pending whole-page snapshot in approval submission storage]
    L --> M[Author tracks status in My Submissions; live content unchanged]
    M --> N[Super Admin opens pending submission in Approvals]
    N --> O[Inspect proposed text, media, and saved canvas]
    O --> P{Review decision}
    P -->|Reject or request revision| Q[Record reason or feedback and review status]
    Q --> R[Author revises saved proposal and resubmits]
    R --> C
    P -->|Approve and publish snapshot| S[Open authorized saved snapshot with original baseline]
    S --> T[Run existing page publication workflow]
    B -->|Super Admin direct publication| T

    T --> U[Inspect content completeness]
    U --> V{Missing authored text or media?}
    V -->|Yes| W{Completeness warning decision}
    W -->|Keep Editing| E
    W -->|Publish Anyway| X[Set allow_incomplete for this operation]
    V -->|No| Y{Confirm publication?}
    Y -->|Keep editing| E
    Y -->|Publish changes| Z[Request current Super Admin password]
    X --> Z
    Z --> AA{Password confirmation valid?}
    AA -->|Incorrect| AB[Show error; allow retry under rate limits]
    AB --> Z
    AA -->|Cancelled| E
    AA -->|Yes| AC[Issue short-lived publication confirmation token]
    AC --> AD[Send placement, background, media, and adapter-specific save requests]
    AD --> AE[Server rechecks identity, authorization, CSRF, token, and input validity]
    AE --> AF[Serialize guarded writes; verify saved baseline and publishing lease]
    AF --> AG{Request valid and version current?}
    AG -->|No| AH[Reject write; report validation, permission, or conflict error]
    AG -->|Yes| AI[Reserve content revision; execute existing feature handler]
    AI --> AJ{All required writes complete?}
    AJ -->|More requests| AD
    AJ -->|Failure or partial completion| AH
    AH --> AK[Retain remaining work; some earlier requests may already be committed]
    AK --> AL[Release lease and confirmation; clear operation flags]
    AL --> E
    AJ -->|Success| AM{Publishing a reviewed snapshot?}
    AM -->|Yes| AN[Complete pending approval and record approval/publication activity]
    AM -->|No| AO[Record page publication summary for Recent Content where applicable]
    AN --> AO
    AO --> AP[Clear completed private checkpoint and refresh preview as needed]
    AP --> AQ[Release lease and confirmation; clear incomplete-content override]
    AQ --> AR([Published content available to visitors])
```

The completeness warning can permit missing authored content; it does not bypass URL/type/length validation, upload safety, authentication, authorization, CSRF, publication confirmation, record integrity, or conflict protection. A stale proposal must be reviewed and resubmitted against current content before approval can succeed.

Publishing can use several requests. Existing per-handler transactions remain in place, but the complete page operation is not one database transaction. Partial success can change live content even if a later request fails. Successful writes establish a new local history baseline; Undo cannot reverse a committed server action. Approval/history recording errors must also be inspected separately from whether content writes already succeeded.

## 4. Embedded forms and independent managers

```mermaid
flowchart TD
    A([Open embedded post editor or independent manager]) --> B[Use existing News, Announcements, Events, or feature form]
    B --> C[Enter changes and choose existing save/create/delete action]
    C --> D[POST to allowlisted original handler]
    D --> E[Check active account, role, method, CSRF, ownership, and action validation]
    E --> F{Allowed?}
    F -->|No| G[Reject and show error]
    F -->|Yes| H{Super Admin?}
    H -->|Yes| I[Run applicable password/publication confirmation and conflict checks]
    I --> J[Execute original persistence handler]
    J --> K[Return result and audit according to existing feature behavior]
    H -->|No: Content Editor proposal| L[Validate and stage submitted fields and uploads privately]
    L --> M{Draft intent?}
    M -->|Yes| N[Keep proposal Draft]
    M -->|No| O[Mark proposal Pending]
    N --> P[Author revises and submits]
    P --> O
    O --> Q[Super Admin reviews in Approvals]
    Q --> R{Decision}
    R -->|Reject or request revision| S[Record reason and return to author]
    S --> P
    R -->|Approve| T[Confirm review and mark Processing]
    T --> U[Replay allowlisted original handler using short-lived one-use reviewer session]
    U --> V[Revalidate authorization, CSRF, input, uploads, and content conflicts]
    V --> W{Execution result}
    W -->|Confirmed success| X[Record Approved and audit]
    W -->|Authentication failure| Y[Report failure]
    W -->|Timeout or unconfirmed result| Z[Uncertain, or Processing after crash; inspect actual content]
    Z --> AA([No automatic retry])
```

These forms retain their existing behavior. Save Draft in the visual canvas does not stage or undo independent manager actions that have already committed. Pending form proposals and whole-page snapshot submissions are distinct from private visual-editor checkpoints. Legacy page-text review also remains supported: reviewers compare current/proposed sections, approve with confirmation or request changes, and section-version checks prevent stale decisions.

## Implementation references

| Responsibility | Source |
| --- | --- |
| Editor entry, permitted panels, real-page preview, configuration | [`admin/content-edit.php`](../admin/content-edit.php) |
| Permission map and section content functions | [`admin/admin-functions.php`](../admin/admin-functions.php) |
| Role-aware route boundary | [`includes/admin-rbac.php`](../includes/admin-rbac.php) |
| Canvas adapters, confirmation, publish lifecycle | [`assets/js/content-edit.js`](../assets/js/content-edit.js) |
| Private draft save/restore/submit | [`assets/js/editor-drafts.js`](../assets/js/editor-drafts.js), [`admin/action/editor-draft-handler.php`](../admin/action/editor-draft-handler.php), [`includes/content-editor-drafts.php`](../includes/content-editor-drafts.php) |
| Presence, versions, locks, leases | [`includes/editor-presence.php`](../includes/editor-presence.php), [`assets/js/editor-presence.js`](../assets/js/editor-presence.js) |
| Local undo/redo | [`assets/js/editor-history.js`](../assets/js/editor-history.js) |
| Password-confirmed publishing | [`assets/js/admin-publish-confirmation.js`](../assets/js/admin-publish-confirmation.js), [`includes/publish-confirmation.php`](../includes/publish-confirmation.php) |
| Completeness policy | [`includes/content-publish-policy.php`](../includes/content-publish-policy.php) |
| Proposal storage/replay and review presentation | [`includes/content-submissions.php`](../includes/content-submissions.php), [`admin/approval-view.php`](../admin/approval-view.php) |

Related documentation: [drafts](content-editor-drafts.md), [publishing](content-editor-publishing.md), [presence/conflicts](content-editor-presence.md), [undo/redo](content-editor-history.md), and [roles/approvals](admin-access-and-approvals.md).

Verification: traced against the source files and existing workflow documentation. These are documentation diagrams, not evidence of an end-to-end runtime test. No application code, database records, or configuration were changed.
