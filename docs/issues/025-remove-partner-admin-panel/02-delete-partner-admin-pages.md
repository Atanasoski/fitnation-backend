# 02 — Delete the partner-admin pages, library-programs UI and role branching; admin-only web login

**Parent:** [025 — Remove the partner-admin panel, library-programs UI, invitations and web registration](../025-remove-partner-admin-panel.md). Read it first; its Implementation and Testing Decisions are binding. The parent's **Kept** list is the boundary: nothing on it is deleted.

**What to build:** Everything under the parent's *Deleted: partner-admin pages*, *Deleted: library-programs UI*, *Deleted: role branching* and *Changed: login and roles*, except invitations (03). In short:

- Web login admits `admin` only. `/dashboard` and `/` send a super admin to Overview; anyone else gets the existing 403.
- Delete `DashboardController::partnerDashboard`, the old `UserController::index|show`, `UserWorkoutSessionController`, `PartnerController::index|show`, the partner exercise pages and endpoints, `PlanController` and `PlanService`, and their views, routes, requests, JS and modal components.
- Remove `MenuHelper`'s non-admin branch, the role-based back links (`UserPlanController`, `partners/edit`), and the library-plan branches in `WorkoutTemplateController` / `WorkoutTemplateExerciseController` / `UserPlanController` (library plans 404 on the web).
- Partner store/update/destroy redirect to `admin.partners.index`.
- `UpdatePlanRequest::failedValidation` stops referencing `partner.programs.*`. `UpdatePartnerExerciseRequest::overrideRules()` moves to the admin side; the old request class goes.
- Delete the unrouted `Api\PartnerController` methods.
- System → Admins grants super admin only; revoking an existing partner admin still works.
- Leave the old `UserController` invitation methods and their routes for 03 (move them into a small controller if deleting the rest of `UserController` makes that cleaner).

**Blocked by:** 01

**Status:** open

- [ ] A data-driven test that every deleted route returns 404.
- [ ] Partner-admin web login refused with the mobile-app message; super admin lands on Overview.
- [ ] `/plans/{plan}` for a library plan returns 404; 01's API tests still pass unchanged.
- [ ] Grant form refuses `partner_admin`; revoke of an existing partner admin works.
- [ ] Tests for deleted code removed or narrowed deliberately (see parent's Testing Decisions); 01's tests pass unchanged, except redirects 01 recorded as moving to `admin.partners.index`.
- [ ] `grep -rn "partner_admin\|partner\.programs\|users\.show\|partners\.index\|partners\.show" app resources routes` shows only the kept uses listed in the parent.
- [ ] `npm run build` succeeds (JS entries removed); `composer test` green; `pint` on changed files.
