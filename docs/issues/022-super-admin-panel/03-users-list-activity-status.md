# 03 — Users list with Activity Status

**Parent:** [022 — Super-admin panel v1](../022-super-admin-panel.md). Terms: [Activity Status](../../../CONTEXT.md#activity-status), [Completed Session](../../../CONTEXT.md#completed-session), [Unfinished Account](../../../CONTEXT.md#unfinished-account).

**What to build:** The super admin opens Users and sees every user across all partners — name, email, partner, signup date, last Completed Session, Completed Sessions in 30 days, Activity Status chip — paginated, filterable by partner and Activity Status, with filters in the URL. Behind it, an Activity Status module with two faces that agree: the status for one user, and a query constraint "users whose status is X" that runs in SQL.

**Blocked by:** 01.

**Status:** ready-for-agent

- [ ] Activity Status module per the spec's rules (Unfinished, New, Active, Slipping, Inactive, Deleted); owns its own loading
- [ ] Agreement test: fixture users on each side of every boundary (6/7/8, 13/14/15 days; New vs Inactive at 14 days from onboarding); per-user label == expected, and the constraint for each label returns exactly those users; time frozen
- [ ] Users list excludes admin and partner-admin accounts; deleted users hidden unless the Deleted status is filtered
- [ ] `?partner=` and `?activity=` filter the list and survive pagination
- [ ] Partner admins and plain users get 403
- [ ] Prototype variant A is the layout reference
