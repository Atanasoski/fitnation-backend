# 04 — Access Source on the Users list

**Parent:** [022 — Super-admin panel v1](../022-super-admin-panel.md). Terms: [Access Source](../../../CONTEXT.md#access-source), [Complimentary Access](../../../CONTEXT.md#complimentary-access), [Sponsoring Partner](../../../CONTEXT.md#sponsoring-partner).

**What to build:** Each row on the Users list shows an Access Source chip (Subscribed, Trial, Cancelled paid until, Billing issue, Paused, Sponsored, Complimentary, None) and the list filters by it via `?access=`. Behind it, an Access Source module with the same two faces as Activity Status — per user (label plus the facts for a detail line) and a SQL query constraint — read off the real rules, ignoring `SUBSCRIPTIONS_ENFORCED`.

**Blocked by:** 03.

**Status:** ready-for-agent

- [ ] Precedence: subscription states, then Sponsored, then Complimentary, else None; subscription states only while `expires_at` is in the future (matches `Subscription::isActive()`)
- [ ] Agreement test like 03's, including expiry just past / just ahead for subscriptions, sponsorships and Complimentary Access
- [ ] Label is identical with `subscriptions.enforced` on and off
- [ ] Precedence cases: sponsored + active subscription → Subscribed; Complimentary + active subscription → Subscribed
- [ ] `User::entitlements()` and API behaviour unchanged (existing entitlement tests stay green); shared predicates factored, not duplicated
- [ ] Detail facts available per user (product/period, renew/expiry date, sponsor partner, Complimentary until)
