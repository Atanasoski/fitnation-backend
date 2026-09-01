# 010 — Personal record detection has no seam and no test

**Area:** back-end / API
**Severity:** medium (correctness, untested)
**Status:** open
**Overlaps:** [003](003-session-status-transitions-unguarded.md) and
[005](005-session-resource-duplication-and-pr-detection.md) — all three rewrite
`complete()`. Land them together or in a fixed order; do not run them in parallel.

## Problem

`Api/WorkoutSessionController::complete()` (`app/Http/Controllers/Api/WorkoutSessionController.php:265`)
carries roughly 55 lines defining what a personal record is — the history query at
`:289`, the per-exercise loop at `:302`, the weight rule at `:315`, the reps rule at
`:325`. There is no module to call, and `POST /api/workout-sessions/{session}/complete`
has **no test at all**, so none of it is exercised.

Two consequences, neither currently assertable:

**1. Weight and reps PRs are independent maxima.** `:289` selects
`MAX(weight)` and `MAX(reps)` in one aggregate across all of an exercise's history,
then `:315` and `:325` compare each against the session's own maxima separately. One
heavy single plus one light high-rep set therefore emits two records, neither of which
is a performance anyone actually achieved in one set.

**2. A first-ever session emits a PR for everything.** Both rules treat "no history"
(`$histWeight === null`) as beaten, so the first time a user logs an exercise they get
a weight PR *and* a reps PR for it. A first workout with six exercises produces twelve
personal records.

Whether those are bugs is a product question. The architectural point is that nobody
can answer it, because the rules cannot be run without a completed HTTP request.

## Fix

Extract a module — suggested `app/Services/WorkoutSession/PersonalRecords` — taking
the session's sets and the user's prior bests and returning the records. Pure: no
request, no mutation, no `$session->update()`.

```php
final class PersonalRecords
{
    /** @return Collection<int, PersonalRecord> */
    public static function detect(WorkoutSession $session): Collection;
}
```

`complete()` then reads: authorize, guard the transition (that is 003's job), persist,
ask the module, serialize.

Do **not** change the PR rules in the same change. Extract first with behaviour
identical, prove it with the tests below, then decide about the two problems above
separately — the whole reason they are hard to discuss now is that they are tangled
with the endpoint.

## Tests

Write these against the current behaviour *before* extracting, so the extraction is
provably behaviour-preserving:

- A completed session with no history emits a weight PR and a reps PR per exercise.
  (Locks current behaviour, questionable as it is — annotate it as such.)
- A session beating a previous weight but not previous reps emits one record.
- A session beating neither emits none.
- Completing an already-completed session re-emits the same records. This is 003's bug,
  not this one's; lock it here and let 003 change it.

Then, after extraction, the same assertions against the module directly with no HTTP.

## Notes

- `SessionDetail` at `app/Services/WorkoutSession/SessionDetail.php` is the precedent
  for module shape, naming and placement.
- Records are in Canonical Units. Any formatting belongs in the resource, per
  [ADR-0001](../adr/0001-convert-units-at-the-http-boundary.md).
- Conflicts with [009](009-set-ownership-has-no-owner.md) on the same controller.
