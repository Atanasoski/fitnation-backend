<?php

namespace App\Services\WorkoutGenerator;

use App\Enums\FitnessGoal;

/**
 * Default Prescription (rep range + rest) for an exercise a user has no
 * history with. Classifies by the exercise's raw movement pattern (ADR-0009:
 * mechanics, never a selection alias). The generator applies these only when
 * no history exists — a user's own progression always outranks them.
 */
class PrescriptionCalculator
{
    /**
     * @return array{min_target_reps: int, max_target_reps: int, rest_seconds: int}
     */
    public function defaults(?string $movementPatternCode, FitnessGoal $goal, ?int $defaultRestSec): array
    {
        $tier = $this->isCompound($movementPatternCode) ? 'compound' : 'isolation';

        [$minReps, $maxReps] = config("workout_generator.rep_ranges.{$goal->value}.{$tier}")
            ?? config("workout_generator.rep_ranges.general_fitness.{$tier}")
            ?? [8, 12];

        $override = config("workout_generator.prescription_overrides.{$movementPatternCode}", []);

        return array_merge([
            'min_target_reps' => $minReps,
            'max_target_reps' => $maxReps,
            'rest_seconds' => $this->rest($goal, $defaultRestSec, $tier),
        ], $override);
    }

    private function isCompound(?string $movementPatternCode): bool
    {
        return in_array($movementPatternCode, config('workout_generator.compound_patterns', []));
    }

    private function rest(FitnessGoal $goal, ?int $defaultRestSec, string $tier): int
    {
        if ($defaultRestSec === null) {
            return config("workout_generator.fitness_goal_defaults.{$goal->value}.rest_seconds", 90);
        }

        $multiplier = config("workout_generator.rest_multipliers.{$goal->value}", 1.0);
        $bounds = config('workout_generator.rest_bounds', []);
        $roundTo = $bounds['round_to'] ?? 15;

        $rest = max(
            min($defaultRestSec * $multiplier, $bounds['max'] ?? 240),
            $bounds["{$tier}_min"] ?? 30,
        );

        return (int) (round($rest / $roundTo) * $roundTo);
    }
}
