# 12 — Mobile: request timeout and unpurchased-grant message

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first, plus the Notes / handoff of tickets 01–09 in this folder; these follow-ups close their open items (decided 2026-10-09).

**What to build:** Remaining edges from 07/09.

**Blocked by:** None — can start immediately

**Branch:** `fix/subscription-hardening` in `front-end`. Work in `front-end/apps/mobile` (and `packages/shared` where needed); read its `CLAUDE.md` first.

**Status:** ready-for-agent

- [ ] The shared HTTP layer aborts requests after 15 s with a recognisable timeout error, so the post-purchase wait stays ≈10 s plus at most one request.
- [ ] A purchase that completes but RevenueCat does not grant `app_access` shows "Purchase didn't go through — try Restore." instead of nothing.
- [ ] The duplicated `sub()` subscription test fixture is one shared test helper.
- [ ] `pnpm test` and typecheck (no new errors over baseline) green.
