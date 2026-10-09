# 07 — Plan outline: workouts and exercise rows

**Parent:** [023 — Super-admin panel v2](../023-super-admin-panel-v2.md).

**What to build:** the workout and row editors in the outline. Workout: name and day (or any day), add, remove, row order. Row: sets, min and max reps, target weight in the user's Unit System (stored in kg, converted at the HTTP boundary per ADR-0001), rest, swap exercise, remove, move up or down. "+ exercise" shows a searchable picker of the plan owner's partner exercises (`forPartner` + `available()`). The row form always sends every field. Fix `WorkoutTemplateExerciseController::update`, which resets omitted fields to defaults.

**Blocked by:** 06, 02

**Status:** done

- [x] Writes go through resourceful routes guarded by the 05 policy, and each redirects to the touched node.
- [x] A partial update no longer resets other fields. Tested.
- [x] Imperial user: weight entered in lb is stored in kg, and shown back in lb. Tested.
- [x] Picker excludes archived exercises and other partners' exercises.
- [x] Reorder keeps `order` contiguous.

**Notes (done):**
- New routes: `workout-exercises.swap` (PUT) and `workout-exercises.move` (POST, `direction=up|down`), both scoped to the workout. Outline URLs add `workout`, `row` and `add=workout|exercise`.
- Moving a row reopens its workout (the order list lives there); every other write reopens the node it touched. Library-plan writes keep their old redirects.
- Store and swap refuse exercises outside the plan's catalogue (`Plan::offeredExercises()`), for library plans too.
- Until 08 retires them, the old user-plan pages (`workouts.show` add-exercise modal, `workout-exercises.edit`) show kg but their weight is now read in the owner's Unit System. Land 08 before release.
