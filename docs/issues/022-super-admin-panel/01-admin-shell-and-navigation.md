# 01 — Admin shell and navigation

**Parent:** [022 — Super-admin panel v1](../022-super-admin-panel.md). Read it first; vocabulary from `CONTEXT.md`.

**What to build:** A super admin logs in and sees the new left sidebar — Overview · Users · Partners · Content (group: Exercises, Workout Splits, Generator Preview) · Insights · System — on the existing TailAdmin shell and brand tokens. Every super-admin page lives under `/admin` behind an admin-role gate. Overview, Insights ("coming soon") and System are placeholder pages for now; Users and Partners point at their current pages until later tickets replace them. Partner admins see their menu exactly as today.

**Blocked by:** None — can start immediately. Branch off `origin/dev` (fetch first); first commit = the uncommitted `CONTEXT.md` glossary additions and the 022 docs.

**Status:** ready-for-agent

- [x] Sidebar shows the six items for admins, with Content as an expandable group; active item highlighted
- [x] Exercises, Workout Splits and Generator Preview reachable under Content with no behaviour change
- [x] An admin-only gate protects every `/admin` super-admin route; a partner admin and a plain user get 403 (feature test)
- [x] `/dashboard` sends admins to the new Overview placeholder and partner admins to their dashboard as today
- [x] Partner-admin navigation unchanged (feature test)
- [ ] Unused TailAdmin demo components (ecommerce widgets, example tables) deleted; nothing references them
- [x] `composer test` green; pint on touched files
