# 10 — Access follow-ups: end a Signup Trial, labels, launch grace, Access Source home

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first, plus the Notes / handoff of tickets 01–09 in this folder; these follow-ups close their open items (decided 2026-10-09).

**What to build:** Admin and access loose ends from 05/06.

**Blocked by:** None — can start immediately

**Branch:** `fix/subscription-hardening` in `back-end`.

**Status:** ready-for-agent

- [ ] A super admin can end a running Signup Trial from the user page the same way as Complimentary Access (recorded as an Admin Change; it still blocks a later Signup Trial).
- [ ] `Partner::kind()` (and every admin label built from it) calls a deactivated sponsor-plan partner something other than Sponsoring Partner, matching the glossary.
- [ ] `subscriptions:grant-launch-grace` skips users who already had Free Access of either kind, including ended grants (no re-granting what an admin ended).
- [ ] The Access Source rules live outside the Admin services namespace, since `/user` depends on them; admin code uses them from there. No behaviour change in that move.
- [ ] `composer test` green.
