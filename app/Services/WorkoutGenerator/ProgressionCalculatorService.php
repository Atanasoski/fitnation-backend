<?php

namespace App\Services\WorkoutGenerator;

use App\Enums\TrainingExperience;
use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\User;

class ProgressionCalculatorService
{
    /**
     * Calculate target sets, reps, and weight for an exercise based on user history
     */
    public function calculateTargets(Exercise $exercise, User $user, ?TrainingExperience $experience = null): array
    {
        $experience ??= TrainingExperience::Beginner;
        $lastPerformance = $this->getLastPerformance($exercise, $user);

        if (! $lastPerformance) {
            return $this->getDefaultTargets($experience, $exercise, $user);
        }

        $defaultTargets = $this->getDefaultTargets($experience, $exercise, $user);
        $targets = $this->applyProgressiveOverload($lastPerformance, $exercise, $defaultTargets['max_target_reps']);

        return [
            'target_sets' => $targets['sets'],
            'min_target_reps' => $defaultTargets['min_target_reps'],
            'max_target_reps' => $defaultTargets['max_target_reps'],
            'target_weight' => $targets['weight'],
            'rest_seconds' => $lastPerformance['rest_seconds'] ?? $exercise->default_rest_sec ?? 90,
        ];
    }

    /**
     * Get last performance for an exercise
     */
    public function getLastPerformance(Exercise $exercise, User $user): ?array
    {
        $lastSetLog = $user->workoutSessions()
            ->where('status', WorkoutSessionStatus::Completed)
            ->whereHas('setLogs', function ($q) use ($exercise) {
                $q->where('exercise_id', $exercise->id);
            })
            ->with(['setLogs' => function ($q) use ($exercise) {
                $q->where('exercise_id', $exercise->id)
                    ->orderBy('set_number');
            }])
            ->orderBy('completed_at', 'desc')
            ->first();

        if (! $lastSetLog || $lastSetLog->setLogs->isEmpty()) {
            return null;
        }

        $weights = $lastSetLog->setLogs
            ->pluck('weight')
            ->map(fn ($weight) => (float) $weight)
            ->all();

        $normalizedWeightKeys = array_map(
            fn (float $weight) => number_format($weight, 2, '.', ''),
            $weights
        );
        $weightCounts = array_count_values($normalizedWeightKeys);
        arsort($weightCounts);
        $mostCommonWeight = (float) array_key_first($weightCounts);

        $reps = $lastSetLog->setLogs
            ->pluck('reps')
            ->map(fn ($repCount) => (int) $repCount)
            ->all();

        $restSeconds = $lastSetLog->setLogs
            ->pluck('rest_seconds')
            ->filter(fn ($rest) => $rest !== null)
            ->map(fn ($rest) => (int) $rest)
            ->first();

        return [
            'weight' => $mostCommonWeight,
            'sets' => $lastSetLog->setLogs->count(),
            'rest_seconds' => $restSeconds ?? $exercise->default_rest_sec ?? 90,
            'weights' => $weights,
            'reps' => $reps,
        ];
    }

    /**
     * Estimate one rep max using Epley formula
     */
    public function estimateOneRepMax(float $weight, int $reps): float
    {
        if ($reps <= 0) {
            return $weight;
        }

        if ($reps === 1) {
            return $weight;
        }

        // Epley formula: 1RM = weight × (1 + reps/30)
        return $weight * (1 + ($reps / 30));
    }

    /**
     * Apply progressive overload based on last performance
     */
    public function applyProgressiveOverload(array $lastPerformance, ?Exercise $exercise = null, int $maxTargetReps = 12): array
    {
        $weight = $lastPerformance['weight'];
        $sets = $lastPerformance['sets'] ?? 3;
        $lastReps = $lastPerformance['reps'] ?? [];
        $lastWeights = $lastPerformance['weights'] ?? [];
        $increment = $exercise ? $this->getWeightIncrement($exercise) : 2.5;
        $targetWeight = $weight;

        if (
            $this->shouldProgressWeight($lastReps, $lastWeights, $maxTargetReps)
            && $weight > 0
            && $increment > 0
        ) {
            $targetWeight = $this->roundToEquipmentIncrement($weight + $increment, $exercise);
        }

        return [
            'weight' => $targetWeight,
            'sets' => $sets,
        ];
    }

    /**
     * Determine if the user should progress by weight.
     */
    public function shouldProgressWeight(array $lastReps, array $lastWeights, int $maxTargetReps): bool
    {
        if ($lastReps === [] || $lastWeights === []) {
            return false;
        }

        $allSetsAtMaxReps = collect($lastReps)
            ->every(fn (int $reps) => $reps >= $maxTargetReps);

        $allSetsSameWeight = count(array_unique($lastWeights, SORT_NUMERIC)) === 1;

        return $allSetsAtMaxReps && $allSetsSameWeight;
    }

