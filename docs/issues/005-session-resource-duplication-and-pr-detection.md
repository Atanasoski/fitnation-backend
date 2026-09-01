# 005 — `WorkoutSessionResource` duplication + noisy PR detection in `complete()`

**Area:** back-end / API serialization
**Severity:** low–medium (maintainability, UX noise)
**Status:** open

Two small, independent cleanups in the same blast radius. Split into two commits.

---

## Part A — Resource duplication

`app/Http/Resources/Api/WorkoutSessionResource.php` lines ~44–49:

```php
"status" => $this->status,           // ← missing from GET
"rationale" => $this->notes,
"is_auto_generated" => $this->is_auto_generated,
"replaced_session_id" => $this->replaced_session_id,
'notes' => $this->notes,
```

- The `// ← missing from GET` comment is a leftover note from when these fields *were*
  missing. It reads as a TODO and is stale — delete it.
- Double-quoted keys next to single-quoted ones in the same array; the block is also
  inconsistently indented relative to the rest of the file. Run `./vendor/bin/pint`.
- `GeneratedWorkoutSessionResource` extends the same payload and re-sets all four of these keys
  via `array_merge`, so every one of them is written twice on the generator endpoints. Since
  `WorkoutSessionResource` now emits them, `GeneratedWorkoutSessionResource::toArray()` is a
  no-op wrapper — either delete the class and use the base resource, or reduce it to only what
  it genuinely adds.
  Note: `status` is a backed enum in the base and `$this->status?->value` in the subclass; both
  serialize to the same JSON string, so this is cosmetic, not a bug. Pick one form.
- `notes` and `rationale` are both `$this->notes`. **Both must stay** — the front-end reads
  `rationale` (`front-end/apps/web/src/components/WorkoutPreviewPage.tsx:216`,
  `apps/mobile/src/screens/placeholders/WorkoutPreviewScreen.tsx:195`,
  `packages/shared/src/types/api.ts:463,659`) and `notes` elsewhere. Add a one-line comment
  explaining that `rationale` is the generator-facing alias of `notes` so the next reader
  doesn't "clean it up".
- `is_completed` uses `$sessionExercise->target_sets ?? 3` — the `3` is a magic default that
  disagrees with `ProgressionCalculatorService::getDefaultTargets()`, which returns 4 sets for
  intermediate/advanced users. Source it from the same place or drop the fallback.

### Acceptance criteria (A)

- [ ] Response JSON for both `/workout-sessions/{id}` and the generator endpoints is unchanged,
      asserted by a test that snapshots the key set.
- [ ] Stale comment removed; `./vendor/bin/pint` clean.
- [ ] `GeneratedWorkoutSessionResource` either deleted (callers switched to the base resource) or
      no longer restates keys the base already emits.
- [ ] `rationale`/`notes` duplication documented in a comment, not removed.

---

## Part B — PR detection fires on every first-ever set

In `Api/WorkoutSessionController::complete()`:

```php
if ($histWeight === null || $sessionMaxWeight > $histWeight) { $newPrs[] = [...'pr_type' => 'weight'...]; }
if ($histReps   === null || $sessionMaxReps   > $histReps)   { $newPrs[] = [...'pr_type' => 'reps'...]; }
```

- With no history, **both** a weight PR and a reps PR are emitted for every exercise, with
  `previous_best => 0`. A first workout of 6 exercises returns 12 "PRs". That is celebration
  noise, not a personal record.
- The two maxima are computed independently — `MAX(weight)` and `MAX(reps)` across all
  historical sets — so a "reps PR" can be a set of 15 at a much lighter weight than the working
  set. A rep PR at a lower weight is not a rep PR.
- `previous_best` is typed `float` in one branch and `int` in the other; the front-end gets a
  mixed type on the same field.

### Proposed fix (B)

> **Mostly superseded.** [010](010-personal-record-detection-has-no-seam.md) did
> point 4 — the logic lives in `App\Services\WorkoutSession\PersonalRecords`
> with a test file of its own — and [017](017-personal-record-rules.md) decided
> and did points 1 and 2: a first-ever session records nothing, and one
> record-setting set per exercise is chosen by estimated 1RM. Epley now has one
> home, `StrengthScore::oneRepMax()`; the
> `ProgressionCalculatorService::estimateOneRepMax()` named below was a second
> copy with no callers and is deleted. Point 3 — the mixed
> `previous_best`/`new_best` type — is the part still open.

1. Suppress PRs entirely when there is no prior history for that exercise (`$historic === null`),
   or mark them with a distinct `pr_type` (e.g. `first_time`) the client can render differently.
   Decide with the product owner and record the choice.
2. Qualify the reps PR by weight: compare `MAX(reps)` *at or above* the historic best weight,
   not globally. A single `selectRaw` won't express this cleanly — consider comparing estimated
   1RM (`StrengthScore::oneRepMax()`) instead of two independent maxima.
3. Normalize `previous_best`/`new_best` to a consistent type per `pr_type`.
4. Extract this into a `PersonalRecordDetector` service — it is ~50 lines of domain logic
   sitting in a controller action, and it needs unit tests, which it currently has none of.

### Acceptance criteria (B)

- [ ] Completing a first-ever session emits zero PRs (or `first_time`-typed entries, per the
      decision), not two per exercise.
- [ ] A set of 15 reps at 40 kg does not register a reps PR against a history of 8 reps at 100 kg.
- [ ] `previous_best` / `new_best` types are consistent per `pr_type`.
- [ ] PR detection has unit-test coverage: no history, weight PR, reps PR, no PR, tie
      (equal to previous best must **not** be a PR).

## Files

- `app/Http/Resources/Api/WorkoutSessionResource.php`
- `app/Http/Resources/Api/GeneratedWorkoutSessionResource.php`
- `app/Http/Controllers/Api/WorkoutSessionController.php` (`complete`)
- `app/Services/` (new `PersonalRecordDetector`)
- `front-end/packages/shared/src/types/api.ts` if `pr_type` gains a value
- `tests/Unit/Services/`

## Out of scope

Status guards on `complete()` — issue 003 covers those.
