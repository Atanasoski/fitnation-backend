# 026 — Subscription hardening before enforcement

**Area:** back-end (RevenueCat webhook, access rules, `/user`, new sync endpoint) + mobile (`front-end/apps/mobile`: purchase flow, 403 recovery, trial copy)
**Severity:** feature / fix. Nothing here blocks the first production deploy with `SUBSCRIPTIONS_ENFORCED=false`; all of it must land before enforcement is turned on.
**Status:** open. Findings from a four-agent production-readiness review on 2026-10-08; decisions settled in a grilling session on 2026-10-09.
**Builds on:** the RevenueCat integration and spec 0038 (Signup Trial), both in `dev`. Branch off `dev`, PR into `dev`. The mobile half is a separate PR in the front-end repo, off its `dev`.
**Vocabulary:** [Access Source](../../CONTEXT.md#access-source), [Signup Trial](../../CONTEXT.md#signup-trial) (new), [Complimentary Access](../../CONTEXT.md#complimentary-access), [Sponsoring Partner](../../CONTEXT.md#sponsoring-partner) (now: *active* partner only), [House Partner](../../CONTEXT.md#house-partner).

## Problem Statement

Subscriptions work in sandbox on iOS and Android — purchase, renewal and the
Signup Trial — but the review found ways a user can end up with the wrong access
once enforcement is on:

- **A paying user can be told they have not paid.** The app lets a user in as soon
  as RevenueCat says they paid, but the backend only learns of it when the webhook
  arrives. Until then (seconds, sometimes minutes, never if the webhook fails for
  good) gated screens answer 403. Nothing re-syncs the backend from RevenueCat.
- **A purchase can belong to nobody.** If the app's RevenueCat login fails it is
  swallowed, the purchase is filed under an anonymous RevenueCat id, and the
  backend can never match it to a user.
- **The backend loses access it should keep.** A billing issue does not honour the
  store's grace period, and an extended subscription (Apple refund-extension,
  Play defer) or a temporary entitlement grant does not move the end date, so
  those users are locked out early.
- **Webhooks give up too fast and can pick the wrong user.** Five retries run back to
  back within seconds, so "user not registered yet" never recovers on its own; and
  the user lookup takes the lowest id among the RevenueCat ids instead of the
  most specific one.
- **Smaller webhook errors.** A plan change records the old product; a first purchase
  overwrites the frozen acquisition partner; a cancellation is stamped with the
  processing time rather than the event time; a transfer deletes the receiving
  user's subscription even when it is the one still active.
- **Signup Trial and Complimentary Access look the same.** Both are one date on the
  user, so the app calls an admin grant "Free trial" (the App Review account
  will be on Complimentary Access) and the admin Access Source shows every
  Signup Trial as Complimentary.
- **A deactivated sponsoring gym still sponsors** its members forever.
- **The app talks about a store trial that no longer exists.** The store free trial
  has been removed; the paywall still carries hardcoded "7-Day" store-trial copy,
  while the trial we actually run is the Signup Trial, whose length only the
  backend knows.
- **The app counts down a trial that does not matter yet.** While enforcement is off
  the Profile card shows "Free trial · Ends X", then "No active plan".

## Solution

- The backend can **sync a user's subscription from RevenueCat on demand**. The app
  calls it after a purchase or restore, and whenever the backend answers
  `subscription_required` while RevenueCat says the user has paid. A late or
  failed webhook can no longer cost a paying user access.
- After a purchase the app **waits briefly for the backend to agree** before entering.
- The app **makes sure RevenueCat knows who the user is before a purchase**, and
  refuses to start a purchase it could not attribute.
- The webhook **keeps access for exactly as long as the store does**, retries with
  real delays, and resolves the right user.
- The backend **records why a user has free access** — Signup Trial or Complimentary
  Access — and both the app and the admin say which it is.
- **Only an active Sponsoring Partner sponsors.**
- The **Signup Trial length comes from the backend** and the app shows it at the end
  of onboarding and on the Profile card. Store-trial copy is removed.
- While enforcement is off, the app **shows no trial countdown**.

## User Stories

