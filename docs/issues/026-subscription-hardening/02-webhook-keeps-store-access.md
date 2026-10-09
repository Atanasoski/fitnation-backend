# 02 — Webhook keeps access as long as the store does

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** With enforcement on, a subscriber with a billing issue keeps access until the store's grace-period end; a subscription the store extended (SUBSCRIPTION_EXTENDED) or a RevenueCat TEMPORARY_ENTITLEMENT_GRANT grants access until the event's expiration.

**Blocked by:** 01

**Branch:** `fix/subscription-hardening` in `back-end` (off `dev`; spec and tickets are committed here too).

**Status:** ready-for-agent

- [ ] BILLING_ISSUE sets the expiry to the event's grace-period end when present, else its expiration; access inside the window, none after (tested through the webhook + a gated route).
- [ ] SUBSCRIPTION_EXTENDED and TEMPORARY_ENTITLEMENT_GRANT move the expiry to the event's expiration.
- [ ] Stale-event rules still apply to these events.
- [ ] `composer test` green.
