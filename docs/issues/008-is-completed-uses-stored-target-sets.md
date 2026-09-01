# 008 — `is_completed` is judged against a different target than the one displayed

**Area:** back-end / API serialization
**Severity:** low–medium (correctness / UX)
**Status:** open
**Found:** while implementing the SessionDetail module (`fix/session-detail-module`)

## Problem

A Session Exercise Detail reports two things that are supposed to agree and don't:

- `session_exercise.target_sets` — the **resolved** target. If the row has no stored
  value, the progression calculation supplies one.
- `is_completed` — computed against the **stored** target, falling back to a hardcoded 3.

`app/Services/WorkoutSession/SessionDetail.php`:

```php
isCompleted: $loggedSets->count() >= ($row->target_sets ?? 3),
```

So a row whose `target_sets` is null and whose calculated target is 4 is reported as
`target_sets: 4` and marked complete after 3 sets. The athlete is told to do four sets,
does three, and the app says they are done.

This flows onward: `is_completed` feeds `progress.completed_exercises` and
`progress.progress_percent`, so the session progress bar is wrong for the same rows.

## Why it wasn't fixed in place

The one-word fix is to compare against `$targets['target_sets']`. It was left alone
deliberately: `fix/session-detail-module` committed to holding the `show` payload
byte-identical, and this changes `is_completed`, `completed_exercises` and
`progress_percent` for every existing session with a null `target_sets` — a
user-visible change to progress numbers, buried inside a refactor whose whole claim
was that it changed nothing. It wants its own change, with its own reasoning.

`CONTEXT.md` now states the intended rule under **Session Exercise Detail**: an
exercise is done when the number of sets logged against it reaches its target sets.
Singular "its target sets" means the resolved one — the target the athlete was shown.

## Fix

In `SessionDetail::for()`:

```php
- isCompleted: $loggedSets->count() >= ($row->target_sets ?? 3),
+ isCompleted: $loggedSets->count() >= (int) $targets['target_sets'],
```

The `?? 3` fallback goes with it: `$targets['target_sets']` is always populated —
`SessionProgression::withoutUser()` supplies 3 when there is no user, which is where
that literal came from.

## Tests

- A row with `target_sets = null` and a calculated target of 4: three logged sets must
  report `is_completed: false`, four must report `true`.
- `progress.progress_percent` must follow from the same rule.
- `tests/Feature/SessionDetailCharacterizationTest.php` will need its expected payload
  updated — that is the signal this issue is doing something, not a problem. The
  fixture stores `target_sets = 3` explicitly, so it may not move; add a row with a
  null target to the fixture, or assert in a new test.

## Scope note

Check whether anything else compares logged-set counts against a stored target. At the
time of writing, `UserWorkoutSessionController::show()` has its own third definition —
it treats an exercise as done when it has **any** logged set (`isNotEmpty()`), so the
Blade view and the API already disagree. Worth folding into this fix, since after it
there is one correct answer to point both at.
