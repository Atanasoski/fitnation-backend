# 08 — Admin user page Plans section; retire old plan pages

**Parent:** [023 — Super-admin panel v2](../023-super-admin-panel-v2.md).

**What to build:** add a Plans section to the admin user page (name, type, active, workouts, updated), linking to the outline. Make the "Active plan" fact link to its node. Point the partner-admin Users pages (`users/index`, `users/show`) at the outline. Redirect the old `plans.index/create/show/edit` and the user-plan `workouts.*` and `workout-exercises.*` GET pages to the matching outline node. Delete the `plans/users/*`, `workout-templates/users/*` and `workout-template-exercises/users/*` views and the now-unused `PlanController::userPlan*` methods. Library-plan views stay.

**Blocked by:** 07

**Status:** ready-for-agent

- [ ] Old URLs redirect to the right node. Tested.
- [ ] No route or view references the deleted views (search the tree).
- [ ] `AdminShellCharacterizationTest` (partner-admin sidebar) stays green.
- [ ] `UserPageTest` covers the Plans section and links.
- [ ] Release note line: partner admins' plan pages moved to the outline, which now includes Routines.