    /**
     * Determine current progression status for frontend messaging.
     */
    public function getProgressionStatus(?array $lastPerformance, int $minTargetReps, int $maxTargetReps): string
    {
        if (! $lastPerformance) {
            return 'no_history';
        }

        $lastReps = $lastPerformance['reps'] ?? [];
        $lastWeights = $lastPerformance['weights'] ?? [];

        if ($this->shouldProgressWeight($lastReps, $lastWeights, $maxTargetReps)) {
            return 'ready';
        }

        $anyBelowMin = collect($lastReps)
            ->contains(fn (int $reps) => $reps < $minTargetReps);

        return $anyBelowMin ? 'below_min' : 'working';
    }

    /**
     * Round a weight to the nearest realistic equipment increment
     */
    private function roundToEquipmentIncrement(float $weight, ?Exercise $exercise): float
    {
        if (! $exercise) {
            // Fallback to 2.5kg if no exercise provided
            return round($weight / 2.5) * 2.5;
        }

        $increment = $this->getWeightIncrement($exercise);

        // If increment is 0 (bodyweight/band), return 0
        if ($increment <= 0) {
            return 0;
        }

        return max(0, round($weight / $increment) * $increment);
    }

    /**
     * Get default targets based on experience level
     */
    private function getDefaultTargets(TrainingExperience $experience, ?Exercise $exercise = null, ?User $user = null): array
    {
        $targetWeight = 0;

        if ($exercise && $user) {
            $targetWeight = $this->estimateStartingWeight($exercise, $user, $experience);
        }

        return match ($experience) {
            TrainingExperience::Beginner => [
                'target_sets' => 3,
                'min_target_reps' => 8,
                'max_target_reps' => 12,
                'target_weight' => $targetWeight,
                'rest_seconds' => 90,
            ],
            TrainingExperience::Intermediate => [
                'target_sets' => 4,
                'min_target_reps' => 8,
                'max_target_reps' => 12,
                'target_weight' => $targetWeight,
                'rest_seconds' => 90,
            ],
            TrainingExperience::Advanced => [
                'target_sets' => 4,
                'min_target_reps' => 6,
                'max_target_reps' => 10,
                'target_weight' => $targetWeight,
                'rest_seconds' => 120,
            ],
        };
    }

    /**
     * Estimate starting weight based on user profile and exercise characteristics.
     *
     * Applies modifiers for movement pattern, gender, experience, equipment type,
     * and angle. Dumbbell weights are estimated per dumbbell (not total).
     */
    private function estimateStartingWeight(Exercise $exercise, User $user, TrainingExperience $experience): float
    {
        $profile = $user->profile;

        if (! $profile || ! $profile->weight) {
            return 0; // Can't estimate without body weight
        }

        $bodyWeight = (float) $profile->weight;
        $gender = $profile->gender ?? \App\Enums\Gender::Male;

        $movementCode = $exercise->movementPattern?->code ?? 'UNKNOWN';
        $equipmentCode = $exercise->equipmentType?->code ?? 'UNKNOWN';
        $angleCode = $exercise->angle?->code ?? null;

        // Base multiplier by movement pattern (as fraction of body weight)
        $baseMultiplier = $this->getBaseMultiplier($movementCode);

        // Adjust for gender (females typically ~60-70% of male strength ratios)
        $genderModifier = match ($gender) {
            \App\Enums\Gender::Female => 0.65,
            \App\Enums\Gender::Other => 0.80,
            default => 1.0,
        };

        // Adjust for experience
        $experienceModifier = match ($experience) {
            TrainingExperience::Beginner => 0.6,
            TrainingExperience::Intermediate => 1.0,
            TrainingExperience::Advanced => 1.3,
        };

        // Adjust for equipment type (dumbbell = per hand, cable lighter, etc.)
        $equipmentModifier = $this->getEquipmentModifier($equipmentCode);

        // Adjust for angle (overhead press lighter than bench, etc.)
        $angleModifier = $this->getAngleModifier($movementCode, $angleCode);

        $estimatedWeight = $bodyWeight * $baseMultiplier * $genderModifier
            * $experienceModifier * $equipmentModifier * $angleModifier;

        // Round to realistic weight increments based on equipment type
        return $this->roundToEquipmentIncrement($estimatedWeight, $exercise);
    }

    /**
     * Get the appropriate weight increment based on equipment type.
     *
     * Barbell: 2.5kg (smallest common plate pair = 1.25kg × 2)
     * Dumbbell: Fixed increments, typically 2kg or 2.5kg jumps (per dumbbell)
     * Cable/Machine: Usually 2.5kg or 5kg pin increments
     * Kettlebell: Fixed weights, typically 4kg jumps
     */
    private function getWeightIncrement(Exercise $exercise): float
    {
        $equipmentCode = $exercise->equipmentType?->code ?? $exercise->equipmentType?->name ?? 'UNKNOWN';

        return match (strtoupper($equipmentCode)) {
            'BARBELL' => 2.5,           // 1.25kg plates on each side
            'DUMBBELL' => 2.0,          // Fixed dumbbell increments (per dumbbell)
            'CABLE' => 2.5,             // Cable stack increments
            'MACHINE' => 5.0,           // Machine weight stack increments
            'SMITH' => 2.5,             // Similar to barbell
            'KETTLEBELL' => 4.0,        // Standard KB jumps (8, 12, 16, 20, 24...)
            'BAND' => 0.0,              // Bands don't have weight
            'BODYWEIGHT' => 0.0,        // No external weight
            'TRX' => 0.0,              // No external weight
            'MEDICINE_BALL' => 1.0,    // Fixed weight, smallest ~1kg increment
            'LANDMINE' => 2.5,         // Barbell in landmine attachment, same plate increments
            default => 2.5,             // Safe default
        };
    }

