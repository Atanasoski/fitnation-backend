# 14. Generated workouts are Bodybuilding; some EquipmentTypes are opt-in

Date: 2026-11-20

## Status

Accepted. Supersedes the training-style clause of ADR-0010's "Filling" section.

## Context

Every Exercise carries one or more Training Styles (Bodybuilding, Functional, Olympic, Calisthenics). The generator defaulted to the Bodybuilding catalogue, but ADR-0010 carried over a legacy rule verbatim: when an Equipment Selection included any functional EquipmentType (TRX, Landmine, Kettlebell, Band, Medicine Ball), the Training Style filter was dropped entirely, not widened. Because the equipment filter always admits Bodyweight, picking "Dumbbell + Kettlebell" opened the pool to Olympic dumbbell lifts, Functional dumbbell moves and Calisthenics bodyweight moves the member never asked for.

The clients no longer offer a Training Style picker, and the product wants Bodybuilding workouts only for now.

## Decision

**Generated workouts are Bodybuilding unless the request names another Training Style.** The functional-equipment override is removed; the Training Style filter and the Equipment Selection always both apply. The API still accepts `training_styles`, so a future style picker needs no server change.

**The EquipmentTypes offered for generation are those with at least one Bodybuilding Exercise in the member's Partner Library**, not a hardcoded list. Medicine Ball, Landmine and Kettlebell drop out because none of their Exercises are Bodybuilding (Goblet Squat moves from Kettlebell to Dumbbell). The full list stays available to the exercise catalogue and its filters.

**TRX and Band are Opt-in EquipmentTypes**: a global list whose Exercises are generated only when the Equipment Selection names them. An empty selection means every EquipmentType except the opt-in ones.

**The retag is selective.** Only TRX, Band and Bodyweight Exercises that are genuinely hypertrophy movements gain the Bodybuilding tag, alongside their Functional one. Core, plank, plyometric, BOSU and combination moves stay Functional only.

## Considered Options

- **Widen instead of drop** (functional EquipmentType ⇒ Bodybuilding + Functional): keeps Olympic and Calisthenics out, but still serves Functional workouts the product does not want yet.
- **Retag whole EquipmentTypes as Bodybuilding** (all TRX, Band, Bodyweight): an empty Equipment Selection has no equipment filter, so TRX Body Saw and Box Jumps would reach every onboarding Program.
- **Low `selection_priority` for TRX/Band**: the sort is strict, so it makes them a last resort within a slot, not an exclusion; and it ties equipment policy to a column meant for curating Exercises.
- **Equipment Selection at onboarding**: worthwhile later, but it does not define what an empty selection means, so it complements the opt-in rule rather than replacing it.

## Consequences

- The Bodybuilding tag now carries policy: tagging a TRX or Band Exercise Bodybuilding makes it generatable for members who opt in, and tagging a Bodyweight Exercise makes it generatable for everyone.
- The opt-in rule must ship before or with the retag; otherwise retagged TRX Exercises reach default Programs in between.
- Training Styles are set in three places: the inline data in `exercises:import`, the classification seeders' inference (`ExerciseClassificationSeeder::inferTrainingStyles`, also used by the catalogue enrichment seeder), and data migrations for existing rows. A retag goes in all three, so neither a deploy, a re-import nor a re-seed reverts it.
- App builds released before the filtered EquipmentType list still offer Kettlebell, Landmine and Medicine Ball. A member who selects only those now gets bodyweight-heavy workouts from the Relaxation Waterfall instead of those exercises. Accepted: the selection is honoured rather than silently widened to equipment the member may not have.
- The opt-in list is global. A Partner whose gym floor has a TRX rig cannot yet make TRX a default for its members.
