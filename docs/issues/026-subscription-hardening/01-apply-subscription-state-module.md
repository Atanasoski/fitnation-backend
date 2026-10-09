# 01 — Prefactor: one module applies subscription state

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** Behaviour-preserving refactor. Every RevenueCat webhook handler writes the user's subscription through one "apply subscription state" module that owns product/store mapping, period type, expiry, cancelled-at, billing-issue state, the stale-event high-water mark, and never overwriting the frozen acquisition partner. Its interface must also fit a REST snapshot (ticket 04): a state plus an optional event time, not a raw webhook event. Make the change easy for 02–04; change no behaviour.

**Blocked by:** None — can start immediately

**Branch:** `fix/subscription-hardening` in `back-end` (off `dev`; spec and tickets are committed here too).

**Status:** ready-for-agent

- [ ] Existing RevenueCat, subscription, entitlement and middleware tests pass unchanged (no test edits other than additions).
- [ ] Webhook handlers no longer write subscription rows directly.
- [ ] `composer test` green; `./vendor/bin/pint` clean.
