# 025 — Remove the partner-admin panel, library-programs UI, invitations and web registration

**Area:** back-end / web panel (Blade), auth, one API route
**Severity:** cleanup (deletion; one deliberate behaviour change: partner admins lose web login)
**Status:** open. Decisions settled in a grilling session on 2026-10-07.
**Builds on:** [022](022-super-admin-panel.md), [023](023-super-admin-panel-v2.md), [024](024-super-admin-insights-revenue-system.md), all in `dev`, and `fix/admin-role-cleanup` (redundant admin checks, `appUsers()`). Branch off `dev`, PR into `dev`.
**Vocabulary:** [House Partner](../../CONTEXT.md#house-partner), [Partner Override](../../CONTEXT.md#partner-override), [Access Source](../../CONTEXT.md#access-source). No new terms.

## Problem Statement

Partner admins still get the pre-022 web pages: a dashboard, a Users list and
member page, workout-session pages, a partners list and page, a programs library
and an exercises page. Several duplicate a super-admin page with separate code
and their own copies of domain rules, and the controllers branch on role to send
each role to its own copy.

Nobody needs them. There is one partner. What it needs (its exercise videos and
images) a super admin already manages through Partner Overrides on
`/admin/exercises` (023). The invitations feature and web self-registration are
unused: app users register through the API, and a web sign-up without an
invitation lands on a 403.

The code also has holes nobody is watching:
- `/partners` lists **every** partner to a partner admin.
- `/user-invitations` checks only `partner_id !== null`. Since the House Partner
  migration every app user has a partner, so any verified app user who logs in
  could send invitations.
- The old `/users` list does not use `appUsers()`, so staff appear in it.

## Solution

Delete the partner-admin side of the web panel, the partner library-programs UI,
the invitations feature and web registration. Keep the `partner_admin` **role**
dormant so dashboard access can be added back later.

- **Web login is for super admins only.** A partner admin who signs in gets the
  same "use the Fit Nation mobile app" message an app user gets today.
- **Library programs and routines stay live in the app.** The API that serves
  them (`/api/programs/library`, `/api/programs/{plan}/clone`, `/api/routines`)
  is untouched. Changes to the library are rare and go through
  `RoutinePlanSeeder` or tinker; there is no web UI for them.
- The super admin's pages lose nothing except the Invitation section on the
  member page.

## User Stories

1. As the business, I want the web panel to be for super admins only, so that there is one panel to maintain and no partner-scoped copy of it to keep correct.
2. As a partner admin, I want a clear message when I try to log in to the web, so that I know to use the mobile app.
3. As the business, I want the `partner_admin` role, its seeder, `STAFF_ROLES` and the policies' partner-admin rules kept, so that partner dashboard access can be added later without re-modelling.
4. As a super admin, I want System → Admins to offer only the super-admin role, so that nobody grants a role that does nothing. Existing partner admins stay listed and can be revoked.
5. As a mobile user, I want the Program Library, cloning and Routines to keep working exactly as before, so that the cleanup is invisible to me.
6. As a super admin, I want the plan outline, partner create/edit, Partner Overrides and every `/admin` page to keep working, so that nothing I use is lost.
7. As a super admin, I want the plan outline's back link to always go to the member's admin page, so that there is no dead link.
8. As the business, I want invitations removed end to end (web, API, mail, table, member-page section), so that no unused sign-up path stays exposed.
9. As the business, I want web self-registration removed, so that nobody can create an account that can only see a 403.
10. As a developer, I want every role branch that existed only to route a role to its own copy of a page removed, so that controllers stop deciding by role.

## Implementation Decisions

**Kept (do not delete)**
- `partner_admin` role: `RoleSeeder`, `PartnerSeeder`'s `admin@fitnation.gym`, `User::STAFF_ROLES`, `isStaff()`, `scopeAppUsers()`.
- `PartnerPolicy` and `PlanPolicy` partner-admin rules (`isAdminOfPartner`). They become unreachable for now; that is intended.
- `AppServiceProvider`'s staff password-reset link (backend `/reset-password` for staff).
- `Admin\PartnerController::show` listing a partner's partner admins; `Admin\Admins::list()` and `revoke()` for both roles.
- API library and routines: `Api\PlanController` `programsLibrary`, `programsClone`, `routinesIndex`, `routinesShow`; `PlanCloningService`; `Plan::scopeForPartner`, `isPartnerLibraryPlan`, `isPartnerProvided`; `RoutinePlanSeeder`; `PlanFactory::partnerLibrary()`/`partnerRoutine()`.
- `StorePlanRequest` and `UpdatePlanRequest` (used by `Api\PlanController`). Fix `UpdatePlanRequest::failedValidation`, which redirects to `partner.programs.*`: it must not reference a deleted route.
- `UpdatePartnerExerciseRequest::overrideRules()` (used by `Admin\PartnerOverrideController`). Move the rules to where the admin side owns them (e.g. a static on `PartnerOverrides` or a new admin request) and delete the old request class.
- `PartnerOverrides`, `PartnerExerciseView`, `PartnerExerciseFileService` and their artisan commands.
- `FitnessMetricsService`, `WeeklyProgress`, `SetOwnership`, `SessionDetail` (API and mail use them).
- Partner create/store/edit/update/destroy (`PartnerController`, `partners/create|edit|_form`): super-admin only after this. Redirects that go to `partners.index` go to `admin.partners.index` instead.
- Plan outline (`UserPlanController`, `plans/outline`, `plans/_outline-*`), and the `workouts.*` / `workout-exercises.*` routes the outline posts to, for user-owned plans.

**Deleted: partner-admin pages**
- `DashboardController::partnerDashboard` and `dashboard/partner.blade.php`. `/dashboard` (and `/`) redirect a super admin to `admin.overview`; anyone else gets the existing 403. Keep the `dashboard` route name: login, email verification, password confirm and the guest layout point at it.
- Old `UserController` (`index`, `show`, the invitation methods, `partnerAppUrl`), `users/index`, `users/show`; `resources/js/components/chart/user-progress.js` and its lazy-load in `app.js`.
- `UserWorkoutSessionController` and `users/workout-sessions/*`.
- `PartnerController::index` and `::show`, `partners/index`, `partners/show`, `ColorHelper::getColorPalette` / `getDarkColorPalette` if nothing else calls them. Remove `index`/`show` from the resource route.
- `/partner/exercises*`, `PUT /exercises/{exercise}/partner`, `POST /exercises/{exercise}/link|unlink`, `POST /partner/exercises/bulk-link`; `ExerciseController` methods `partnerIndex`, `show`, `edit`, `updatePartnerExercises`, `linkExercise`, `unlinkExercise`, `bulkLink`; `exercises/partner/*`; `BulkLinkExerciseRequest`.
- `Api\PartnerController` methods with no route (only `activeList` and `branding` are routed).
- `MenuHelper`'s non-admin branch (Dashboard, Users, Programs, Exercises).

**Deleted: library-programs UI**
- `PlanController`, `PlanService` (only `PlanController` uses it), `/partner/programs*` routes, `plans/index|create|show|edit`, `plans/_form`, `plans/library-form`, modals `create-program`, `edit-program`, `add-workout`, `edit-workout`, `add-exercise`, `workouts/add-form`, `workouts/edit-form`.
- The library-plan branches of `WorkoutTemplateController` and `WorkoutTemplateExerciseController`, and the views only they render (`workout-templates/*`, `workout-template-exercises/edit`). A web request for a library plan's workout or exercise row returns 404.
- `UserPlanController::show|edit` redirect library plans to `partner.programs.*`; they return 404 instead.
- The filepond component and `filepond-init.js` if nothing else uses them after this (grep; the outline's cover upload may).

**Deleted: role branching**
- `UserPlanController`'s `$back` branch: always `admin.users.show`.
- `partners/edit.blade.php` back link branch: always the admin partner page.
- `PartnerController::index/show` redirects (gone with the methods).
- Any remaining `hasRole('partner_admin')` in web controllers and requests.

**Changed: login and roles**
- `AuthenticatedSessionController`: the web gate admits `admin` only.
- `Admin\Admins::ROLES` / the System grant form: grant offers super admin only. The revoke route still accepts `partner_admin` so existing ones can be removed.

**Deleted: invitations**
- `UserInvitation`, `Partner::invitations()`, `UserInvitationMail` + `emails/user-invitation`, `InviteUserRequest`, `user-invitations/index`, the four `/user-invitations*` routes, `config('app.invitation_expiry_days')`.
- `GET /api/invitations/{token}`, `Api\InvitationController`, `InvitationResource`; `API_DOCUMENTATION.md` entry if any.
- The Invitation section on `admin/users/show` and its query in `Admin\UserController::show`.
- A new migration dropping `user_invitations` (`down()` recreates it). **Before merging, confirm the production table holds nothing worth keeping.**
- Front end: `validateInvitation` and its types in `front-end/packages/shared` (no app code calls them). Separate PR in the front-end repo.

**Deleted: web registration**
- `/register` routes, `RegisteredUserController`, `auth/register`, the `registration-success` route and view, and register links in the guest layout or login page.

**No new modules, no glossary changes, no ADR.** The decision to keep the role dormant is recorded here.

## Testing Decisions

- Test external behaviour only: HTTP status, redirect, database state, API payload.
- **Characterization first, in its own commit (house rule).** Ticket 01 locks what must survive before 02 and 03 delete anything. Existing coverage to lean on: `ProgramApiTest`, `RoutinePlanApiTest`, `PlanCloningTest`, `PlanOutlineTest`, `PlanOutlineWorkoutsTest`, `PlanActivationTest`, `PartnerEditTest`, `PartnerManagementCharacterizationTest`, `Auth/AuthenticationTest`, `Admin/*`.
- Tests for deleted code go with it, **deliberately**, in the same commit as the code; never loosen an assertion to make it pass. Read each before deciding:
  - Likely whole file: `PartnerExercisePagesTest`, `OldPlanPagesTest`, `PartnerUserPagesPlanLinksTest`, `UserShowWeeklyChartTest`, `Api/InvitationControllerTest`, `Auth/RegistrationTest`.
  - Only the partner-admin, library-page, invitation or registration cases: `PlanWebTest`, `UserPlanPagesCharacterizationTest`, `PlanWritesCharacterizationTest`, `PartnerOverrideCharacterizationTest`, `ArchivedExerciseVisibilityTest`, `PlanOutlineTest`, `PlanOutlineWorkoutsTest`, `PlanActivationTest`, `PlanPolicyTest`, `Admin/AdminShellCharacterizationTest`, `PartnerManagementCharacterizationTest`, `PartnerEditTest`, `Admin/GlobalSearchTest`, `HousePartnerTest`, `HousePartnerCharacterizationTest`, `Admin/UserPageTest`, `ExampleTest`.
  - Where a case covered behaviour that survives (e.g. a partner admin's plan write through the policy), keep it at the policy or API level rather than deleting it.
- Tests that use `partner_admin` only as a "forbidden" or "excluded from counts" fixture stay as they are: the role still exists and must still be excluded and refused.
- New assertions:
  - A partner admin's web login is refused with the mobile-app message; a super admin's lands on Overview.
  - Every deleted route returns 404 (one data-driven test listing them), so a stray route left behind fails.
  - A web request for a library plan through `/plans/{plan}` returns 404; the API still serves it.
  - The System grant form refuses `partner_admin`; revoking an existing partner admin still works.
  - `user_invitations` does not exist after migrating.

## Out of Scope

- Removing the `partner_admin` role, its policy branches or existing partner-admin accounts.
- A super-admin UI for library programs or routines. If the library starts changing often, spec it then.
- Partner-scoped versions of the super-admin pages.
- Redundant `hasRole('admin')` checks in `Store/UpdateWorkoutSplitRequest` and the exercise requests behind the `admin` middleware.

## Tickets

| # | Ticket | Blocked by |
|---|--------|------------|
| [01](025-remove-partner-admin-panel/01-characterize-survivors.md) | Characterize what survives | — |
| [02](025-remove-partner-admin-panel/02-delete-partner-admin-pages.md) | Delete the partner-admin pages, library-programs UI and role branching; admin-only web login | 01 |
| [03](025-remove-partner-admin-panel/03-delete-invitations-and-registration.md) | Delete invitations and web registration | 02 |
