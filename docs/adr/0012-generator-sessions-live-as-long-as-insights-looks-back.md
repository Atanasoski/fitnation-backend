# Generator sessions live as long as Insights looks back

Draft Sessions and Cancelled sessions with no set logged are pruned once they
are older than Insights' longest range (`max(Insights::RANGES)`, 90 days
today), by `WorkoutSession::prunable()` through the daily `model:prune`.

The workout generator leaves a row behind on every preview and every swap, and
nothing ever removed them. Most are junk to the athlete, but they are not junk
to Insights: its generator section reads sessions created in the range and
splits them into completed, swapped (a cancelled session another points back
to through `replaced_session_id`) and cancelled, with drafts counted in each
group's size. Delete them sooner and every past range under-reports swaps and
gives-ups after the fact. Keeping them for exactly as long as Insights can ask
about them is the shortest retention that leaves every number it shows true.

Retention is read from `Insights::RANGES`, not written down twice, so adding
a longer range lengthens retention with it instead of silently showing a range
with its oldest days missing.

## Considered Options

- **Prune after a day.** Rejected: rewrites the generator section of every
  Insights range, in a way nobody reading it could notice.
- **Prune drafts after a day, keep cancels forever.** Rejected: drafts count
  in the generator's group sizes too, and cancels still grow without bound.
- **Keep everything.** Rejected: the table grows with every preview, and stale
  drafts held Inactivity Nudges off indefinitely (fixed separately: a draft
  older than a day no longer counts as in progress).

## Consequences

- A cancelled session with a set logged is never pruned: it is training data
  (a Stuck Session an admin cancelled keeps its sets), not generator junk.
- A setless Stuck Session an admin cancelled is pruned like any other
  cancel; its Admin Change keeps the record but loses the link to the session.
- A one-off manual delete on production in November 2026 used a 1-day cutoff,
  so the generator section under-reports swaps and cancels for ranges that
  reach back before it, until about mid-February 2027.
- Deleted rows cannot be recovered. Raising retention later only protects rows
  created after the change.
