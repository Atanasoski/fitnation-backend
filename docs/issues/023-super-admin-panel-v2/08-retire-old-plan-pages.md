# 08 — Admin user page Plans section; retire old plan pages

**Parent:** [023 — Super-admin panel v2](../023-super-admin-panel-v2.md).

**What to build:** add a Plans section to the admin user page (name, type, active, workouts, updated), linking to the outline. Make the "Active plan" fact link to its node. Point the partner-admin Users pages (`users/index`, `users/show`) at the outline. Redirect the old `plans.index/create/show/edit` and the user-plan `workouts.*` and `workout-exercises.*` GET pages to the matching outline node. Delete the `plans/users/*`, `workout-templates/users/*` and `workout-template-exercises/users/*` views and the now-unused `PlanController::userPlan*` methods. Library-plan views stay.

**Blocked by:** 07

**Status:** done

- [x] Old URLs redirect to the right node. Tested.
- [x] No route or view references the deleted views (search the tree).
- [x] `AdminShellCharacterizationTest` (partner-admin sidebar) stays green.
- [x] `UserPageTest` covers the Plans section and links.
- [x] Release note line: partner admins' plan pages moved to the outline, which now includes Routines.

**Release note:** Partner admins' plan pages moved to the plan outline (`/users/{user}/plans`), which now includes Routines. Old plan, workout and workout-exercise page links redirect to the matching node.

**Done notes:**
- `plans.create` → outline with `create=program`; `plans.show`/`edit` → the plan node; `workouts.create` → `add=workout`; `workouts.show`/`edit` → the workout; `workout-exercises.create` (routed before, but had no method) → `add=exercise`; `workout-exercises.edit` → the row. New `PlanOutline::adding()` builds the `add` URLs. Redirects still pass the 05 policy first.
- Library plans keep their pages: `plans.show` → `partner.programs.show` as before, `plans.edit` → `partner.programs.edit` (it rendered the user-plan edit view before), `workout-exercises.create` → the library workout page.
- Also deleted the `create-plan` / `edit-plan` modals and `x-plans.edit-form`, which only the deleted views used, and the dead `user` branch of `plans/_form` and `workout-templates/_form`.
- Partner-admin `users/show` "Your Plans" still lists the latest three Programs only (not Routines); its links and "View all" go to the outline.
- `ArchivedExerciseVisibilityTest`'s picker test now uses a library plan's workout page; the user-plan picker is covered by `PlanOutlineWorkoutsTest`.
