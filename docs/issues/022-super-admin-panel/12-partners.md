# 12 — Partners

**Parent:** [022 — Super-admin panel v1](../022-super-admin-panel.md). Terms: [House Partner](../../../CONTEXT.md#house-partner), [Sponsoring Partner](../../../CONTEXT.md#sponsoring-partner).

**What to build:** Partners list (name, kind House / Sponsoring / plain, members, active this week, plan, sponsorship expiry, active flag) and a partner page (members with Activity and Access chips, its admins, plan and expiry, branding preview light and dark, "All members →" opening the Users list filtered by that partner). The super admin can deactivate a partner. Existing create/edit/branding forms move into the new layout unchanged.

**Blocked by:** 02, 04.

**Status:** ready-for-agent

- [x] Kind derived from the House Partner config and the sponsor plan
- [x] Member counts exclude admin / partner-admin accounts
- [x] "All members →" link carries `?partner=`
- [x] Deactivate toggles `is_active` with confirmation
- [x] Existing partner create/edit tests stay green; new feature tests for list, page, deactivate; non-admins 403
