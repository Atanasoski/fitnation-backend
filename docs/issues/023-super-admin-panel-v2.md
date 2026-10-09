# 023 — Super-admin panel v2: partner edit, exercise catalogue, a user's plans

**Area:** back-end / admin panel (Blade + Alpine, TailAdmin shell)
**Severity:** feature
**Status:** ready-for-agent — decisions settled in the prototype session of 2026-10-05
**Builds on:** [022](022-super-admin-panel.md), merged into `dev` (PR #58). Branch off `dev`, PR into `dev`.
**Prototype:** branch `prototype/admin-panel-v2`, route `/admin/prototype/admin-v2?area=partner|exercises|plans&variant=A|B|C` (local only, admin login). Winners: **partner A, exercises C, plans C**. The verdict and rejected variants are in commit `7692368`. It is the primary source for layout; do not merge it or copy its code.
**Vocabulary:** [Archived Exercise](../../CONTEXT.md#archived-exercise), [Partner Override](../../CONTEXT.md#partner-override), [House Partner](../../CONTEXT.md#house-partner), [Admin Change](../../CONTEXT.md#admin-change). Use these words in code and UI.

## Problem Statement

v1 rebuilt the super-admin shell, but three jobs still go through older pages
outside it, or cannot be done at all:

- **Partner branding** is a form with 24 colour inputs, a font and a background
  pattern. Nobody can tell which ones matter, and saving it writes defaults into
  every column that was empty.
- **The exercise catalogue** cannot set difficulty or selection priority (the
  generator reads both), cannot upload media when creating an exercise, and has
  no view of Partner Overrides. Its **Delete hard-deletes the exercise and, through
  `ON DELETE CASCADE`, every user's logged sets and session exercises for it.**
- **A user's plans** can only be managed by a partner admin, only for Programs,
  across seven pages. The super admin is locked out (role check, then
  `Partner::findOrFail(null)`). The workout and workout-exercise controllers
  behind those pages have **no authorisation**: any signed-in user can change any
  template.

## Solution

Three areas rebuilt in the v1 shell, following the prototype winners:

- **Partner edit (A).** One edit page. The form is on the left: details, logo,
  and **four colours** (primary and secondary, for light and dark). A sticky
  live preview of the app card in light and dark is on the right. Every other
  identity column stays in the database and keeps its value.
- **Exercises (C).** A media-first gallery with facet filters (region,
  equipment, difficulty, missing media, archived). Create and edit open in a
  slide-over over the gallery. It covers classification, difficulty, selection
  priority, rest, image, video, the muscle-group image, and **editable Partner
  Overrides**. Bulk select. Deleting a used exercise **archives** it, and its
  history is kept.
- **A user's plans (C).** One outline page per user: plans → workouts →
  exercises in a tree on the left, and an editor for the selected node on the
  right. It covers Programs and Routines, create, edit, activate (ADR-0002),
  delete, workouts, and exercise rows. **This page replaces the partner-admin
  plan pages.** Super admins reach it from the admin user page for any user.
  Partner admins reach it for their own members.

## User Stories

Partner edit
1. As a super admin, I want the partner edit page to show only name, slug, domain, active, logo and four colours (primary and secondary, light and dark), so that I set what matters without guessing.
2. As a super admin, I want a live preview of the app in light and dark that updates as I pick colours or a logo, so that I see the result before saving.
3. As a super admin, I want the preview to use the partner's stored (or default) backgrounds and text colours, so that it shows what the app will really render.
4. As a super admin, I want saving to leave every colour, font and pattern I can no longer see exactly as it was, so that the mobile app's branding does not shift.
5. As a super admin, I want the create page to have the same form and preview, so that new partners start the same way.
6. As a super admin, I want unchecking "Active" on the edit page to actually deactivate the partner, and the House Partner to be refused, so that the form matches the v1 Partners page rule.

Exercises
7. As a super admin, I want the catalogue as a gallery of cards with image, video badge, equipment, primary muscles, difficulty and priority, so that I can spot gaps visually.
8. As a super admin, I want facet filters (target region, equipment, difficulty) with counts, a "missing media" toggle, an "archived" toggle and a name search, all in the URL, so that I can work through a slice and share it.
9. As a super admin, I want to open create and edit in a slide-over without leaving the gallery, and keep my filters when it closes, so that catalogue work is fast.
10. As a super admin, I want to set category, movement pattern, target region, equipment, angle, primary and secondary muscle groups, and training styles, so that the generator and the app classify the exercise correctly.
11. As a super admin, I want to set difficulty, selection priority and default rest, so that I control what the generator picks and how long users rest.
12. As a super admin, I want to upload an image and a video when creating an exercise, not only when editing it, so that a new exercise is complete in one step.
13. As a super admin, I want to see the muscle-group image and regenerate it, so that it matches the muscle groups after I change them.
14. As a super admin, I want to see every partner an exercise is linked to, with its Partner Override (description, image, video), and edit or clear any of them, so that I can fix a partner's content for them.
15. As a super admin, I want to link an exercise to a partner, or unlink it, so that I can fix a partner's library.
16. As a super admin, I want Delete to archive an exercise that is used in any plan or logged session, and to really delete only one nobody ever used, so that I can never destroy users' training history.
17. As a super admin, I want archived exercises hidden from the app's catalogue, the plan exercise pickers and the generator, but still shown in existing plans and history, so that retiring an exercise is safe.
18. As a super admin, I want to restore an archived exercise, so that a mistake is cheap.
19. As a super admin, I want to select several cards and archive or delete them together, following the same rule, so that cleanup is quick.

A user's plans
20. As a super admin, I want a Plans section on the admin user page, listing their plans with type, active flag and workout count, and linking to the plan outline, so that plans are one click from the person.
21. As a super admin or partner admin, I want one page showing all of a user's plans (Programs and Routines) as a tree of plans → workouts → exercises, so that I see their whole programme at once.
22. As a super admin or partner admin, I want to select any node and edit it on the right — a plan's name, type, weeks and description; a workout's name and day; an exercise row's sets, reps range, target weight and rest — so that each edit is in one place.
23. As a super admin or partner admin, I want to create a Program or a Routine for the user, so that I can set them up.
24. As a super admin or partner admin, I want to activate a plan and be told which plan of the same type it replaces, so that ADR-0002 is never a surprise.
25. As a super admin or partner admin, I want to add, rename, re-day and remove workouts, so that the week matches the person.
26. As a super admin or partner admin, I want to add an exercise from the plan owner's partner catalogue, reorder rows, swap a row's exercise, and remove rows, so that the session is right.
27. As a super admin or partner admin, I want to delete a plan with a confirmation saying the user's logged sessions are kept, so that I don't fear losing history.
28. As a super admin or partner admin, I want target weights shown and entered in the user's Unit System, so that I prescribe what they will see.
29. As a super admin or partner admin, I want the page to reopen on the node I just saved, so that I can make several edits in a row.
30. As a partner admin, I want my Users pages to link to this plan page instead of the old plan pages, so that I have one tool.

Access control
31. As the business, I want a super admin to manage any app user's plans, and a partner admin only their own partner's members, so that partner data stays separate.
32. As the business, I want every workout and workout-exercise write — for user plans and partner library plans — to check that the actor may manage that plan, so that no signed-in user can change someone else's templates.
33. As a partner admin, I want my library programmes (`/partner/programs`) and my exercise overrides (`/partner/exercises`) to keep working as they do, so that this change does not disturb my other work.

## Implementation Decisions

**Branch and shell**
- Branch `feat/…` off `dev`. Everything renders in the existing `layouts.app` shell and brand tokens. Partner colours rendered as data (preview cards) may be raw values. Everything else uses tokens.
- Rewrite the winners properly. Do not copy prototype code. Its layout, field order and copy are the reference.

**Partner edit** (`PartnerController` edit/create, `partners/{edit,create}.blade.php`)
- The form posts name, slug, domain, `is_active` (with a hidden `0`), logo, `primary_color`, `secondary_color`, `primary_color_dark` and `secondary_color_dark`. Nothing else.
- Untouched columns stay untouched because the controller saves `$request->only(config('branding.identity_fields'))`, and absent keys are skipped. Keep it that way. Do **not** remove the other rules from `StorePartnerRequest` / `UpdatePartnerRequest`: `Api\PartnerController` uses the same requests, and API clients may still send them.
- On the web form, the four colours are required. Prefill each from the stored value, or the `config('branding.light|dark')` default.
- The live preview is Alpine. Its backgrounds and text colours come from the partner's stored identity or the config defaults (the same `ColorHelper` values `_branding-preview` uses today), so the preview matches the app. Show the white-on-primary contrast ratio under each mode.
- Unchecking Active goes through the same House Partner guard as `Admin\PartnerController::updateActive`. Share that rule; do not copy it.
- The partner-admin "edit own partner" path (`PartnerPolicy::update`) uses the same form and gets the same four colours.
- No change to `PartnerVisualIdentityResource` or the mobile API. Mobile keeps receiving every field.

**Archived Exercise** (new rule, `app/Services/Exercise/`)
- New nullable `archived_at` on `workout_exercises`. **Not `SoftDeletes`**: a global scope would make `SetLog->exercise` and template rows resolve to null and break history. Archived exercises must still load through relations.
- One module owns "delete or archive": an exercise is **used** if any `workout_template_exercises`, `workout_session_exercises` or `workout_session_set_logs` row references it. Used → set `archived_at`. Unused → hard delete (the cascades then only touch pivots: muscle groups, training styles, `partner_exercises`). Restore clears `archived_at`.
- One scope (e.g. `Exercise::available()`) excludes archived exercises. It is applied in the API catalogue listing and search (`GET /api/exercises`), the web plan exercise pickers, the partner-admin library, `syncDefaultExercises`, and the workout generators. It is **not** applied to relations, session detail or `GET /api/exercises/{id}`.
- `DELETE /api/exercises/{exercise}` (admin API) and the web destroy both go through the module.
- Archiving does not remove the exercise from existing plans. Those rows keep working.

**Exercise catalogue (C)**
- Server-rendered gallery. Filters are query parameters: `q` (through `Exercise::scopeSearch`), `region`, `equipment`, `difficulty`, `missing=media`, `archived=1`. Facet counts are computed server-side for the current filter set. Paginate (~48).
- `?edit={id}` or `?create=1` renders the slide-over open over the same filtered gallery. Saves post to the existing `exercises.store` / `exercises.update` and redirect back to the gallery URL they came from. No client-side fetching is needed. The old `exercises.show` / `exercises.edit` / `exercises.create` pages redirect to the gallery with the slide-over open.
- New fields:
  - `difficulty`: nullable, `ExerciseDifficulty`.
  - `selection_priority`: integer 0–1000, default 100, higher picked first. See `DeterministicWorkoutGenerator::sortByCompoundPriority`.
  - `default_rest_sec`.
- Create accepts image and video uploads, with the same rules as update. Align `StoreExerciseRequest` with `UpdateExerciseRequest`; today `image` there is a string.
- Muscle-group image: show it, with a "Regenerate" action on the existing `exercises.updateMuscleGroupImage`. It is not uploaded.
- Bulk action: archive-or-delete the selected ids through the same module. The response states how many were archived and how many deleted.

**Partner Overrides, as a super admin**
- The slide-over lists every partner linked to the exercise (`partner_exercises`) with its override: description, image, video. Each can be edited or cleared. A partner can be linked or unlinked (with confirmation: unlinking hides the exercise from that partner's members).
- Extract the override write that `ExerciseController::updatePartnerExercises` does today (files through `PartnerExerciseFileService`, remove-video, description) into one module. The partner-admin route and the new admin route (`/admin/exercises/{exercise}/partners/{partner}`) both call it. Reads go through `PartnerExerciseView`.

**A user's plans (C)** (replaces `PlanController::userPlan*` and the `plans/users/*`, `workout-templates/users/*`, `workout-template-exercises/users/*` views)
- **Who may manage a user's plans:** one policy (`PlanPolicy`, or a method on a new `UserPlans` module). It allows a super admin for any app user (not staff accounts), and a partner admin when `user.partner_id` is their partner. Library plans (`user_id` null) allow a super admin, or a partner admin of `plan.partner_id`. Every plan, workout and workout-exercise write authorises against the **plan** through this policy, including `WorkoutTemplateController` / `WorkoutTemplateExerciseController` when they serve library plans.
- **Page:** `GET /users/{user}/plans`, outside `/admin`, so both roles share it. The selected node is in the URL (`?plan=…&workout=…&row=…`). Super admins see it inside the admin shell. The sidebar already differs by role.
- **Writes:** plain form posts to resourceful routes for plans, workouts and rows. Each redirects back to the outline with the touched node selected. Activation and `is_active` go through `PlanActivation` only (ADR-0002). The plan editor names the plan it would replace.
- **Types:** Programs and Routines. A new Program or Routine starts inactive. Program weeks are required; Routines have none. Changing an active plan's type re-enters activation (ADR-0002 consequence).
- **Exercise picker:** exercises linked to the **plan owner's** partner (`Exercise::forPartner` + `available()`), the same for both roles.
- **Units:** target weight is shown and entered in the user's Unit System, and stored in kilograms. Convert at the HTTP boundary (ADR-0001), using the same helpers as the API.
- **Row edits:** the form always sends every field. Fix `WorkoutTemplateExerciseController::update`, which resets omitted fields to defaults; it must not.
- **Delete plan:** hard delete as today. Templates and rows cascade, and `workout_sessions.workout_template_id` becomes null, so sessions and set logs are kept. Confirmation copy says so.
- **Retire:**
  - The old `plans.index/create/show/edit` pages and the `workouts.*` / `workout-exercises.*` user-plan pages redirect to the outline with the right node selected.
  - The library-plan views (`partner.programs.*`) stay.
  - Partner-admin Users pages (`users/index`, `users/show`) link to the outline.
- **Admin user page:** a Plans section (name, type, active, workouts, updated) linking to the outline. The "Active plan" fact links to its node.
- **No Admin Change** is recorded for plan, exercise or partner edits. Admin Change stays two kinds.

**Routes**
- Exercises and Partner Overrides: under `/admin/...` behind `admin`, as today.
- Partner edit: the existing `partners.*` resource (policy-guarded).
- User plans: under `auth, verified`, guarded by the plan policy. There is no `admin` middleware, because partner admins use them too.

## Testing Decisions

- Test through public surfaces only: an HTTP request as a given role and its response or database state, or a module's public call with factory data. No assertions on view internals or query shape.
- **Characterization first, in its own commit (house rule).** Admin exercise store, update and destroy have no tests today. Lock them in before changing them. Lock the partner-admin plan flow (`PlanWebTest`, `PlanActivationTest`) and the partner update (`PartnerManagementCharacterizationTest`) before replacing their pages.

**Seam 1 — Partner edit**
- Updating a partner whose identity has a stored `background_color`, `font_family`, `background_pattern` and dark text colours, through the new form fields only, leaves those columns byte-for-byte unchanged.
- The four colours save. The logo replaces. Unchecking Active deactivates. The House Partner is refused. A partner admin can edit only their own partner.

**Seam 2 — Archived Exercise module**
- Fixtures: an exercise used only in a template, one only in a session exercise, one only in set logs, and one used nowhere. The first three archive and keep every referencing row. The last is deleted.
- Restore clears it.
- `available()` excludes archived exercises from `GET /api/exercises`, from the generator's candidate set and from the plan picker. `GET /api/exercises/{id}` and session detail still return them.
- Bulk returns archived and deleted counts.

**Seam 3 — admin exercise HTTP surface**
- Gallery filters narrow correctly and round-trip through the URL. The slide-over renders for `?edit` and `?create`.
- Create with image, video, difficulty and priority. Update. Validation (priority range, difficulty enum).
- Partner Override edit, clear, link and unlink as super admin. The partner-admin override route still works (it calls the shared module).
- Non-admins get 403.

**Seam 4 — plan policy and outline**
- Matrix: super admin × any app user (allowed), × staff account (404 or 403). Partner admin × own member (allowed), × other partner's member (403). Plain user (403). Library plan: own-partner admin allowed, other partner admin 403.
- Workout and workout-exercise writes as a plain user, or as another partner's admin, get 403. **This closes today's hole.** Add the failing test first.
- Create a Program and a Routine. Activate a Routine while a Program is active: both stay active. Activate a second Program: the first is deactivated.
- Row edit with a partial field set does not reset other fields. Target weight entered in pounds for an imperial user is stored in kilograms.
- Delete a plan that has logged sessions: the sessions and set logs remain.
- Old plan URLs redirect to the outline node.
- Prior art: `tests/Feature/PlanWebTest.php`, `tests/Feature/PlanActivationTest.php`, `tests/Feature/Admin/UserPageTest.php`, `tests/Feature/PartnerExercisePagesTest.php`.

- Concurrent agents: give each worktree its own DB (`phpunit.xml` pins one schema).

## Out of Scope

- Insights, Revenue, and System beyond v1. These are still v2 items from 022.
- Recording plan, exercise or partner edits as Admin Changes. Admin Change stays two kinds.
- Editing or deleting a user's logged sessions or set logs.
- Partner library plans (`/partner/programs`) UI. Only their write authorisation is in scope (story 32).
- A super-admin UI for partner library plans.
- Removing the hidden identity columns, or changing what the mobile API returns for branding.
- Uploading a custom muscle-group image (it stays generated).
- Livewire. The pages are plain form posts. See 022's v2 note.

## Further Notes

- `config/database.php` and `.claude/settings.json` have unrelated local changes. Leave them out.
- **Found while scoping, not in this spec:** partner logo and pattern replacement deletes the old file with `Storage::delete()` on the **default** disk, while uploads go to `public` (`PartnerController` :188/:200/:233/:238, `Api\PartnerController` :120/:162). Old files are probably never removed. Raise it as its own backlog issue.
- **Release note:**
  - Archived exercises disappear from the app's exercise search and pickers.
  - Partner admins' plan pages move to the new outline, which now includes Routines.
- The new terms (Archived Exercise, Partner Override) are in `CONTEXT.md`, committed with this spec.

## Tickets

Work the frontier: any ticket whose blockers are done. Start: 01, 02, 05.

| # | Ticket | Blocked by |
|---|---|---|
| [01](023-super-admin-panel-v2/01-partner-edit.md) | Partner edit: four colours, logo, live preview | — |
| [02](023-super-admin-panel-v2/02-archived-exercise.md) | Archived Exercise: delete never destroys history | — |
| [03](023-super-admin-panel-v2/03-exercise-gallery.md) | Exercise gallery with slide-over editor | 02 |
| [04](023-super-admin-panel-v2/04-partner-overrides.md) | Partner Overrides editable by the super admin | 03 |
| [05](023-super-admin-panel-v2/05-plan-policy.md) | Plan policy: who may manage a user's plans | — |
| [06](023-super-admin-panel-v2/06-plan-outline.md) | Plan outline: plans (view, create, edit, activate, delete) | 05 |
| [07](023-super-admin-panel-v2/07-plan-outline-workouts.md) | Plan outline: workouts and exercise rows | 06, 02 |
| [08](023-super-admin-panel-v2/08-retire-old-plan-pages.md) | Admin user page Plans section; retire old plan pages | 07 |
