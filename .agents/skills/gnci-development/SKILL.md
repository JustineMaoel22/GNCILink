---
name: gnci-development
description: Implement, debug, review, and maintain the GNCI PHP/MySQL school website while preserving existing functionality, admin editability, security, and a consistent design system. Use for GNCI website code and UI requests.
---

# GNCI Development Skill

## Before editing
1. Read the repository `AGENTS.md` and the relevant sections of `architecture.md`, `security.md`, and `designsystem.md` from the repository root.
2. Inspect existing files, routes, templates, SQL usage, and CSS for the requested feature.
3. Determine which content is dynamic or managed by the admin.
4. Identify constraints: hosting PHP version, SQL engine, enabled extensions, and production compatibility.
5. If instructions contradict actual code, treat the code as current implementation and flag the discrepancy.

## Implement changes
1. Follow the user's scope exactly: a consistency request is not authorization for redesign.
2. Minimize diffs; reuse existing files and components before adding new ones.
3. Preserve all existing functionality, database relationships, page URLs, published content, and user permissions unless a change is explicitly requested.
4. Avoid hardcoded records for admin-managed collections. Make new content use shared templates.
5. Include server-side input validation, authorization, CSRF protection, and safe SQL where applicable.
6. Do not silently perform production migrations or destructive data operations.
7. Keep CSS tokens and responsive patterns consistent with established pages.

## Test changes
- Run available syntax/lint/test commands relevant to touched files; never invent a test runner.
- For PHP, use `php -l <file>` when PHP CLI is available.
- Test create/read/update/delete paths if the feature supports them.
- Test invalid inputs, unauthorized access, empty optional content, and mobile behavior when relevant.
- Check for regressions in shared navbar, visual editor, dynamic program/department pages, and public site.

## Response format
Report: (1) what changed, (2) which files changed, (3) how it was verified, (4) any migration/setup step, and (5) outstanding limitations. Distinguish tests run from tests recommended.

## Strict prohibitions
- Do not fabricate repository facts or test results.
- Do not expose secrets or weaken security to fix a deployment issue.
- Do not delete or rebuild databases/tables without explicit approval and verified rollback.
- Do not replace existing layout with a new design unless requested.
- Do not modify unrelated files or introduce dependencies without clear need.
