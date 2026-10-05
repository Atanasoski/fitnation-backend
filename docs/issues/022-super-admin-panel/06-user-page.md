# 06 — User page

**Parent:** [022 — Super-admin panel v1](../022-super-admin-panel.md). Terms: [Sent Record](../../../CONTEXT.md#sent-record), [Device](../../../CONTEXT.md#device).

**What to build:** Clicking a user opens one page that leads with the four-fact strip — Activity Status, Access Source with its detail line, Partner with its kind (House / Sponsoring), Active plan with split and week — and below it: profile (goal, experience, gender, age, height, weight, training days, duration, Unit System), recent sessions (stuck ones flagged), Personal Records, Devices (platform, app version, timezone, last seen, push on/off), recent Sent Records, invitation. A back link returns to the list with its filters intact.

**Blocked by:** 02, 04.

**Status:** ready-for-agent

- [ ] Strip shows the right four values for fixture users of each kind (feature test)
- [ ] Detail line examples render: renews date, "Cancelled, paid until", "Complimentary until"
- [ ] Measurements displayed in the user's Unit System (ADR-0001: convert at the boundary)
- [ ] Stuck sessions flagged
- [ ] Back link carries the originating list's query string
- [ ] Admin-only; admin/partner-admin accounts are not viewable as users (404)
