<?php

namespace App\Services;

use App\Enums\Gender;
use App\Enums\PlanType;
use App\Enums\SplitFocus;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkoutSplit;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use App\Services\WorkoutGenerator\DeterministicWorkoutGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WelcomePlanGenerationService
{
    public function __construct(
        private DeterministicWorkoutGenerator $workoutGenerator
    ) {}

    const DURATION_WEEKS = 5;

    /**
     * Generate a personalized program from the user's profile.
     * Deactivates any existing active auto-generated plan for this user.
     */
    public function generatePlan(User $user, ?string $planName = null, array $preferences = []): Plan
    {
        $this->validateUserProfile($user);

        return DB::transaction(function () use ($user, $planName, $preferences) {
            Plan::query()
                ->where('user_id', $user->id)
                ->where('is_auto_generated', true)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $splitFocus = match ($user->profile->gender) {
                Gender::Female => SplitFocus::LowerFocus,
                default => SplitFocus::Balanced,
            };

            $split = $this->determineSplit($user->profile->training_days_per_week, $splitFocus);

            $plan = Plan::create([
                'user_id' => $user->id,
                'partner_id' => $user->partner_id,
                'name' => $planName ?? 'Your Personalized Plan',
                'description' => 'Auto-generated '.self::DURATION_WEEKS.'-week program based on your profile',
                'type' => PlanType::Program,
                'duration_weeks' => self::DURATION_WEEKS,
                'is_active' => true,
                'is_auto_generated' => true,
            ]);

            Log::info('Personalized plan created', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'training_days' => $user->profile->training_days_per_week,
                'duration_weeks' => self::DURATION_WEEKS,
            ]);

            $orderIndex = 0;
            for ($week = 1; $week <= 5; $week++) {
                $dayIndex = 0;
                foreach ($split as $targetRegions) {
                    $this->createWorkoutTemplate($plan, $dayIndex, $targetRegions, $user, $week, $orderIndex, $preferences);
                    $dayIndex++;
                    $orderIndex++;
                }
            }

            Log::info('Personalized plan generation completed', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'templates_count' => $plan->workoutTemplates()->count(),
            ]);

            return $plan->load(['workoutTemplates.exercises']);
        });
    }

    /**
     * Generate a welcome plan for a user based on their profile (onboarding).
     */
    public function generateWelcomePlan(User $user, ?string $planName = null, array $preferences = []): Plan
    {
        if ($user->onboarding_completed_at !== null) {
            throw new \Exception('Onboarding has already been completed for this user');
        }

        $plan = $this->generatePlan($user, $planName, $preferences);

        $user->update([
            'onboarding_completed_at' => now(),
        ]);

        return $plan;
    }

    /**
     * Determine workout split based on training days per week and focus
     */
    private function determineSplit(int $daysPerWeek, SplitFocus $splitFocus): array
    {
        $split = WorkoutSplit::getSplit($daysPerWeek, $splitFocus);

        if (empty($split)) {
            throw new \RuntimeException(
                "No workout split found for {$daysPerWeek} days/week with {$splitFocus->value} focus"
            );
        }

        return $split;
    }

    /**
     * Create a workout template for a specific day and week
     */
    private function createWorkoutTemplate(Plan $plan, int $dayIndex, array $targetRegions, User $user, int $weekNumber, int $orderIndex, array $preferences = []): WorkoutTemplate
    {
        $workoutName = $this->getWorkoutName($targetRegions, $dayIndex);

        $generatedWorkout = $this->workoutGenerator->generate($user, array_merge(
            array_filter([
                'equipment_types' => $preferences['equipment_types'] ?? null,
                'movement_patterns' => $preferences['movement_patterns'] ?? null,
                'angles' => $preferences['angles'] ?? null,
                'training_styles' => $preferences['training_styles'] ?? null,
            ], fn ($v) => $v !== null),
            [
                'target_regions' => $targetRegions,
                'duration_minutes' => $user->profile->workout_duration_minutes,
            ]
        ));

        $template = WorkoutTemplate::create([
            'plan_id' => $plan->id,
            'name' => $workoutName,
            'description' => $generatedWorkout['rationale'] ?? null,
            'day_of_week' => $dayIndex,
            'week_number' => $weekNumber,
            'order_index' => $orderIndex,
        ]);

        $order = 1;
        foreach ($generatedWorkout['exercises'] as $exerciseData) {
            WorkoutTemplateExercise::create([
                'workout_template_id' => $template->id,
                'exercise_id' => $exerciseData['exercise_id'],
                'order' => $order++,
                'target_sets' => $exerciseData['target_sets'],
                'min_target_reps' => $exerciseData['min_target_reps'],
                'max_target_reps' => $exerciseData['max_target_reps'],
                'target_weight' => $exerciseData['target_weight'],
                'rest_seconds' => $exerciseData['rest_seconds'],
            ]);
        }

        Log::info('Workout template created', [
            'template_id' => $template->id,
            'plan_id' => $plan->id,
            'week_number' => $weekNumber,
            'day_index' => $dayIndex,
            'exercises_count' => count($generatedWorkout['exercises']),
        ]);

        return $template;
    }

    /**
     * Generate a workout name based on target regions
     */
    private function getWorkoutName(array $targetRegions, int $dayIndex): string
    {
        $regionSet = array_unique($targetRegions);
        sort($regionSet);
        $regionKey = implode('|', $regionSet);

        $isFullBody = count($regionSet) === 3
            && in_array('UPPER_PUSH', $regionSet)
            && in_array('UPPER_PULL', $regionSet)
            && in_array('LOWER', $regionSet);

        if ($isFullBody) {
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

        $regionNames = [
            'UPPER_PUSH' => 'Push',
            'UPPER_PULL' => 'Pull',
            'LOWER' => 'Legs',
            'ARMS' => 'Arms',
            'CORE' => 'Core',
        ];

        $names = [];
        foreach ($targetRegions as $region) {
            if (isset($regionNames[$region])) {
                $names[] = $regionNames[$region];
            }
        }

        if (empty($names)) {
            return 'Workout Day '.($dayIndex + 1);
        }

        if (count($names) === 1) {
            return $names[0].' Day';
        }

        return implode(' & ', $names).' Day';
    }

    /**
     * Validate user has required profile data
     */
    private function validateUserProfile(User $user): void
    {
        $profile = $user->profile;

        if (! $profile) {
            throw new \Exception('User profile is required for personalized plan generation');
        }

        if (! $profile->fitness_goal) {
            throw new \Exception('Fitness goal is required for personalized plan generation');
        }

        if (! $profile->training_experience) {
            throw new \Exception('Training experience is required for personalized plan generation');
        }

        if (! $profile->training_days_per_week) {
            throw new \Exception('Training days per week is required for personalized plan generation');
        }

        if (! $profile->workout_duration_minutes) {
            throw new \Exception('Workout duration is required for personalized plan generation');
        }
    }
}
