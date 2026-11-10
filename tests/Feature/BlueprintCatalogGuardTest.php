<?php

namespace Tests\Feature;

use App\Services\WorkoutGenerator\Slots\BlueprintRepository;
use App\Services\WorkoutGenerator\Slots\RelaxationLevel;
use App\Services\WorkoutGenerator\Slots\Slot;
use App\Services\WorkoutGenerator\Slots\SlotConstraints;
use App\Services\WorkoutGenerator\Slots\SlotFiller;
use App\Services\WorkoutGenerator\Slots\SlotPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCatalogExercises;
use Tests\TestCase;

/**
 * The CI catalog guard (ADR-0010, issue #110): every Slot of every
 * Blueprint and Fragment stays fillable under the three equipment presets.
 * Fails whenever a blueprint edit (or an edit to this representative
 * catalog fixture) empties a slot beyond its allowed relaxation:
 *
 * - full gym            → every slot fills at waterfall level 0
 * - dumbbell+bodyweight → every slot fills at level ≤ 2
 * - bodyweight-only     → every slot fills at level ≤ 3; skips permitted
 *                         only on the exception list below
 *
 * The fixture mirrors the production catalog's verified shape
 * (2026-11-10, 274 available exercises): every slot's full-gym pool ≥ 3 at
 * level 0, every slot has ≥ 1 DUMBBELL/BODYWEIGHT exercise, and bodyweight
 * has genuine zeros exactly on the movement roles in the exception list.
 */
class BlueprintCatalogGuardTest extends TestCase
{
    use CreatesCatalogExercises;
    use RefreshDatabase;

    /**
     * Slots allowed to skip on the bodyweight-only preset, keyed by
     * blueprint code (or "fragment:REGION"). The production catalog has no
     * bodyweight exercise at all for these movement roles: side delts
     * (SHOULDER_RAISE), rear delts (REAR_DELT_FLY / FACE_PULL), flies
     * (FLY), biceps (ELBOW_FLEXION), triceps (ELBOW_EXTENSION), incline
     * press (PRESS∧INCLINE), and pullovers (PULLOVER_STRAIGHT_ARM).
     * A listed slot may still fill through the waterfall (incline press
     * resolves to another bodyweight push compound at level 2); the list
     * only permits the skip. Seeding a bodyweight exercise for one of
     * these roles makes the slot fill — then remove it from this list.
     */
    private const BODYWEIGHT_SKIP_EXCEPTIONS = [
        'PUSH' => ['incline_press', 'side_delts', 'triceps', 'chest_iso'],
        'PULL' => ['rear_delts', 'biceps', 'lats_iso'],
        'UPPER' => ['side_delts', 'arms'],
        'LOWER' => [],
        'FULL_BODY' => ['delts_arms'],
        'ARMS' => ['biceps_heavy', 'triceps_heavy', 'side_delts', 'arms_finisher', 'rear_delts'],
        'fragment:UPPER_PUSH' => ['chest_iso'],
        'fragment:UPPER_PULL' => ['rear_delts'],
        'fragment:LOWER' => [],
        'fragment:ARMS' => ['biceps', 'triceps', 'side_delts'],
        'fragment:CORE' => [],
    ];

    public function test_full_gym_fills_every_slot_at_level_zero(): void
    {
        $this->seedRepresentativeCatalog();

        $this->assertPresetFillsEverySlot(
            equipmentTypes: null,
            maxLevel: RelaxationLevel::None->value,
        );
    }

    public function test_dumbbell_and_bodyweight_fills_every_slot_at_level_two_or_better(): void
    {
        $this->seedRepresentativeCatalog();

        $this->assertPresetFillsEverySlot(
            equipmentTypes: ['DUMBBELL', 'BODYWEIGHT'],
            maxLevel: RelaxationLevel::PatternDropped->value,
        );
    }

    public function test_bodyweight_fills_every_slot_at_level_three_or_better_except_the_documented_zeros(): void
    {
        $this->seedRepresentativeCatalog();

        $this->assertPresetFillsEverySlot(
            equipmentTypes: ['BODYWEIGHT'],
            maxLevel: RelaxationLevel::BodyweightEquivalent->value,
            skipExceptions: self::BODYWEIGHT_SKIP_EXCEPTIONS,
        );
    }

    /**
     * Fills each day the way the generator will — slots in order, picks
     * excluded from later slots — and asserts every slot's relaxation
     * ceiling.
     *
     * @param  list<string>|null  $equipmentTypes
     * @param  array<string, list<string>>  $skipExceptions
     */
    private function assertPresetFillsEverySlot(?array $equipmentTypes, int $maxLevel, array $skipExceptions = []): void
    {
        $pool = SlotPool::load(new SlotConstraints(equipmentTypes: $equipmentTypes));
        $filler = new SlotFiller;

        foreach ($this->allDays() as $dayKey => $slots) {
            $excludeIds = [];

            foreach ($slots as $slot) {
                $result = $filler->fill($slot, $pool, $excludeIds);
                $label = "{$dayKey}.{$slot->key}";

                if ($result->isSkipped()) {
                    $this->assertContains(
                        $slot->key,
                        $skipExceptions[$dayKey] ?? [],
                        "Slot {$label} skipped but is not on the skip-exception list"
                    );

                    continue;
                }

                $this->assertLessThanOrEqual(
                    $maxLevel,
                    $result->level->value,
                    "Slot {$label} needed relaxation level {$result->level->value} (allowed ≤ {$maxLevel})"
                );

                $excludeIds[] = $result->exercise->id;
            }
        }
    }

