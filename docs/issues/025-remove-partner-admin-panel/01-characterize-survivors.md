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

**Status:** open

- [ ] Gaps found and listed in the PR description; each new test committed green against `dev`, in a test-only commit.
- [ ] `composer test` green.
