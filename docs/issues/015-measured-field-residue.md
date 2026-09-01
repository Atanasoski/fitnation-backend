# 015 — Measured-field residue: a second imperial step and dead validation rules

**Area:** back-end / measurement
**Severity:** low
**Status:** open
**Independent:** touches no file any other open issue touches.

## Read this first — it is smaller than it first looks

An earlier review called this a data-corruption hole, claiming the web write paths
store pounds in kilogram columns. **That was wrong**, and the correction is the reason
this issue is `low` rather than `high`:

- The Blade profile form does not expose weight or height at all — see
  `resources/views/profile/partials/update-profile-information-form.blade.php`. The
  rules at `app/Http/Requests/ProfileUpdateRequest.php:34–35` are unreachable through
  the UI.
- `target_weight` *is* editable in Blade, but only in
  `resources/views/workout-template-exercises/edit.blade.php:133`, its `users/` twin,
  and `resources/views/components/modals/add-exercise.blade.php:231` — all partner and
  admin tooling, which
  [ADR-0001](../adr/0001-convert-units-at-the-http-boundary.md) explicitly excludes:
  *"Admin and partner Blade tooling is deliberately excluded and stays metric — it is
  staff-facing, and staff read the canonical numbers."*

So the `api/`-only scoping of the invariant test is **consistent with the ADR**, not a
hole in it. Do not "fix" it by widening the guard without first reading the ADR — you
would be enforcing conversion on staff tooling the ADR deliberately leaves in kg.

What remains is genuine but small.

## Problem

### 1. The imperial step has two homes

`app/Enums/MeasurementKind.php:41` declares `imperialStep()` — 5 lb for a Training
Weight, 0.5 lb for a Body Weight. `app/Services/UnitConversionService.php:14` and `:16`
re-declare both as private constants:

```php
private const TRAINING_WEIGHT_STEP_LBS = 5.0;
private const BODY_WEIGHT_STEP_LBS = 0.5;
```

`toDisplay()` (`:27`) reads the enum; the three format aliases —
`formatTrainingWeight()` (`:55`), `formatBodyWeight()` (`:64`), `formatHeight()` (`:73`)
— read the constants. Two sources of truth for one fact, inside the module whose whole
purpose was to establish that there is one. This is exactly the failure the Measured
Field seam was built to prevent, reintroduced one layer down.

### 2. Three aliases that `toDisplay()` already subsumes

The three format methods are `toDisplay()` with the kind pre-bound. They exist only
because the constants do. Collapsing them removes the second source of truth as a side
effect.

Note the test asymmetry: all 16 tests in `tests/Unit/UnitConversionServiceTest.php`
cover the three aliases, while `toDisplay()`/`toStorage()` — the pair the design
actually rests on — are exercised only indirectly via
`tests/Feature/MeasurementInvariantsTest.php:121`. The tests will need rewriting
against `toDisplay()`, which is an improvement in itself.

### 3. Dead code

- `app/Http/Requests/ProfileUpdateRequest.php:34–35` — `height` and `weight` rules on a
  form that has no such inputs. Either remove them, or add `ConvertsIncomingUnits` if
  the fields are meant to come back. Removing is the honest default; a rule that
  validates something unreachable is a claim the code does not keep.
- `app/Enums/MeasurementKind.php:24`, `:32`, `:53` — `canonicalUnit()`,
  `imperialUnit()` and `isWholeNumber()` have **zero** callers in `app/`, `tests/` or
  `resources/`. Confirm with a grep, then delete.
- `app/Services/MeasuredFields.php` — `isMeasuredColumnName()` has zero callers.

Keep `MeasuredFields` itself. It looks shallow — four methods over two constants — but
it is the one shallow module in the repo that earns its place: deleting it returns the
"what kind is this column" decision to nine call sites, which is the failure its
docblock records.

## Fix

1. Delete `TRAINING_WEIGHT_STEP_LBS` and `BODY_WEIGHT_STEP_LBS`; have everything read
   `MeasurementKind::imperialStep()`.
2. Collapse the three format aliases into `toDisplay()` and update call sites —
   `app/Http/Resources/Concerns/FormatsMeasurements.php` is the main one.
3. Delete the dead methods and the unreachable validation rules.

## Tests

- `tests/Feature/MeasurementInvariantsTest.php` must stay green untouched. Its three
  guards are the safety net; if a change requires loosening them, the change is wrong.
- Rewrite `tests/Unit/UnitConversionServiceTest.php` against `toDisplay()`/`toStorage()`,
  keeping every existing case — the 5 lb and 0.5 lb rounding behaviour must not move.
- Add a case asserting a Training Weight and a Body Weight round differently at the same
  input, so the two steps cannot silently converge.

## Notes

- Do **not** widen `MeasurementInvariantsTest`'s `api/` filter (`:81`) without a
  decision to change ADR-0001's staff-facing exclusion. If you think the exclusion is
  wrong, that is an ADR amendment, not a test change.
