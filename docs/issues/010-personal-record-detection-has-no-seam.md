# 010 — Personal record detection has no seam and no test

**Area:** back-end / API
**Severity:** medium (correctness, untested)
**Status:** done — the seam exists; see *Left open by the extraction* below
**Follow-up:** the two product questions below are answered in [017](017-personal-record-rules.md)
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

## Left open by the extraction

The rules moved out of `complete()` unchanged, so all three of these survive it.
Each is now locked by a named test in
`tests/Feature/PersonalRecordDetectionTest.php`, which is the point: changing one
means editing a test that says the current behaviour is questionable, rather than
editing code nobody could run.

1. **Independent maxima** (problem 1 above) —
   `test_weight_and_reps_prs_need_not_come_from_the_same_set`. ~~Product
   question.~~ **Decided: a record must come from one set.** Now
   [017](017-personal-record-rules.md).
2. **A first-ever session records everything** (problem 2 above) —
   `test_a_first_ever_session_records_a_weight_and_a_reps_pr_for_every_exercise`.
   ~~Product question.~~ **Decided: a first-ever session records nothing.** Now
   [017](017-personal-record-rules.md).
3. **Record weights go out in kilograms, unconverted** —
   `test_a_fractional_weight_survives_unconverted`. Not in the problem statement
   above; found while extracting. `new_prs` carries Canonical Units straight to
   the client, so an imperial user reads kilograms as pounds — a plain
   [ADR-0001](../adr/0001-convert-units-at-the-http-boundary.md) breach, and not
   a product question. Nothing catches it either:
   `MeasurementInvariantsTest` guards Measured Fields — columns — and a record's
   numbers are computed, so they are not one.

   The extraction is what makes it fixable: `PersonalRecordResource` is now the
   one place it happens, and the record's `pr_type` is there to branch on —
   convert a weight record, leave a reps record alone. It was not fixed here
   because that moves a number users see, which house rules say belongs in its
   own change.
