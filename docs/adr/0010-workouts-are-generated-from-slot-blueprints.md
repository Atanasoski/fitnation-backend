# Workouts are generated from slot Blueprints, not composition caps

The deterministic generator built a day's shape emergently: a three-pass greedy loop constrained by four cap families (`max_exercises_per_pattern` + overrides, `max_exercises_per_region`, `max_compound_by_region`, `max_total_exercises`), a complementary-pattern import with ARMS special-casing, a muscle-group ordering table, and a set-distribution round-robin. Every balance bug (ADR-0009's alias removal, the arm-day cap overrides, the 2026-10-25 set-distribution fix) was a patch on emergence, and the day's shape was only knowable by running the code. We replace all of it with declared shape: six Blueprints (Push, Pull, Upper, Lower, Full Body, Arms) in `config/workout_blueprints.php`, each an ordered list of Slots — movement role (patterns, optional angles), Tier, sets, rep range. Selection becomes: resolve blueprint, fill each slot from its pool, done. Balance is guaranteed by construction, weekly per-muscle volume is auditable by reading config, and a CI guard test (three equipment presets: full gym at waterfall level 0, dumbbell+bodyweight ≤ 2, bodyweight ≤ 3 with a documented skip-exception list) keeps the catalog honest.

## The mechanism

- **Resolver**: the request's `target_regions` map to a Blueprint:

  | request | blueprint |
  |---|---|
  | `[UPPER_PUSH]`, `[UPPER_PUSH, ARMS]` | PUSH |
  | `[UPPER_PULL]`, `[UPPER_PULL, ARMS]` | PULL |
  | `[UPPER_PUSH, UPPER_PULL]` | UPPER |
  | `[LOWER]`, `[LOWER, CORE]` | LOWER |
  | `[]`, `[FULL_BODY]`, all regions, any push/pull/lower 3-region combo | FULL_BODY (focus = first region; empty or the literal FULL_BODY region ⇒ Push focus, reorder only — one blueprint, no variants) |
  | `[ARMS]` | ARMS |
  | anything else (API-only; mobile presets can't produce it) | per-region Fragments, concatenated in request order, tier-filtered, trailing slots trimmed to the tier's set budget; a region with no Fragment (CONDITIONING, or FULL_BODY inside a larger combination) contributes nothing, and composed days never get the Extended +1-set bonus — the budget is their volume mechanism (#112) |

  A single region maps to the full day type: `[UPPER_PUSH]` yields PUSH including its ARMS-region triceps slot — the region list is a lookup key, the Blueprint defines content.
- **Session length is a fixed menu, and Tiers do the scaling**: durations are exactly 30/45/60/90/120 (what mobile already offers; Generate/Regenerate/ProfileUpdate validation tightens to this set, admin preview follows). 30 ⇒ Express (tier-1 slots only), 45/60 ⇒ Standard (tiers 1–2), 90/120 ⇒ Extended (all tiers, +1 set on the first two tier-1 slots; 120 adds nothing beyond 90). Internal tier resolution stays threshold-based (`<40`, `40–60`, `>60`) so legacy stored durations (e.g. 75) resolve without migration. `minutes_per_set`, `exercise_count_by_goal` and the set-distribution logic die.
- **Filling**: one pool query per day (equipment/beginner/partner/user filters), partitioned per slot in memory; pick = shuffle, excluding already-picked IDs (`selection_priority` descending is wired as the pre-shuffle sort but inert while uncurated). User-supplied `movement_patterns`/`angles`/`equipment_types` remain hard pool constraints. An empty slot walks the Relaxation Waterfall — drop angle; drop pattern keeping region + compound/isolation class; bodyweight equivalents; skip — and a skipped slot's sets go +1 to the earliest filled slots (cap: slot sets + 1). Every non-zero relaxation is logged with `slot_key` and level.
- **Prescriptions**: history → slot rep range, goal-shifted on **compound slots only** (strength ⇒ 4–6, fat loss ⇒ 8–12, others ⇒ slot base; isolations always keep the slot range) → the ADR-0009 matrix, which survives solely as the fallback for non-generated rows (manual templates, user-added session exercises). Slot sets always win in generated flows. Rest, starting weights, double progression, deload (ADR-0007/0008) are untouched.
- **Traceability**: `workout_template_exercises` and `workout_session_exercises` gain nullable `slot_key`, written by the generator, not yet exposed in API resources. The future "swap exercise" feature is exactly `pool(slot_key) − current`, out of scope here.

## Consequences

- **60-minute sessions drop from ~20 to ~15 sets.** Deliberate: Standard tier is the quality-volume target; do not "fix" this back by remapping 60 to Extended.
- Day shapes are uniform per blueprint by design; variety lives in pool shuffle, per split row (duplicate day types in one split fill independently), frozen within a Mesocycle as before.
- Two curls or two extensions on an Arms day is now by design (heavy + finisher slots); the `ELBOW_*` per-pattern cap overrides die with the caps. Issue #101's region-ownership concern stops affecting generation — slots filter by pattern.
- A 30-minute UPPER day is slightly push-biased (its three tier-1 slots are two presses + one pull) — accepted; balanced splits alternate focus.
- Prerequisite catalog work: the delt slots (side delts in PUSH/UPPER/ARMS, rear delts in PULL/ARMS) are unfillable until `SHOULDER_RAISE`/`REAR_DELT_FLY` exercises are seeded (0 of each in the dev catalog; verify prod). Catalog audit + seeding ships with or before the engine.
- Direct swap, no runtime engine flag: the legacy generator, its caps config, and its dead surface (`difficulty` request param — validated but never read — `formatForAI`, `calculateEstimatedDuration`, `isLowerBodyMovement`, `isolation_patterns`, `min_exercises_per_region`) are deleted in the same PR train. Emergence-pinning tests (`WorkoutGeneratorDiversityTest`, `WorkoutGeneratorArmsDayTest`) are replaced by table-driven blueprint tests; Prescription/Progression suites stand.
- Rows generated before the switch keep their stored prescriptions (same transitional posture as ADR-0009).
- Blueprints live in config, PR-reviewed; they move to the DB only if partners ever need custom blueprints.
