<?php

namespace Tests\Support;

use App\Models\User;
use App\Services\WorkoutGenerator\BlueprintWorkoutGenerator;
use App\Services\WorkoutGenerator\GeneratedDay;

/**
 * Generates one day through the generator's plan-level seam, the way a
 * single session is generated: as a one-day plan (ADR-0010).
 */
trait GeneratesOneDay
{
    /**
     * @param  list<string>  $regions  the day's requested target regions; none means a Full Body day
     * @param  array<string, mixed>  $preferences  duration_minutes, equipment_types, movement_patterns, angles, training_styles
     */
    protected function generateDay(User $user, array $regions, array $preferences = []): GeneratedDay
    {
        return app(BlueprintWorkoutGenerator::class)->generatePlan($user, $preferences, [$regions])[0];
    }
}
