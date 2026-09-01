# 011 — Program progress runs three full scans per serialized program

**Area:** back-end / API serialization
**Severity:** medium (performance, with two correctness bugs)
**Status:** done — the module exists; see *Left open by the extraction* below
**Independent:** touches no file the workout-session issues touch, except `Plan.php`,
which it shares with [012](012-plan-activation-one-rule-five-scopes.md).

## Problem

`app/Http/Resources/Api/ProgramResource.php` calls three `Plan` methods per program:

```php
'progress_percentage' => $this->when($this->user_id, fn () => $this->getProgressPercentage(auth()->user())),   // :30
'next_workout'        => $this->when($this->user_id, fn () => new WorkoutTemplateResource($this->nextWorkout(auth()->user()))),  // :34
'current_active_week' => $this->when($this->user_id, fn () => $this->getCurrentActiveWeek(auth()->user())),    // :38
```

Each of the three loops the plan's templates issuing an `exists()` **per template**
(`app/Models/Plan.php:159` and `:128`), and `getCurrentActiveWeek()` (`:190`) internally
calls `nextWorkout()` *again*. So serializing one program scans its templates three
times over; serializing a list of programs multiplies that by the list length. Each
nested `WorkoutTemplateResource` then adds its own query for
`last_completed_session_id`.

Three further defects in the same few lines:

1. **`'cover_image'` is declared twice** — `ProgramResource.php:22` and `:27`. The
   second silently wins; they are identical today, so this is latent rather than live.
2. **Status is compared as a raw string.** `Plan.php:144` and `:176` use
   `->where('status', 'completed')` while the rest of the codebase uses
   `WorkoutSessionStatus::Completed`. If the enum's backing value ever changes, these
   two report zero progress with no error.
3. **The resource reads global `auth()`** rather than `$request->user()`, so it cannot
   be serialized outside a request — including from a queue job or a test.

## Fix

A module owning the question, suggested `app/Services/Plan/ProgramProgress`:

```php
final class ProgramProgress
{
    public static function for(Plan $plan, User $user): self;

    public function nextWorkout(): ?WorkoutTemplate;
    public function percentComplete(): float;
    public function currentWeek(): ?int;
}
```

One query resolves it: the set of template ids with a completed session for this user,
fetched once, with all three answers derived from it in memory. The resource asks the
module and takes `$request->user()` rather than reaching for `auth()`.

While in there: delete the duplicate `cover_image` key and replace both raw
`'completed'` strings with the enum.

Whether the three `Plan` methods survive as thin delegations or are deleted outright is
the implementer's call — check for other callers first (at time of writing,
`ProgramResource` is the only one).

## Tests

There is no direct coverage of `ProgramResource` today; `tests/Feature/ProgramApiTest.php`
exercises the endpoints around it.

- Lock the current payload for a program with a mix of completed and incomplete
  templates, before changing anything. `progress_percentage`, `next_workout.id` and
  `current_active_week` must be unchanged afterwards.
- A query-count assertion in the style of
  `tests/Feature/WorkoutSessionResourceQueryCountTest.php`: serializing a program with
  3 templates and one with 12 must issue the same number of queries.
- A program whose sessions are `cancelled` rather than `completed` must report zero
  progress — this is the enum bug, and the test should fail before the fix.

## Notes

- `app/Services/WorkoutSession/SessionDetail.php` is the precedent for module shape,
  naming and placement. This is the same pattern applied to the Plan side.
- Shares `app/Models/Plan.php` with [012](012-plan-activation-one-rule-five-scopes.md).
  Different methods, so a merge is usually clean, but do not run both against one branch.

## Left open by the extraction

1. **The enum defect was latent, not live.** `WorkoutSessionStatus::Completed`
   backs onto the same `'completed'` string the raw comparisons used, so no
   payload was ever wrong and the test asked for above
   (`ProgramProgressTest::test_only_completed_sessions_count_towards_progress`)
   passes before the fix as well as after. It is kept because it fails if that
   backing value ever moves, which is the defect the issue actually names.
2. **`GET /api/programs/active` still 500s when the user has no active
   program.** `PlanController::activeProgram()` hands a null plan to
   `ProgramResource`. This predates the extraction — it 500s identically at
   `334d2e3`, only with `ErrorException` instead of `TypeError` — and fixing it
   means deciding what that endpoint returns when there is nothing active,
   which belongs to [012](012-plan-activation-one-rule-five-scopes.md).
3. **`WorkoutTemplateResource` still reads global `auth()`** for the partner
   whose exercise presentation it picks and for the unit system it formats
   with, so serializing a program outside a request gets default images and
   Canonical Units. `ProgramResource` itself no longer does. The two remaining
   reads belong to [014](014-partner-exercise-presentation.md) and
   [015](015-measured-field-residue.md), which own those payload areas.
4. **`percentComplete()` counts the templates it was handed**, where the old
   code took its denominator from a separate `count()` query. A caller that
   eager-loads `workoutTemplates` through a constrained closure would therefore
   change the percentage. None does, and every caller serializes the same set it
   measures — which the old shape could not promise, since its numerator and
   denominator came from two different reads.
