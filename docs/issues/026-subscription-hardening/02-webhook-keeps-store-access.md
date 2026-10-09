# 02 — Webhook keeps access as long as the store does

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** With enforcement on, a subscriber with a billing issue keeps access until the store's grace-period end; a subscription the store extended (SUBSCRIPTION_EXTENDED) or a RevenueCat TEMPORARY_ENTITLEMENT_GRANT grants access until the event's expiration.

**Blocked by:** 01

**Branch:** `fix/subscription-hardening` in `back-end` (off `dev`; spec and tickets are committed here too).

**Status:** done

- [x] BILLING_ISSUE sets the expiry to the event's grace-period end when present, else its expiration; access inside the window, none after (tested through the webhook + a gated route).
- [x] SUBSCRIPTION_EXTENDED and TEMPORARY_ENTITLEMENT_GRANT move the expiry to the event's expiration.
- [x] Stale-event rules still apply to these events.
- [x] `composer test` green.

## Notes / handoff

- **Webhook** (`ProcessRevenueCatWebhook::stateFor()`): BILLING_ISSUE → `SubscriptionState::billingIssue(grace_period_expiration_at_ms ?? expiration_at_ms)` (null leaves the expiry as is), same reading as the sync. `EXTENSION_EVENTS` (SUBSCRIPTION_EXTENDED, TEMPORARY_ENTITLEMENT_GRANT) → `extendedState()` → `SubscriptionState::extended($until)`.
- **Extension rule** (in `SubscriptionRecord::extend()`): only pushes `expires_at` later, reopens an `expired` row as `active`, keeps any other status. `SubscriptionState::$status` is null only for an extension.
- **Temporary grant:** RevenueCat's sample payload has no `expiration_at_ms`/`product_id`; without an expiration it runs 24 h from `event_timestamp_ms`. With **no row yet** (outage during a first purchase) it is a no-op: a status change never creates a row. The app's sync (07) / later INITIAL_PURCHASE covers it; accepted.
- **For 03:** stale-event rules untouched (all go through `apply()`). Cancellation time still `now()` in `stateFor()`. PRODUCT_CHANGE still `renewed()`. Partner re-stamp still in `fillPurchase()`.
- Glossary: Billing Retry, Subscription Extension in `CONTEXT.md`.
- Tests: `RevenueCatWebhookTest` section "Access lasts as long as the store says (026/02)" (9), webhook → `/api/muscle-groups` with `travel()`. Its `assertGate()` uses `$user->fresh()` — `actingAs` reuses the instance and its loaded subscription goes stale.
