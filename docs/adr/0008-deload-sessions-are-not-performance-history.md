# Deload sessions are not performance history

Deload sessions are marked and skipped when progression reads a user's last
performance. `workout_templates.is_deload` is the source of truth, set only by
the program generator; `workout_sessions.is_deload` is copied from the template
when a session starts, so both last-performance lookups
(`ProgressionCalculatorService::getLastPerformance()` and the batched window
query) filter on the session flag alone — no join, and the answer survives
template deletion. "Last performance" therefore always means the last *working*
session: a 2-set, 3–4-rep recovery week cannot masquerade as a plateau
(`below_min`), leak its 2 sets into calculated targets, or raise a bodyweight
exercise's total-reps bar. During a deload session itself, `total_reps`
exercises show no total target at all rather than "beat last week + 1".

Plan cloning propagates the template flag for free (`replicate()`), and the
flag is exposed on template and session API resources so clients can label
deload work. Deload sessions still complete their templates in
`ProgramProgress`, and the weight raise needs no guard of its own: the
Logged Range verdict (ADR-0011) judges only working sessions, so a deload's
3–4 reps are never judged, and a Deload Week session holds the Working
Weight.

## Considered Options

- **Template flag only, joined at read time.** Rejected: puts a join inside
  the ranked window query, and deload-ness dies with the template row.
- **Session flag only, inferred at start.** Rejected: the only signal at
  session start would be the "(Deload)" name suffix — string-sniffing as
  schema.
- **A `session_kind` enum instead of a boolean.** Rejected as speculative:
  no other kinds (testing weeks, AMRAP days) are planned, and boolean→enum
  is one cheap migration later.
- **Special-casing only the status messaging.** Rejected: fixes one of three
  symptoms and leaves "last performance" meaning something false.

## Consequences

- A user whose only history for an exercise is deload sessions reads as
  having no history and gets the starting-weight estimate. Accepted: the
  estimate beats 2-set recovery data, and reaching the deload with no
  accumulation sessions is the degenerate case.
- Only the generator writes `is_deload`; there is deliberately no admin or
  API surface for setting it on hand-built templates yet.
- Resolves the "known, accepted limitations" recorded in ADR-0007.
