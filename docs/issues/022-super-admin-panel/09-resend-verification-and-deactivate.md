# 09 — Resend verification, deactivate and restore a user

**Parent:** [022 — Super-admin panel v1](../022-super-admin-panel.md).

**What to build:** On the user page the super admin can resend the verification email to an unverified Unfinished Account, and deactivate (soft-delete) or restore a user. Each confirms first and flashes the result.

**Blocked by:** 06.

**Status:** ready-for-agent

- [ ] Resend only offered and accepted for unverified users; reuses the existing verification notification (`Notification::fake` test)
- [ ] Deactivate soft-deletes; the user then shows as Deleted; restore reverses it
- [ ] Non-admins 403 (feature tests)
