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
