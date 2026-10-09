# 07 — Mobile: purchase and restore flow syncs the backend

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** A purchase or restore can't go unattributed and the user enters the app only once the backend agrees (or after a short wait).

**Blocked by:** 04

**Branch:** `fix/subscription-hardening` in `front-end` (off `dev`). Work in `front-end/apps/mobile`; read its `CLAUDE.md` first. The back-end tickets it needs are already on the back-end branch.

**Status:** done

- [x] One purchase-flow module with RevenueCat, sync and `/user` injected, unit-tested (prior art: `paywall-restores-owned-purchase.test.ts`).
- [x] Before purchasing: RevenueCat's user id must equal the backend user id; if not, log in again once; still mismatched → "try again" error and no purchase.
- [x] After purchase: call `POST /api/subscription/sync`, then poll `/user` until it carries `app_access`, up to ≈10 s, then enter regardless.
- [x] Restore follows the same flow minus the purchase.
- [x] `pnpm test` and typecheck green.

## Notes / handoff

front-end `a956f48`, `55b7656`. `runPurchaseFlow` (apps/mobile/src/lib/purchaseFlow.ts), `authApi.syncSubscription()`, `revenueCatIdentity`. Open: the HTTP layer has no timeout, so a hung request can stretch the ≈10 s wait; a purchase RevenueCat doesn't grant still shows no message.
