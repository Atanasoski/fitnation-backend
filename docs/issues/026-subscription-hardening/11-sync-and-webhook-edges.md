# 11 — Sync and webhook edge cases

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first, plus the Notes / handoff of tickets 01–09 in this folder; these follow-ups close their open items (decided 2026-10-09).

**What to build:** Remaining correctness gaps from 02–04.

**Blocked by:** 10 (both touch subscription/access code; run after it)

**Branch:** `fix/subscription-hardening` in `back-end`.

**Status:** done

- [x] Sync maps a paused Google Play subscription (RevenueCat REST `auto_resume_date` / pause fields — verify in docs) to Paused, consistent with the webhook's SUBSCRIPTION_PAUSED handling.
- [x] TRANSFER moves every subscription row of the source users and carries the stale-event high-water mark so an older event for the receiver can't undo it.
- [x] PRODUCT_CHANGE on an expired subscription leaves it expired — locked with a test (no behaviour change).
- [x] `composer test` green.

## Notes / handoff

- **Paused sync:** `SubscriptionSync::stateOf()` maps a non-null `auto_resume_date` (REST v1 subscriptions entry; mirrors Play's `autoResumeTimeMillis`, "only present if the user has requested to pause") to `paused`, access to `expires_date`. Precedence: refund → past expiry (expired, so a pause already in effect reads expired, like the webhook's EXPIRATION) → billing issue → cancelled (auto-renew off wins over a pause) → paused → active. Row equals the one INITIAL_PURCHASE + SUBSCRIPTION_PAUSED leave. RevenueCat docs don't say verbatim whether `auto_resume_date` is set while the pause is only scheduled (SDK says "currently paused"); worth a sandbox check on Play.
- **TRANSFER:** `SubscriptionRecord::transfer($fromUserIds, $to, ?int $eventAtMs)`. Every source row leaves its user: the best one (grants access, then latest expiry) moves to `$to`, the rest and `$to`'s own are deleted. The moved row's `last_event_at_ms` = max of every replaced row's mark and the TRANSFER's `event_timestamp_ms`. Keep-receiver case unchanged (nothing moves, source rows stay, all inactive).
- **PRODUCT_CHANGE on expired:** locked by `test_a_product_change_on_an_expired_subscription_leaves_it_expired` (committed alone, green on unchanged code).
- **Open:** a late, older purchase event for a *source* user after a TRANSFER finds no row and recreates one (mark only carried to the receiver). The TRANSFER itself is not stale-checked. Flaky pre-existing test: `Admin/AccessSourcesTest` occasionally hits `partners_domain_unique` (PartnerFactory faker domain collision).
- Tests: `SubscriptionSyncTest` (+3 paused), `RevenueCatWebhookTest` (+4: product change, 3 transfer).
