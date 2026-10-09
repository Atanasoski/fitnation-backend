# 11 — Sync and webhook edge cases

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first, plus the Notes / handoff of tickets 01–09 in this folder; these follow-ups close their open items (decided 2026-10-09).

**What to build:** Remaining correctness gaps from 02–04.

**Blocked by:** 10 (both touch subscription/access code; run after it)

**Branch:** `fix/subscription-hardening` in `back-end`.

**Status:** ready-for-agent

- [ ] Sync maps a paused Google Play subscription (RevenueCat REST `auto_resume_date` / pause fields — verify in docs) to Paused, consistent with the webhook's SUBSCRIPTION_PAUSED handling.
- [ ] TRANSFER moves every subscription row of the source users and carries the stale-event high-water mark so an older event for the receiver can't undo it.
- [ ] PRODUCT_CHANGE on an expired subscription leaves it expired — locked with a test (no behaviour change).
- [ ] `composer test` green.
