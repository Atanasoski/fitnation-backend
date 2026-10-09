# 01 — Prefactor: one module applies subscription state

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** Behaviour-preserving refactor. Every RevenueCat webhook handler writes the user's subscription through one "apply subscription state" module that owns product/store mapping, period type, expiry, cancelled-at, billing-issue state, the stale-event high-water mark, and never overwriting the frozen acquisition partner. Its interface must also fit a REST snapshot (ticket 04): a state plus an optional event time, not a raw webhook event. Make the change easy for 02–04; change no behaviour.

**Blocked by:** None — can start immediately

**Branch:** `fix/subscription-hardening` in `back-end` (off `dev`; spec and tickets are committed here too).

**Status:** done

- [x] Existing RevenueCat, subscription, entitlement and middleware tests pass unchanged (no test edits other than additions).
- [x] Webhook handlers no longer write subscription rows directly.
- [x] `composer test` green; `./vendor/bin/pint` clean.

## Notes / handoff

- **Module:** `app/Services/Subscription/`.
  - `SubscriptionRecord::apply(User $user, SubscriptionState $state, ?int $eventAtMs = null): bool` — the only writer of a user's subscription row. Owns the stale-event high-water mark (`<=` last `last_event_at_ms` → returns `false`, writes nothing), row creation (only a purchase state creates one; status changes with no row are a no-op), the acquisition partner, and the renewal merge (keeps original `purchased_at`, keeps price/currency when unreported). One transaction.
  - `SubscriptionRecord::noteEvent(User, ?int $eventAtMs): bool` — moves the mark only (PRICE_CHANGE, unknown types).
  - `SubscriptionRecord::transfer(array $fromUserIds, User $to): ?Subscription` — TRANSFER's re-point + delete of the target's superseded row. **03** changes its keep-the-active-receiver rule here.
  - `SubscriptionState` — immutable value: `purchased(...)`, `renewed(...)`, `cancelled($at)`, `refunded($at)`, `uncancelled()`, `expired()`, `billingIssue()`, `paused()`; `SubscriptionState::store(?string)` maps `APP_STORE`/`app_store` etc. (null = not a store we sell through). Internally a state is status + optional purchase columns + optional `expires_at`/`cancelled_at`, so any combination is one private-constructor call away.
- **For 02:** give `billingIssue()` an expiry (grace-period end ?? expiration) and add `extended($expiresAt)` (status unchanged? decide — today every state carries a status) — both just set `dates['expires_at']`.
- **For 03:** the partner re-stamp on INITIAL_PURCHASE over an existing row is kept on purpose, in `SubscriptionRecord::fillPurchase()` (comment names 026/03) — delete those lines. Cancellation time: pass the event time to `cancelled()`/`refunded()` instead of `now()` (webhook `stateFor()`). PRODUCT_CHANGE currently uses `renewed()`, which clears `cancelled_at` and sets Active; needs its own factory.
- **For 04:** the factories are webhook-event shaped. A REST snapshot (e.g. active trial with auto-renew off, or billing issue) needs a new `SubscriptionState::snapshot(...)` factory taking status + purchase fields + `cancelledAt`, rather than two `apply()` calls (the second would be stale at the same ms). Pass `now()->getTimestampMs()` as the event time so it moves the mark (docblock on `SubscriptionRecord` says so).
- **Tiny drift, accepted:** status events now save through the model, so `updated_at` is not bumped when nothing changed; price is typed `?float` (a non-numeric price string would now throw and retry).
- Characterization tests for the merge rules: `RevenueCatWebhookTest` (`test_renewal_keeps_the_known_price_original_purchase_and_partner` and following), committed green before the refactor in `0249f59`.

