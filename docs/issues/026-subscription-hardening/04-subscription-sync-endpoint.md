# 04 — Sync endpoint pulls a user's subscription from RevenueCat

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** `POST /api/subscription/sync` lets the app repair a user's backend subscription on demand: it fetches the subscriber from the RevenueCat REST API, reads the `app_access` entitlement and its subscription, applies it through the module from 01, and answers with the same payload as `GET /user`.

**Blocked by:** 01

**Branch:** `fix/subscription-hardening` in `back-end` (off `dev`; spec and tickets are committed here too).

**Status:** ready-for-agent

- [ ] Authenticated, reachable while blocked by the subscription middleware, throttled per user.
- [ ] RevenueCat REST faked with `Http::fake()`; a webhook and a sync describing the same state leave the same row.
- [ ] A sync counts as current: an older webhook processed afterwards does not undo it.
- [ ] Sandbox subscriptions are ignored in production.
- [ ] Without `REVENUECAT_SECRET_API_KEY` it answers a server error, logs, and leaves the row untouched; the key is documented in `.env.example`.
- [ ] Documented in `API_DOCUMENTATION.md` next to the subscription section.
- [ ] `composer test` green.
