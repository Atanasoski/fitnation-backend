# 07 — Plan outline: workouts and exercise rows

**Parent:** [023 — Super-admin panel v2](../023-super-admin-panel-v2.md).

**What to build:** the workout and row editors in the outline. Workout: name and day (or any day), add, remove, row order. Row: sets, min and max reps, target weight in the user's Unit System (stored in kg, converted at the HTTP boundary per ADR-0001), rest, swap exercise, remove, move up or down. "+ exercise" shows a searchable picker of the plan owner's partner exercises (`forPartner` + `available()`). The row form always sends every field. Fix `WorkoutTemplateExerciseController::update`, which resets omitted fields to defaults.

**Blocked by:** 06, 02

**Status:** ready-for-agent

- [ ] Writes go through resourceful routes guarded by the 05 policy, and each redirects to the touched node.
- [ ] A partial update no longer resets other fields. Tested.
- [ ] Imperial user: weight entered in lb is stored in kg, and shown back in lb. Tested.
- [ ] Picker excludes archived exercises and other partners' exercises.
- [ ] Reorder keeps `order` contiguous.
