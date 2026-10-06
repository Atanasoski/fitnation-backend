# 024 — Super-admin panel: Insights, Revenue, and the rest of System

**Area:** back-end / admin panel (Blade + Alpine + ApexCharts, TailAdmin shell)
**Severity:** feature
**Status:** done on `feat/super-admin-insights` (tickets 01–06); PR pending. Decisions settled in the prototype session of 2026-10-05/06.
**Builds on:** [022](022-super-admin-panel.md) (PR #58) and [023](023-super-admin-panel-v2.md) (PR #59), both in `dev`. Branch off `dev`, PR into `dev`.
**Prototype:** branch `prototype/admin-panel-insights`, route `/admin/prototype/insights?area=insights|revenue|system&variant=A|B|C` (local only, admin login). Winners: **Insights A, Revenue B, System A**. The verdict and rejected variants are in commit `92818fc`. Use it as the primary source for layout and copy. Do not merge it or copy its code. Its numbers are fake.
**Vocabulary:** [Completed Session](../../CONTEXT.md#completed-session), [Activity Status](../../CONTEXT.md#activity-status), [Access Source](../../CONTEXT.md#access-source), [Sponsoring Partner](../../CONTEXT.md#sponsoring-partner), [Inactivity Nudge](../../CONTEXT.md#inactivity-nudge), [Sent Record](../../CONTEXT.md#sent-record), [Weekly Summary](../../CONTEXT.md#weekly-summary), [Notification Setting](../../CONTEXT.md#notification-setting), [Device](../../CONTEXT.md#device), [Push Switch](../../CONTEXT.md#push-switch), [Expected Monthly Revenue](../../CONTEXT.md#expected-monthly-revenue) (new), [Old Build](../../CONTEXT.md#old-build) (new). Use these words in code and UI.

## Problem Statement

The v1 Overview answers "is everything OK this week?". It cannot answer the
questions that decide what to build next:

- **Training:**
  - Do people keep training after signing up?
  - How fast do they start?
  - Are generated workouts any good?
  - Which exercises get skipped?
  - Do people train as often as they said they would?
  - Do the Inactivity Nudges bring anyone back?
  - Who are our users?

  The data is all in the database. The Insights page is a "Coming soon" placeholder.
- **Money:**
  - How many people pay?
  - How many are in a trial?
  - What do we expect to earn per month?
  - Which store and product does the money come from?
  - Do trials convert?

  Today the only way to answer these is the RevenueCat dashboard or SQL.
- **The app fleet:**
  - Which app versions are people on?
  - Who is stuck on an old build?
  - How many have push on?
  - Who can sign in to the admin panels?

  None of this is visible. Granting or revoking an admin or partner-admin role takes a database edit.

## Solution

Three pieces in the v1 shell, following the prototype winners:

- **Insights (A): the question wall.** `/admin/insights` gets a **Training** tab:
  - One card per question.
  - Each card leads with its headline answer (one number and one sentence), then one small chart or ranked list, then a one-line footnote saying how it's counted.
  - A **fixed 7 / 30 / 90-day** range control (default 30) applies to the whole tab.
- **Revenue (B): a tab under Insights.** `/admin/insights/revenue`. There is no new sidebar item.
  - Everything is computed from the current state of `subscriptions`, production rows only.
  - It shows:
    - four KPI tiles: Expected Monthly Revenue, paying, in trial, billing issue;
    - a stacked bar of where Expected Monthly Revenue comes from (store × monthly/yearly);
    - trial → paid per plan.
  - Money is shown in **USD only**, using RevenueCat's USD price.
  - A dashed note on the page says what is deliberately missing: no trends over time.
- **System (A): one long page.**
  - v1's Failed jobs and Failed webhooks shrink to one row each at the top, linking to their tables lower down.
  - Then, in order:
    - **App versions in use**, with production Old Builds flagged and a link to those users;
    - **Devices and push**: iOS/Android split and % of users with the Push Switch on;
    - **Admins**: every super admin and partner admin, with **grant** and **revoke** inline.

## User Stories

Insights: page and range
1. As a super admin, I want Insights to open on a Training tab with a Revenue tab beside it, so that all analysis lives in one place.
2. As a super admin, I want one 7 / 30 / 90-day control for the Training tab, defaulting to 30 and kept in the URL, so that every card answers for the same period and I can share the link.
3. As a super admin, I want every card to lead with a headline number and a plain sentence, so that I get the answer without reading a chart.
4. As a super admin, I want a one-line footnote on each card saying how the number is counted, so that I trust it and can explain it.
5. As a super admin, I want only app users counted (no admins or partner admins), so that the numbers match the Users list and the Overview.
6. As a super admin, I want the page to load fast even on a large database, so that I open it casually. Numbers may be up to 10 minutes old.

Insights: retention
7. As a super admin, I want the share of users who signed up in the range and logged a Completed Session in week 1, 2, 4 and 8 after signup, so that I see whether people keep training.
8. As a super admin, I want each week's share computed only over signups old enough to have reached that week, so that recent signups don't drag week 8 to zero.
9. As a super admin, I want the week-4 share as the card's headline, so that one number tracks retention.

Insights: time to first workout
10. As a super admin, I want the median hours from signup to first Completed Session for users who signed up in the range, so that I know how fast people start.
11. As a super admin, I want it compared with the median for the range before, so that I see whether onboarding changes helped.
12. As a super admin, I want the signups bucketed (under 1 h, 1–24 h, 1–3 days, 3–7 days, over 7 days, not yet), so that I see the shape, including who never started.

Insights: workout generator
13. As a super admin, I want the completion rate of generated sessions against all other sessions in the range, so that I know whether the generator's workouts get done.
14. As a super admin, I want the swap rate (sessions regenerated into another) and the cancel rate (cancelled without a replacement), shown separately, so that "didn't like it" and "gave up" are not mixed.

Insights: skipped exercises
15. As a super admin, I want the exercises most often left without a single logged set in Completed Sessions in the range, ranked by rate, with the count, so that I find the ones people avoid.
16. As a super admin, I want exercises included fewer than a minimum number of times left out, so that one-offs don't top the list.
17. As a super admin, I want each exercise name to link to its catalogue entry, so that I can act on it.

Insights: planned vs actual
18. As a super admin, I want users grouped by the training days per week they chose at onboarding, with their average Completed Sessions per week in the range, so that I see how far reality falls from intent.
19. As a super admin, I want the overall share of planned days that happen as the headline, so that one number tracks it.

Insights: nudges
20. As a super admin, I want, for each Inactivity Nudge step (day 3, 7, 14) sent in the range, how many went out and the share whose user logged a Completed Session within 48 hours, so that I know whether nudges work.
21. As a super admin, I want the number of users who have the Weekly Summary turned off, out of all who could get it, so that I know whether the email annoys people.

Insights: who our users are
22. As a super admin, I want the split of onboarded app users by goal, experience, gender and age band, so that I know who we serve.
23. As a super admin, I want "not set" shown as its own row rather than dropped, so that the shares add up.

Revenue
24. As a super admin, I want Expected Monthly Revenue in USD (monthly subscribers' price, plus yearly subscribers' price ÷ 12), so that I know what we expect to earn per month.
25. As a super admin, I want counts of paying users (by monthly and yearly), users in a trial, and users in billing issue, so that I see the subscriber base at a glance.
26. As a super admin, I want the billing-issue count to open the Users list filtered to Access Source "Billing issue", so that I can see who is affected.
27. As a super admin, I want Expected Monthly Revenue split by store (App Store, Google Play) and by plan (monthly, yearly), so that I know where the money comes from.
28. As a super admin, I want trial → paid conversion for each plan, with the counts behind it and users still in trial left out, so that I know whether trials work.
29. As a super admin, I want sandbox subscriptions always excluded, and how many there are shown, so that test purchases never inflate revenue.
30. As a super admin, I want subscriptions with an unknown price counted as subscribers but not as revenue, and how many there are said, so that a missing price is visible, not hidden.
31. As a super admin, I want the page to say plainly that churn over time, paid vs sponsored over time and the paywall funnel are not in this version and why, so that nobody looks for them.

System: app versions
32. As a super admin, I want every app version Devices report, with build profile, iOS and Android counts and a share bar, so that I know what's in the field.
33. As a super admin, I want production versions that are Old Builds flagged, and the newest marked "latest", so that I see who is behind.
34. As a super admin, I want "N users are on an Old Build" to open the Users list filtered to them, so that I can contact or investigate them.
35. As a super admin, I want preview and development builds listed but never counted as old, so that internal testers don't look like stragglers.

System: devices and push
36. As a super admin, I want the iOS / Android split of Devices, so that I know where to test first.
37. As a super admin, I want the share of app users with the Push Switch on, out of those with at least one Device, so that I know how many nudges can reach anyone.

System: admins
38. As a super admin, I want a list of every super admin and partner admin (name, email, role, partner, since when), so that I know who can get in.
39. As a super admin, I want to grant the super-admin role to an existing user by email, so that I can add a colleague without a database edit.
40. As a super admin, I want to grant the partner-admin role for a chosen partner to an existing user, so that a gym can manage its members.
41. As a super admin, I want to revoke either role, with a confirmation, so that someone who leaves loses access.
42. As a super admin, I want to be stopped from revoking my own super-admin role or the last super admin, so that nobody locks the panel.
43. As a super admin, I want to be told that granting a role to an app user takes them out of every app-user count, so that the effect on the numbers is not a surprise.

System: unchanged
44. As a super admin, I want failed jobs and failed webhooks to keep their retry and forget actions, so that nothing in v1 is lost.

## Implementation Decisions

**Branch and shell**
- Branch `feat/…` off `dev`. Render everything in the existing `layouts.app` shell with brand tokens. Rewrite the winners properly; do not copy prototype code. Its layout, card order and copy are the reference.
- Blade + Alpine + ApexCharts (already bundled). **No Livewire.** Pages are server-rendered GETs, and the range is a query parameter.
- Charts: the prototype's palette was validated with the dataviz validator:
  - light: `brand-500`, `orange-500`, `blue-light-700`;
  - dark: `brand-600`, `orange-600`, `blue-light-600`;
  - recessive grid, no dual axes.

  Read the colours from tokens, not hex: the prototype read them off hidden swatch elements, and that works. Light-mode teal and orange are under 3:1 on white, so keep visible value labels on the bars. Re-theme the charts when the dark toggle flips. Dark is the default; check contrast there.
- Routes:
  - `GET /admin/insights?range=7|30|90` (Training tab; the existing named route);
  - `GET /admin/insights/revenue`;
  - the existing `GET /admin/system`;
  - `POST /admin/system/admins` (grant);
  - `DELETE /admin/system/admins/{user}/{role}` (revoke).

  All sit behind `admin`. An invalid `range` falls back to 30.

**Read modules, one per page**
All three follow the `App\Services\Admin\Overview` shape:
- one public static entry point returning one array structure, documented in the docblock;
- live queries, `Cache::remember` for 10 minutes;
- **no snapshot tables and no scheduled jobs**;
- every people count composes `User::appUsers()`;
- training reads compose `WorkoutSession::completed()`.

Never write a second definition of an existing rule.

- **`App\Services\Admin\Insights::summary(int $days)`**: cache key per range. The range is `[now − days, now]`. Keys:
  - `retention`:
    - Cohort: app users who signed up in the range.
    - Week N means days 7(N−1) to 7N−1 after signup.
    - For N in 1, 2, 4, 8, return the share with a Completed Session in week N, the denominator, and whether any signup was old enough. Only signups whose week N has fully passed count in that week's denominator.
  - `first_workout`:
    - The median hours from `users.created_at` to the first Completed Session's `completed_at`, over signups in the range that have one.
    - The same median for the range before, for comparison.
    - The six buckets, with "not yet" = signups in the range with no Completed Session.
  - `generator`:
    - Sessions created in the range, split by `workout_sessions.is_auto_generated`.
    - For each group: total, completed % (Completed Session), swapped %, cancelled %.
    - **Swapped** = a cancelled session some other session points to through `replaced_session_id`. `WorkoutGenerationService::regenerateSession` cancels the old draft and links the new one to it.
    - **Cancelled** = cancelled and not swapped.
    - Drafts and active sessions count in the total only.
  - `skipped`:
    - Over session exercises of Completed Sessions completed in the range, per exercise: times included, times with **no** set log, and the rate.
    - Keep exercises included at least `SKIPPED_MIN_INCLUDED` (100) times.
    - Return the top 10 by rate, with exercise id and name.
  - `planned_vs_actual`:
    - App users with `user_profiles.training_days_per_week` set and onboarded before the range started, grouped by that value.
    - For each group: users, and the average Completed Sessions per week in the range (count ÷ days × 7).
    - Headline: Σ actual ÷ Σ planned over all of them.
  - `nudges`:
    - For each Inactivity Nudge step: the Sent Records (`notifications` rows of the `InactivityNudge` type, `data.step`) created in the range, and the share whose user has a Completed Session within 48 hours after the record. Read through `SentRecord` or its query; do not re-derive the type string.
    - `weekly_summary`: app users who could receive it (onboarded, not deleted), and how many have `weekly_summary_email` off. "Off" means `notification_settings->weekly_summary_email` is explicitly false. Missing means on, and `WeeklySummaries`' query already encodes that. Extract that condition into one shared constraint rather than writing it a second time.
  - `who`:
    - Onboarded app users, grouped by `fitness_goal`, `training_experience` and `gender`, using the enums' labels, plus "Not set".
    - Age bands from `user_profiles.age`: under 18, 18–24, 25–34, 35–44, 45–54, 55+, not set.
    - This card ignores the range: it describes everyone.
- **`App\Services\Admin\Revenue::summary()`**: current state only. Rows are `subscriptions` with `environment = production`. Keys:
  - `sandbox_excluded`.
  - `paying`: active subscriptions (`Subscription::active()`) whose `period_type` is not trial, split monthly / yearly.
  - `trials`.
  - `billing_issue`: Access Source Billing issue, through `AccessSources::constrain`, so the count equals the Users list it links to.
  - `expected_monthly_usd`: see below.
  - `by_store_and_plan`.
  - `conversion`.
  - `unknown_price`: paying subscriptions whose `price` is null; they are counted in `paying` but contribute no revenue.

  The plan (monthly or yearly) is read from `product_id`. Reuse the existing product-to-period mapping (`AccessSources::period`; make it public or move it to one shared place). Do not write a third copy.
- **Expected Monthly Revenue (USD)**:
  - Over paying subscriptions only: Σ `price` for monthly plus Σ `price` ÷ 12 for yearly.
  - `subscriptions.price` is **RevenueCat's USD price of the last transaction** (their webhook field `price`). It is not in `subscriptions.currency`. So the page shows **one USD figure, labelled as RevenueCat's USD conversion**, and never pairs `price` with `currency`.
  - Trials have price 0, so they contribute nothing, which is intended.
  - Cancelled-but-paid-until and billing-issue subscriptions are still active and count as paying. State that in the footnote.
- **Trial → paid per plan** (from current rows; the footnote names the assumption):
  - Converted = production subscriptions on that plan whose `period_type` is no longer trial (any status).
  - Lapsed trial = `period_type` trial and no longer active.
  - Still in trial = active trial; left out.
  - Rate = converted ÷ (converted + lapsed trial).
  - The assumption is that every first purchase starts with the product's 7-day trial. A returning subscriber who was not trial-eligible counts as converted.
- **`App\Services\System\Fleet::summary()`** (name it for the domain):
  - `versions`: Devices grouped by `app_version` × `build_profile`, with iOS and Android counts, `latest` and `old` flags.
  - `platform`: iOS and Android Device counts.
  - `push`: app users with ≥ 1 Device, split by `push_enabled`.
  - `old_build_users`: the count of app users whose most recently seen Device is an Old Build.
  - `versions` and `platform` count only app users' Devices (`User::appUsers()`): staff test phones are deliberately left out.
- **Old Build**:
  - Only a Device on `build_profile = production` can be an Old Build.
  - A production version is an Old Build when it is older, by semantic version compare, than the **two newest production versions any Device reports**.
  - Devices with a null version are listed as "unknown" and never counted as old.
  - Put this rule in one place. The Users list filter (next bullet) and the count use the same query constraint (`constrain()`-style, like `ActivityStatuses`).
- **Users list filter**:
  - Add `old_build=1` to the existing Users list filters, through the Old Build constraint. The System link opens it.
  - The Revenue billing-issue tile links to the existing `access=billing_issue` filter.

**Admins (System A)**
- New module `App\Services\Admin\Admins`:
  - `list()`: users with the `admin` or `partner_admin` role, with their partner and the role row's `created_at` as "since".
  - `grant(User $user, string $role, ?Partner $partner, User $by)`.
  - `revoke(User $user, string $role, User $by)`.
- Rules:
  - A grant targets an existing, non-deleted user found by exact email.
  - Partner admin requires a partner and sets `users.partner_id` to it. Partner admins are scoped by `partner_id` (`PartnerPolicy`).
  - Granting a role the user already has is a no-op with a message.
  - Revoking refuses your own super-admin role and the last remaining super admin.
  - Revoking partner admin leaves `partner_id` as it is.
- Granting a role to an app user makes them staff, so `User::appUsers()` drops them from every count. The grant form says so before submitting.
- **No Admin Change is recorded.** Admin Change stays two kinds (CONTEXT.md). The role row's timestamps are the record. Log each grant and revoke at info level, with who did it.
- Form posts with redirect back and a flash message, like v1's System actions. A user with active sessions keeps them until their next request, where the `admin` middleware decides.

**System page layout**
- The Failed jobs and Failed webhooks tables stay as in v1, including retry and forget. Above the new sections, two compact rows show their counts and jump to them.
- Section order: summary rows → App versions → Devices and push → Admins → Failed jobs table → Failed webhooks table.

**Glossary**
- Add **Expected Monthly Revenue** and **Old Build** to `CONTEXT.md` (Access and Notifications sections), committed with this spec.

## Testing Decisions

- Test external behaviour only: a module's public structure for known factory fixtures, or an HTTP response or database state for a given role. Do not assert on view internals, chart options or query shape.
- Freeze time with `travelTo` as `OverviewTest` does, and read ids from fixtures rather than hardcoding them (house rule).
- **Characterization first, in its own commit (house rule):** `SystemTest` already covers the v1 System page. Extend it to lock the failed-jobs and failed-webhooks behaviour before restructuring the page, and commit that green against the unchanged page.

**Seam 1: `Insights::summary`** (`tests/Feature/Admin/InsightsTest.php`)
- Retention: a signup with sessions in weeks 1 and 4 only. A signup too recent to have reached week 4 is not in week 4's denominator. Cancelled or active sessions never count.
- Time to first workout: a known median. The previous-range median. "Not yet" counts signups with no Completed Session.
- Generator: a regenerated draft counts as swapped, not cancelled. A plain cancel counts as cancelled. Generated and other sessions are split.
- Skipped: an exercise with no set log in a Completed Session counts. One in a cancelled session does not. Below the minimum is excluded.
- Planned vs actual: grouping and per-week average for a fixed range.
- Nudges: a Sent Record followed by a Completed Session 47 h later counts. One at 49 h does not. Steps are split. The Weekly Summary off-count honours the default-on rule.
- Who: "Not set" rows. Age band edges (17 and 18, 24 and 25).
- Staff accounts never count anywhere. Range 7 vs 90 changes what's in.

**Seam 2: `Revenue::summary`** (`tests/Feature/Admin/RevenueTest.php`)
- Expected Monthly Revenue for a monthly and a yearly subscription (yearly ÷ 12). A trial contributes 0. A null price is counted in `paying` and `unknown_price` but not in revenue.
- Sandbox rows are excluded and counted. An expired subscription is not paying.
- The billing-issue count equals `AccessSources::constrain(…, BillingIssue)`.
- Conversion: converted, lapsed trial and still-in-trial fixtures per plan. A Google Play `product:base_plan` id maps to the right plan.
- Store × plan split.

**Seam 3: `Fleet::summary` and the Old Build constraint** (`tests/Feature/Admin/FleetTest.php`)
- Versions grouped with platform counts. With production versions 1.9.0, 1.8.3, 1.8.1 and 1.7.4, the last two are old. A preview build on an old number is not old. A null version is unknown.
- The old-build user count uses each user's most recently seen Device. It equals the Users list `old_build=1` result.
- The push share is over users with a Device.

**Seam 4: HTTP**
- Pages: Insights (each range, and an invalid range), Revenue and System return 200 for a super admin, and 403 for a partner admin and a plain user. Extend `AdminShellTest` or add the pages to it.
- Admins (`tests/Feature/Admin/AdminsTest.php`):
  - grant super admin by email;
  - grant partner admin with a partner, which sets `partner_id`;
  - an unknown email gives a validation error;
  - revoke;
  - refuse self-revoke and last super admin;
  - a granted user disappears from `User::appUsers()`;
  - a partner admin cannot reach the endpoints.

Prior art: `tests/Feature/Admin/OverviewTest.php`, `OverviewPageTest.php`, `SystemTest.php`, `UsersListFiltersTest.php`.

Concurrent agents: give each worktree its own DB (`phpunit.xml` pins one schema).

## Out of Scope

- **Anything needing history of subscription state:**
  - churn (cancelled / expired) per month;
  - billing issues over time;
  - paid vs sponsored users over time;
  - the paywall funnel (onboarding → trial → paid).

  Subscription rows hold only their latest state. v2 accepts that: no daily snapshot (it would reverse v1's "no scheduled jobs", kept because Laravel Cloud scales to zero) and no `webhook_calls` replay.
- **Revenue per purchase currency.** It would need RevenueCat's `price_in_purchased_currency`, which is not stored. Revisit together with history.
- A "saw the paywall" event.
- **Devices retired as dead push tokens.** `ExpoErrors` deletes the Device, so there is nothing to count. Not recorded in v2.
- **Weekly Summary unsubscribes per period.** Only the current setting is stored, so v2 shows the current total.
- A no-nudge baseline for the nudge card (the prototype's dashed "9% without a nudge" line).
- A free date range. Ranges other than 7 / 30 / 90.
- Per-partner Insights or Revenue. Partner-admin access to any of these pages.
- Recording role grants as Admin Changes. Inviting a person who has no account yet.
- Livewire.

## Further Notes

- `config/database.php` and `.claude/settings.json` have unrelated local changes. Leave them out.
- **Found while scoping:** `subscriptions.currency` is the purchase currency, but `price` next to it is USD (RevenueCat's `price`; `price_in_purchased_currency` is not stored). Nothing reads `price` today. The trap is for the next reader: never format `price` in `currency`. Add a column comment or a docblock on the model saying so in this work.
- Insights numbers are cached for 10 minutes per range, with the same cost profile as the Overview. If a query is slow on production-size data, add an index in the ticket that needs it. Do not pre-aggregate.
- The skipped-exercises minimum (100), the nudge window (48 h) and the retention weeks (1, 2, 4, 8) are constants on `Insights`, documented where they are declared.
- The prototype route and views live only on `prototype/admin-panel-insights`. Do not bring them over.

## Tickets

All six tickets are done on `feat/super-admin-insights`.

| # | Ticket | Blocked by | Status |
|---|---|---|---|
| [01](024-super-admin-insights-revenue-system/01-insights-page-retention-first-workout.md) | Insights page: Training tab, range, retention and time to first workout | — | done |
| [02](024-super-admin-insights-revenue-system/02-insights-generator-skipped.md) | Insights: generator quality and most-skipped exercises | 01 | done |
| [03](024-super-admin-insights-revenue-system/03-insights-planned-nudges-who.md) | Insights: planned vs actual, nudges, who our users are | 01 | done |
| [04](024-super-admin-insights-revenue-system/04-revenue-tab.md) | Revenue tab | 01 | done |
| [05](024-super-admin-insights-revenue-system/05-system-fleet-old-build.md) | System: restructure, app versions, devices and push, Old Build | — | done |
| [06](024-super-admin-insights-revenue-system/06-system-admins.md) | System: Admins list, grant and revoke | 05 | done |
