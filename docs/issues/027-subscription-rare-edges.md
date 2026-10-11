# 027 — Subscription rare edge cases (backlog)

**Area:** back-end (RevenueCat webhook, sync) + mobile (access gate)
**Severity:** low — rare cases left open by [026](026-subscription-hardening.md); none blocks enforcement.
**Status:** open, not scheduled. Pick up when convenient, or sooner if one shows up in logs or support.
**Vocabulary:** [Access Source](../../CONTEXT.md#access-source), [Subscription Sync](../../CONTEXT.md).

## Items

1. **Play pause scheduled vs running (verify first).** Sync maps a non-null
   `auto_resume_date` to Paused (026/11). RevenueCat's docs don't confirm whether
   that field is already set while a pause is only *scheduled*. Check on Play
   sandbox: pause a subscription, call `POST /api/subscription/sync`, compare
   with the webhook result. Fix the mapping if they differ.
2. **TRANSFER is not stale-checked.** An older TRANSFER processed after newer
   events is applied anyway.
3. **Late purchase after TRANSFER recreates the source row.** An older
   INITIAL_PURCHASE/RENEWAL for the user who gave the subscription away,
   processed after the TRANSFER, creates a row for them again.
4. **Mobile: backend refusal not persisted.** After a successful sync without
   `app_access` the app trusts the backend (026/13), but only in memory. After an
   app restart RevenueCat's cached customerInfo is trusted again until the next
   `subscription_required` 403 re-checks.
5. **Mobile: AuthContext refusal-lift wiring has no tests** (the pure modules do).
6. **Temporary entitlement grant with no subscription row** changes nothing — the
   event carries no product to create one from (accepted in 026/02).
7. **Sync never records price** — REST has no USD price; the webhook fills a NULL
   price later (026/04).

Context for each item is in the Notes / handoff of the 026 tickets
(`026-subscription-hardening/`).
