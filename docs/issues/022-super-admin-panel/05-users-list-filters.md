# 05 — Remaining Users list filters

**Parent:** [022 — Super-admin panel v1](../022-super-admin-panel.md).

**What to build:** The Users list gains the rest of its filters and controls, all as URL query parameters so any link can open a filtered list: fitness goal, training experience, Device platform, sign-in method (social / password), stuck session, deleted, text search on name/email, and sort by signup or last Completed Session.

**Blocked by:** 03.

**Status:** ready-for-agent

- [x] Each filter narrows correctly (one feature test per filter) and combines with the others
- [x] Platform = users with a Device on that platform
- [x] Stuck session = user has a session with status `active` started more than 24h ago
- [x] Text search matches name or email
- [x] Sort by signup and by last Completed Session, both directions
- [x] Filters round-trip: the URL reproduces the same list; pagination keeps them
