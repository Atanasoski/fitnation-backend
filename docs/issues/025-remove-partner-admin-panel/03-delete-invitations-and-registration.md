# 03 — Delete invitations and web registration

**Parent:** [025 — Remove the partner-admin panel, library-programs UI, invitations and web registration](../025-remove-partner-admin-panel.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** Everything under the parent's *Deleted: invitations* and *Deleted: web registration*:

- Invitations end to end:
  - web routes, controller methods, request, mail and view, model, `Partner::invitations()`, config key;
  - `GET /api/invitations/{token}`, its controller and resource, and the API docs entry;
  - the Invitation section on the super-admin member page.
- A migration dropping `user_invitations`; `down()` recreates it as the original migration did.
- Web registration: `/register`, `RegisteredUserController`, `auth/register`, the `registration-success` route and view, and register links in the guest layout and login page.
- Front end, as a separate PR in the front-end repo: remove `validateInvitation` and its types from `packages/shared`.

**Before merging:** the user confirms the production `user_invitations` table holds nothing worth keeping.

**Blocked by:** 02 (both touch the old `UserController` and `routes/web.php`)

**Status:** back-end done; front-end PR and production-table confirmation pending

- [x] Every deleted route (web and API) is added to 02's 404 test.
- [x] The super-admin member page still renders for a user who was invited before (no Invitation section, no error).
- [x] `user_invitations` does not exist after `migrate`; `migrate:rollback` of the new migration recreates it.
- [x] Tests for deleted code removed or narrowed deliberately; 01's tests pass unchanged.
- [x] `grep -rni "invitation\|register" app resources routes config` shows nothing left over (excluding unrelated hits such as service-provider `register()`).
- [x] `composer test` green; `pint` on changed files.
- [ ] Front-end PR removes `validateInvitation` and its types from `packages/shared` (`front-end/packages/shared/src/api.ts` still calls `/invitations/{token}`, now 404).
- [ ] User confirms the production `user_invitations` table holds nothing worth keeping.
