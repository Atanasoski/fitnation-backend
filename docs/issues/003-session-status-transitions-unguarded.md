# 003 — Session status transitions are unguarded

**Area:** back-end / API
**Severity:** medium (data correctness)
**Status:** open

## Problem

`WorkoutSessionStatus` (draft → active → completed | cancelled) is treated as a label, not a
state machine. The mutating endpoints in `app/Http/Controllers/Api/WorkoutSessionController.php`
authorize ownership and nothing else:

- **`complete()`** has no status check. A session that is already `completed` can be completed
  again — this overwrites `completed_at`, overwrites `notes`, and re-runs the PR detection,
  which now compares the session against a history that excludes itself (`where('id', '!=',
  $session->id)`) and therefore re-emits the same PRs. A `cancelled` or `draft` session can also
  be completed straight to `completed`, skipping `active` entirely.
- **`logSet()` / `updateSet()` / `deleteSet()`** work on `completed` and `cancelled` sessions —
  the history a completed session represents is freely mutable after the fact, and
  `ProgressionCalculatorService::getLastPerformance()` reads exactly those rows.
- **`addExercise()` / `removeExercise()` / `updateExercise()` / `swapExercise()` /
  `reorderExercises()`** likewise accept any status.
- **`cancel()`** can cancel an already-`completed` session, silently destroying the record that
  it happened.

The generator side is stricter and shows the intended pattern:
`WorkoutGenerationService::confirmSession()` and `regenerateSession()` both reject
non-`draft` sessions, and `WorkoutSessionPolicy::confirm()`/`regenerate()` document that state
rules belong in the service as a 422, not in the policy as a 403.

## Proposed fix

1. Add the allowed transitions to `App\Enums\WorkoutSessionStatus`, e.g.
   `canTransitionTo(self $to): bool` and `isTerminal(): bool`
   (`draft → active|cancelled`, `active → completed|cancelled`, `completed`/`cancelled` terminal).
2. Add a guard used by every mutating endpoint — a small service method or a
   `EnsureSessionIsEditable` check — that returns **422** (not 403; matches the existing
   convention) with a clear message when the session is terminal.
3. Apply it to: `complete` (require `active`), `cancel` (require `draft` or `active`),
   `logSet`, `updateSet`, `deleteSet`, `addExercise`, `removeExercise`, `updateExercise`,
   `swapExercise`, `reorderExercises` (all require `active`; decide whether `draft` should also
   allow exercise edits — the draft-preview UI in `front-end/apps/web/src/components/
   WorkoutPreviewPage.tsx` may depend on it, check before locking it down).
4. Make `complete()` idempotent-safe: if already `completed`, return the existing session with
   an empty `new_prs` rather than recomputing.

## Acceptance criteria

- [ ] `POST /workout-sessions/{id}/complete` on a `completed` session → 422, `completed_at`
      and `notes` unchanged, no duplicate PRs emitted.
- [ ] `POST /workout-sessions/{id}/complete` on a `draft` session → 422.
- [ ] `POST /workout-sessions/{id}/sets` on a `completed` session → 422.
- [ ] `DELETE /workout-sessions/{id}/cancel` on a `completed` session → 422.
- [ ] Happy paths (`draft → confirm → active → log sets → complete`) are unaffected;
      `WorkoutSessionGenerationTest` and `WorkoutPreviewTest` still pass.
- [ ] Transition rules live in the enum and are unit-tested there.

## Files

- `app/Enums/WorkoutSessionStatus.php`
- `app/Http/Controllers/Api/WorkoutSessionController.php`
- `app/Services/WorkoutGenerator/WorkoutGenerationService.php` (reuse the same guard)
- `tests/Feature/` (new `WorkoutSessionStatusTransitionTest.php`)

## Out of scope

The PR-detection logic itself (see issue 005).
