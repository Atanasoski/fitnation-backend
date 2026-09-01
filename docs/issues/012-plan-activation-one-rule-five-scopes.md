# 012 — "Only one plan may be active" is written eight times, five different ways

**Area:** back-end / domain rule
**Severity:** medium (correctness — a user-visible bug)
**Status:** done — the rule is one active plan per type; see [ADR-0002](../adr/0002-one-active-plan-per-type.md)
**Independent:** shares only `app/Models/Plan.php` with
[011](011-program-progress-leaks-into-the-resource.md).

## Problem

Eight sites deactivate a user's other plans when one becomes active. All eight carry
the same comment — *"Deactivate all other plans if this one is being set as active"* —
and no two agree on scope:

| File:line | Method | Scope |
|---|---|---|
| `app/Http/Controllers/Api/PlanController.php:91` | `store` | user |
| `app/Http/Controllers/Api/PlanController.php:142` | `update` | user + not self |
| `app/Http/Controllers/Api/PlanController.php:200` | `customPlansStore` | **user — every type** |
| `app/Http/Controllers/Api/PlanController.php:267` | `customPlansUpdate` | **user + Routine + not self** |
| `app/Http/Controllers/Api/PlanController.php:414` | `programsUpdate` | user + Program + not self |
| `app/Http/Controllers/PlanController.php:91` | web `store` | user |
| `app/Http/Controllers/PlanController.php:235` | web `update` | user |
| `app/Services/WelcomePlanGenerationService.php:38` | welcome plan | user + auto-generated + active |

### The live bug

Compare the two bolded rows. Creating a routine (`customPlansStore`) deactivates
**every** plan the user has, including their active Program. Updating that same routine
(`customPlansUpdate`) deactivates only other Routines. So:

- User has an active 12-week Program.
- User creates a routine and marks it active → their Program is silently deactivated.
- User edits the routine → the Program would have been left alone.

Same user action, two different outcomes depending on whether it was a create or an
update. Nothing tells the user their program was switched off.

There is no `Plan::activate()` and no activation service; the rule exists only as eight
copies of a `->update(['is_active' => false])` call.

## Fix

Decide the rule once — that is the substance of this issue, and it is a **product
question, not a refactor**: may a user hold an active Program and an active Routine at
the same time, or exactly one active plan overall? The code currently asserts both.
Get an answer before writing anything.

Then a single module, suggested `app/Services/Plan/PlanActivation`:

```php
final class PlanActivation
{
    /** Make this the user's active plan, deactivating whatever the rule says it must. */
    public static function activate(Plan $plan): void;
}
```

All eight sites call it. The welcome-plan case at
`WelcomePlanGenerationService.php:38` looks different — it scopes to
`is_auto_generated` — but it is the same rule with a narrower target; check whether the
narrowing is deliberate or just another divergence, and fold it in if it is the latter.

Wrap it in a transaction: today the deactivate and the activate are two statements with
no transaction around them in most of the eight.

## Tests

There is no test covering activation today. `tests/Feature/CustomPlanApiTest.php`,
`ProgramApiTest.php` and `RoutinePlanApiTest.php` are the neighbours.

- **The bug, first:** a user with an active Program creates an active Routine. Assert
  the intended outcome once decided — this test should fail before the fix.
- Create-then-update parity: the same two operations must leave the same set of plans
  active.
- Each of the eight entry points leaves exactly the intended set active.
- The welcome plan deactivates what it is supposed to and nothing more.

## Notes

- If the answer to the product question is "one Program and one Routine may both be
  active", this is a behaviour change to `customPlansStore`, and the release note should
  say so — some users will currently have a deactivated program they did not deactivate.
- `app/Services/WorkoutSession/SessionDetail.php` is the precedent for module shape,
  naming and placement.
- Worth an [ADR](../adr/) once the product question is answered: it is hard to reverse,
  surprising without context, and a real trade-off.

## Resolved

The product question was answered **one active plan per type**: a user may hold
an active Program and an active Routine at once. `User::activePlan()` and
`User::activeProgram()` had always assumed it; the write paths now agree.

`App\Services\Plan\PlanActivation` holds the rule. All eight sites call it,
`WelcomePlanGenerationService`'s `is_auto_generated` narrowing was folded in as
another divergence, and the deactivate/activate pair is one transaction. The
behaviour change to `customPlansStore` needs a release note — see ADR-0002.

Covered by `tests/Feature/PlanActivationTest.php`.
