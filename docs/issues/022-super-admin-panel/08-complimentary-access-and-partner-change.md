# 08 — Complimentary Access and partner changes

**Parent:** [022 — Super-admin panel v1](../022-super-admin-panel.md). Terms: [Complimentary Access](../../../CONTEXT.md#complimentary-access).

**What to build:** On the user page the super admin can grant or extend Complimentary Access until a date with a reason, end it now, or move the user to another partner with a reason. Each is recorded (who, when, until / from→to, reason) in a new admin change record, and a "Grants & partner changes" section on the user page shows the history.

**Blocked by:** 06.

**Status:** ready-for-agent

- [ ] Migration for the admin change record: user, admin, kind (`complimentary_access` | `partner_change`), kind values, reason, timestamps
- [ ] Grant/extend writes `users.grace_period_ends_at` and a record; date must be in the future, reason required
- [ ] End now clears access and writes a record
- [ ] Change partner: target must be active, reason required, record holds from and to
- [ ] Access Source on the user page reflects the change immediately
- [ ] Each action confirms first and flashes success; validation failures rejected; non-admins 403 (feature tests)
