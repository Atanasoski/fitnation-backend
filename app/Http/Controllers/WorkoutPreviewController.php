<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FitnessGoal;
use App\Enums\Gender;
use App\Enums\SplitFocus;
use App\Enums\TrainingExperience;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\WorkoutSplit;
use App\Services\WorkoutGenerator\DeterministicWorkoutGenerator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkoutPreviewController extends Controller
{
    public function __construct(
        private DeterministicWorkoutGenerator $workoutGenerator
    ) {}

    /**
     * Display the workout preview form
     */
    public function index(): View
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        $partners = Partner::orderBy('name')->get();

        return view('admin.workout-preview.index', compact('partners'));
    }

    /**
     * Generate and display workout preview
     */
    public function preview(Request $request): View
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        $validated = $request->validate([
            'fitness_goal' => ['required', 'string', 'in:fat_loss,muscle_gain,strength,general_fitness'],
            'training_experience' => ['required', 'string', 'in:beginner,intermediate,advanced'],
            'gender' => ['required', 'string', 'in:male,female,other'],
            'training_days_per_week' => ['required', 'integer', 'min:1', 'max:7'],
            'duration_minutes' => ['required', 'integer', 'in:30,45,60,75,90'],
            'weeks' => ['required', 'integer', 'min:1', 'max:12'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
        ]);

        // Get partner if provided
        $partner = isset($validated['partner_id']) ? Partner::find($validated['partner_id']) : null;

        // Build fake user with profile (no DB writes)
        $fakeUser = new User(['partner_id' => $validated['partner_id'] ?? null]);
        $fakeProfile = new UserProfile([
            'fitness_goal' => FitnessGoal::from($validated['fitness_goal']),
            'training_experience' => TrainingExperience::from($validated['training_experience']),
            'workout_duration_minutes' => (int) $validated['duration_minutes'],
            'training_days_per_week' => (int) $validated['training_days_per_week'],
            'gender' => Gender::from($validated['gender']),
        ]);
        $fakeUser->setRelation('profile', $fakeProfile);
        if ($partner) {
            $fakeUser->setRelation('partner', $partner);
        }

        // Determine split focus based on gender (same logic as WelcomePlanGenerationService)
        $splitFocus = match ($fakeProfile->gender) {
            Gender::Female => SplitFocus::LowerFocus,
            default => SplitFocus::Balanced,
        };

        // Get workout split
        $split = WorkoutSplit::getSplit((int) $validated['training_days_per_week'], $splitFocus);

        if (empty($split)) {
            $partners = Partner::orderBy('name')->get();

            return view('admin.workout-preview.index', [
                'error' => "No workout split found for {$validated['training_days_per_week']} days/week",
                'partners' => $partners,
                'params' => $validated,
            ]);
        }

        // Generate workouts for each week and day
        $weeks = [];
        $allExerciseIds = [];

        for ($week = 1; $week <= $validated['weeks']; $week++) {
            $weeks[$week] = [];

            foreach ($split as $dayIndex => $targetRegions) {
                $workout = $this->workoutGenerator->generate($fakeUser, [
                    'target_regions' => $targetRegions,
                    'duration_minutes' => (int) $validated['duration_minutes'],
                ]);

                $workoutName = $this->getWorkoutName($targetRegions, $dayIndex);

                $weeks[$week][] = [
                    'name' => $workoutName,
                    'exercises' => $workout['exercises'],
                ];

                // Collect exercise IDs for eager loading
                foreach ($workout['exercises'] as $exerciseData) {
                    $allExerciseIds[] = $exerciseData['exercise_id'];
                }
            }
        }

        // Eager load all exercises to avoid N+1 queries
        $exercises = Exercise::whereIn('id', array_unique($allExerciseIds))
            ->with(['primaryMuscleGroups', 'movementPattern'])
            ->get()
            ->keyBy('id');

        // Attach exercise models to workout data
        foreach ($weeks as $weekNum => $days) {
            foreach ($days as $dayIndex => $dayData) {
                foreach ($dayData['exercises'] as $exIndex => $exerciseData) {
                    if (isset($exercises[$exerciseData['exercise_id']])) {
                        $weeks[$weekNum][$dayIndex]['exercises'][$exIndex]['exercise'] = $exercises[$exerciseData['exercise_id']];
                    }
                }
            }
        }

        // Calculate summary stats
        $totalWorkouts = count($split) * $validated['weeks'];
        $uniqueExercises = count(array_unique($allExerciseIds));
        $avgExercisesPerSession = $totalWorkouts > 0 ? round(count($allExerciseIds) / $totalWorkouts, 1) : 0;

        $partners = Partner::orderBy('name')->get();

        return view('admin.workout-preview.index', [
            'weeks' => $weeks,
            'summary' => [
                'total_workouts' => $totalWorkouts,
                'unique_exercises' => $uniqueExercises,
                'avg_exercises_per_session' => $avgExercisesPerSession,
            ],
            'params' => $validated,
            'partners' => $partners,
        ]);
    }

    /**
     * Generate a workout name based on target regions
     * (Same logic as WelcomePlanGenerationService)
     */
    private function getWorkoutName(array $targetRegions, int $dayIndex): string
    {
        // Check for special multi-region combinations first
        $regionSet = array_unique($targetRegions);
        sort($regionSet);
        $regionKey = implode('|', $regionSet);

        // Check if it's a full body workout (all three main regions)
        $isFullBody = count($regionSet) === 3
            && in_array('UPPER_PUSH', $regionSet)
            && in_array('UPPER_PULL', $regionSet)
            && in_array('LOWER', $regionSet);

        if ($isFullBody) {
            // Determine focus based on first region in the array (order matters for exercise selection)
            $firstRegion = $targetRegions[0];
            $focus = match ($firstRegion) {
                'UPPER_PUSH' => 'Push',
                'UPPER_PULL' => 'Pull',
                'LOWER' => 'Legs',
                default => '',
            };

            return $focus ? "Full Body ({$focus})" : 'Full Body';
        }

        $specialNames = [
            'CORE|LOWER' => 'Legs Day',
            'UPPER_PULL|UPPER_PUSH' => 'Upper Body Day',
            'ARMS|UPPER_PUSH' => 'Push Day',
            'ARMS|UPPER_PULL' => 'Pull Day',
        ];

        if (isset($specialNames[$regionKey])) {
            return $specialNames[$regionKey];
        }

        // Single region or other combinations
        $regionNames = [
            'UPPER_PUSH' => 'Push',
            'UPPER_PULL' => 'Pull',
            'LOWER' => 'Legs',
            'ARMS' => 'Arms',
            'CORE' => 'Core',
        ];

        $names = [];
        foreach ($regionSet as $region) {
            $names[] = $regionNames[$region] ?? $region;
        }

        return implode(' + ', $names);
    }
}
