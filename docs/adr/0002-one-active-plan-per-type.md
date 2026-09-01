# One active plan per type

A user may hold **one active Program and one active Routine at the same time**.
Activating a plan deactivates the user's other plans of the **same type**, and
nothing else. `App\Services\Plan\PlanActivation::activate()` is the only place
that rule is written; every write path that sets `is_active` goes through it.

A Program is a finite, structured course — twelve weeks, progressing. A Routine
is a repeatable session the user drops into whenever they want. They answer
different questions, so holding both is the normal case, not an edge case.
`User::activePlan()` (the active Routine) and `User::activeProgram()` have
always been two independent `hasOne` relations; this ADR makes the write paths
agree with the read model they were already serving.

Partner library plans (`user_id === null`) are outside the rule: a partner's
library may offer many active plans at once, so activating one deactivates
nothing.

## Considered Options

- **Exactly one active plan overall, any type.** Rejected: it cannot represent
  a user following a 12-week program who also keeps a Saturday mobility
  routine, which is the case the two-relation read model was built for. It also
  makes `activeProgram()` and `activePlan()` mutually exclusive, so a mobile
  client showing both would blank one slot every time the user touched the
  other.
- **Leave the rule at each call site, documented.** Rejected: that is the state
  this ADR replaces. Eight sites carried the same comment over five different
  queries, and the divergence was invisible until a user lost a program.
- **Enforce it with a unique index on `(user_id, type)` where `is_active`.**
  Rejected for now: MySQL has no partial index, so it would need a generated
  column, and it turns a rule violation into a 500 rather than a corrected
  write. `PlanActivation` is the enforcement point; the index is worth
  revisiting if writes ever bypass it.

## Consequences

- **This is a behaviour change users will see.** `POST /api/custom-plans` with
  `is_active: true` used to deactivate *every* plan the user had, including
  their active Program — while `PUT` on that same routine deactivated only
  other Routines. Some users are currently carrying a program they never
  switched off. Creating an active routine no longer touches their program.
  This belongs in the release note.
- Generating a personalized program (`WelcomePlanGenerationService`) now
  deactivates whichever Program was active, not only auto-generated ones. The
  old `is_auto_generated` scope could leave a hand-made program active
  alongside the generated one — two active Programs, a state
  `User::activeProgram()` cannot represent.
- Deactivate-then-activate is one transaction. It previously was not, at seven
  of the eight sites: a failure between the two statements left the user with
  no active plan of that type. `PlanActivation` owns the `is_active` column
  outright — the update paths strip it from the attributes they write, because
  a plan committed active *before* that transaction opens can fail the other
  way and leave two active plans, which is the worse of the two states.
- A request that changes an already-active plan's `type` re-enters the rule
  even though it carries no `is_active` flag, since that plan has just started
  competing for a slot it was not holding.
- On update paths the update is applied *before* activation, so a plan whose
  `type` changed in the same request is scoped against its new type.

See `docs/issues/012-plan-activation-one-rule-five-scopes.md`.
