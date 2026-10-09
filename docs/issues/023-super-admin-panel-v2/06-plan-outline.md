# 06 — Plan outline: plans

**Parent:** [023 — Super-admin panel v2](../023-super-admin-panel-v2.md). Prototype: plans variant C.

**What to build:** `GET /users/{user}/plans` as the outline page. On the left is a tree of all the user's Programs and Routines (active dot, type), expandable to workouts and rows (read-only rows until 07). On the right is the selected node's editor, with the selection in the URL. The plan editor covers name, type, weeks (Programs only) and description; save, activate (naming the plan it replaces, via `PlanActivation`) and delete (confirmation says logged sessions are kept). Create a Program or a Routine, starting inactive. Every write redirects back with the node selected. The page is guarded by the 05 policy and used by both roles.

**Blocked by:** 05

**Status:** done

- [x] Characterization commit first: the current partner-admin user-plan index, store, update and destroy responses.
- [x] Tree lists Programs and Routines, both types.
- [x] `is_active` only changes through `PlanActivation`. Changing an active plan's type re-enters activation.
- [x] Tests: activate a Routine while a Program is active (both stay active). A second Program replaces the first. Delete keeps sessions and set logs. Policy matrix on the page.
- [x] Super admin sees it in the admin shell. Partner admin sees it in theirs.
