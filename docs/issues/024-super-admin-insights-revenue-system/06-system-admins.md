# 06 — System: Admins list, grant and revoke

**Parent:** [024 — Super-admin panel: Insights, Revenue, and the rest of System](../024-super-admin-insights-revenue-system.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** The Admins section on System.
- New `Admins` module with `list()`, `grant(user, role, ?partner, by)` and `revoke(user, role, by)`.
- The list shows every super admin and partner admin: name, email, role, partner, and since when (the role row's timestamp).
- **Grant** finds an existing, non-deleted user by exact email.
  - Super admin needs nothing more.
  - Partner admin requires a partner and sets `users.partner_id` to it.
  - Granting a role the user already has is a no-op with a message.
  - The form warns that a granted user leaves every app-user count.
- **Revoke** asks for confirmation. It refuses your own super-admin role and the last super admin. Revoking partner admin leaves `partner_id` as it is.
- Form posts redirect back with a flash message. Log each grant and revoke at info level. **No Admin Change** is recorded.

**Blocked by:** 05 (same page)

**Status:** ready-for-agent

- [ ] HTTP tests per Seam 4 (`AdminsTest`): grant either role; an unknown email gives a validation error; revoke; self-revoke and last-super-admin are refused; the granted user leaves `User::appUsers()`; a partner admin cannot reach the endpoints.
- [ ] Section renders on System and matches prototype System A.
