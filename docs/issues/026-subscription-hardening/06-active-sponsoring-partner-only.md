# 06 — Only an active Sponsoring Partner sponsors

**Parent:** [026 — Subscription hardening before enforcement](../026-subscription-hardening.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** A deactivated gym on the sponsor plan no longer gives its members access. See Sponsoring Partner in `CONTEXT.md`.

**Blocked by:** None — can start immediately

**Branch:** `fix/subscription-hardening` in `back-end` (off `dev`; spec and tickets are committed here too).

**Status:** done

- [x] With enforcement on, a member of a deactivated sponsor-plan partner is blocked; reactivating restores access.
- [x] Access Source (admin) and `/user` `is_sponsored_by_gym` agree with the access rule.
- [x] `composer test` green.

## Notes / handoff

- **Rule:** `Partner::isSponsoringMembers()` and its SQL twin `scopeSponsoringMembers()` now require `is_active`. Everything reads through them: `User::entitlements()` (gate), `AccessSources` (per user, batch, `constrain()` counts/filters), `UserResource` `is_sponsored_by_gym`. Reactivating restores access immediately — no stored state.
- **Admin:** a deactivated sponsor's members fall through to their real Access Source (subscription / Signup Trial / Complimentary / None). `scopeSponsorshipExpiringWithin` (Overview "Sponsorships expiring", partners `?expiring=1`) also skips deactivated partners.
- **Left as is:** `Partner::kind()` still labels a deactivated sponsor-plan partner "Sponsoring Partner" (a plan label; docblock says so). Gate it on `is_active` if the glossary label should follow strictly.
- **For mobile:** no payload shape change; a deactivated gym's member now gets `is_sponsored_by_gym:false`, `access_source:"none"` (or their other source), and 403 `subscription_required` when enforced.
- Tests: `RequiresSubscriptionMiddlewareTest` (gate + `/user`, deactivate→reactivate), `Admin/AccessSourceTest` (deactivated-sponsor fixtures), `Admin/OverviewTest` (expiring count).
