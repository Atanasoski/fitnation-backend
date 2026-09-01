# 002 — Set logs are keyed by `exercise_id`, not by the session-exercise row

**Area:** back-end / data model
**Severity:** high (data correctness)
**Status:** done — `eda4e40`

## Problem

`workout_session_set_logs` has `(workout_session_id, exercise_id)` but **no**
`workout_session_exercise_id`. `workout_session_exercises` has **no** unique constraint on
`(workout_session_id, exercise_id)`. So the same exercise can legitimately appear twice in one
session (e.g. a top set early + back-off sets later, or a superset repeat), and when it does,
nothing can tell which row a logged set belongs to.

Concrete failures that follow:

1. **Set logs merge across duplicate rows.**
   `WorkoutSessionResource::toArray()` matches sets with
   `$this->setLogs->where('exercise_id', $sessionExercise->exercise_id)`, so both rows show the
   *same* set list, and `is_completed` / `progress.progress_percent` double-count.
   Same pattern in `UserWorkoutSessionController::show()` (`$exerciseRows`).

2. **`removeExercise` over-deletes.**
   `Api/WorkoutSessionController::removeExercise()` runs
   `SetLog::where('workout_session_id', ...)->where('exercise_id', $exercise->exercise_id)->delete()`.
   Removing one of two rows for the same exercise wipes the sets logged against the other.

3. **`swapExercise` orphans logged sets.**
   `Api/WorkoutSessionController::swapExercise()` updates only
   `$sessionExercise->exercise_id`. Any set already logged under the old `exercise_id` stays in
   the table, is no longer joined to any session-exercise row, and silently vanishes from the
   response while still counting toward that exercise's history in
   `ProgressionCalculatorService::getLastPerformance()`.

4. **`deleteSet` re-sequences the wrong scope.**
   The bulk `decrement('set_number')` is scoped to `(workout_session_id, exercise_id)`, so it
   shifts set numbers belonging to the duplicate row too.

## Decision needed first

Pick one, and note it in the PR description:

- **(A) Forbid duplicates.** Add a unique index on
  `workout_session_exercises (workout_session_id, exercise_id)`, and make `addExercise` /
  `swapExercise` reject a duplicate with a 422. Cheapest; loses back-off-set and superset-repeat
  modelling.
- **(B) Key sets to the row (recommended).** Add nullable
  `workout_session_exercise_id` to `workout_session_set_logs`, backfill it, make it required for
  new writes, and match on it everywhere. Correct long-term; needs a data migration.

## Proposed fix (assuming B)

1. Migration: add `workout_session_exercise_id` (nullable FK → `workout_session_exercises`,
   `cascadeOnDelete`), indexed as `(workout_session_exercise_id, set_number)`.
2. Backfill: for each existing set log, resolve the single matching session-exercise row by
   `(workout_session_id, exercise_id)`. Log rows that match zero or >1 candidates instead of
   guessing; those are the pre-existing orphans from bug 3.
3. `LogSetRequest` / `logSet()` accept and persist the session-exercise row id (keep
   `exercise_id` as a denormalized column — `getLastPerformance` and the history endpoints
   query it directly).
4. Match by row id in `WorkoutSessionResource`, `UserWorkoutSessionController::show()`,
   `removeExercise`, `deleteSet` re-sequencing.
5. `swapExercise`: decide and implement explicitly — either delete the sets logged under the old
   exercise inside the transaction, or refuse the swap with a 422 once sets exist. Do not leave
   them orphaned.
6. Follow-up migration (separate PR) to make the column non-nullable once backfill is verified.

## Acceptance criteria

- [ ] A session containing the same exercise twice shows each row's own sets, and `progress`
      counts them independently. (Feature test.)
- [ ] Removing one of two duplicate rows leaves the other row's sets intact. (Feature test.)
- [ ] Swapping an exercise on a row that already has logged sets behaves per the chosen rule,
      with a test asserting no orphaned rows remain in `workout_session_set_logs`.
- [ ] Deleting set 2 of 4 on one row leaves the duplicate row's numbering untouched, and the
      edited row is contiguous 1..3. (Feature test.)
- [ ] Backfill migration is idempotent and reports unmatched rows rather than failing silently.

## Files

- `database/migrations/` (new)
- `app/Models/SetLog.php`, `app/Models/WorkoutSessionExercise.php`
- `app/Http/Controllers/Api/WorkoutSessionController.php` (`logSet`, `deleteSet`,
  `removeExercise`, `swapExercise`)
- `app/Http/Requests/LogSetRequest.php`, `SwapWorkoutSessionExerciseRequest.php`
- `app/Http/Resources/Api/WorkoutSessionResource.php`
- `app/Http/Controllers/UserWorkoutSessionController.php`
- `front-end/packages/shared/src/types/api.ts` if the log-set payload gains a field

## Out of scope

Issue 001's N+1 work. Coordinate if both land at once — both touch `WorkoutSessionResource`.
