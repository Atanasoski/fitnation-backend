# 12 — Mobile: request timeout and unpurchased-grant message

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first, plus the Notes / handoff of tickets 01–09 in this folder; these follow-ups close their open items (decided 2026-10-09).

**What to build:** Remaining edges from 07/09.

**Blocked by:** None — can start immediately

**Branch:** `fix/subscription-hardening` in `front-end`. Work in `front-end/apps/mobile` (and `packages/shared` where needed); read its `CLAUDE.md` first.

**Status:** done

- [x] The shared HTTP layer aborts requests after 15 s with a recognisable timeout error, so the post-purchase wait stays ≈10 s plus at most one request.
- [x] A purchase that completes but RevenueCat does not grant `app_access` shows "Purchase didn't go through — try Restore." instead of nothing.
- [x] The duplicated `sub()` subscription test fixture is one shared test helper.
- [x] `pnpm test` and typecheck (no new errors over baseline) green.

## Notes / handoff

front-end `691c514`, `56a0d29`, `1bc00e9`, `400da5a`, `dfc9a62`. 15 s default timeout (`'timeout'` failure kind), uploads 120 s, per-request `timeoutMs`; sync + `/user` poll share one ≈10 s budget; `purchaseOutcomeMessage`; shared `sub()` in `apps/mobile/src/test/fixtures.ts`.
