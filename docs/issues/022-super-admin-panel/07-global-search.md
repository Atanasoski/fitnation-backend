# 07 — Global search (⌘K)

**Parent:** [022 — Super-admin panel v1](../022-super-admin-panel.md).

**What to build:** From any super-admin page, ⌘K / Ctrl-K or the header search button opens a palette. Typing finds users by name or email (each with Activity and Access chips), partners by name, and pages by title. Arrow keys move, Enter opens. Opening a user goes to the user page.

**Blocked by:** 04.

**Status:** ready-for-agent

- [x] Search endpoint returns up to ~8 users, partners and matching pages; admin-only (403 otherwise)
- [x] Users matched by partial name and email; admin accounts excluded
- [x] User results include both chips
- [x] Keyboard: open shortcut, arrows, Enter, Escape
- [x] Feature tests on the endpoint; palette behaviour checked manually against prototype variant A
  (endpoint tests done in `tests/Feature/Admin/GlobalSearchTest.php`; checked in the browser 2026-10-05: search, chips, Enter to open, Esc; dark-mode contrast fixed in ecf62e0)
