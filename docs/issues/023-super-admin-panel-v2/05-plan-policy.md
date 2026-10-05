# 05 — Plan policy: who may manage a user's plans

**Parent:** [023 — Super-admin panel v2](../023-super-admin-panel-v2.md).

**What to build:** one authorisation rule for plans and everything under them. A super admin may manage any app user's plans (staff accounts excluded). A partner admin may manage their own partner's members' plans. Library plans (`user_id` null): super admin, or a partner admin of `plan.partner_id`. Apply it to every plan, workout and workout-exercise web write, including `WorkoutTemplateController` and `WorkoutTemplateExerciseController` for library plans. Those have no authorisation today. Fix `UpdateWorkoutTemplateRequest`'s `plan_id` check, which compares against `auth()->id()`.

**Blocked by:** —

**Status:** done

- [x] Failing test first: a plain user, and another partner's admin, can today update or delete someone else's workout and workout-exercise. Commit that test (marked or skipped if needed) before the fix.
- [x] `PlanPolicy` (or a module method) with the matrix from Seam 4 in the spec. Tested as a table.
- [x] Every web write path authorises against the plan. Library-plan flows (`/partner/programs`) keep working for their own partner admin.
- [x] `PlanWebTest` and `PlanActivationTest` stay green.
