# 001 — `WorkoutSessionExerciseResource` fans out N+1 queries per session GET

**Area:** back-end / API serialization
**Severity:** high (performance)
**Status:** done — `306fcda`

## Problem

`app/Http/Resources/Api/WorkoutSessionExerciseResource.php` does per-row database work
inside `toArray()`. Every exercise in a session triggers its own progression lookup, so
a GET of a 6-exercise session issues dozens of queries that should be a handful.

Per exercise row, the resource currently:

1. `new ProgressionCalculatorService` — a fresh instance per row (no container, no shared cache).
2. `$progressionCalculator->getLastPerformance($this->exercise, $user)` — 1 `whereHas` +
   1 eager-load query on `workout_sessions`/`workout_session_set_logs`.
3. `$progressionCalculator->calculateTargets(...)` — which calls `getLastPerformance()`
   **again** with identical arguments (`ProgressionCalculatorService::calculateTargets()`
   line ~19), duplicating step 2 verbatim.
4. `calculateTargets` → `getDefaultTargets` → `estimateStartingWeight` reads
   `$exercise->movementPattern`, `$exercise->equipmentType`, `$exercise->angle` — lazy loads
   unless those relations happen to be eager-loaded by the caller.
5. `getProgressionStatus` → `isBodyweight` → `getWeightIncrement` reads
   `$exercise->equipmentType` again.
6. The `exercise` block calls `$this->exercise->load('partners', 'muscleGroups',
   'primaryMuscleGroups', 'secondaryMuscleGroups')` — 4 more queries **per row**, and
   `->load()` on an already-loaded relation re-queries.

Callers only eager-load `workoutSessionExercises.exercise.category`
(`Api/WorkoutSessionController.php` — `show`, `start`, `complete`, `swapExercise`,
`addExercise`, `updateExercise`, `reorderExercises`), so everything in 4/5/6 is a lazy load.

## Reproduce

```php
// tests/Feature — session with 6 exercises, user with prior completed sessions
DB::enableQueryLog();
$this->actingAs($user, 'sanctum')->getJson("/api/workout-sessions/{$session->id}");
count(DB::getQueryLog()); // expect a small constant; observe it scale with exercise count
```

## Proposed fix

Move the progression work out of the resource and into a batch step, then pass it in:

1. **Deduplicate the double lookup.** `calculateTargets()` should accept an optional
   pre-fetched `$lastPerformance` (or the resource should stop calling `getLastPerformance`
   separately and read it from the returned targets array). One lookup per exercise, not two.
2. **Batch `getLastPerformance` across exercises.** Add
   `ProgressionCalculatorService::getLastPerformanceForExercises(Collection $exercises, User $user): array`
   keyed by `exercise_id`, resolving the latest completed session per exercise in one or two
   queries (a single query over `workout_session_set_logs` joined to completed sessions,
   window-function or group-wise-max style, instead of one query per exercise).
   Note `WorkoutSession::getPreviousSetLogsForExercises()` already solves a near-identical
   problem — consider consolidating rather than adding a third variant.
3. **Inject the computed data.** `WorkoutSessionResource` already loops the exercises; have it
   compute the batch once and hand each `WorkoutSessionExerciseResource` its slice
   (constructor arg, `->additional()`, or a small DTO). The resource should do formatting only,
   no queries.
4. **Eager-load the exercise relations at the controller/service level**, not with `->load()`
   inside the resource: `workoutSessionExercises.exercise.{category,partners,muscleGroups,
   primaryMuscleGroups,secondaryMuscleGroups,movementPattern,equipmentType,angle}`. Replace the
   in-resource `->load(...)` with `whenLoaded`/plain access.
5. Resolve `ProgressionCalculatorService` from the container instead of `new`-ing it per row.

## Acceptance criteria

- [ ] Query count for `GET /api/workout-sessions/{session}` is constant w.r.t. exercise count
      (assert with `DB::getQueryLog()` in a feature test; 1 vs. 12 exercises → same count).
- [ ] JSON response shape is byte-identical to today's for the same fixture data
      (targets, `progression_mode`, `progression_status`, `target_weight` formatting all unchanged).
- [ ] `getLastPerformance` is invoked at most once per (exercise, user) per request.
- [ ] No `->load()` calls remain inside `WorkoutSessionExerciseResource`.
- [ ] Every endpoint returning `WorkoutSessionResource` eager-loads the new relation set:
      `show`, `start`, `complete`, `swapExercise`, `today`, plus
      `WorkoutGenerationService::generate()`/`confirmSession()`/`regenerateSession()`.

## Files

- `app/Http/Resources/Api/WorkoutSessionExerciseResource.php` (primary)
- `app/Http/Resources/Api/WorkoutSessionResource.php`
- `app/Services/WorkoutGenerator/ProgressionCalculatorService.php`
- `app/Http/Controllers/Api/WorkoutSessionController.php`
- `app/Services/WorkoutGenerator/WorkoutGenerationService.php`
- `app/Models/WorkoutSession.php` (`getPreviousSetLogsForExercises`)

## Out of scope

Changing progression *maths* or default targets. This is a pure performance/structure change —
the numbers that come out must not move.
