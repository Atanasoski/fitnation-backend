<?php

namespace App\Services\WorkoutGenerator;

use App\Enums\FitnessGoal;
use App\Enums\TrainingExperience;
use App\Models\Exercise;
use App\Models\TargetRegion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DeterministicWorkoutGenerator
{
    public function __construct(
        private ExerciseSelectorService $exerciseSelector,
        private ProgressionCalculatorService $progressionCalculator
    ) {}

    /**
     * Generate a workout based on user preferences using deterministic rules
     */
    public function generate(User $user, array $preferences = []): array
    {
        // Normalize preferences
        // Empty target_regions = full body (all regions)
        $targetRegions = $preferences['target_regions'] ?? [];
        if (empty($targetRegions)) {
            $targetRegions = TargetRegion::orderBy('display_order')->pluck('code')->toArray();
        }

        // Duration from preferences or profile default
        $durationMinutes = $preferences['duration_minutes']
            ?? $user->profile?->workout_duration_minutes
            ?? 60;

        // Build normalized preferences for downstream use
        $normalizedPreferences = array_merge($preferences, [
            'target_regions' => $targetRegions,
            'duration_minutes' => $durationMinutes,
        ]);

        // Get available exercises matching filters
        $exercises = $this->exerciseSelector->getAvailableExercises([
            'target_regions' => $targetRegions,
            'equipment_types' => $preferences['equipment_types'] ?? null,
            'movement_patterns' => $preferences['movement_patterns'] ?? null,
            'angles' => $preferences['angles'] ?? null,
            'training_styles' => $preferences['training_styles'] ?? ['BODYBUILDING'],
            'limit' => 200,
        ], $user->partner);

        $complementaryPatterns = config('workout_generator.complementary_patterns', []);
        $complementaryMovementPatterns = collect($targetRegions)
            ->flatMap(fn (string $region) => $complementaryPatterns[$region] ?? [])
            ->unique()
            ->values()
            ->all();

        if (! empty($complementaryMovementPatterns)) {
            $complementary = $this->exerciseSelector->getAvailableExercises([
                'target_regions' => null,
                'equipment_types' => $preferences['equipment_types'] ?? null,
                'movement_patterns' => $complementaryMovementPatterns,
                'angles' => $preferences['angles'] ?? null,
                'training_styles' => $preferences['training_styles'] ?? ['BODYBUILDING'],
                'limit' => 200,
            ], $user->partner);

            foreach ($complementary as $exercise) {
                $exercise->generator_target_region_code = $this->inferComplementaryRegion($exercise, $targetRegions, $complementaryPatterns);
            }

            $exercises = $exercises
                ->concat($complementary)
                ->unique('id')
                ->values();
        }

        if ($exercises->isEmpty()) {
            throw new \Exception('No exercises available matching the specified criteria');
        }

        // Select diverse exercises and distribute sets based on duration
        $selectedExercises = $this->selectExercisesForDuration($exercises, $normalizedPreferences, $user);

        if ($selectedExercises->isEmpty()) {
            throw new \Exception('Could not select any exercises for the workout');
        }

        // Order exercises: compound first, isolation last
        $orderedExercises = $this->orderByCompoundFirst($selectedExercises);

        // Apply progression targets for each exercise (sets already distributed)
        $exercisesWithTargets = $this->applyTargets($orderedExercises, $user, $normalizedPreferences);

        Log::info('Deterministic workout generated', [
            'user_id' => $user->id,
            'exercises_count' => count($exercisesWithTargets),
            'exercise_ids' => array_column($exercisesWithTargets, 'exercise_id'),
        ]);

        return [
            'exercises' => $exercisesWithTargets,
            'rationale' => $this->buildRationale($orderedExercises, $normalizedPreferences),
        ];
    }

    /**
     * Select diverse exercises based on duration using set-based calculation.
     * 1 set = 3 minutes. Distributes sets among selected exercises.
     */
    private function selectExercisesForDuration(EloquentCollection $exercises, array $preferences, User $user): Collection
    {
        $durationMinutes = $preferences['duration_minutes'] ?? 60;
        $fitnessGoal = $user->profile?->fitness_goal ?? FitnessGoal::GeneralFitness;
        $experience = $user->profile?->training_experience ?? TrainingExperience::Beginner;

        // Calculate total sets: duration ÷ 3 minutes per set
        $minutesPerSet = config('workout_generator.minutes_per_set', 3);
        $totalSets = (int) floor($durationMinutes / $minutesPerSet);

        // Get target exercise count from config based on goal and duration
        $exerciseCounts = config('workout_generator.exercise_count_by_goal', []);
        $goalKey = $fitnessGoal->value;
        $goalCounts = $exerciseCounts[$goalKey] ?? $exerciseCounts['general_fitness'] ?? [];

        // Find closest duration match (round down to nearest configured duration)
        $targetExerciseCount = 4; // Default minimum
        $durations = array_keys($goalCounts);
        sort($durations);
        foreach ($durations as $configDuration) {
            if ($durationMinutes >= $configDuration) {
                $targetExerciseCount = $goalCounts[$configDuration];
            } else {
                break;
            }
        }

        // Ensure we have at least minimum exercises
        $minExercises = config('workout_generator.min_total_exercises', 4);
        $targetExerciseCount = max($targetExerciseCount, $minExercises);

        if ($experience === TrainingExperience::Beginner) {
            $excludedEquipment = config('workout_generator.beginner_excluded_equipment', []);
            if (! empty($excludedEquipment)) {
                $exercises = $exercises
                    ->reject(function (Exercise $exercise) use ($excludedEquipment) {
                        $equipmentCode = $exercise->equipmentType?->code;

                        return $equipmentCode && in_array($equipmentCode, $excludedEquipment);
                    })
                    ->values();
            }
        }

        $selected = collect();
        $selectedIds = [];

        // Strict pass: enforce pattern|angle uniqueness
        $seen = [];
        $compoundPatterns = config('workout_generator.compound_patterns', []);
        $compoundCount = 0;
        $maxPerPattern = config('workout_generator.max_exercises_per_pattern', 4);
        $maxPerRegion = config('workout_generator.max_exercises_per_region', 4);

        $byRegion = $exercises->groupBy(function (Exercise $exercise) {
            return $exercise->generator_target_region_code
                ?? $exercise->targetRegion?->code
                ?? 'UNKNOWN';
        });
        $preferredRegions = $preferences['target_regions'] ?? array_keys($byRegion->toArray());

        $maxCompoundByRegion = config('workout_generator.max_compound_by_region', []);
        $maxCompoundExercises = null;
        if (count($preferredRegions) === 1) {
            $maxCompoundExercises = $maxCompoundByRegion[$preferredRegions[0]] ?? null;
        }

        // If only one region is targeted, allow more exercises from it
        if (count($preferredRegions) === 1) {
            $maxPerRegion = config('workout_generator.max_total_exercises', 12);
        }

        $countByRegion = [];
        $countByPattern = [];

        $regionPools = [];
        $regionCursors = [];
        foreach ($preferredRegions as $region) {
            if (! $byRegion->has($region)) {
                continue;
            }

            $countByRegion[$region] = 0;
            $shuffled = $byRegion->get($region)->shuffle();
            $regionPools[$region] = $this->sortByCompoundPriority($shuffled)->values();
            $regionCursors[$region] = 0;
        }

        $activeRegions = array_keys($regionPools);

        foreach ([false, true] as $relaxed) {
            if ($relaxed) {
                foreach ($activeRegions as $r) {
                    $regionCursors[$r] = 0;
                }
            }

            while ($selected->count() < $targetExerciseCount) {
                $addedThisRound = false;

                foreach ($activeRegions as $region) {
                    if ($selected->count() >= $targetExerciseCount) {
                        break;
                    }
                    if (($countByRegion[$region] ?? 0) >= $maxPerRegion) {
                        continue;
                    }

                    $pool = $regionPools[$region];
                    while (($regionCursors[$region] ?? 0) < $pool->count()) {
                        $cursor = $regionCursors[$region];
                        /** @var \App\Models\Exercise $exercise */
                        $exercise = $pool->get($cursor);
                        $regionCursors[$region] = $cursor + 1;

                        if (! $exercise || isset($selectedIds[$exercise->id])) {
                            continue;
                        }

                        $pattern = $exercise->movementPattern?->code ?? 'UNKNOWN';
                        $angle = $exercise->angle?->code ?? 'NO_ANGLE';
                        $key = "{$pattern}|{$angle}";

                        $isCompound = in_array($pattern, $compoundPatterns);
                        if ($isCompound && $maxCompoundExercises !== null && $compoundCount >= $maxCompoundExercises) {
                            continue;
                        }
                        if (! $relaxed && isset($seen[$key])) {
                            continue;
                        }
                        if (($countByPattern[$pattern] ?? 0) >= $maxPerPattern) {
                            continue;
                        }

                        $selected->push($exercise);
                        $selectedIds[$exercise->id] = true;
                        $countByRegion[$region] = ($countByRegion[$region] ?? 0) + 1;
                        $countByPattern[$pattern] = ($countByPattern[$pattern] ?? 0) + 1;
                        if (! $relaxed) {
                            $seen[$key] = true;
                        }
                        if ($isCompound) {
                            $compoundCount++;
                        }
                        $addedThisRound = true;
                        break;
                    }
                }

                if (! $addedThisRound) {
                    break;
                }
            }
        }

        // Distribute sets among selected exercises
        $this->distributeSets($selected, $totalSets, $compoundPatterns);

        return $selected;
    }

    private function inferComplementaryRegion(Exercise $exercise, array $targetRegions, array $complementaryPatterns): ?string
    {
        $movementPattern = $exercise->movementPattern?->code;
        if (! $movementPattern) {
            return null;
        }

        foreach ($targetRegions as $region) {
            $patterns = $complementaryPatterns[$region] ?? [];
            if (in_array($movementPattern, $patterns)) {
                return $region;
            }
        }

        return null;
    }

    /**
     * Distribute total sets among exercises: compounds get 4, isolations get 2-3.
     * Adjusts to fit exactly into total sets.
     */
    private function distributeSets(Collection $exercises, int $totalSets, array $compoundPatterns): void
    {
        if ($exercises->isEmpty()) {
            return;
        }

        $maxSetsCompound = config('workout_generator.max_sets_per_compound', 4);
        $maxSetsIsolation = config('workout_generator.max_sets_per_isolation', 3);

        // Separate compounds and isolations
        $compounds = $exercises->filter(function ($exercise) use ($compoundPatterns) {
            $pattern = $exercise->movementPattern?->code ?? 'UNKNOWN';

            return in_array($pattern, $compoundPatterns);
        });

        $isolations = $exercises->filter(function ($exercise) use ($compoundPatterns) {
            $pattern = $exercise->movementPattern?->code ?? 'UNKNOWN';

            return ! in_array($pattern, $compoundPatterns);
        });

        // Assign sets: compounds first, then isolations
        $setsAssigned = 0;
        $setsPerExercise = [];

        // Assign sets to compounds (4 sets each, up to max)
        foreach ($compounds as $exercise) {
            $setsToAssign = min($maxSetsCompound, $totalSets - $setsAssigned);
            if ($setsToAssign > 0) {
                $setsPerExercise[$exercise->id] = $setsToAssign;
                $setsAssigned += $setsToAssign;
            }
        }

        // Assign sets to isolations (2-3 sets each, up to max)
        foreach ($isolations as $exercise) {
            if ($setsAssigned >= $totalSets) {
                break;
            }

            $remainingSets = $totalSets - $setsAssigned;
            $setsToAssign = min($maxSetsIsolation, $remainingSets);
            if ($setsToAssign > 0) {
                $setsPerExercise[$exercise->id] = $setsToAssign;
                $setsAssigned += $setsToAssign;
            }
        }

        // If we still have sets remaining, distribute them starting from compounds
        if ($setsAssigned < $totalSets) {
            $remainingSets = $totalSets - $setsAssigned;
            foreach ($compounds as $exercise) {
                if ($remainingSets <= 0) {
                    break;
                }
                $currentSets = $setsPerExercise[$exercise->id] ?? 0;
                if ($currentSets < $maxSetsCompound) {
                    $canAdd = min($maxSetsCompound - $currentSets, $remainingSets);
                    $setsPerExercise[$exercise->id] = $currentSets + $canAdd;
                    $remainingSets -= $canAdd;
                }
            }

            // Then to isolations
            foreach ($isolations as $exercise) {
                if ($remainingSets <= 0) {
                    break;
                }
                $currentSets = $setsPerExercise[$exercise->id] ?? 0;
                if ($currentSets < $maxSetsIsolation) {
                    $canAdd = min($maxSetsIsolation - $currentSets, $remainingSets);
                    $setsPerExercise[$exercise->id] = $currentSets + $canAdd;
                    $remainingSets -= $canAdd;
                }
            }
        }

        // Store sets on exercise objects for later use
        foreach ($exercises as $exercise) {
            $exercise->target_sets = $setsPerExercise[$exercise->id] ?? 0;
        }
    }

    /**
     * Sort exercises by compound-first priority while preserving shuffle order within priority groups
     * For beginners, deprioritize complex compound patterns
     */
    private function sortByCompoundPriority(Collection $exercises): Collection
    {
        $compoundPatterns = config('workout_generator.compound_patterns', []);

        return $exercises->sortBy(function ($exercise) use ($compoundPatterns) {
            $pattern = $exercise->movementPattern?->code;
            $compoundPriority = in_array($pattern, $compoundPatterns) ? 0 : 1;
            $selectionPriority = (int) ($exercise->selection_priority ?? 100);

            return ($compoundPriority * 1000) + (1000 - $selectionPriority);
        })->values();
    }

    /**
     * Muscle group ordering priority for bodybuilding-style workouts.
     * Lower number = earlier in workout (bigger muscles first).
     */
    private const MUSCLE_GROUP_PRIORITY = [
        // UPPER_PUSH (Push day order)
        'Chest' => 10,
        'Front Delts' => 20,
        'Side Delts' => 25,
        'Triceps' => 30,

        // UPPER_PULL (Pull day order)
        'Lats' => 10,
        'Upper Back' => 15,
        'Rear Delts' => 20,
        'Traps' => 25,
        'Biceps' => 30,
        'Forearms' => 35,

        // LOWER (Leg day order)
        'Quadriceps' => 10,
        'Glutes' => 10,
        'Hamstrings' => 20,
        'Calves' => 30,

        // CORE
        'Abs' => 10,
        'Obliques' => 20,
        'Lower Back' => 25,
    ];

    /**
     * Order all selected exercises: by muscle group priority, then compound first
     */
    private function orderByCompoundFirst(Collection $exercises): Collection
    {
        $compoundPatterns = config('workout_generator.compound_patterns', []);

        return $exercises->sortBy(function ($exercise) use ($compoundPatterns) {
            // Get primary muscle group priority (use the first/highest priority primary muscle)
            $primaryMuscles = $exercise->muscleGroups->filter(function ($muscle) {
                return $muscle->pivot->is_primary ?? false;
            });

            $muscleGroupPriority = 100; // Default for unknown muscles

            foreach ($primaryMuscles as $muscle) {
                $priority = self::MUSCLE_GROUP_PRIORITY[$muscle->name] ?? 50;
                $muscleGroupPriority = min($muscleGroupPriority, $priority);
            }

            // Compound vs isolation (0 for compound, 1 for isolation)
            $pattern = $exercise->movementPattern?->code;
            $compoundPriority = in_array($pattern, $compoundPatterns) ? 0 : 1;

            // Combine: muscle group (0-100) * 10 + compound priority (0-1)
            // This ensures muscle group order takes precedence, then compound/isolation within each group
            return ($muscleGroupPriority * 10) + $compoundPriority + (mt_rand(0, 99) / 100);
        })->values();
    }

    /**
     * Apply progression targets to selected exercises.
     * Sets come from distribution, reps/rest come from goal defaults or progression calculator.
     */
    private function applyTargets(Collection $exercises, User $user, array $preferences): array
    {
        $fitnessGoal = $user->profile?->fitness_goal ?? FitnessGoal::GeneralFitness;
        $trainingExperience = $user->profile?->training_experience;
        $defaults = $this->getGoalDefaults($fitnessGoal);

        $result = [];
        $order = 1;

        foreach ($exercises as $exercise) {
            // Try to get progression-based targets from user history
            $targets = $this->progressionCalculator->calculateTargets($exercise, $user, $trainingExperience);

            // Sets come from distribution (stored on exercise object)
            $distributedSets = $exercise->target_sets ?? 0;

            // If no history (weight = 0), use fitness goal defaults for rep range/rest
            if ($targets['target_weight'] == 0) {
                $targets['min_target_reps'] = $defaults['min_reps'];
                $targets['max_target_reps'] = $defaults['max_reps'];
                $targets['rest_seconds'] = $defaults['rest_seconds'];
            }

            $result[] = [
                'exercise_id' => $exercise->id,
                'order' => $order++,
                'target_sets' => $distributedSets > 0 ? $distributedSets : $defaults['sets'], // Fallback to defaults if distribution failed
                'min_target_reps' => $targets['min_target_reps'],
                'max_target_reps' => $targets['max_target_reps'],
                'target_weight' => $targets['target_weight'] ?? 0,
                'rest_seconds' => $targets['rest_seconds'],
            ];
        }

        return $result;
    }

    /**
     * Get default sets/reps/rest based on fitness goal
     */
    private function getGoalDefaults(FitnessGoal $goal): array
    {
        $defaults = config('workout_generator.fitness_goal_defaults', []);

        return $defaults[$goal->value] ?? [
            'sets' => 3,
            'min_reps' => 8,
            'max_reps' => 12,
            'rest_seconds' => 90,
        ];
    }

    /**
     * Build a rationale explaining the workout selection
     */
    private function buildRationale(Collection $exercises, array $preferences): string
    {
        $regions = $exercises->pluck('targetRegion.name')->unique()->filter()->implode(', ');
        $patterns = $exercises->pluck('movementPattern.name')->unique()->filter()->implode(', ');
        $equipmentTypes = $exercises->pluck('equipmentType.name')->unique()->filter()->implode(', ');

        $parts = [];

        if ($regions) {
            $parts[] = "targeting {$regions}";
        }

        if ($patterns) {
            $parts[] = "including {$patterns} movements";
        }

        if ($equipmentTypes) {
            $parts[] = "using {$equipmentTypes}";
        }

        if (! empty($preferences['duration_minutes'])) {
            $parts[] = "designed for approximately {$preferences['duration_minutes']} minutes";
        }

        $description = implode(', ', $parts);

        return "Generated workout {$description}. Exercises ordered from compound to isolation for optimal performance.";
    }
}
