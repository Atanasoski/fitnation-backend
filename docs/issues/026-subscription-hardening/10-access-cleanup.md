# 10 — Access follow-ups: end a Signup Trial, labels, launch grace, Access Source home

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first, plus the Notes / handoff of tickets 01–09 in this folder; these follow-ups close their open items (decided 2026-10-09).

**What to build:** Admin and access loose ends from 05/06.

**Blocked by:** None — can start immediately

**Branch:** `fix/subscription-hardening` in `back-end`.

**Status:** done

- [x] A super admin can end a running Signup Trial from the user page the same way as Complimentary Access (recorded as an Admin Change; it still blocks a later Signup Trial).
- [x] `Partner::kind()` (and every admin label built from it) calls a deactivated sponsor-plan partner something other than Sponsoring Partner, matching the glossary.
- [x] `subscriptions:grant-launch-grace` skips users who already had Free Access of either kind, including ended grants (no re-granting what an admin ended).
- [x] The Access Source rules live outside the Admin services namespace, since `/user` depends on them; admin code uses them from there. No behaviour change in that move.
- [x] `composer test` green.

## Notes / handoff

- **End a Signup Trial:** `DELETE /admin/users/{user}/signup-trial` (reason optional) → `UserChanges::endSignupTrial()`: nulls the date, keeps `free_access_kind=signup_trial` (so `startSignupTrial()` still refuses), records an Admin Change of new kind `AdminChangeKind::SignupTrial` (`signup_trial`, until null; history reads "Signup Trial · ended"). Only a user on a running Signup Trial; otherwise a flash error. User page shows "End Signup Trial now" under the grant form. CONTEXT.md Admin Change now lists three kinds.
- **Partner label:** `Partner::kind()` is Sponsoring only when `is_active`; a deactivated sponsor-plan partner reads plain "Partner" (partners list, partner page, user page).
- **Launch grace:** selects `grace_period_ends_at IS NULL AND free_access_kind IS NULL`, so ended grants and ended/expired Signup Trials are skipped.
- **Namespace:** `AccessSources` and `Access` moved to `App\Services\Access`; no behaviour change.
- Tests: `Admin/ComplimentaryAccessAndPartnerChangeTest`, `Admin/PartnersTest`, `SignupTrialTest`.
