# 017 — Personal record rules: one set, and nothing on a first session

**Area:** back-end / domain rule
**Severity:** medium (user-visible — wrong and noisy celebrations)
**Status:** done — see *Resolved* below
**Answers:** the two product questions parked in
[010](010-personal-record-detection-has-no-seam.md)
**Conflicts with:** [003](003-session-status-transitions-unguarded.md) and
[005](005-session-resource-duplication-and-pr-detection.md) — all three touch
`complete()` and `PersonalRecordDetectionTest`. Do not run in parallel.

## The decisions

Both were product questions. Both now have answers:

1. **A personal record must come from a single set.** Today the session's
   heaviest set and its highest-rep set are compared against the all-time
   heaviest and all-time highest-rep sets as four independent numbers, so a
   session can report records describing a performance that never happened in
   one set.
2. **A first-ever session records nothing.** Today the absence of history counts
   as beaten, so a first workout of six exercises fires twelve celebrations.

Implement both. The rules live in
`app/Services/WorkoutSession/PersonalRecords.php`, which [010](010-personal-record-detection-has-no-seam.md)
extracted for exactly this purpose — the module's docblock currently describes
these as deliberately-preserved defects and will need rewriting.

## Problem

`PersonalRecords::recordsFor()` (`app/Services/WorkoutSession/PersonalRecords.php:77`):

```php
$sessionWeight = (float) $logs->max(fn (SetLog $log) => (float) $log->weight);   // :81
$sessionReps   = (int)   $logs->max(fn (SetLog $log) => (int) $log->reps);       // :82

if ($priorWeight === null || $sessionWeight > $priorWeight) { /* weight record */ }  // :89
if ($priorReps   === null || $sessionReps   > $priorReps)   { /* reps record   */ }  // :99
```

and `priorBests()` (`:123`) takes `MAX(weight)` and `MAX(reps)` from one
aggregate at `:136`, which is what makes the two independent — the query cannot
say whether they came from the same set.

Worked example, with a history of 3×12 at 100 kg. The session logs 110 kg × 1
and 40 kg × 20:

| | today | wanted |
|---|---|---|
| weight record | ✅ 100 → 110 | ✅ 100 → 110 |
| reps record | ✅ 12 → 20 | ❌ — a different set, and 40 kg is not a rep record worth the name |

And with no history at all, one set of 80 kg × 8 today emits a weight record
(`previous_best: 0`) *and* a reps record (`previous_best: 0`). Wanted: nothing.

## Fix

### Decision 2 first — it is three lines

If the exercise has no prior sets, emit no records. `priorBests()` returns both
maxima from the same aggregate over the same rows, so either both are present or
neither is: one `$priorBest === []` check covers it. The `?? 0` fallbacks at
`:94` and `:104` then become unreachable and should go, along with
`PersonalRecord`'s tolerance for a zero previous best if it has any.

### Decision 1 — pick the set, then ask what it beat

Keep both record types (see **Do not change the payload shape** below). The rule
becomes: choose one record-setting set per exercise, and emit only what *that*
set beat.

1. Take the exercise's prior bests as now.
2. Take the session's sets that beat prior weight **or** prior reps. If none,
   no records.
3. Among those, pick the best single set.
4. Emit a weight record if that set's weight beats the prior weight, and a reps
   record if its reps beat the prior reps. Both describe the same set by
   construction, and a set that beat both produces two records that are
   genuinely one performance.

**"Best single set" should be estimated 1RM, not heaviest.** `StrengthScore::oneRepMax()`
(`app/Services/FitnessMetrics/StrengthScore.php:127`) is public, static and
already the codebase's Epley implementation — reuse it rather than writing a
third. Heaviest-wins is simpler but picks badly: with a history of 100 × 12 and
a session of 101 × 1 and 100 × 20, heaviest picks the 101 single and drops the
20-rep set, while e1RM picks 100 × 20 (167 vs 104) and reports the rep record,
which is the better session.

While you are there: `ProgressionCalculatorService::estimateOneRepMax()`
(`:224`) is the same formula with zero callers. Delete it, so Epley has one home.

## Do not change the payload shape

`pr_type` is load-bearing in the client. `WorkoutSummaryScreen.tsx` branches on
it for the label (`:292` — "Max weight" / "Max reps"), for the unit in the
"was …" line (`:294`) and for the React key (`:272`,
`` `${pr.exercise_id}-${pr.pr_type}` ``). Collapsing the two types into one
record is therefore a client change, and out of scope here — keep
`PersonalRecordType::Weight` and `::Reps` and keep emitting at most one of each
per exercise.

