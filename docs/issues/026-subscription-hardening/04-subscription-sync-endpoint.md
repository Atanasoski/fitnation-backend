# 04 — Sync endpoint pulls a user's subscription from RevenueCat

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** `POST /api/subscription/sync` lets the app repair a user's backend subscription on demand: it fetches the subscriber from the RevenueCat REST API, reads the `app_access` entitlement and its subscription, applies it through the module from 01, and answers with the same payload as `GET /user`.

**Blocked by:** 01

**Branch:** `fix/subscription-hardening` in `back-end` (off `dev`; spec and tickets are committed here too).

**Status:** done

- [x] Authenticated, reachable while blocked by the subscription middleware, throttled per user.
- [x] RevenueCat REST faked with `Http::fake()`; a webhook and a sync describing the same state leave the same row.
- [x] A sync counts as current: an older webhook processed afterwards does not undo it.
- [x] Sandbox subscriptions are ignored in production.
- [x] Without `REVENUECAT_SECRET_API_KEY` it answers a server error, logs, and leaves the row untouched; the key is documented in `.env.example`.
- [x] Documented in `API_DOCUMENTATION.md` next to the subscription section.
- [x] `composer test` green.

## Notes / handoff

- **Endpoint (for 07/08):** `POST /api/subscription/sync`, `auth:sanctum`, no body, **not** behind `RequiresSubscription`. Throttle `throttle:10,1` (10/min per user) → `429`.
  - `200` → `{ user: UserResource }`, identical to `GET /api/user` (both load `UserResource::RELATIONS`). Read `user.entitlements` as usual; no need to refetch `/user` after it.
  - `500 { message, code: "subscription_sync_not_configured" }` — no `REVENUECAT_SECRET_API_KEY` on the server (logged as error).
  - `502 { message, code: "subscription_sync_failed" }` — RevenueCat unreachable or non-2xx (logged). Retryable.
  - Nothing is written on any error. A subscriber with no `app_access` entitlement, a non-App-Store/Play store (e.g. RevenueCat promotional), or a sandbox purchase in production → `200`, row unchanged.
- **Module:** `App\Services\Subscription\SubscriptionSync::run(User)` (throws `SubscriptionSyncFailed`, carrying `reason`/`status`). Calls `GET {services.revenuecat.base_url}/subscribers/{users.id}` with `Bearer services.revenuecat.secret_api_key`, takes `entitlements.app_access.product_identifier` → `subscriptions[that]`, applies `SubscriptionState::snapshot(...)` with `now()` ms as the event time.
- **Snapshot mapping:** refunded_at → expired (expires/cancelled = refunded_at); past expiry → expired; billing issue → `billing_issue` with expiry = `grace_period_expires_date ?? expires_date`; `unsubscribe_detected_at` → cancelled; else active. `purchased_at` = `original_purchase_date`. Environment from `is_sandbox`.
- **Price:** REST reports no USD price, so a sync never writes price/currency (keeps the known one). `SubscriptionRecord::apply()` now lets a *stale* event still fill a NULL price, so a sync that beat INITIAL_PURCHASE gets its price from that late webhook.
- **Known divergence until 02/03 land:** webhook BILLING_ISSUE doesn't move expiry and webhook refund stamps `now()`; the sync already does both the spec's way. Paused Play subs: REST v1 has no pause status, so they sync as active/expired by date.
- Tests: `tests/Feature/SubscriptionSyncTest.php` (16). Glossary: "Subscription Sync" in `CONTEXT.md`.
