# 05 — System: restructure, app versions, devices and push, Old Build

**Parent:** [024 — Super-admin panel: Insights, Revenue, and the rest of System](../024-super-admin-insights-revenue-system.md). Read it first; its Implementation and Testing Decisions are binding.

**What to build:** Characterization first, in its own commit, green against the unchanged page: extend `SystemTest` to lock failed jobs and failed webhooks (list, retry, forget). Then restructure the page as prototype System A:
- summary rows for failed jobs and failed webhooks at the top;
- App versions;
- Devices and push;
- an Admins placeholder slot (ticket 06);
- the v1 tables, unchanged.

The rest of the work:
- New `Fleet::summary()`:
  - versions by `app_version` × `build_profile`, with iOS and Android counts and latest / old flags;
  - the platform split;
  - the push share over app users with at least one Device;
  - the count of users on an Old Build.
- **Old Build** rule, in one place as a query constraint: a production version older than the two newest production versions any Device reports. Compare versions semantically. Preview and development builds never count; a null version is "unknown".
- Users list filter `old_build=1` through the same constraint, decided by each user's most recently seen Device. System links "N users are on an Old Build" to it.

**Blocked by:** None — can start immediately.

**Status:** done

- [x] Characterization commit first.
- [x] `Fleet` and the Old Build constraint tested per Seam 3. The count equals the `old_build=1` Users list result.
- [x] System renders the new sections for a super admin; 403 for others; retry and forget still work.
