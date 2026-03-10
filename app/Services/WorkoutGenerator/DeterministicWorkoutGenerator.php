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

        // Select diverse exercises (pattern|angle uniqueness)
        $selected = collect();
        $seen = []; // Track movement_pattern|angle combinations
        $compoundPatterns = config('workout_generator.compound_patterns', []);
        $maxPerPattern = config('workout_generator.max_exercises_per_pattern', 4);
        $maxPerRegion = config('workout_generator.max_exercises_per_region', 4);

        // Group exercises by target region
        $byRegion = $exercises->groupBy(fn ($e) => $e->targetRegion?->code ?? 'UNKNOWN');
        $preferredRegions = $preferences['target_regions'] ?? array_keys($byRegion->toArray());

        // If only one region is targeted, allow more exercises from it
        if (count($preferredRegions) === 1) {
            $maxPerRegion = config('workout_generator.max_total_exercises', 12);
        }

        $countByRegion = [];
        $countByPattern = [];

        // Select exercises with diversity constraint
        foreach ($preferredRegions as $region) {
            if (! $byRegion->has($region)) {
                continue;
            }

            if ($selected->count() >= $targetExerciseCount) {
                break;
            }

            $regionExercises = $byRegion->get($region);
            $countByRegion[$region] = $countByRegion[$region] ?? 0;

            // Shuffle for variety, then sort by compound-first priority
            $shuffled = $regionExercises->shuffle();
            $sorted = $this->sortByCompoundPriority($shuffled, $user);

            foreach ($sorted as $exercise) {
                if ($selected->count() >= $targetExerciseCount) {
                    break 2;
                }

                // Check region limit
                if ($countByRegion[$region] >= $maxPerRegion) {
                    break;
                }

                // Create diversity key
                $movementPattern = $exercise->movementPattern?->code ?? 'UNKNOWN';
                $angle = $exercise->angle?->code ?? 'NO_ANGLE';
                $key = "{$movementPattern}|{$angle}";

                // Skip if we already have this pattern+angle combination
                if (isset($seen[$key])) {
                    continue;
                }

                // Check pattern limit
                $patternCount = $countByPattern[$movementPattern] ?? 0;
                if ($patternCount >= $maxPerPattern) {
                    continue;
                }

                $selected->push($exercise);
                $seen[$key] = true;
                $countByRegion[$region]++;
                $countByPattern[$movementPattern] = ($countByPattern[$movementPattern] ?? 0) + 1;
            }
        }

        // Distribute sets among selected exercises
        $this->distributeSets($selected, $totalSets, $compoundPatterns);

        return $selected;
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
    private function sortByCompoundPriority(Collection $exercises, User $user): Collection
    {
        $compoundPatterns = config('workout_generator.compound_patterns', []);
        $experience = $user->profile?->training_experience ?? TrainingExperience::Beginner;

        // Complex patterns that beginners should deprioritize
        $complexPatterns = ['HINGE', 'PULL_VERTICAL'];
        $isBeginner = $experience === TrainingExperience::Beginner;

        // Use stable sort to preserve shuffle order within same priority
        return $exercises->sortBy(function ($exercise) use ($compoundPatterns, $complexPatterns, $isBeginner) {
            $pattern = $exercise->movementPattern?->code;

            // Compound movements get priority 0, isolation gets 1
            $basePriority = in_array($pattern, $compoundPatterns) ? 0 : 1;

            // For beginners, deprioritize complex patterns (add 0.5 to push them after simple compounds)
            if ($isBeginner && in_array($pattern, $complexPatterns)) {
                $basePriority += 0.5;
            }

            // For beginners, prefer machine/cable (safer, guided movements)
            if ($isBeginner) {
                $beginnerPreferredEquipment = config('workout_generator.beginner_preferred_equipment', []);
                $equipmentCode = $exercise->equipmentType?->code ?? '';
                if (! in_array($equipmentCode, $beginnerPreferredEquipment)) {
                    $basePriority += 0.3;
                }
            }

            return $basePriority;
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
            return ($muscleGroupPriority * 10) + $compoundPriority;
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

            // If no history (weight = 0), use fitness goal defaults for reps/rest
            if ($targets['target_weight'] == 0) {
                $targets['target_reps'] = $defaults['reps'];
                $targets['rest_seconds'] = $defaults['rest_seconds'];
            }

            $result[] = [
                'exercise_id' => $exercise->id,
                'order' => $order++,
                'target_sets' => $distributedSets > 0 ? $distributedSets : $defaults['sets'], // Fallback to defaults if distribution failed
                'target_reps' => $targets['target_reps'],
                'target_weight' => $targets['target_weight'],
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
            'reps' => 10,
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
