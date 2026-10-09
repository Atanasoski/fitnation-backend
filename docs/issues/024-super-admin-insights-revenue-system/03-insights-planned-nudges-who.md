# 03 — Insights: planned vs actual, nudges, who our users are

**Parent:** [024 — Super-admin panel: Insights, Revenue, and the rest of System](../024-super-admin-insights-revenue-system.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** The last three cards. Prefactor first, in its own commit: extract the "Weekly Summary is on" condition from `WeeklySummaries` (a missing setting means on) into one shared constraint, and have `WeeklySummaries` use it. Its tests stay green.
- **Do they train as often as they said?** App users with `training_days_per_week` set, onboarded before the range started, grouped by that value. Per group: users, and the average Completed Sessions per week. Headline: Σ actual ÷ Σ planned.
- **Do nudges work?** For each Inactivity Nudge step (3, 7, 14) with Sent Records in the range: how many were sent, and the share whose user logged a Completed Session within 48 h (a constant). Below that: how many users have the Weekly Summary off, out of all who could get it (a current total).
- **Who are our users?** Onboarded app users by goal, experience and gender (enum labels, plus "Not set"), and age band from `user_profiles.age`. This card ignores the range, and its footnote says so.

**Blocked by:** 01

**Status:** done

- [x] Prefactor commit: shared Weekly Summary constraint; existing Weekly Summary tests green.
- [x] `planned_vs_actual`, `nudges` and `who` keys tested per Seam 1: 47 h counts and 49 h does not; steps are split; the Weekly Summary off-count respects default-on; "Not set" rows; age-band edges.
- [x] Cards render; the Training tab now matches prototype Insights A.
