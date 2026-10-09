# 06 — Only an active Sponsoring Partner sponsors

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** A deactivated gym on the sponsor plan no longer gives its members access. See Sponsoring Partner in `CONTEXT.md`.

**Blocked by:** None — can start immediately

**Branch:** `fix/subscription-hardening` in `back-end` (off `dev`; spec and tickets are committed here too).

**Status:** ready-for-agent

- [ ] With enforcement on, a member of a deactivated sponsor-plan partner is blocked; reactivating restores access.
- [ ] Access Source (admin) and `/user` `is_sponsored_by_gym` agree with the access rule.
- [ ] `composer test` green.
