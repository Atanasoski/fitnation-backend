# 05 — Signup Trial vs Complimentary Access

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** The backend records why a user has free access, and the app and admin say which it is. See the Signup Trial term in `CONTEXT.md`.

**Blocked by:** None — can start immediately

**Branch:** `fix/subscription-hardening` in `back-end` (off `dev`; spec and tickets are committed here too).

**Status:** done

- [x] Users carry a free-access kind (`signup_trial` | `complimentary` | null) beside the existing until-date; existing rows with a date are backfilled `complimentary`.
- [x] The Signup Trial writes `signup_trial`; an admin grant/extension and the launch-grace command write `complimentary` (a grant over a running Signup Trial becomes Complimentary). Signup Trial stays once per account.
- [x] Access Source gains Signup Trial (ahead of Complimentary); admin labels, filters and counts follow (`AccessSourceTest` seam).
- [x] `GET /user` `subscription` block adds `access_source`, `free_access_kind`, `enforced`, `signup_trial_days`; `grace_period_ends_at` stays. Documented in `API_DOCUMENTATION.md`.
- [x] `composer test` green.

## Notes / handoff

- **Storage:** `users.free_access_kind` (`App\Enums\FreeAccessKind`: `signup_trial` | `complimentary` | null) beside `grace_period_ends_at`. Migration `2026_10_09_000001` backfills `complimentary` for every dated row and for users with any past admin Complimentary Access record (so an ended grant still blocks a later Signup Trial). A new grant replaces the kind; ending Complimentary Access nulls the date but keeps the kind.
- **Model:** `User::hasFreeAccess()` (either kind; what entitlements read), `isOnSignupTrial()`, `hasComplimentaryAccess()` (now complimentary only), `freeAccessKind()` (null without a date; a date with no kind reads complimentary), `grantFreeAccess($kind, $until)`, `signupTrialDays()`; SQL twins `withFreeAccess` / `onSignupTrial` / `withComplimentaryAccess`.
- **Access Source:** new `signup_trial` ("Signup Trial", detail "Signup Trial ends {date}"), resolved after Sponsored, ahead of Complimentary. `AccessSources::sourceOf(User)` = source only, via the user's relations (used by `/user`).
- **For mobile 09 — `GET /user` `user.subscription` additions** (also on `POST /subscription/sync`, login, profile — every `UserResource`):
  - `access_source`: string, one of `subscribed` `trial` `cancelled` `billing_issue` `paused` `sponsored` `signup_trial` `complimentary` `none`. Same whether enforced or not.
  - `free_access_kind`: `"signup_trial"` | `"complimentary"` | `null` (null only when `grace_period_ends_at` is null). Stays set after the date passes.
  - `enforced`: boolean (`SUBSCRIPTIONS_ENFORCED`).
  - `signup_trial_days`: integer ≥ 0 (default 7; 0 = no trial). Reported to every user.
  - `grace_period_ends_at`: unchanged, ISO 8601 UTC, until-date of either kind, may be past.
  - Cases: Signup Trial running → `access_source:"signup_trial"`, `free_access_kind:"signup_trial"`, date ahead → "Free trial · N days left". Complimentary (admin grant, App Review, launch grace) → `"complimentary"`/`"complimentary"` → "Free access until {date}". Trial ended → `access_source:"none"`, `free_access_kind:"signup_trial"`, date past. Paying/sponsored user who also has free access → `access_source` is the subscription/`sponsored`. Enforcement off → `enforced:false`, `entitlements:["app_access"]`, other fields reported as-is → show no card.
- **Admin panel:** on a Signup Trial user the actions card offers "Grant Complimentary Access" (replaces the trial) and no "End" button; ending a trial by hand is not offered.
- **Not done (out of scope):** `subscriptions:grant-launch-grace` still selects on a null date only, so it would re-grant users whose access was ended (pre-existing). `UserResource` now depends on `App\Services\Admin\AccessSources` (Access Source rule lives in the Admin area).
- Tests: `SignupTrialTest`, `Admin/AccessSourceTest`, `Admin/ComplimentaryAccessAndPartnerChangeTest`.
