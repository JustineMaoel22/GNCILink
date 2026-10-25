# Codex Instructions — GNCI Website

This project is a GNCI school website with public content and administrative editing. **Inspect the repository before making assumptions.** The actual codebase is the source of truth.

Before making changes, read:
- [`architecture.md`](./architecture.md) for architecture, CMS data flow, and change boundaries.
- [`security.md`](./security.md) for authentication, SQL, uploads, APIs, and production safety.
- [`designsystem.md`](./designsystem.md) for established UI patterns and visual consistency.

For GNCI implementation/debugging tasks, use the reusable skill in `.agents/skills/gnci-development/SKILL.md` when available.

## Always
- Follow the exact user request; don't redesign when asked only for consistency.
- Prefer minimal changes to existing files and reusable components.
- Preserve current functionality, public links, data, and admin editability.
- Confirm whether content is database-managed before hardcoding anything.
- Verify server-side authorization and validation for state-changing actions.
- Never expose secrets, perform unapproved destructive database changes, or claim unrun tests passed.
- Summarize changed files, verification, and known risks when finished.
