# 13 — Mobile: after a successful sync the backend decides

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first, plus the Notes / handoff of tickets 01–09 in this folder; these follow-ups close their open items (decided 2026-10-09).

**What to build:** When RevenueCat says paid but a successful sync still returns no `app_access`, the backend's answer wins: the user goes to the paywall (with Restore) instead of staying on Tabs with errors. An action that hit `subscription_required` during recovery shows a plain "Something went wrong — try again" and is never retried automatically.

**Blocked by:** 12 (same files in the shared HTTP layer / gate)

**Branch:** `fix/subscription-hardening` in `front-end`. Work in `front-end/apps/mobile` (and `packages/shared` where needed); read its `CLAUDE.md` first.

**Status:** ready-for-agent

- [ ] After a successful sync without `app_access`, the gate routes to the paywall even though RevenueCat's cached customerInfo grants access, until RevenueCat or the backend changes (a later purchase/restore or a new `/user` with access lifts it).
- [ ] A failed sync (500/502/429/network) keeps today's fallback.
- [ ] A mutation that got `subscription_required` surfaces a retryable error; no automatic retry.
- [ ] Unit-tested at the recovery/gate modules (prior art: `subscriptionRecovery` tests, `gate.test.ts`). `pnpm test` and typecheck green.
