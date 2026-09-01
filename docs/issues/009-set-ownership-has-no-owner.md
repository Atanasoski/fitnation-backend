# 009 — "Which row owns this set" is written ten times

**Area:** back-end / data model
**Severity:** medium (maintainability, with one live bug)
**Status:** open
**Depends on:** nothing. **Blocks:** [007](007-set-log-row-id-not-null-followup.md), which shrinks to a deletion once this lands.

## Problem

Since [002](002-set-logs-keyed-by-exercise-id.md), a set log belongs to a
session-exercise *row*, not to an exercise. Legacy sets written before that column
existed carry `null` and are matched on the old `(session, exercise)` pair — but only
when the exercise occupies a single row, because with duplicates there is no way to
tell which row such a set belonged to.

That rule is correct. The problem is that nothing owns it. It is re-expressed in ten
places, and the `$matchLegacySets` flag it depends on is derived three different ways:

| File:line | What it holds |
|---|---|
| `app/Models/WorkoutSessionExercise.php:73` | `ownedSetsFrom()` — in-memory, takes the flag from its caller |
| `app/Models/WorkoutSessionExercise.php:90` | `isOnlyRowForItsExercise()` — derivation #1, costs a query |
| `app/Services/WorkoutSession/SessionDetail.php:53` | derivation #2, a `groupBy` over the loaded rows |
| `app/Http/Controllers/Api/WorkoutSessionController.php:240` | derivation #3, `$rows->count() <= 1` |
| `app/Http/Controllers/Api/WorkoutSessionController.php:544` | `scopeToRow()` — the SQL form of the predicate |
| `app/Http/Controllers/Api/WorkoutSessionController.php:523` | `deleteSetLogsFor()` — wraps `scopeToRow` with derivation #1 |
| `app/Http/Controllers/Api/WorkoutSessionController.php:507` | `resolveSessionExerciseId()` — the write-side "earliest row wins" tiebreak |
| `app/Http/Requests/LogSetRequest.php:60` | `after()` — re-queries to check row/exercise agreement |
| `app/Http/Controllers/UserWorkoutSessionController.php:89` | `setsFor()` — **passes the wrong flag** |
| `database/migrations/2026_08_27_191638_add_workout_session_exercise_id_to_workout_session_set_logs_table.php` | the backfill's copy of the tiebreak |

The invariant is also written out in prose three times — `WorkoutSessionExercise.php`
above `ownedSetsFrom()`, and twice in `WorkoutSessionController` — which is itself the
tell that no single artifact owns it.

### The live bug

`UserWorkoutSessionController::setsFor()` (`:89`) calls
`$sessionExercise->ownedSetsFrom($workoutSession->setLogs)` with no second argument, so
it takes the default `true`. On a session where an exercise occupies two rows, a legacy
set is therefore shown under **both** — the exact double-counting the API path was
changed to remove in 002. Staff-facing Blade view, so user impact is low; the point is
that a defaulted parameter let one call site silently disagree with the other nine.

## Fix

One module owning the predicate, both forms of it. Suggested shape, in
`app/Services/WorkoutSession/` alongside `SessionDetail`:

```php
final class SetOwnership
{
    /** Partition a session's loaded set logs across its rows. */
    public static function forSession(WorkoutSession $session): self;

    /** @return Collection<int, SetLog> the sets belonging to one row */
    public function setsFor(WorkoutSessionExercise $row): Collection;

    /** Constrain a query to one row's sets, legacy fallback included. */
    public function constrain($query, WorkoutSessionExercise $row);
}
```

The duplicate-row question is answered **inside**, once per session, from rows the
module already holds. No caller passes a flag; there is no flag to pass wrongly.

`SessionDetail::for()` uses it in place of its own `groupBy` and its
`ownedSetsFrom` call. `deleteSetLogsFor`, `deleteSet`'s resequencing, and
`resolveSessionExerciseId` use it in place of `scopeToRow`. `setsFor()` in the web
controller is deleted and its two call sites go through the module, which fixes the bug
above as a side effect rather than as a separate change.

Keep `ownedSetsFrom()` only if something still needs it after the rewrite; prefer
deleting it, since a defaulted boolean is what caused the divergence.

## Tests

`tests/Feature/WorkoutSessionDuplicateExerciseTest.php` already covers most of the
behaviour through HTTP and must stay green unchanged — it is the safety net for this
work, so read it first.

Add:

- A direct test of the module: a session with one exercise on two rows plus a legacy
  set, asserting the legacy set appears under neither.
- The web-view regression: `UserWorkoutSessionController::show()` must agree with the
  API on which sets belong to which row. This currently fails; that is the point.

## Notes

- `SessionDetail` is the precedent for module shape, naming and placement — read
  `app/Services/WorkoutSession/SessionDetail.php` before designing this one.
- Do not change what a set log *means* here. This is a consolidation, not a schema
  change; 007 is the schema follow-up and should stay separate.
- Do not run alongside [010](010-personal-record-detection-has-no-seam.md): both
  rewrite `Api/WorkoutSessionController`.