    /**
     * Get equipment modifier for starting weight estimation.
     *
     * Dumbbell weights are per dumbbell (~45% of barbell equivalent due to
     * stabilization demands). Cable/machine weights differ from free weights.
     */
    private function getEquipmentModifier(string $equipmentCode): float
    {
        return match (strtoupper($equipmentCode)) {
            'BARBELL' => 1.0,          // Baseline: total weight on bar
            'DUMBBELL' => 0.45,        // Per dumbbell (~45% of barbell, stabilization cost)
            'CABLE' => 0.55,           // Cable stacks lighter due to pulley mechanics
            'MACHINE' => 0.80,         // Guided path, but stack calibration varies
            'SMITH' => 0.85,           // Guided barbell, slightly less than free barbell
            'KETTLEBELL' => 0.45,      // Single implement, similar to per-dumbbell
            'MEDICINE_BALL' => 0.10,   // Very light implements
            'LANDMINE' => 0.75,        // Barbell in landmine, angled leverage reduces load
            'BODYWEIGHT' => 1.0,       // N/A (weight increment is 0)
            'TRX' => 1.0,             // N/A (weight increment is 0)
            'BAND' => 1.0,            // N/A (weight increment is 0)
            default => 0.70,           // Conservative default
        };
    }

    /**
     * Get angle modifier for starting weight estimation.
     *
     * Only affects upper body presses, rows, and flies where the angle
     * significantly changes the leverage and difficulty.
     * e.g. Overhead press (~65% of bench press), incline (~85% of flat).
     */
    private function getAngleModifier(string $movementCode, ?string $angleCode): float
    {
        // Angle only significantly affects upper body pressing, rowing, and flies
        if (! in_array($movementCode, ['PRESS', 'ROW', 'FLY'])) {
            return 1.0;
        }

        return match ($angleCode) {
            'FLAT' => 1.0,             // Strongest position for press/fly
            'HORIZONTAL' => 1.0,       // Strongest position for rows
            'INCLINE' => 0.85,         // ~15% less than flat
            'DECLINE' => 1.05,         // Slightly stronger than flat for press
            'VERTICAL' => 0.65,        // Overhead press ~65% of bench press
            'LOW_TO_HIGH' => 0.60,     // Landmine/low-to-high angle press
            'HIGH_TO_LOW' => 0.80,     // High-to-low cable work
            default => 0.85,           // Conservative default
        };
    }

    /**
     * Base multipliers as fraction of body weight (for intermediate male)
     * These are realistic starting points based on fitness standards
     */
    private function getBaseMultiplier(string $movementCode): float
    {
        return match ($movementCode) {
            // Compound Lower Body (strongest)
            'SQUAT', 'LEG_PRESS' => 1.0,
            'HINGE' => 1.1,                           // Deadlift patterns
            'HIP_THRUST_BRIDGE' => 0.8,
            'LUNGE_SPLIT_SQUAT' => 0.4,               // Per leg
            'BACK_EXTENSION' => 0.5,

            // Compound Upper Body
            'PRESS' => 0.65,                          // Bench press type
            'ROW' => 0.55,
            'PULL_VERTICAL' => 0.5,                   // Lat pulldown
            'DIP', 'PUSHUP' => 0.0,                   // Bodyweight
            'PULLOVER_STRAIGHT_ARM' => 0.25,

            // Isolation
            'ELBOW_FLEXION' => 0.15,                  // Bicep curls
            'ELBOW_EXTENSION' => 0.18,                // Tricep exercises
            'FLY' => 0.20,                            // Chest flies
            'KNEE_EXTENSION' => 0.35,                 // Leg extension
            'KNEE_FLEXION' => 0.30,                   // Leg curl
            'CALF_RAISE' => 0.7,
            'REAR_DELT_FLY', 'FACE_PULL' => 0.12,
            'HIP_ABDUCTION' => 0.20,
            'CARRY' => 0.35,

            // Core (often bodyweight or light)
            'TRUNK_FLEXION', 'ROTATION', 'ANTI_ROTATION' => 0.0,

            default => 0.30,                          // Conservative fallback
        };
    }

    /**
     * Check if a movement is lower body focused
     */
    private function isLowerBodyMovement(string $movementCode): bool
    {
        return in_array($movementCode, [
            'SQUAT', 'HINGE', 'LUNGE_SPLIT_SQUAT', 'LEG_PRESS',
            'KNEE_EXTENSION', 'KNEE_FLEXION', 'HIP_THRUST_BRIDGE',
            'HIP_ABDUCTION', 'CALF_RAISE', 'BACK_EXTENSION',
        ]);
    }
}
