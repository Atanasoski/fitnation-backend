# 02 — Every user has a partner (House Partner)

**Parent:** [022 — Super-admin panel v1](../022-super-admin-panel.md). Terms: [House Partner](../../../CONTEXT.md#house-partner).

**What to build:** Every non-admin user belongs to a partner. The House Partner is identified by one config value (env, default `1`) instead of the literal `1` in social sign-in. Web registration without an invitation assigns the House Partner instead of `null`. A data migration moves existing non-admin users with no partner to the House Partner; admin and partner-admin accounts keep none.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [x] Characterization tests for social sign-in partner resolution and web registration, committed green against unchanged code in their own commit (house rule)
- [x] House Partner id read from config everywhere the literal was used
- [x] Web registration without invitation → House Partner; with invitation → invitation's partner (unchanged)
- [x] Migration moves partnerless non-admins to the House Partner, leaves admin / partner-admin accounts untouched (feature test)
- [x] Mobile API email registration behaviour unchanged
- [x] `composer test` green
