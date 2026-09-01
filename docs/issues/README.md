# Backlog

Two reviews' worth of findings. **001–008** came from a review of the workout-session
code on 2026-08-27. **009–015** came from an architecture review on 2026-08-30 that
looked more widely — plans, metrics, measurement, partner exercises — for shallow
modules and duplicated domain rules.

Each file is self-contained and can be handed to a separate agent with nothing but its
path. Read [House rules](#house-rules) below first; it is what stops seven separately
run agents inventing seven different module shapes.

## Open

| # | Issue | Severity | Touches |
|---|-------|----------|---------|
| [003](003-session-status-transitions-unguarded.md) | Session status transitions are unguarded | medium | `WorkoutSessionStatus`, session controller |
| [004](004-today-endpoint-matches-stale-drafts.md) | `today()` returns stale drafts; `start()` can create a second session | medium | session controller, `WorkoutSession` scopes |
| [005](005-session-resource-duplication-and-pr-detection.md) | Resource duplication + noisy PR detection in `complete()` | low–medium | resources, `complete()` |
| [006](006-reorder-request-validation-n-plus-1.md) | `exists` rules fan out one query per array element | low | `ReorderSessionExercisesRequest` |
| [007](007-set-log-row-id-not-null-followup.md) | Make the new set-log row id NOT NULL and drop the legacy fallback | medium | migration, `ownedSetsFrom`, `LogSetRequest` |
| [008](008-is-completed-uses-stored-target-sets.md) | `is_completed` judged against a different target than the one displayed | low–medium | `SessionDetail`, `UserWorkoutSessionController` |
| [009](009-set-ownership-has-no-owner.md) | "Which row owns this set" is written ten times | medium | `WorkoutSessionExercise`, session controllers, `SessionDetail` |
| [011](011-program-progress-leaks-into-the-resource.md) | Program progress runs three full scans per serialized program | medium | `ProgramResource`, `Plan` |
| [012](012-plan-activation-one-rule-five-scopes.md) | "Only one plan may be active" written eight times, five ways | medium | both `PlanController`s, `WelcomePlanGenerationService` |
| [013](013-fitness-metrics-service-is-wide-not-deep.md) | `FitnessMetricsService` is wide, not deep | low–medium | `FitnessMetricsService`, `UserController` |
| [014](014-partner-exercise-presentation.md) | Partner exercise overrides resolved three times | low | `Exercise`, exercise resources |
| [015](015-measured-field-residue.md) | A second imperial step, and dead validation rules | low | `UnitConversionService`, `MeasurementKind` |

## Done

- [001](001-session-exercise-resource-n-plus-1.md) — `306fcda`
- [002](002-set-logs-keyed-by-exercise-id.md) — `eda4e40`
- [010](010-personal-record-detection-has-no-seam.md) — `fix/personal-records-seam` — three follow-ups recorded in the issue, one of them new

## Suggested order

**Session domain** — these three all rewrite `Api/WorkoutSessionController`. One at a
time, in this order:

1. **003** — smallest, and 004 depends on the transition rules it defines.
2. **004**.
3. **009** — set ownership. Land before 007, which then shrinks to a deletion.

Then **005** (cleanup, last), **007** (needs 002's backfill verified against production
first), and **008** (a one-line change to `SessionDetail`, but it moves progress
percentages, so give it its own PR).

**Everything else runs in parallel.** 011, 012, 013, 014 and 015 touch disjoint files —
except 011 and 012, which share `app/Models/Plan.php` in different methods, so avoid
running those two against the same branch.

By user-visible impact rather than architecture, **012** is the one to take first: it
has a bug users hit today.

## House rules

Read these before starting any issue above. They are the conventions the `SessionDetail`
work settled, and following them is what keeps the codebase converging rather than
sprouting a new shape per issue.

- **Read the precedent.** `app/Services/WorkoutSession/SessionDetail.php` is the worked
  example for module shape, naming, placement and docblock tone. Modules live in
  `app/Services/<Area>/`, named for the domain concept, not the mechanism.
- **Characterization test first.** Before changing behaviour, write a test that locks
  the current payload by equality and commit it green. The refactor then *demonstrates*
  it changed nothing rather than asserting it.
  `tests/Feature/SessionDetailCharacterizationTest.php` is the pattern — note that it
  reads ids from the fixture rather than hardcoding them, because `RefreshDatabase`
  rolls transactions back but does not reset auto-increment counters.
- **A module owns its own loading.** Do not require callers to eager-load before calling
  you. A precondition nobody can violate beats one everybody has to remember — that was
  the whole bug behind `today()` returning an empty exercise list.
- **Canonical Units behind the seam.** Modules speak kilograms and centimetres and know
  nothing of unit systems; conversion happens at the HTTP boundary. See
  [ADR-0001](../adr/0001-convert-units-at-the-http-boundary.md), and do not re-litigate
  it.
- **New domain term? Add it to [`CONTEXT.md`](../../CONTEXT.md).** If a term is already
  published on the wire — in `API_DOCUMENTATION.md` or the front-end types — adopt that
  name rather than inventing a third.
- **Don't fold behaviour changes into a refactor.** If a fix would move numbers users
  see, split it into its own issue and say why. 008 exists because of this rule.
- **Keep `pint` to your own files.** `./vendor/bin/pint <your files>`, not the whole
  tree — running it broadly reformats unrelated files and pollutes the diff.

## Shared context

- Coverage of `Api/WorkoutSessionController` is thin but no longer absent.
  `tests/Feature/SessionDetailCharacterizationTest.php` locks the `show` and `today`
  payloads by equality, so any issue that changes a session response will fail it — that
  is the intended signal, and the expected payload should be updated deliberately rather
  than loosened. `tests/Feature/PersonalRecordDetectionTest.php` does the same for the
  records `complete` emits, against both the endpoint and
  `App\Services\WorkoutSession\PersonalRecords`. `start` and `cancel` remain untested,
  as do `complete`'s status transition (003) and its response body. Other existing
  coverage (`WorkoutSessionGenerationTest.php`, `WorkoutPreviewTest.php`,
  `WorkoutGeneratorDiversityTest.php`) is generator-focused. Add new tests under
  `tests/Feature/` per the conventions in `CLAUDE.md`.
- Session serialization runs through `App\Services\WorkoutSession\SessionDetail`, which
  loads its own relations. Issues touching the read path should change that module, not
  `WorkoutSessionResource`, and must not reintroduce caller-side eager loading.
- Branch per issue: `fix/<description>` → `main`. Never commit to `main`.
- Run `composer test` and `./vendor/bin/pint` before opening the PR.
- **Running two agents at once?** `phpunit.xml` pins `DB_DATABASE=muscle_hustle`, so
  concurrent test runs share one MySQL schema and will corrupt each other. Give each
  worktree its own database before parallelising.
