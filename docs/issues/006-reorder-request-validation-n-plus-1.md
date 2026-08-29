# 006 — `exists` validation rules fan out one query per array element

**Area:** back-end / request validation
**Severity:** low (performance)
**Status:** open
**Found:** while implementing [001](001-session-exercise-resource-n-plus-1.md)

## Problem

`app/Http/Requests/ReorderSessionExercisesRequest.php`:

```php
'exercise_ids.*' => 'required|exists:workout_session_exercises,id',
```

Laravel applies `exists` per array element, so reordering an 8-exercise session
issues 8 separate `select count(*) as aggregate from workout_session_exercises where id = ?`
queries before the controller action even runs. Measured while fixing issue 001:
the read path is now flat at 15 queries, but the validator adds one per element on top.

The rule is also weaker than it looks: it checks the row exists *anywhere*, not that it
belongs to `$session`. The controller's update is correctly scoped
(`->where('workout_session_id', $session->id)`), so a foreign id is silently ignored rather
than rejected — the request 200s having reordered nothing.

Worth a sweep for the same pattern elsewhere:

```bash
grep -rn "\.\*.*exists:" app/Http/Requests/
```

## Proposed fix

Replace the per-element rule with one scoped, batched check — a custom rule or an
`after` validation hook that does a single query:

```php
$found = WorkoutSessionExercise::where('workout_session_id', $this->route('session')->id)
    ->whereIn('id', $this->exercise_ids)
    ->pluck('id');

// reject if $found->count() !== count(array_unique($this->exercise_ids))
```

That is one query instead of N, and it enforces session ownership, which the current rule
does not.

## Acceptance criteria

- [ ] Reordering an 8-exercise session issues one validation query, not eight.
      (Feature test asserting a flat count, in the style of
      `tests/Feature/WorkoutSessionResourceQueryCountTest.php`.)
- [ ] An `exercise_ids` entry belonging to another session is rejected with a 422,
      rather than silently ignored.
- [ ] Duplicate ids in the payload are rejected.
- [ ] The existing happy-path reorder behaviour is unchanged.

## Files

- `app/Http/Requests/ReorderSessionExercisesRequest.php`
- `app/Http/Controllers/Api/WorkoutSessionController.php` (`reorderExercises`)
- `tests/Feature/`

## Out of scope

The serialization fan-out in `reorderExercises` — fixed in 001 via
`WorkoutSessionExerciseResource::collectionForRows()`.
