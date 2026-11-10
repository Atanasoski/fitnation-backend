<?php

namespace App\Services\WorkoutGenerator;

use App\Enums\FitnessGoal;
use App\Enums\MovementClass;

/**
 * Default Prescription (rep range + rest) for an exercise a user has no
 * history with, for the compound/isolation class it is given (ADR-0009:
 * mechanics, never a selection alias). Generated rows pass their Slot's
 * class and take only the rest (ADR-0010); non-generated rows (manual
 * templates, hand-added session exercises) pass the exercise's class,
 * MovementClass::fromPattern() of its raw movement pattern. Rest is the
 * exercise's Default Rest — every Exercise has one — scaled by the goal.
 * A user's own logged history always outranks these defaults.
 */
class PrescriptionCalculator
{
    /**
     * @param  string|null  $movementPatternCode  keys prescription_overrides only; the class decides the matrix
     * @return array{min_target_reps: int, max_target_reps: int, rest_seconds: int}
     */
    public function defaults(MovementClass $class, FitnessGoal $goal, int $defaultRestSec, ?string $movementPatternCode = null): array
    {
        [$minReps, $maxReps] = config("workout_generator.rep_ranges.{$goal->value}.{$class->value}")
            ?? config("workout_generator.rep_ranges.general_fitness.{$class->value}")
            ?? [8, 12];

        $override = $movementPatternCode === null
            ? []
            : config("workout_generator.prescription_overrides.{$movementPatternCode}", []);

        return array_merge([
            'min_target_reps' => $minReps,
            'max_target_reps' => $maxReps,
            'rest_seconds' => $this->rest($goal, $defaultRestSec, $class),
        ], $override);
    }

    /**
     * The Default Rest scaled by the goal, clamped between the class's
     * floor (rest_bounds compound_min / isolation_min) and the ceiling, then
     * rounded.
     */
    private function rest(FitnessGoal $goal, int $defaultRestSec, MovementClass $class): int
    {
        $multiplier = config("workout_generator.rest_multipliers.{$goal->value}", 1.0);
        $bounds = config('workout_generator.rest_bounds', []);
        $roundTo = $bounds['round_to'] ?? 15;

        $rest = max(
            min($defaultRestSec * $multiplier, $bounds['max'] ?? 240),
            $bounds["{$class->value}_min"] ?? 30,
        );

        return (int) (round($rest / $roundTo) * $roundTo);
    }
}
