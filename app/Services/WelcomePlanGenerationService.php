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
use App\Services\Plan\PlanActivation;
use App\Services\WorkoutGenerator\BlueprintWorkoutGenerator;
use App\Services\WorkoutGenerator\GeneratedDay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WelcomePlanGenerationService
{
    public function __construct(
        private BlueprintWorkoutGenerator $workoutGenerator
    ) {}

    /**
     * How many weeks this user's mesocycle runs, from the goal (ADR-0007).
     */
    public static function durationWeeksFor(User $user): int
    {
        $byGoal = config('workout_generator.program.duration_weeks_by_goal', []);
        $goal = $user->profile?->fitness_goal?->value;

        return $byGoal[$goal]
            ?? config('workout_generator.program.default_duration_weeks', 8);
    }

    /**
     * Generate a personalized program from the user's profile.
     * Becomes the user's active program, deactivating whichever program held
     * that slot (see PlanActivation). Their active routine is untouched.
     */
    public function generatePlan(User $user, ?string $planName = null, array $preferences = []): Plan
    {
        $this->validateUserProfile($user);

        return DB::transaction(function () use ($user, $planName, $preferences) {
            $splitFocus = match ($user->profile->gender) {
                Gender::Female => SplitFocus::LowerFocus,
                default => SplitFocus::Balanced,
            };

            $split = $this->determineSplit($user->profile->training_days_per_week, $splitFocus);
            $durationWeeks = self::durationWeeksFor($user);

            $plan = Plan::create([
                'user_id' => $user->id,
                'partner_id' => $user->partner_id,
                'name' => $planName ?? 'Your Personalized Plan',
                'description' => 'Auto-generated '.$durationWeeks.'-week program based on your profile',
                'type' => PlanType::Program,
                'duration_weeks' => $durationWeeks,
                'is_active' => false,
                'is_auto_generated' => true,
            ]);

            // The generated program becomes THE active program, replacing
            // whichever one held that slot. This used to deactivate only
            // is_auto_generated plans, which left a hand-made program active
            // alongside it — two active programs, which User::activeProgram()
            // cannot represent.
            PlanActivation::activate($plan);

            Log::info('Personalized plan created', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'training_days' => $user->profile->training_days_per_week,
                'duration_weeks' => $durationWeeks,
            ]);

            // One mesocycle (ADR-0007): each split day is generated once and
            // repeated identically across every week, so progression has the
            // same exercises to progress against. The whole split is one plan
            // generation: one pool load and one history lookup (#124).
            $split = array_values($split);
            $workouts = $this->workoutGenerator->generatePlan(
                $user,
                $this->generationPreferences($user, $preferences),
                $split,
            );

            // A workout is named from its resolved day, the same day type
            // its description names (#131).
            $days = [];
            foreach ($workouts as $dayIndex => $workout) {
                $days[$dayIndex] = [
                    'name' => $workout->blueprint->workoutName(),
                    'workout' => $workout,
                ];
            }

            $orderIndex = 0;
            for ($week = 1; $week <= $durationWeeks; $week++) {
                // A program is never deload-only: a misconfigured 1-week
                // duration produces one accumulation week, not one deload.
                $isDeloadWeek = $durationWeeks >= 2 && $week === $durationWeeks;

                foreach ($days as $dayIndex => $day) {
                    $this->writeTemplate(
                        $plan,
                        $isDeloadWeek ? $day['name'].' (Deload)' : $day['name'],
                        $isDeloadWeek ? $this->deloadWorkout($day['workout']) : $day['workout'],
                        $dayIndex,
                        $week,
                        $orderIndex,
                        $isDeloadWeek
                    );
                    $orderIndex++;
                }
            }

            Log::info('Personalized plan generation completed', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'templates_count' => $plan->workoutTemplates()->count(),
                'exercises_per_day' => array_map(
                    fn (array $day) => count($day['workout']->rows),
                    $days
                ),
            ]);

            return $plan->load(['workoutTemplates.exercises', 'workoutTemplates.exercises.partners']);
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

        // The sign-up trial starts here, not at registration: this is where
        // every registration path converges and the app becomes usable.
        $user->startSignupTrial();

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
     * The preferences every split day is generated under: the member's own
     * filters plus their stored session length.
     *
     * @return array<string, mixed>
     */
    private function generationPreferences(User $user, array $preferences): array
    {
        return array_merge(
            array_filter([
                'equipment_types' => $preferences['equipment_types'] ?? null,
                'movement_patterns' => $preferences['movement_patterns'] ?? null,
                'angles' => $preferences['angles'] ?? null,
                'training_styles' => $preferences['training_styles'] ?? null,
            ], fn ($v) => $v !== null),
            ['duration_minutes' => $user->profile->workout_duration_minutes]
        );
    }

    /**
     * The Deload Week variant of a generated workout (ADR-0007): every exercise
     * drops to the deload set count, rows filling a compound Slot to 3-4 reps
     * while isolation Slots keep their rep range. The Slot's class decides,
     * not the exercise's (ADR-0010): a split squat filling the quad-isolation
     * Slot keeps 10-15. Weight is untouched here because the Working Weight
     * is recomputed from history on every read, never from these rows.
     *
     * Public so the admin workout preview can show the same final week a
     * generated program would carry.
     */
    public function deloadWorkout(GeneratedDay $generatedWorkout): GeneratedDay
    {
        $deload = config('workout_generator.program.deload', []);
        $sets = $deload['sets'] ?? 2;
        $compoundMinReps = $deload['compound_min_reps'] ?? 3;
        $compoundMaxReps = $deload['compound_max_reps'] ?? 4;

        return $generatedWorkout->withRows(array_map(
            function (array $row) use ($generatedWorkout, $sets, $compoundMinReps, $compoundMaxReps) {
                $row['target_sets'] = $sets;

                if ($generatedWorkout->slotFor($row)->isCompound()) {
                    $row['min_target_reps'] = $compoundMinReps;
                    $row['max_target_reps'] = $compoundMaxReps;
                }

                return $row;
            },
            $generatedWorkout->rows,
        ));
    }

    /**
     * Write one generated workout as a template for one week.
     */
    private function writeTemplate(Plan $plan, string $workoutName, GeneratedDay $generatedWorkout, int $dayIndex, int $weekNumber, int $orderIndex, bool $isDeload = false): WorkoutTemplate
    {
        $template = WorkoutTemplate::create([
            'plan_id' => $plan->id,
            'name' => $workoutName,
            'description' => $generatedWorkout->description,
            'day_of_week' => $dayIndex,
            'week_number' => $weekNumber,
            'is_deload' => $isDeload,
            'order_index' => $orderIndex,
        ]);

        // Bulk insert: a program writes these rows per exercise per week (an
        // 8-week plan is 300+ rows) inside the onboarding request.
        $now = now();
        $rows = [];
        $order = 1;
        foreach ($generatedWorkout->rows as $exerciseData) {
            $rows[] = [
                'workout_template_id' => $template->id,
                'exercise_id' => $exerciseData['exercise_id'],
                'order' => $order++,
                'target_sets' => $exerciseData['target_sets'],
                'min_target_reps' => $exerciseData['min_target_reps'],
                'max_target_reps' => $exerciseData['max_target_reps'],
                'target_weight' => $exerciseData['target_weight'],
                'rest_seconds' => $exerciseData['rest_seconds'],
                'slot_key' => $exerciseData['slot_key'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            WorkoutTemplateExercise::insert($rows);
        }

        return $template;
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
