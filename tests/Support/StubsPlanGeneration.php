<?php

namespace Tests\Support;

use App\Services\WorkoutGenerator\BlueprintWorkoutGenerator;
use App\Services\WorkoutGenerator\GeneratedDay;
use App\Services\WorkoutGenerator\Slots\Blueprint;
use App\Services\WorkoutGenerator\Slots\SessionTier;

/**
 * A generator double for tests about what happens around generation (plan
 * activation, splits, the signup trial), not about generation itself: every
 * requested day comes back as an empty generated day, so no catalogue is
 * needed.
 */
trait StubsPlanGeneration
{
    protected function stubPlanGenerator(string $description = 'Test rationale'): BlueprintWorkoutGenerator
    {
        $generator = $this->createMock(BlueprintWorkoutGenerator::class);
        $generator->method('generatePlan')->willReturnCallback(
            fn ($user, array $preferences, array $days) => array_map(
                fn () => new GeneratedDay(
                    blueprint: new Blueprint('PUSH', 'Push', []),
                    tier: SessionTier::Standard,
                    durationMinutes: 60,
                    filledSlots: [],
                    skippedSlots: [],
                    rows: [],
                    description: $description,
                ),
                $days
            )
        );

        return $generator;
    }
}
