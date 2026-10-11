# 08 — Mobile: recover from `subscription_required` by syncing

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** A paying user whose backend is behind is repaired quietly instead of being sent to the paywall.

**Blocked by:** 04

**Branch:** `fix/subscription-hardening` in `front-end` (off `dev`). Work in `front-end/apps/mobile`; read its `CLAUDE.md` first. The back-end tickets it needs are already on the back-end branch.

**Status:** done

- [x] On a 403 `subscription_required`, if RevenueCat reports `app_access`, call sync once and refetch before routing to the paywall; otherwise route to the paywall as today.
- [x] No sync loop: at most one sync per recovery.
- [x] Unit-tested (prior art: `gate.test.ts`). `pnpm test` and typecheck green.

## Notes / handoff

front-end `b2d7059`, `52229d8`. `createSubscriptionRecovery` (apps/mobile/src/lib/subscriptionRecovery.ts); concurrent 403s share one run, 60 s cooldown after a sync. Open: if the backend still refuses after sync while RevenueCat grants, the user stays on Tabs with 403s (the gate trusts either side); a 403'd mutation is not retried.