Related, and worth a look but not this issue: the client already filters out
`pr_type === 'weight' && new_best === 0`
(`apps/web/src/components/workout-session/WorkoutSummaryScreen.tsx:254`, `:270`)
— a workaround for bodyweight exercises reporting a 0 kg weight record. Decision
2 removes the first-session case that filter partly exists for, but bodyweight
sets logged at 0 kg will still produce one. If it turns out the filter can go,
that is a front-end change to raise separately.

## Tests

`tests/Feature/PersonalRecordDetectionTest.php` holds both current behaviours by
name. **Edit those two tests — do not delete them, and do not loosen them.** They
are the record of what changed:

- `test_a_first_ever_session_records_a_weight_and_a_reps_pr_for_every_exercise`
  (`:58`) — becomes the assertion that a first-ever session records nothing.
  Rename accordingly.
- `test_weight_and_reps_prs_need_not_come_from_the_same_set` (`:119`) — already
  sets up the 110 × 1 / 40 × 20 case against a 100 × 12 history. Becomes the
  assertion that only the weight record is emitted. Rename accordingly.

Add:

- A single set beating both dimensions emits two records, both describing it.
- Two qualifying sets where the higher-e1RM one is *not* the heaviest: the
  e1RM winner's records are the ones emitted (the 101 × 1 vs 100 × 20 case
  above). This is the test that distinguishes the recommended rule from
  heaviest-wins; without it either implementation passes.
- A session where no set beats anything emits nothing.
- An exercise with history but whose session sets are all lighter and shorter
  emits nothing, while another exercise in the same session still records
  normally.

Every other test in that file must stay green untouched — in particular
`test_history_is_the_users_own_completed_sessions` and
`test_a_fractional_weight_survives_unconverted`.

## Notes

- The module docblock at `PersonalRecords.php:21–37` lists these two as
  deliberately preserved. Rewrite it to describe the rules as they then are, and
  drop the two bullets. Leave the third — *the session excludes itself from its
  own history by id and not by status*, so detecting twice returns the records
  twice — which belongs to [003](003-session-status-transitions-unguarded.md).
- `PersonalRecordType`'s docblock says the two "are detected independently of
  one another". That stops being true; update it.
- Records stay in Canonical Units. The unconverted-kilograms breach recorded in
  010 is still open and still not this issue — do not fix it here, or the
  behaviour change and the unit change land together and neither can be
  attributed.
- Per the house rules: this changes numbers users see, so it does not ride along
  with anything else, and the characterization commit does not apply — the two
  tests above already lock the old behaviour and are meant to change.

## Resolved

Both decisions are implemented in `PersonalRecords::recordsFor()`.

- No prior bests for an exercise means no records: `priorBests()` returns both
  maxima from one aggregate, so a single `$priorBest === []` check covers it.
  The `?? 0` fallbacks are gone, `priorBests()` no longer widens to null, and
  `PersonalRecord`'s docblock no longer excuses a zero previous best.
- `recordsFrom()` answers what one set beat — nothing at all unless it beat a
  prior best — and `recordsFor()` sorts the exercise's sets by
  `StrengthScore::oneRepMax()` and takes the first set that answers non-empty.
  Only that set's records are emitted, so both types describe one performance,
  and "does this set qualify" and "what does it record" cannot drift apart.

`ProgressionCalculatorService::estimateOneRepMax()` is deleted — Epley now has
one home, `StrengthScore::oneRepMax()`.

The payload shape is unchanged: `PersonalRecordType::Weight` and `::Reps`, at
most one of each per exercise.

Both tests named above were rewritten in place —
`test_a_first_ever_session_records_nothing` and
`test_a_record_describes_a_single_set` — and four added: one set beating both
dimensions, the e1RM winner over the heavier set (101 × 1 vs 100 × 20), a
session of three sets beating nothing, and one exercise going backwards while
another records.
`test_detecting_records_changes_nothing_about_the_session` needed history added
to its fixture, since its first-ever session now records nothing; its assertion
— that detection does not touch the session — is unchanged.

Still open and untouched here: the unconverted kilograms (010) and the
session-excludes-itself-by-id re-completion bug (003).

`CONTEXT.md`'s *Personal Record* entry described both old rules as the domain
vocabulary and is rewritten to the rules as they now are. Issue
[005](005-session-resource-duplication-and-pr-detection.md)'s part B is marked
mostly superseded there: its points 1, 2 and 4 are done between 010 and this,
and its reference to the deleted `estimateOneRepMax()` now points at
`StrengthScore::oneRepMax()`. Its point 3 — `previous_best`/`new_best` typed
`float` in one branch and `int` in the other — is untouched and still open.
