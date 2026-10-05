# 022 — Super-admin panel v1

**Area:** back-end / admin panel (Blade + Alpine, TailAdmin shell)
**Severity:** feature
**Status:** ready-for-agent — decisions settled in the design session and prototype of 2026-10-05
**Builds on:** `origin/dev` (Subscriptions / RevenueCat, `SUBSCRIPTIONS_ENFORCED`). Branch off `dev`, not `main`.
**Prototype:** branch `prototype/admin-panel`, variant A (`/admin/prototype/admin-panel?variant=A`, local only). Primary source for layout; do not merge or promote its code.
**Vocabulary:** [Activity Status](../../CONTEXT.md#activity-status), [Access Source](../../CONTEXT.md#access-source), [Complimentary Access](../../CONTEXT.md#complimentary-access), [House Partner](../../CONTEXT.md#house-partner), [Sponsoring Partner](../../CONTEXT.md#sponsoring-partner), [Completed Session](../../CONTEXT.md#completed-session), [Unfinished Account](../../CONTEXT.md#unfinished-account), [Sent Record](../../CONTEXT.md#sent-record), [Device](../../CONTEXT.md#device). Use these words in code and UI.

## Problem Statement

The super admin can't find information. There is no list of users across the
platform — the Users menu only exists for partner admins — so answering "why
can't this person get in?" or "is this person still training?" means poking at
the database. The dashboard is a grid of partner cards that says nothing about
signups, activation, training or the coming paywall. The navigation is flat:
Partners, Exercises, Workout Splits and Workout Preview sit side by side with
no grouping, and much of the data the platform already records (onboarding
timestamps, session statuses, Devices, Sent Records, subscriptions, failed
jobs and webhooks) is not visible anywhere.

## Solution

A rebuilt super-admin side of the panel, on the existing TailAdmin shell and
brand tokens:

- A left sidebar with **Overview · Users · Partners · Content · Insights · System**
  (Insights is a v2 placeholder; System is minimal in v1) and a **global search**
  (⌘K) in the header over users, partners and pages.
- A **Users list** across all partners with Activity Status and Access Source
  chips and URL-settable filters.
- A **User page** that leads with a four-fact strip — Activity Status, Access
  Source, Partner, Active plan — and then everything else about that person on
  one screen, with the few admin actions v1 allows.
- An **Overview** that answers "is everything OK?": KPIs with week-over-week
  change, Needs attention, a Paywall card, and the activation funnel. Every
  number that names people opens the filtered Users list.
- A small **Partners** section and a minimal **System** page for retrying failed
  jobs and webhooks.

Any user reachable in ≤ 2 clicks (0 with search) — the question the prototype
answered.

## User Stories

Search & navigation
1. As a super admin, I want a sidebar with Overview, Users, Partners, Content, Insights and System, so that I always know where things live.
2. As a super admin, I want Exercises, Workout Splits, Generator Preview and the lookup tables grouped under Content, so that catalogue work stops cluttering the top level.
3. As a super admin, I want ⌘K (and a header search button) to open a palette, so that I can jump anywhere from any page.
4. As a super admin, I want the palette to find users by name or email, so that I can open a person in zero clicks.
5. As a super admin, I want each user result to show their Activity Status and Access Source chips, so that I often get my answer without opening the page.
6. As a super admin, I want the palette to find partners by name and pages by title, so that one box covers everything.
7. As a super admin, I want to move through results with arrow keys and open with Enter, so that search is keyboard-only.
8. As a super admin, I want Insights to show a clear "coming soon" placeholder, so that the nav reflects the plan without dead links.

Users list
9. As a super admin, I want one list of every user on the platform, so that I'm not limited to one partner at a time.
10. As a super admin, I want admin and partner-admin accounts left out of it, so that the list and its counts are about the people using the app.
11. As a super admin, I want columns for name, email, partner, signup date, last Completed Session, Completed Sessions in 30 days, Activity Status and Access Source, so that I can scan someone's state in one row.
12. As a super admin, I want to filter by partner, Activity Status, Access Source, fitness goal, training experience, Device platform, sign-in method (social / password) and stuck session, so that I can find any group.
13. As a super admin, I want every filter reflected in the URL, so that Overview links, bookmarks and shared links open the same list.
14. As a super admin, I want a text search box on the list too, so that I can narrow a filtered list by name or email.
15. As a super admin, I want the list paginated and sortable by signup and last Completed Session, so that it stays fast and useful as users grow.
16. As a super admin, I want deleted users shown only when I filter for Deleted, so that they don't clutter the default list.

User page
17. As a super admin, I want the top of the user page to be the four-fact strip — Activity Status, Access Source with its detail line, Partner with its kind (House / Sponsoring), Active plan with split and week — so that the main question is answered before I scroll.
18. As a super admin, I want the Access Source detail line to say e.g. "Yearly · renews 12 Mar", "Cancelled, paid until 3 Nov", "Complimentary until 1 Dec · granted by X", so that I understand access without opening RevenueCat.
19. As a super admin, I want to see the user's profile (goal, experience, gender, age, height, weight, training days, workout duration, Unit System), so that I know who they are.
20. As a super admin, I want their recent sessions with status, date and duration, so that I can see how they actually train.
21. As a super admin, I want sessions stuck `active` flagged, so that I spot app problems.
22. As a super admin, I want their Personal Records, so that I can see progress.
23. As a super admin, I want their Devices (platform, app version, timezone, last seen, push on/off), so that I can debug push and old-build problems.
24. As a super admin, I want their recent Sent Records, so that I know which nudges and emails they received.
25. As a super admin, I want their invitation (who invited, when, accepted), so that I know how they arrived.
26. As a super admin, I want a "Grants & partner changes" history (who, when, until, reason), so that free access and moves are accountable.
27. As a super admin, I want a back link to the list I came from with its filters intact, so that I can work through a filtered list.

Actions
28. As a super admin, I want to grant or extend Complimentary Access until a date with a reason, so that I can unblock someone without payment.
29. As a super admin, I want to end Complimentary Access early, so that I can undo a grant.
30. As a super admin, I want to resend the verification email to an Unfinished Account, so that I can help someone stuck at signup.
31. As a super admin, I want to move a user to another partner with a reason, so that I can fix wrong assignments.
32. As a super admin, I want to deactivate (soft-delete) and restore a user, so that I can handle abuse and mistakes.
33. As a super admin, I want each action to confirm before it runs and show a success message after, so that I don't act by accident.

Overview
34. As a super admin, I want KPIs — users, signups in 7 days, active this week, Completed Sessions in 7 days — each with change vs the previous week, so that I see the trend at a glance.
35. As a super admin, I want the activation funnel (signed up → verified → onboarded → first Completed Session → trained in their second week) for recent signups, so that I see where people drop off.
36. As a super admin, I want a Paywall card showing whether subscriptions are enforced and how many users have Access Source None, so that I know who the paywall would stop.
37. As a super admin, I want that None count to open the Users list filtered to None, so that I can act on them (e.g. grant Complimentary Access).
38. As a super admin, I want Needs attention to list failed jobs, failed webhooks, Unfinished Accounts piling up, stuck sessions and sponsorships expiring within 30 days, each with a count, so that problems find me.
39. As a super admin, I want each Needs attention row to open the filtered Users list or the System page, so that every alert leads somewhere.
40. As a super admin, I want the Overview numbers to load quickly even as data grows, so that it stays the first page I open.

Partners
41. As a super admin, I want a partner list with name, kind (House / Sponsoring), member count, active-this-week count, plan and sponsorship expiry, and active flag, so that I see every partner in one table.
42. As a super admin, I want a partner page with its members (with Activity and Access chips), its admins, its plan and sponsorship expiry, and its branding preview (light and dark), so that I see everything about one partner.
43. As a super admin, I want "All members →" on the partner page to open the Users list filtered by that partner, so that I can work with its members in full.
44. As a super admin, I want to create and edit partners and their branding as today, so that nothing I can do now is lost.
45. As a super admin, I want to deactivate a partner, so that I can stop one without deleting data.

System
46. As a super admin, I want a System page listing failed queue jobs (job, queue, when, first line of the error), so that I see what broke.
47. As a super admin, I want to retry or delete a failed job, so that I can recover without a terminal.
48. As a super admin, I want to see failed RevenueCat webhook calls and replay one or all, so that subscription state stays correct.

Data integrity
49. As a super admin, I want every user to belong to a partner — signups without a gym go to the House Partner — so that "partner" is never blank and counts add up.
50. As the business, I want existing partnerless users moved to the House Partner, so that history matches the rule.

Access control
51. As the business, I want every page and action here available only to the `admin` role, so that partner admins and users get a 403.
52. As a partner admin, I want my side of the panel to keep working as it does today, so that this change doesn't affect me.

## Implementation Decisions

**Branch and shell**
- Build on a branch off `origin/dev` (fetch first). Keep the TailAdmin layout, header, dark mode and brand tokens; rewrite only the super-admin navigation and pages. The partner-admin side is untouched.
- The menu builder gains grouping (Content as a group) for admins; partner-admin items stay as they are.
- Delete TailAdmin demo leftovers (ecommerce components, example tables) that nothing uses.
- Rewrite variant A properly; don't copy prototype code. The prototype's layout, strip order and chip styles are the reference.

**Activity Status module** (new, `App\Services\Admin` or the nearest existing area)
- One module owning the rule in CONTEXT.md, with two faces that must agree:
  - *for a user* → one status (Unfinished, New, Active, Slipping, Inactive, Deleted);
  - *as a query constraint* → "users whose status is X", in SQL, so the list can filter and the Overview can count without loading every user.
- Inputs: `deleted_at`, `email_verified_at`, `onboarding_completed_at`, the user's latest Completed Session `completed_at` (same column the Inactivity Nudge reads). "Days" measured on the server clock against `now()` — no per-user local time in v1.
- Boundaries: Active = last Completed Session within 7 days; Slipping = 7–14 days; Inactive = over 14 days, or onboarded over 14 days ago with none; New = onboarded within 14 days with none; Unfinished = unverified, or verified and not onboarded.
- Owns its own loading (house rule): callers never pre-aggregate "last Completed Session" for it.

**Access Source module** (new, next to Activity Status)
- Same two faces: *for a user* → one Access Source plus the facts for its detail line; *as a query constraint* → users with Source X.
- Read off the real rules **ignoring** `SUBSCRIPTIONS_ENFORCED`: subscription active (Active → Subscribed or Trial by period type; Cancelled → Cancelled, paid until; Billing issue; Paused — each only while `expires_at` is in the future, matching `Subscription::isActive()`), then Sponsored (partner is a Sponsoring Partner and the sponsorship hasn't expired), then Complimentary (`users.grace_period_ends_at` in the future), else None.
- Precedence when several hold: subscription states first, then Sponsored, then Complimentary. One label per user.
- Must not change `User::entitlements()` or the API's behaviour; it reads the same facts. If the rule is duplicated, factor the shared predicate rather than diverge.

**Admin Overview module** (new)
- Returns one structure: KPIs (current and previous week), funnel counts for users who signed up in the last 28 days, Paywall (enforced flag + None count), Needs attention counts (failed jobs, failed webhooks, Unfinished Accounts, stuck sessions, sponsorships expiring ≤ 30 days).
- Live queries, cached ~10 minutes. No snapshot tables or scheduled jobs (Laravel Cloud scales to zero).
- Stuck session = status `active`, started more than 24 hours ago.
- Weeks are Monday–Sunday, as in Weekly Progress.

**Users list**
- Filters are query parameters (partner, activity, access, goal, experience, platform, signin, stuck, q, sort, deleted). Overview links and partner "All members →" build these URLs.
- Platform filter = users with a Device on that platform.
- Excludes users holding `admin` or `partner_admin` roles.

**Global search**
- Server endpoint returning up to ~8 users (name/email match), partners (name) and pages (static list), each user with its two chips. Alpine palette on ⌘K / Ctrl-K and a header button; arrow keys + Enter. Admin only.

**House Partner**
- Identified by one config value (env, default the current id `1`) instead of the literal `1` in social sign-in.
- Web registration without an invitation assigns the House Partner instead of null.
- Data migration: non-admin users with no partner move to the House Partner. Admin and partner-admin accounts keep none.

**Schema change: admin change record**
- New table recording admin changes to a user, two kinds only: `complimentary_access` (until, reason) and `partner_change` (from partner, to partner, reason). Columns: user, admin (who), kind, the kind's values, reason, timestamps. Shown on the user page. Not a general audit log.
- Granting/extending/ending Complimentary Access writes `users.grace_period_ends_at` (column keeps its name for now) and a record.

**Actions** (POST/PATCH/DELETE, admin only, each redirects back with a flash)
- Complimentary Access: grant/extend (date in the future, reason required), end now.
- Resend verification: only for unverified Unfinished Accounts; reuses the existing verification notification.
- Change partner: target partner must be active; reason required.
- Deactivate / restore: soft delete / restore.
- Partner deactivate: toggles `is_active`.
- System: retry / delete a failed job (Laravel's queue retry/forget); replay a failed webhook call or all of them (same logic as `ReplayFailedRevenueCatWebhooks`, shared rather than copied).

**Partners**
- List and page as in the stories; existing create/edit/branding forms move under the new layout unchanged.
- Partner kind: House if it is the configured House Partner, Sponsoring if its plan is sponsor, otherwise plain.

**Routes**
- All super-admin pages under `/admin/...` behind `auth`, `verified` and an admin-role check. The old admin dashboard view is replaced by Overview; `/dashboard` keeps sending partner admins to their dashboard.

## Testing Decisions

- Good tests here go through public surfaces only: an HTTP request as an admin and the response, or a module's public call with factory data. No assertions on query shape, view internals or private methods.
- Freeze time (`Carbon::setTestNow` / `travelTo`) in every test touching day boundaries.

**Seam 1 — admin HTTP surface** (feature tests, `actingAs` an admin over the web guard)
- Each page renders for an admin; a partner admin and a plain user get 403.
- Users list: each filter narrows correctly; filters round-trip through the URL; admin/partner-admin accounts never appear; deleted users only with the Deleted filter.
- User page: shows the four-fact strip values for a fixture user.
- Search: finds by name and email, returns chips, admin only.
- Each action: happy path changes state and writes the admin change record where required; validation failures (past date, missing reason, inactive target partner) are rejected; non-admins get 403.
- System: retry pushes a failed job back on the queue; replay clears the exception and dispatches the webhook job (`Queue::fake`).
- House Partner: web registration without invitation lands on the House Partner; the data migration moves partnerless non-admins and leaves admins alone.
- Prior art: `tests/Feature/WorkoutPreviewTest.php`, `tests/Feature/PlanWebTest.php`, `tests/Feature/WorkoutSplitTest.php`.

**Seam 2 — Activity Status and Access Source modules**
- A table of fixture users, one per status/source and one on each side of every day boundary (6/7/8, 13/14/15 days; expiry just past and just ahead).
- For each fixture: the per-user answer equals the expected label, **and** the query constraint for that label returns exactly the users with that label. This agreement test is the point of the seam.
- Access Source is unchanged by flipping `subscriptions.enforced`.
- Precedence: a sponsored user with an active subscription reads Subscribed; a Complimentary user with an active subscription reads Subscribed.
- Prior art: `tests/Unit/UserEntitlementsTest.php`, `tests/Unit/SubscriptionModelTest.php` (on dev), `tests/Feature/PlanActivationTest.php`.

**Seam 3 — Admin Overview module**
- Fixture data with known counts for this week and last; assert KPIs, deltas, funnel stages, None count and each Needs attention count.
- The Overview page test only checks the numbers appear and the links carry the right filters.

- Characterization first where existing behaviour is touched (social sign-in partner resolution, web registration): lock current behaviour in its own green commit before changing it (house rule).
- Concurrent agents: give each worktree its own DB (`phpunit.xml` pins one schema).

## Out of Scope

- Insights (retention cohorts, generator quality, most-skipped exercises, nudge effectiveness, demographics) — v2; nav shows a placeholder.
- Revenue (subscription counts by plan, trial → paid, churn, revenue by store/product) — v2.
- System beyond failed jobs/webhooks (app-version spread, dead push tokens, admin/role management) — v2.
- Partner-health metrics and invitation rates — dropped (only ~2 partners).
- Logging in as a user; editing a user's training data, plans or sessions.
- A general audit log of admin actions.
- Snapshot/summary tables or scheduled metric jobs.
- Per-user local-time day boundaries for Activity Status.
- Any change to the partner-admin side or the mobile API.
- Renaming `users.grace_period_ends_at`.

## Further Notes

- `origin/dev` was last seen 19 commits ahead of `main` with RevenueCat merged; the local ref may be stale — fetch before branching.
- `config/database.php` has an unrelated uncommitted change in the working tree; leave it out of this work.
- The CONTEXT.md glossary additions (House Partner, Sponsoring Partner, Access Source, Complimentary Access, Activity Status) are uncommitted; commit them as the first commit on the work branch.
- Mobile clients are unaffected: Access Source is admin-only and does not alter entitlements.

## Tickets

Work the frontier: any ticket whose blockers are done. Start: 01, 02.

| # | Ticket | Blocked by |
|---|---|---|
| [01](022-super-admin-panel/01-admin-shell-and-navigation.md) | Admin shell and navigation | — |
| [02](022-super-admin-panel/02-house-partner.md) | Every user has a partner (House Partner) | — |
| [03](022-super-admin-panel/03-users-list-activity-status.md) | Users list with Activity Status | 01 |
| [04](022-super-admin-panel/04-access-source.md) | Access Source on the Users list | 03 |
| [05](022-super-admin-panel/05-users-list-filters.md) | Remaining Users list filters | 03 |
| [06](022-super-admin-panel/06-user-page.md) | User page | 02, 04 |
| [07](022-super-admin-panel/07-global-search.md) | Global search (⌘K) | 04 |
| [08](022-super-admin-panel/08-complimentary-access-and-partner-change.md) | Complimentary Access and partner changes | 06 |
| [09](022-super-admin-panel/09-resend-verification-and-deactivate.md) | Resend verification, deactivate and restore | 06 |
| [10](022-super-admin-panel/10-system-failed-jobs-and-webhooks.md) | System: failed jobs and webhooks | 01 |
| [11](022-super-admin-panel/11-overview.md) | Overview | 04, 05, 10 |
| [12](022-super-admin-panel/12-partners.md) | Partners | 02, 04 |