1. As a new user who just paid, I want the app to work fully the moment I enter it, so that I don't see errors right after paying.
2. As a paying user whose purchase webhook failed, I want the app to repair my access by itself, so that I never get locked out of something I paid for.
3. As a paying user whose webhook is slow, I want the app to wait a few seconds for the backend instead of dropping me into broken screens, so that my first impression is not an error.
4. As a paying user who hits a "subscription required" answer while my store says I am subscribed, I want the app to re-sync and retry quietly, so that I don't land on a paywall I already paid.
5. As a user restoring purchases on a new phone, I want my access to come back on the backend immediately, so that the restore actually works.
6. As a user whose RevenueCat login failed, I want the app to try again before charging me, so that my payment is linked to my account.
7. As a user whose RevenueCat login still fails, I want a clear "try again" message instead of a purchase, so that I'm never charged for access I won't get.
8. As a subscriber with a failed card, I want to keep access during the store's billing grace period, so that a temporary card problem doesn't lock me out.
9. As a subscriber whose subscription the store extended, I want my access to run to the new end date, so that the extension actually reaches me.
10. As a user given a temporary entitlement by RevenueCat, I want access until it ends, so that store outages don't lock me out.
11. As a user who registered moments after paying, I want a webhook that arrived before my account existed to be applied once it does, so that my early purchase isn't lost.
12. As a user sharing a device with another account, I want a purchase attributed to the account that made it, so that someone else doesn't get my subscription.
13. As a subscriber who changed plan (monthly ↔ yearly), I want the backend to record my new product, so that my plan and revenue are reported correctly.
14. As a subscriber who restored purchases onto an account that already had an active subscription, I want that active subscription kept, so that a transfer of an expired one doesn't wipe it.
15. As a new user, I want to see at the end of onboarding how many free days I get, so that I know what to expect before the paywall.
16. As a user on the Signup Trial, I want the Profile card to show "Free trial · N days left", so that I know when I'll need to subscribe.
17. As a user with Complimentary Access, I want it described as free access until a date, not as a trial, so that I'm not told my "trial has ended" when it ends.
18. As the App Review account on Complimentary Access, I want to use every feature without a store purchase, so that review is not blocked by sandbox purchases the backend ignores.
19. As a user while subscriptions are not enforced, I want no trial countdown, so that I'm not warned about a paywall that doesn't exist yet.
20. As a user seeing the paywall, I want no promise of a store free trial that isn't offered, so that the paywall is honest.
21. As a member of a gym that has been deactivated, I want to be asked to subscribe, so that access reflects who actually pays. (From the gym's members' side: their free access ends with the gym's deactivation.)
22. As a super admin, I want the user page and Access Source counts to tell Signup Trial apart from Complimentary Access, so that I can see who is trialling and who I let in.
23. As a super admin granting Complimentary Access to a user still on their Signup Trial, I want the grant recorded as Complimentary, so that the label follows the latest reason.
24. As a super admin, I want a deactivated Sponsoring Partner's members shown with their real Access Source, so that the sponsored count is true.
25. As an operator, I want to set the Signup Trial length in one env value and have the app follow it, so that changing it needs no app release.
26. As an operator, I want failed webhooks retried with growing delays, so that transient problems heal without me running a replay.
27. As an operator, I want the sync endpoint to refuse to run without the RevenueCat secret API key configured, so that a misconfiguration is loud rather than silently granting or revoking access.
28. As an operator, I want the webhook and the sync to apply subscription state the same way, so that the two paths can never disagree about a user.
29. As an operator, I want a cancellation stamped with the moment it happened, so that a replayed webhook doesn't rewrite history.
30. As an operator, I want the frozen acquisition partner never overwritten by a later purchase event, so that partner attribution stays correct.

## Implementation Decisions

### Backend

- **One "apply subscription state" module.** The webhook handlers and the new sync
  both write a user's subscription through it, so an event and a REST snapshot
  produce the same row. It owns: product and store mapping, period type, expiry,
  cancelled-at, billing-issue state, the stale-event high-water mark, and never
  overwriting the frozen acquisition partner.
- **Sync endpoint.** `POST /api/subscription/sync`, authenticated, **not** behind the
  subscription middleware (the user calling it may currently be blocked).
  - It fetches the subscriber from the RevenueCat REST API by the user's id, reads
    the `app_access` entitlement and its subscription, and applies it through
    the module above.
  - Answers with the same user payload as `GET /user`.
  - Throttled per user.
  - When the RevenueCat secret API key is not configured it answers a server error
    and logs; it never guesses.
  - A REST snapshot carries no event timestamp; it is treated as current and moves
    the high-water mark to "now", so an older webhook arriving later cannot
    undo it.
  - Sandbox subscriptions are ignored in production, matching the webhook.
- **New env:** `REVENUECAT_SECRET_API_KEY` (server-side REST key). Documented in
  `.env.example` next to `REVENUECAT_WEBHOOK_SECRET`.
- **Webhook fixes:**
  - BILLING_ISSUE sets the expiry to the event's grace-period end when present
    (falling back to the expiration), so the store's grace period grants access.
  - SUBSCRIPTION_EXTENDED and TEMPORARY_ENTITLEMENT_GRANT move the expiry to the
    event's expiration.
  - Retries keep 5 tries with growing delays (about 1 min, 5 min, 30 min, 1 h).
  - User resolution checks `app_user_id`, then `original_app_user_id`, then
    aliases, in that order; still numeric ids only.
  - PRODUCT_CHANGE records the **new** product and does not clear a pending
    cancellation as if it were a renewal.
  - Cancellation records the event's time, not processing time.
  - TRANSFER keeps the receiving user's subscription when it grants access and
    the transferred one does not.
- **Free-access grant kind.** The user gets a record of *why* they have free access,
  alongside the existing until-date: `signup_trial` or `complimentary`.
  - The Signup Trial writes `signup_trial`.
  - An admin grant or extension, and the launch-grace command, write `complimentary`.
  - Existing rows are backfilled `complimentary` (all are test or admin grants
    today).
  - The Signup Trial stays once per account: a user who already had any free
    access does not get one.
- **Access Source** gains **Signup Trial** as its own source, ahead of
  Complimentary; the admin labels, filters and counts follow it.
- **Sponsoring Partner** requires the partner to be active. This changes the access
  rule, the Access Source, and the `is_sponsored_by_gym` flag together.
- **`/user` payload** (the `subscription` block), additive:
  - `access_source` — the user's Access Source value.
  - `free_access_kind` — `signup_trial`, `complimentary` or null.
  - `enforced` — whether subscriptions are enforced.
  - `signup_trial_days` — the configured Signup Trial length.
  - The existing `grace_period_ends_at` stays as the until-date for either kind.

### Mobile

- **Purchase flow** becomes one module with its collaborators passed in (RevenueCat,
  sync, `/user`):
  1. Check RevenueCat's current user id equals the backend user id; if not, log in
     again once; if it still doesn't match, stop with a "try again" error and no
     purchase.
  2. Purchase.
  3. Call sync.
  4. Poll `/user` until it carries `app_access`, up to about 10 s.
  5. Enter the app.
  Restore follows the same flow minus the purchase.
- **403 recovery:** on `subscription_required`, if RevenueCat reports `app_access`,
  call sync once and refetch before routing to the paywall. If RevenueCat says no,
  route to the paywall as today.
- The access gate keeps trusting RevenueCat **or** the backend.
- **Copy:**
  - Store-trial copy ("7-Day") is removed from the paywall.
  - The end of onboarding shows "N days free" from `signup_trial_days`, when it is
    above zero.
  - Profile card:
    - Signup Trial → "Free trial · N days left".
    - Complimentary → "Free access until {date}".
    - No card at all while `enforced` is false.

## Testing Decisions

- **Good tests drive the public surface only:**
  - back-end: HTTP requests in, rows and responses out;
  - mobile: a module's exported function in, its returned value or recorded calls out.
  
  No assertions on private methods or on how the shared apply module is called.
- **Backend seam: the HTTP API.**
  - Signed `POST /api/webhooks/revenuecat` with the job run inline;
    `POST /api/subscription/sync`; `GET /user`; a gated route for allow/deny.
  - The RevenueCat REST API is faked with `Http::fake()`.
  - Prior art: `RevenueCatWebhookTest`, `RequiresSubscriptionMiddlewareTest`,
    `SignupTrialTest`, `UserEntitlementsTest`.
  - One further existing seam for admin labels and counts: `AccessSourceTest`
    (Signup Trial vs Complimentary, deactivated Sponsoring Partner).
- **Cases that must exist:**
  - Webhook vs sync agreement: a webhook and a sync describing the same state
    leave the same row.
  - A later stale webhook does not undo a sync.
  - The sync endpoint is reachable while blocked.
  - Missing secret key → error, row untouched.
  - Sandbox snapshot ignored in production.
  - Billing issue within grace → access; after → none.
  - Extended → access to the new date.
  - Lookup priority with conflicting ids.
  - Product change records the new product.
  - Transfer keeps an active receiver.
  - Retry delays are configured.
  - Signup Trial then admin grant → Complimentary.
  - Deactivated sponsor → no access with enforcement on.
- **Mobile seam: existing pure-module tests** (`pnpm test`).
  - Purchase-flow cases: login mismatch then retry then success; still mismatched →
    error and no purchase; sync then poll succeeds; poll times out and still enters.
    Prior art: `paywall-restores-owned-purchase.test.ts`.
  - 403 recovery: prior art `gate.test.ts`.
  - Copy (trial days, kinds, enforced off): prior art `paywallCopy.test.ts`.

## Out of Scope

- **Launch-grace command fix.** Users who start a Signup Trial while enforcement is
  off are skipped by `subscriptions:grant-launch-grace`. There are no real users
  under the House Partner yet, so this is accepted.
- **Old app builds.** They have no paywall and will see 403s once enforcement is on.
  There are no real production users to strand.
- **Accepting sandbox purchases in production.** App Review is handled by giving the
  review account long Complimentary Access instead.
- Backend-only access (dropping the RevenueCat side of the app's gate).
- Lifetime / non-renewing products.
- Row locking for concurrent webhook processing (run one worker on that queue).
- The DST-ambiguous local timestamps from the non-UTC app timezone.

## Further Notes

- **Operator to-dos outside code:**
  - Two RevenueCat webhooks on the shared project: prod URL receives production
    events only, dev URL receives sandbox events only.
  - Create the RevenueCat secret API key and set `REVENUECAT_SECRET_API_KEY` on
    both backends.
  - Give the App Review account long Complimentary Access before submitting.
  - Before enabling enforcement: confirm the House Partner is not on the sponsor
    plan, then flip `SUBSCRIPTIONS_ENFORCED`, `config:cache`, restart workers.
- The column holding the until-date is still called `grace_period_ends_at`.
  Renaming it is not part of this spec; the glossary says neither kind is a
  "grace period".
- Review findings this spec answers are summarised in the conversation of
  2026-10-08; there is no separate review file.
