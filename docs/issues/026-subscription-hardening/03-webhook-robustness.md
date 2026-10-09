# 03 — Webhook robustness: retries, lookup, event data

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** Webhooks heal themselves and land on the right user with the right data.

**Blocked by:** 01

**Branch:** `fix/subscription-hardening` in `back-end` (off `dev`; spec and tickets are committed here too).

**Status:** ready-for-agent

- [ ] Retries keep 5 tries with growing delays (≈1 min, 5 min, 30 min, 1 h).
- [ ] User resolution checks `app_user_id`, then `original_app_user_id`, then aliases, in that order; numeric ids only. Tested with conflicting ids.
- [ ] PRODUCT_CHANGE records the new product and does not clear a pending cancellation.
- [ ] Cancellation records the event's time, not processing time.
- [ ] The frozen acquisition partner is never overwritten, including by INITIAL_PURCHASE on an existing row.
- [ ] TRANSFER keeps the receiving user's subscription when it grants access and the transferred one does not.
- [ ] `composer test` green.
