# 03 — Webhook robustness: retries, lookup, event data

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** Webhooks heal themselves and land on the right user with the right data.

**Blocked by:** 01

**Branch:** `fix/subscription-hardening` in `back-end` (off `dev`; spec and tickets are committed here too).

**Status:** done

- [x] Retries keep 5 tries with growing delays (≈1 min, 5 min, 30 min, 1 h).
- [x] User resolution checks `app_user_id`, then `original_app_user_id`, then aliases, in that order; numeric ids only. Tested with conflicting ids.
- [x] PRODUCT_CHANGE records the new product and does not clear a pending cancellation.
- [x] Cancellation records the event's time, not processing time.
- [x] The frozen acquisition partner is never overwritten, including by INITIAL_PURCHASE on an existing row.
- [x] TRANSFER keeps the receiving user's subscription when it grants access and the transferred one does not.
- [x] `composer test` green.

## Notes / handoff

- **Retries:** `ProcessRevenueCatWebhook::backoff()` = `[60, 300, 1800, 3600]`, `$tries = 5` (4 retries, ~1 h 36 min in all).
- **Lookup:** `resolveUser()` → `firstRegistered()`: first registered id in the order app_user_id, original_app_user_id, aliases (numeric only). A numeric app_user_id with no account falls through to the next id. TRANSFER's `transferred_to` uses the same ordered lookup.
- **PRODUCT_CHANGE:** `SubscriptionState::productChanged()` writes `new_product_id` (falls back to `product_id` when RevenueCat omits it, e.g. an immediate Play change) and a reported expiry; keeps status, `cancelled_at`, period type, original purchase time and known price. `SubscriptionState::$status` null now means "keep the row's status" (extension or product change); a new row from a product change is active. The new product is recorded at the event even when the store applies it at renewal; harmless while every product maps to `app_access`.
- **Cancellation / refund:** stamped with `event_timestamp_ms` (processing time only if absent).
- **Acquisition Partner:** re-stamp removed from `SubscriptionRecord::fillPurchase()`; set only when the row is created.
- **TRANSFER:** `SubscriptionRecord::transfer()` returns null and moves nothing when the receiver's row `isActive()` and the transferred one is not; the transferred (inactive) row stays on the source user.
- **Left as is (pre-existing):** TRANSFER moves only the first source row and does not touch `last_event_at_ms`, so the moved row keeps the source's mark. PRODUCT_CHANGE on an expired row keeps it expired.
- Tests: `RevenueCatWebhookTest` section "Webhook robustness (026/03)" (13).
