<?php

namespace Database\Factories;

use App\Models\Angle;
use App\Models\EquipmentType;
use App\Models\Exercise;
use App\Models\MovementPattern;
use App\Models\TargetRegion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Exercise>
 */
class ExerciseFactory extends Factory
{
    protected $model = Exercise::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'category_id' => null,
            'movement_pattern_id' => null,
            'target_region_id' => null,
            'equipment_type_id' => null,
            'angle_id' => null,
            'muscle_group_image' => null,
            'image' => null,
            'video' => null,
            'default_rest_sec' => fake()->randomElement([60, 90, 120, 180]),
        ];
    }

    /**
     * Indicate that the exercise is a press movement.
     */
    public function press(): static
    {
        return $this->state(function (array $attributes) {
            $movementPattern = MovementPattern::firstOrCreate(
                ['code' => 'PRESS'],
                ['name' => 'Press', 'display_order' => 10]
            );
            $targetRegion = TargetRegion::firstOrCreate(
                ['code' => 'UPPER_PUSH'],
                ['name' => 'Upper Push', 'display_order' => 10]
            );

            return [
                'movement_pattern_id' => $movementPattern->id,
                'target_region_id' => $targetRegion->id,
            ];
        });
    }

    /**
     * Indicate that the exercise is a row movement.
     */
    public function row(): static
    {
        return $this->state(function (array $attributes) {
            $movementPattern = MovementPattern::firstOrCreate(
                ['code' => 'ROW'],
                ['name' => 'Row', 'display_order' => 110]
            );
            $targetRegion = TargetRegion::firstOrCreate(
                ['code' => 'UPPER_PULL'],
                ['name' => 'Upper Pull', 'display_order' => 20]
            );

            return [
                'movement_pattern_id' => $movementPattern->id,
                'target_region_id' => $targetRegion->id,
            ];
        });
    }

    /**
     * Indicate that the exercise uses a barbell.
     */
    public function barbell(): static
    {
        return $this->state(function (array $attributes) {
            $equipmentType = EquipmentType::firstOrCreate(
                ['code' => 'BARBELL'],
                ['name' => 'Barbell', 'display_order' => 10]
            );

            return [
                'equipment_type_id' => $equipmentType->id,
            ];
        });
    }

    /**
     * Indicate that the exercise has a flat angle.
     */
    public function flat(): static
    {
        return $this->state(function (array $attributes) {
            $angle = Angle::firstOrCreate(
                ['code' => 'FLAT'],
                ['name' => 'Flat', 'display_order' => 10]
            );

            return [
                'angle_id' => $angle->id,
            ];
        });
    }

    /**
     * Indicate that the exercise has a vertical angle.
     */
    public function vertical(): static
    {
        return $this->state(function (array $attributes) {
            $angle = Angle::firstOrCreate(
                ['code' => 'VERTICAL'],
                ['name' => 'Vertical', 'display_order' => 50]
            );

            return [
                'angle_id' => $angle->id,
            ];
        });
    }

    /**
     * Indicate that the exercise has a low-to-high angle.
     */
    public function lowToHigh(): static
    {
        return $this->state(function (array $attributes) {
            $angle = Angle::firstOrCreate(
                ['code' => 'LOW_TO_HIGH'],
                ['name' => 'Low to High', 'display_order' => 60]
            );

            return [
                'angle_id' => $angle->id,
            ];
        });
    }

    /**
     * Indicate that the exercise has a horizontal angle.
     */
    public function horizontal(): static
    {
        return $this->state(function (array $attributes) {
            $angle = Angle::firstOrCreate(
                ['code' => 'HORIZONTAL'],
                ['name' => 'Horizontal', 'display_order' => 40]
            );

            return [
                'angle_id' => $angle->id,
            ];
        });
    }

    /**
     * Indicate that the exercise has an incline angle.
     */
    public function incline(): static
    {
        return $this->state(function (array $attributes) {
            $angle = Angle::firstOrCreate(
                ['code' => 'INCLINE'],
                ['name' => 'Incline', 'display_order' => 20]
            );

            return [
                'angle_id' => $angle->id,
            ];
        });
    }

    /**
     * Set an explicit selection priority for deterministic generator ordering.
     */
    public function withPriority(int $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'selection_priority' => $priority,
        ]);
    }
}