    /**
     * @return array<string, list<Slot>>
     */
    private function allDays(): array
    {
        $repository = new BlueprintRepository;
        $days = [];

        foreach ($repository->blueprints() as $blueprint) {
            $days[$blueprint->code] = $blueprint->slots();
        }

        foreach ($repository->fragments() as $region => $slots) {
            $days["fragment:{$region}"] = $slots;
        }

        return $days;
    }

    /**
     * A compact catalog with the production catalog's verified shape.
     * Format: [pattern, region, equipment, angle].
     */
    private function seedRepresentativeCatalog(): void
    {
        $rows = [
            // Upper push — presses, pushups, dips, flies, side delts
            ['PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT'],
            ['PRESS', 'UPPER_PUSH', 'DUMBBELL', 'FLAT'],
            ['PRESS', 'UPPER_PUSH', 'BARBELL', 'INCLINE'],
            ['PRESS', 'UPPER_PUSH', 'DUMBBELL', 'INCLINE'],
            ['PRESS', 'UPPER_PUSH', 'BARBELL', 'VERTICAL'],
            ['PRESS', 'UPPER_PUSH', 'DUMBBELL', 'VERTICAL'],
            ['PUSHUP', 'UPPER_PUSH', 'BODYWEIGHT', 'HORIZONTAL'],
            ['PUSHUP', 'UPPER_PUSH', 'BODYWEIGHT', 'VERTICAL'],
            ['DIP', 'UPPER_PUSH', 'BODYWEIGHT', null],
            ['FLY', 'UPPER_PUSH', 'CABLE', 'HORIZONTAL'],
            ['FLY', 'UPPER_PUSH', 'DUMBBELL', 'FLAT'],
            ['SHOULDER_RAISE', 'UPPER_PUSH', 'DUMBBELL', null],
            ['SHOULDER_RAISE', 'UPPER_PUSH', 'CABLE', null],

            // Upper pull — vertical pulls, rows, rear delts, pullovers
            ['PULL_VERTICAL', 'UPPER_PULL', 'BODYWEIGHT', 'VERTICAL'],
            ['PULL_VERTICAL', 'UPPER_PULL', 'BODYWEIGHT', 'VERTICAL'],
            ['PULL_VERTICAL', 'UPPER_PULL', 'MACHINE', 'VERTICAL'],
            ['ROW', 'UPPER_PULL', 'BARBELL', 'HORIZONTAL'],
            ['ROW', 'UPPER_PULL', 'DUMBBELL', 'HORIZONTAL'],
            ['ROW', 'UPPER_PULL', 'BODYWEIGHT', 'HORIZONTAL'],
            ['REAR_DELT_FLY', 'UPPER_PULL', 'DUMBBELL', null],
            ['FACE_PULL', 'UPPER_PULL', 'CABLE', null],
            ['PULLOVER_STRAIGHT_ARM', 'UPPER_PULL', 'DUMBBELL', null],

            // Arms — curls and extensions (no bodyweight ones in production)
            ['ELBOW_FLEXION', 'ARMS', 'BARBELL', null],
            ['ELBOW_FLEXION', 'ARMS', 'DUMBBELL', null],
            ['ELBOW_FLEXION', 'ARMS', 'DUMBBELL', null],
            ['ELBOW_EXTENSION', 'ARMS', 'CABLE', null],
            ['ELBOW_EXTENSION', 'ARMS', 'DUMBBELL', null],

            // Lower — squats, hinges, isolations, calves
            ['SQUAT', 'LOWER', 'BARBELL', null],
            ['SQUAT', 'LOWER', 'DUMBBELL', null],
            ['SQUAT', 'LOWER', 'BODYWEIGHT', null],
            ['LEG_PRESS', 'LOWER', 'MACHINE', null],
            ['HINGE', 'LOWER', 'BARBELL', null],
            ['HINGE', 'LOWER', 'DUMBBELL', null],
            ['HIP_THRUST_BRIDGE', 'LOWER', 'BODYWEIGHT', null],
            ['KNEE_EXTENSION', 'LOWER', 'MACHINE', null],
            ['LUNGE_SPLIT_SQUAT', 'LOWER', 'DUMBBELL', null],
            ['LUNGE_SPLIT_SQUAT', 'LOWER', 'BODYWEIGHT', null],
            ['KNEE_FLEXION', 'LOWER', 'MACHINE', null],
            ['KNEE_FLEXION', 'LOWER', 'BODYWEIGHT', null],
            ['CALF_RAISE', 'LOWER', 'MACHINE', null],
            ['CALF_RAISE', 'LOWER', 'DUMBBELL', null],
            ['CALF_RAISE', 'LOWER', 'BODYWEIGHT', null],

            // Core
            ['TRUNK_FLEXION', 'CORE', 'BODYWEIGHT', null],
            ['TRUNK_FLEXION', 'CORE', 'CABLE', null],
            ['ROTATION', 'CORE', 'BODYWEIGHT', null],
            ['ANTI_ROTATION', 'CORE', 'BODYWEIGHT', null],
        ];

        foreach ($rows as [$pattern, $region, $equipment, $angle]) {
            $this->catalogExercise($pattern, $region, $equipment, $angle);
        }
    }
}
