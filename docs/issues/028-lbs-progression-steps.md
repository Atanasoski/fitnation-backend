# 028 — Progression steps in lbs for imperial users

**Area:** back-end (progression, HTTP boundary). Server-only: no app release needed.
**Severity:** medium for imperial (US) users; metric is unaffected.
**Status:** open. Needs a decision first: it bends [ADR-0001](../adr/0001-convert-units-at-the-http-boundary.md).
**Vocabulary:** [Training Weight, Unit System, Canonical Units](../../CONTEXT.md).

## Problem

Progression runs in kg with metric equipment steps
(`ProgressionCalculatorService::getWeightIncrement`: dumbbell 2 kg, barbell,
Smith and cable 2.5 kg, machine 5 kg, kettlebell 4 kg). For imperial users the
result is converted and snapped to 5 lb (`MeasurementKind::TrainingWeight`). A kg
step is not a lb step, so after snapping, across 10–200 lb in 5 lb steps:

- **Dumbbell, one step lighter** reads the same as the target at 20, 95, 130 and
  170 lb ("Use 20 lbs / Keep 20", reported 2026-10-12). Patched for 1.0.9:
  the resource now steps down from the displayed target (`20fdb82`), and the app
  hides an offer that reads the same (front-end `a74bb7c`). The numbers
  underneath are still kg steps.
- **Barbell, one step lighter** drops 5 or 10 lb; **machine** 5, 10 or 15 lb.
- **Dumbbell, one step heavier** stalls at 55 lb (24.9 kg + 2 kg → 26 kg = 57.3 lb,
  which reads 55), while the banner says "We've added 5 lbs".

## Proposal

Equipment steps per Unit System. For imperial, e.g. dumbbell 5 lb, barbell and
Smith 5 lb, cable 5 lb, machine 10 lb, kettlebell 5 lb (to confirm). The raise,
the lowered weight and the starting estimate then land on the lb grid for
imperial users, stored as the kg equivalent of a whole lb value.

## Decision needed

ADR-0001 keeps `ProgressionCalculatorService` blind to Unit Systems, and
`UnitSystemTest::test_progression_calculator_target_weight_is_identical_regardless_of_unit_preference`
locks that in. Either:

1. **The calculator takes the user's Unit System** and uses its equipment steps.
   Amend ADR-0001 and that test.
2. **The calculator stays in kg**, and the raise and lowered weight are corrected
   at the HTTP boundary, as `20fdb82` already does for the lowered weight only.

## Done when

- For imperial users, every raise and step down moves by the equipment's lb
  step and never reads the same as the weight it came from.
- Metric output is unchanged (`SessionDetailCharacterizationTest`).
- `WorkoutSessionExerciseResource::formatLoweredWeight` is removed, or becomes
  the one place that does this.
