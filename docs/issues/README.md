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
| [016](016-api-resources-read-global-auth.md) | Four API resources read global `auth()` instead of the request | low | `WorkoutTemplateResource`, `SetLogResource`, `ExerciseResource` |
| [017](017-personal-record-rules.md) | Personal records: one set, and nothing on a first session | medium | `PersonalRecords`, `complete()` |

## Done

- [001](001-session-exercise-resource-n-plus-1.md) — `306fcda`
- [002](002-set-logs-keyed-by-exercise-id.md) — `eda4e40`
- [009](009-set-ownership-has-no-owner.md) — PR #37 — `SetOwnership`; unblocks 007
- [010](010-personal-record-detection-has-no-seam.md) — PR #38 — three follow-ups recorded in the issue, one of them new
- [011](011-program-progress-leaks-into-the-resource.md) — PR #39 — four follow-ups recorded in the issue
- [012](012-plan-activation-one-rule-five-scopes.md) — PR #40 — [ADR-0002](../adr/0002-one-active-plan-per-type.md); **needs a release note**
- [013](013-fitness-metrics-service-is-wide-not-deep.md) — PR #41 — 896 lines → 45; two review findings recorded in the issue
- [014](014-partner-exercise-presentation.md) — PR #42 — `PartnerExerciseView`
- [015](015-measured-field-residue.md) — PR #43 — one home for the imperial step

## Suggested order

Everything from the architecture review has landed. What remains is the original
session-domain backlog plus two follow-ups it produced.

**Session domain** — 003, 004 and 005 all rewrite `Api/WorkoutSessionController`, so
one at a time:

1. **003** — smallest, and 004 depends on the transition rules it defines. Note 010
   locked the current `complete()` behaviour in
   `tests/Feature/PersonalRecordDetectionTest.php`, including that completing an
   already-completed session re-emits its records. Those assertions describe the bug
   003 is here to fix, so expect to edit them — deliberately, not by loosening them.
2. **004**.
3. **005** — cleanup, last.

**Then, in any order:** **007** (now a deletion, since 009 gave the predicate one
owner — still needs 002's backfill verified against production first), **008** (a
one-line change to `SessionDetail`, but it moves progress percentages, so give it its
own PR), **006**, and **016**.

The two product questions [010](010-personal-record-detection-has-no-seam.md) parked
have been answered — a record must come from one set, and a first-ever session records
nothing. They are now [017](017-personal-record-rules.md), ready to implement. It
shares `complete()` and `PersonalRecordDetectionTest` with 003 and 005, so it queues
with the session domain rather than running alongside it.

**Nothing else is waiting on a decision.** Every open issue above can be started as
written.

## House rules

Read these before starting any issue above. They are the conventions the `SessionDetail`
work settled, and following them is what keeps the codebase converging rather than
sprouting a new shape per issue.

- **Read the precedent.** `app/Services/WorkoutSession/SessionDetail.php` is the worked
  example for module shape, naming, placement and docblock tone. Modules live in
  `app/Services/<Area>/`, named for the domain concept, not the mechanism.
- **Characterization test first — in its own commit.** Before changing behaviour, write
  a test that locks the current payload by equality and **commit it green against
  unchanged code**. The refactor then *demonstrates* it changed nothing rather than
  asserting it. `tests/Feature/SessionDetailCharacterizationTest.php` is the pattern —
  note that it reads ids from the fixture rather than hardcoding them, because
  `RefreshDatabase` rolls transactions back but does not reset auto-increment counters.

  The separate commit is the whole mechanism, not ceremony. Bundled with the refactor, a
  "characterization" test cannot be distinguished from one written to match the new
  output — and that is not hypothetical: every PR from 009–015 bundled them, and
  `FitnessMetricsPayloadTest` turned out to describe the post-refactor payload. It fails
  when replayed against its own parent commit, which is how a key-order change in
  `strength_balance.muscle_groups` shipped unnoticed. Harmless there; it will not always
  be. To check your own: `git stash` the implementation and run the test.
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
