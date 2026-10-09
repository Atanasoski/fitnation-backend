# 05 — Signup Trial vs Complimentary Access

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** The backend records why a user has free access, and the app and admin say which it is. See the Signup Trial term in `CONTEXT.md`.

**Blocked by:** None — can start immediately

**Branch:** `fix/subscription-hardening` in `back-end` (off `dev`; spec and tickets are committed here too).

**Status:** ready-for-agent

- [ ] Users carry a free-access kind (`signup_trial` | `complimentary` | null) beside the existing until-date; existing rows with a date are backfilled `complimentary`.
- [ ] The Signup Trial writes `signup_trial`; an admin grant/extension and the launch-grace command write `complimentary` (a grant over a running Signup Trial becomes Complimentary). Signup Trial stays once per account.
- [ ] Access Source gains Signup Trial (ahead of Complimentary); admin labels, filters and counts follow (`AccessSourceTest` seam).
- [ ] `GET /user` `subscription` block adds `access_source`, `free_access_kind`, `enforced`, `signup_trial_days`; `grace_period_ends_at` stays. Documented in `API_DOCUMENTATION.md`.
- [ ] `composer test` green.
