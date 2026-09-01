# 013 — `FitnessMetricsService` is wide, not deep

**Area:** back-end / services
**Severity:** low–medium (maintainability, test cost, one latent inconsistency)
**Status:** done — five modules under `App\Services\FitnessMetrics`; see *Resolved* below
**Independent:** touches no file any other open issue touches.

## Read this first

This is the largest item in the backlog and the one with the most judgment in it.
**Agree the two decisions in "Decide before coding" with a human before writing code.**
Extracting on autopilot will produce three modules that are wrong in the same way the
current one is.

## Problem

`app/Services/FitnessMetricsService.php` is 896 lines, 18 private methods, and exactly
two public members — a constructor at `:15` and `getMetrics()` at `:23`. A small
interface over a large implementation reads as depth, but depth is *leverage*, and
there is none here: every caller wants one third of it and every test pays for all
three.

`getMetrics()` returns three unrelated computations glued into one array —
`getStrengthScore()` (`:35`), `getStrengthBalance()` (`:83`), `getWeeklyProgress()`
(`:139`). They share nothing but the user, and do not even agree on their input:

```php
:262   ->whereNotNull('workout_sessions.completed_at')          // getUserSetLogs()
:680   ->where('workout_sessions.status', WorkoutSessionStatus::Completed)  // getUserSetLogsForUser()
```

The first feeds your own score; the second feeds the percentile cohort you are ranked
against. A session that is `Completed` with a null `completed_at`, or vice versa, counts
for one and not the other. Nothing in the interface reveals this.

### Duplication inside the file

- `getUserSetLogs()` (`:254`) vs `getUserSetLogsForUser()` (`:672`) — same five joins,
  differing only in the predicate above.
- `getMuscleGroupVolumes()` (`:305`) vs `getMuscleGroupVolumesForUser()` (`:778`).
- `calculateStrengthPercentile()` (`:482`) vs `calculateBalancePercentile()` (`:698`) —
  roughly 40 lines each, identical but for the scoring function.

### Duplication outside it

`UserController::getWeeklyWorkoutFrequency()` (`app/Http/Controllers/UserController.php:225`)
reimplements `getHistoricalWeeklyProgress()` (`:856`) line for line — same week bounds,
same "group by week manually to avoid MySQL-specific functions" comment, same zero-fill
— while holding a `FitnessMetricsService` instance three lines earlier (`:81`) whose
result already contains that data.

### Test cost

`tests/Feature/FitnessMetricsTest.php` is 635 lines for 12 tests, all full HTTP
round-trips, because nothing is reachable except through `getMetrics()`. To exercise
`calculateBalancePercentage()` (`:353`) — a pure function of an array — you must build
muscle groups, exercises, sessions and set logs and call the endpoint.
`calculateStrengthPercentile()` (`:482`) additionally needs **ten comparable users with
five completed sessions each** before it returns anything but null, so
`test_percentile_not_calculated_with_insufficient_comparable_users` is really asserting
that the fixture is too small.

`Api/FitnessMetricsController.php:20` does `new FitnessMetricsService($user)` rather
than resolving from the container, so it cannot be substituted in a controller test.

## Decide before coding

1. **What is a completed session?** `completed_at IS NOT NULL` or
   `status = Completed`? Both are in use. Picking one changes existing users' scores
   and percentiles — check how many rows disagree in production before choosing, and
   say so in the PR.
2. **Three modules or one?** The seams are obvious (score / balance / weekly progress),
   but splitting is only worth it if callers actually want them separately. Today one
   endpoint wants all three and `UserController` wants a slice. Confirm the split earns
   itself rather than assuming it does.

## Fix

Assuming the split is agreed: three modules under `app/Services/FitnessMetrics/`, over
one shared module that settles what a completed set log is and is the only place either
predicate appears. Collapse the four duplicate pairs as they move. Point
`UserController` at the service result it already has instead of re-deriving it. Inject
`FitnessMetricsService` rather than `new`-ing it.

Keep `getMetrics()` as a façade returning the same array — the endpoint's payload must
not change.

## Tests

- Lock the `/api/user/fitness-metrics` payload for a fixed fixture before touching
  anything. It must be byte-identical afterwards.
- As each calculation moves behind its own interface, add direct tests for the pure
  parts — `calculateBalancePercentage`, `determineStrengthLevel`, `getMuscleGroupPercentages`
  — with arrays rather than fixtures. That reduction in test setup is the main return on
  this work; if it does not materialise, the split was not worth doing.
- A test pinning the chosen definition of "completed", so the two predicates cannot
  drift apart again.

## Notes

- `app/Services/WorkoutSession/SessionDetail.php` is the precedent for module shape,
  naming and placement.
- Everything stays in Canonical Units; the resource formats. See
  [ADR-0001](../adr/0001-convert-units-at-the-http-boundary.md).
- `resources/views/users/show.blade.php` is a clean consumer — it reads the metrics
  array by index and does no arithmetic. Its trend→colour mapping at `:193` is
  presentation over the `trend` value the service decides at `:204`, not duplication.
  Leave it alone; it is the one caller not making this worse.

## Resolved

`FitnessMetricsService` went from 896 lines to 45 — a façade returning the same
three-key array over `StrengthScore`, `StrengthBalance`, `WeeklyProgress` and
`PartnerCohort`, all under `app/Services/FitnessMetrics/`.

**Decision 1 — what "completed" means: `status = Completed`.**
`CompletedSessions` is the only place it is decided, and its docblock gives the
reason: that is what `Api\WorkoutSessionController::complete()` writes and what
every other consumer already reads. The `completed_at IS NOT NULL` variant is
gone.

**Decision 2 — three modules, plus two the issue did not anticipate.**
`CompletedSessions` holds the shared predicate; `PartnerCohort` holds the
~40 lines that `calculateStrengthPercentile` and `calculateBalancePercentile`
had a copy of each. All four duplicate pairs named above collapsed.

`UserController` no longer reimplements `getHistoricalWeeklyProgress`; it
injects the service and asks `WeeklyProgress` for its 12 weeks directly, with a
comment explaining why it does not read `historical_weeks` off the payload (that
one is fixed at 8 and label-only). Covered by
`tests/Feature/UserShowWeeklyChartTest.php`.

`RECENT_DAYS = 30` unifies the window across the metrics. This is not a
behaviour change: the pre-refactor code already used `subDays(30)` at every site
that had a window, and weekly progress uses week boundaries, which is a
different thing.

Unit tests now exist for the pure parts —
`tests/Unit/FitnessMetrics/{StrengthScore,StrengthBalance,WeeklyProgress}Test.php`
— which was the main return this issue was after.

Landed as `fix/fitness-metrics-modules` (PR #41).

### Two things found while reviewing the merge

1. **`strength_balance.muscle_groups` changed key order.** Groups carrying
   volume now come first; before, they were interleaved with the zero-filled
   ones. Same keys, same values. Both `BalanceModal`s re-sort by percentage
   before rendering (`apps/web/src/components/BalanceModal.tsx:90`, mobile
   `:84`), so no client can observe it — but the issue asked for a
   byte-identical payload and this is not one. Recorded rather than reverted:
   the new order is arguably better, and nothing depends on either.

2. **`FitnessMetricsPayloadTest` does not characterize.** Replayed against
   `6f660e7^` it fails on exactly the ordering above, so it was written to
   describe the post-refactor payload rather than to lock the pre-refactor one.
   It is a perfectly good regression test going forward; it just never provided
   the guarantee it was named for, which is why the ordering change went
   unnoticed. See the house rules in the README — the test has to be committed
   green against unchanged code for that guarantee to exist.
