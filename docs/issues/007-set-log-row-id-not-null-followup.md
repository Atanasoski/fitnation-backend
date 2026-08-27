# 007 — Make `workout_session_set_logs.workout_session_exercise_id` NOT NULL

**Area:** back-end / data model
**Severity:** medium (finishes issue 002)
**Status:** open
**Depends on:** [002](002-set-logs-keyed-by-exercise-id.md), which must be deployed and its backfill verified first

## Why this is separate

002 added the column as nullable on purpose: the backfill had to be observed against
production data before anything depended on it being present, and a rolling deploy leaves old
instances writing NULL for a window after the migration lands.

That transition state carries two costs, both of which this ticket removes.

### 1. The legacy fallback

`WorkoutSessionExercise::ownedSetsFrom()` matches sets with a NULL row id on the old
`(session, exercise)` pair so they stay visible. `WorkoutSessionController::scopeToRow()` and
`deleteSet()` mirror that predicate. Three code paths carry a branch that exists only for
rows written before the column did.

### 2. Orphans can resurface

Sets whose exercise is no longer in the session — the residue of pre-002 exercise swaps — are
left NULL by the backfill. They are invisible *while* that exercise is absent, but if the same
exercise is later re-added to the session, the legacy fallback surfaces them under the new row,
with `set_number` values that can collide with newly logged sets.

The backfill logs a count and a sample of these ids under
`Set logs could not be attached to any session-exercise row`. Read that log from production
before deciding.

## Proposed fix

1. Read the backfill's warnings from production. Two numbers matter: how many sets were
   ambiguous (attached to the earliest row), and how many are true orphans.
2. Decide the orphans explicitly — delete them, or attach them to a row. They are logged
   training history, so this is a product call, not a technical one.
3. Migration: resolve any remaining NULLs, then `->nullable(false)->change()`.
4. Delete the legacy branch from `ownedSetsFrom()` (and its `$matchLegacySets` parameter),
   `scopeToRow()`, and the row resolution in `deleteSet()`.
5. Make `workout_session_exercise_id` required in `LogSetRequest` once clients that send it
   are the only ones in the field. Check mobile release adoption before doing this — it is the
   step that breaks old builds.

## Acceptance criteria

- [ ] No `workout_session_set_logs` row has a NULL `workout_session_exercise_id`.
- [ ] The column is NOT NULL at the schema level.
- [ ] No `whereNull('workout_session_exercise_id')` branch remains in app code.
- [ ] The tests in `WorkoutSessionDuplicateExerciseTest` covering legacy sets are removed
      along with the behaviour, not left asserting a path that can no longer occur.
- [ ] Orphan disposition is recorded in the PR description.

## Files

- `database/migrations/` (new)
- `app/Models/WorkoutSessionExercise.php` (`ownedSetsFrom`)
- `app/Http/Controllers/Api/WorkoutSessionController.php` (`scopeToRow`, `deleteSet`)
- `app/Http/Requests/LogSetRequest.php`
- `tests/Feature/WorkoutSessionDuplicateExerciseTest.php`

## Out of scope

Anything else in 002. This ticket only retires the transition state it deliberately created.
