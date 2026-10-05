# 11 — Overview

**Parent:** [022 — Super-admin panel v1](../022-super-admin-panel.md).

**What to build:** The Overview answers "is everything OK?": KPI row (users, signups 7d, active this week, Completed Sessions 7d) with change vs the previous week; activation funnel for the last 28 days of signups (signed up → verified → onboarded → first Completed Session → trained in week 2); Paywall card (enforcement on/off + count of Access Source None, linking to the filtered list); Needs attention (failed jobs, failed webhooks, Unfinished Accounts, stuck sessions, sponsorships expiring ≤ 30 days), each row opening the filtered Users list or System.

**Blocked by:** 04, 05, 10.

**Status:** ready-for-agent

- [ ] Admin Overview module returns one structure; live queries, cached ~10 min; weeks Monday–Sunday
- [ ] Module test with frozen time and known fixture counts: KPIs, deltas, funnel stages, None count, every Needs attention count
- [ ] Counts use the Activity Status / Access Source query constraints (no second definition)
- [ ] Every link carries the right filter query (feature test); page test only checks numbers appear
- [ ] Replaces the old admin dashboard; old view and its controller branch removed
