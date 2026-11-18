<?php

namespace Tests\Support;

use App\Models\Angle;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\TargetRegion;
use Illuminate\Support\Str;

/**
 * Seeds catalog exercises with explicit classification — the four codes a
 * Slot's pool query reads (pattern, region, equipment, angle).
 */
trait CreatesCatalogExercises
{
    protected function catalogExercise(
        string $pattern,
        string $region,
        string $equipment,
        ?string $angle = null,
        array $attributes = []
    ): Exercise {
        return Exercise::factory()->create(array_merge([
            'movement_pattern_id' => $this->lookupId(MovementPattern::class, $pattern),
            'target_region_id' => $this->lookupId(TargetRegion::class, $region),
            'equipment_type_id' => $this->lookupId(EquipmentType::class, $equipment),
            'angle_id' => $angle === null ? null : $this->lookupId(Angle::class, $angle),
        ], $attributes));
    }

    /**
     * One level-0 candidate per Slot role across every day type, so a
     * Standard or Extended session fills without relaxation. Shared,
     * because four suites needed the same gym (#143).
     */
    protected function seedFullGymCatalog(): void
    {
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'INCLINE');
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'VERTICAL');
        $this->catalogExercise('SHOULDER_RAISE', 'UPPER_PUSH', 'DUMBBELL');
        $this->catalogExercise('FLY', 'UPPER_PUSH', 'CABLE', 'HORIZONTAL');
        $this->catalogExercise('ELBOW_EXTENSION', 'ARMS', 'CABLE');
        $this->catalogExercise('ELBOW_EXTENSION', 'ARMS', 'BARBELL');
        $this->catalogExercise('ELBOW_FLEXION', 'ARMS', 'DUMBBELL');
        $this->catalogExercise('ELBOW_FLEXION', 'ARMS', 'CABLE');
        $this->catalogExercise('PULL_VERTICAL', 'UPPER_PULL', 'CABLE');
        $this->catalogExercise('ROW', 'UPPER_PULL', 'BARBELL', 'HORIZONTAL');
        $this->catalogExercise('ROW', 'UPPER_PULL', 'CABLE', 'HORIZONTAL');
        $this->catalogExercise('REAR_DELT_FLY', 'UPPER_PULL', 'DUMBBELL');
        $this->catalogExercise('PULLOVER_STRAIGHT_ARM', 'UPPER_PULL', 'CABLE');
        $this->catalogExercise('SQUAT', 'LOWER', 'BARBELL');
        $this->catalogExercise('HINGE', 'LOWER', 'BARBELL');
        $this->catalogExercise('KNEE_EXTENSION', 'LOWER', 'MACHINE');
        $this->catalogExercise('KNEE_FLEXION', 'LOWER', 'MACHINE');
        $this->catalogExercise('CALF_RAISE', 'LOWER', 'MACHINE');
        $this->catalogExercise('TRUNK_FLEXION', 'CORE', 'BODYWEIGHT');
        $this->catalogExercise('ROTATION', 'CORE', 'CABLE');
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function lookupId(string $model, string $code): int
    {
        return $model::firstOrCreate(
            ['code' => $code],
            ['name' => Str::of($code)->lower()->replace('_', ' ')->title()->toString(), 'display_order' => 0]
        )->id;
    }
}
