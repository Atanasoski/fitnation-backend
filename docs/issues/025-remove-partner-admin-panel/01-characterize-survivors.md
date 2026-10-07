# 01 — Characterize what survives

**Parent:** [025 — Remove the partner-admin panel, library-programs UI, invitations and web registration](../025-remove-partner-admin-panel.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** Tests only, committed green against unchanged code (house rule), so 02 and 03 can show they deleted nothing that matters. Check existing coverage first and add only the gaps.

- **Web login and landing:** a super admin signs in and lands on `admin.overview` (via `/dashboard`); an app user is refused with the mobile-app message.
- **Plan outline as super admin:** for an app user's plan, `plans.index`, `plans.show`, create, update, activate, destroy, and the `workouts.*` / `workout-exercises.*` writes the outline posts to.
- **Partner create/edit as super admin:** create, store, edit, update, destroy succeed. Record where each redirects today.
- **API library and routines:** `GET /api/programs/library`, `POST /api/programs/{plan}/clone`, `GET /api/routines`, `GET /api/routines/{plan}`. Lock payloads for a fixture partner with one Program and one Routine library plan, ids read from fixtures.
- **Partner Overrides as super admin:** link, update (with a file), clear, unlink, and the validation rules that come from `UpdatePartnerExerciseRequest::overrideRules()` (one rejected file type, one oversized file).
- **Super-admin member page:** renders for a user with and without an invitation (the Invitation section goes in 03).
- **`partner_admin` as a fixture:** a partner admin is excluded from `User::appUsers()` and gets 403 on `/admin`.

**Blocked by:** —

**Branch:** `feat/remove-partner-admin-panel` (spec and tickets are committed here too).

**Status:** done

- [x] Gaps found and listed below (copy into the PR description when the PR is opened); each new test committed green against `dev`, in a test-only commit (`3e4588c`).
- [x] `composer test` green.

## Gaps added / handoff

Gaps that existing coverage missed, added in `3e4588c`:

- `tests/Feature/Auth/WebLoginCharacterizationTest.php`: super admin lands on `admin.overview` via `/dashboard`; app user refused with the mobile-app message.
- `tests/Feature/SuperAdminPlanOutlineCharacterizationTest.php`: plan pages, store/update/activate/destroy, `workouts.*` / `workout-exercises.*` writes, and the old pages' redirects into the outline, as a super admin.
- `tests/Feature/PartnerManagementCharacterizationTest.php`: partner delete (and delete refused with users) as a super admin, with the current `partners.index` redirect.
- `tests/Feature/LibraryApiCharacterizationTest.php`: Program Library, clone and Routines API payloads, by equality.
- `tests/Feature/Admin/PartnerOverridesTest.php`: super admin uploads a partner video, every `overrideRules()` rule refused (incl. wrong file type, oversized file), files at the size limit accepted.
- `tests/Feature/PartnerAdminFixtureCharacterizationTest.php`: `partner_admin` is not an app user and gets 403 on `/admin`.

Already covered, not duplicated: partner create/store/edit/update redirects (`PartnerManagementCharacterizationTest`), member page with and without an invitation (`Admin/UserPageTest`).

Follow-ups the later tickets must act on deliberately:

- **02:** the 5 `assertRedirect(route('partners.index'))` asserts in `tests/Feature/PartnerManagementCharacterizationTest.php` (create, update, update-leaves-identity, delete, delete-refused-with-users) change to `route('admin.partners.index')` when the `partners.index` page goes. Change the expected route only; keep the rest of each assertion.
- **03:** narrow the two invitation tests in `tests/Feature/Admin/UserPageTest.php` (`test_the_invitation_shows_who_invited_when_and_whether_accepted`, `test_a_user_who_joined_without_an_invitation_says_so`) when the Invitation section is removed: keep that the page renders for both users, drop the Invitation-section asserts, and say so in the report.
