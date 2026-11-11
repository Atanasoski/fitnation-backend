# Programs repeat identical weeks and end in a deload

A generated Program is one **Mesocycle**: every accumulation week carries the
*same* workouts (the same exercises, sets and rep ranges, duplicated template
rows per week), and the final week is a **Deload Week** (2 sets per exercise,
compounds at 3–4 reps, Working Weight unchanged). Length is goal-driven:
6 weeks for `fat_loss`, 8 for everything else. Exercise variety arrives
*between* Mesocycles — each regeneration shuffles a fresh selection — never
within one.

This deliberately reverses the original per-week shuffle, which was
trainer-advised for beginner engagement. A user who meets different exercises
every week has no history for `ProgressionCalculatorService` to progress
against, so double progression — the app's core progression mechanic — never
fired inside a program. Measurable progression won over in-block variety.

The deload is expressed purely through stored template rows, with no changes
to progression code, because of an existing asymmetry in
`SessionProgression::targetsFor()`: stored `target_sets`/`target_reps` win
over calculated targets, while `target_weight` is always recomputed from the
latest completed session (see **Working Weight** in the glossary). A deload
session cannot trigger a weight raise afterwards: deload sessions are not
performance history (ADR-0008), and every performance is judged against its
own Logged Range (ADR-0011). The deload week itself holds the Working Weight
(ADR-0011).

## Considered Options

- **Keep the per-week shuffle.** Rejected: progression never engages, so a
  program is five unrelated weeks, not a program.
- **Derive weeks from one template per day instead of duplicating rows.**
  Rejected for now: cleaner model, but `ProgramProgress`, completion tracking
  and the mobile program overview all key on per-week template rows. Revisit
  if the duplication grows painful.
- **Deload at reduced weight.** Rejected: a lighter prescribed weight cannot be
  expressed through stored rows (stored `target_weight` never wins), so it
  would require touching the progression read path. Low volume at Working
  Weight achieves the recovery goal within existing mechanics.
- **Automated deload triggers (plateau detection).** Deferred: needs new state
  tracking and prompting UX. The hardcoded final week ships first.

## Consequences

- Plans generated before this change keep their shuffled 5-week structure;
  nothing is migrated. Users get the new structure on their next regeneration.
- Deload-week workout names carry a "(Deload)" suffix so the sudden target
  drop reads as designed, not broken.
- End-of-mesocycle transition is manual: the user regenerates via the existing
  endpoint. Prompts, 1RM recalculation and accessory rotation are deferred.
- Known limitations, since resolved by ADR-0008 (deload sessions are marked
  and skipped as performance history) — all because a deload session used to
  be indistinguishable from an ordinary completed session:
  - After a completed deload, the next read of the same exercise reports
    `below_min` (3–4 reps under the working rep range's min), so the app may
    offer a one-step weight drop at the start of the next mesocycle.
  - A row with no stored set count (e.g. an exercise added by hand to a
    session) inherits the deload's 2 sets from last performance until a
    higher-set session is logged.
  - Bodyweight (`total_reps` mode) exercises get no rep deload: the stored
    3–4 bounds are suppressed and the total-reps target still asks for last
    total + 1. Their sets still drop to 2.
  ADR-0008 is that distinction: deload sessions are flagged and excluded
  from performance history.
