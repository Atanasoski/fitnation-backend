# Workout session issues

Findings from a review of the workout-session code (models, API controller, generator service,
resources, partner-facing web views) on 2026-08-27. Each file is self-contained and can be
handed to a separate agent.

| # | Issue | Severity | Touches |
|---|-------|----------|---------|
| [001](001-session-exercise-resource-n-plus-1.md) | `WorkoutSessionExerciseResource` fans out N+1 queries per session GET | high | resources, `ProgressionCalculatorService` |
| [002](002-set-logs-keyed-by-exercise-id.md) | Set logs keyed by `exercise_id`, not by the session-exercise row | high | migration, `SetLog`, session controller |
| [003](003-session-status-transitions-unguarded.md) | Session status transitions are unguarded | medium | `WorkoutSessionStatus`, session controller |
| [004](004-today-endpoint-matches-stale-drafts.md) | `today()` returns stale drafts; `start()` can create a second session | medium | session controller, `WorkoutSession` scopes |
| [005](005-session-resource-duplication-and-pr-detection.md) | Resource duplication + noisy PR detection in `complete()` | low–medium | resources, `complete()` |
| [006](006-reorder-request-validation-n-plus-1.md) | `exists` rules fan out one query per array element | low | `ReorderSessionExercisesRequest` |
| [007](007-set-log-row-id-not-null-followup.md) | Make the new set-log row id NOT NULL and drop the legacy fallback | medium | migration, `ownedSetsFrom`, `LogSetRequest` |

## Suggested order

1. **003** first — the smallest, and 004 depends on the transition rules it defines.
2. **004** next.
3. **002** — schema change; land before 001 so the N+1 rework targets the final matching key.
4. **001** — the performance work.
5. **005** — cleanup, last, since 001 and 002 both rewrite `WorkoutSessionResource`.

006 was found while implementing 001 and is independent of the rest. 007 finishes 002 and
must wait until 002 is deployed and its backfill checked against production data.

001, 002 and 005 all edit `app/Http/Resources/Api/WorkoutSessionResource.php`. Do not run
them in parallel against the same branch.

## Shared context

- No feature test currently covers `Api/WorkoutSessionController` directly. Existing coverage is
  `tests/Feature/WorkoutSessionGenerationTest.php`, `WorkoutPreviewTest.php`,
  `WorkoutGeneratorDiversityTest.php` — all generator-focused. Every issue above asks for new
  tests; add them under `tests/Feature/` per the conventions in `CLAUDE.md`.
- Branch per issue: `fix/<description>` → `main`. Never commit to `main`.
- Run `composer test` and `./vendor/bin/pint` before opening the PR.
