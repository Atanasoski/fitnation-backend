<?php

namespace App\Services\WorkoutGenerator;

use App\Enums\WorkoutSessionStatus;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkoutGenerationService
{
    public function __construct(
        private DeterministicWorkoutGenerator $workoutGenerator
    ) {}

    /**
     * Generate a new workout session in draft status
     * Creates session with exercises, ready for user to modify before confirming
     */
    public function generate(User $user, array $preferences = []): WorkoutSession
    {
        // Validate user profile
        $this->validateUserProfile($user);

        try {
            // Generate workout using deterministic algorithm
            $generatedWorkout = $this->workoutGenerator->generate($user, $preferences);

            Log::info('Workout generated', [
                'user_id' => $user->id,
                'exercises_count' => count($generatedWorkout['exercises'] ?? []),
                'exercise_ids' => array_column($generatedWorkout['exercises'] ?? [], 'exercise_id'),
            ]);

            // Validate we have exercises
            if (empty($generatedWorkout['exercises'])) {
                throw new \Exception('No exercises could be selected for this workout. Please try different criteria.');
            }

            // Create draft session in database
            return DB::transaction(function () use ($user, $generatedWorkout) {
                $session = WorkoutSession::create([
                    'user_id' => $user->id,
                    'workout_template_id' => null,
                    'performed_at' => null, // Draft sessions don't have performed_at
                    'is_auto_generated' => true,
                    'status' => WorkoutSessionStatus::Draft,
                    'notes' => $generatedWorkout['rationale'],
                ]);

                // Create workout session exercises
                $now = now();
                $exercisesToInsert = [];

                foreach ($generatedWorkout['exercises'] as $exerciseData) {
                    $exercisesToInsert[] = [
                        'workout_session_id' => $session->id,
                        'exercise_id' => $exerciseData['exercise_id'],
                        'order' => $exerciseData['order'] ?? 0,
                        'target_sets' => $exerciseData['target_sets'] ?? 3,
                        'min_target_reps' => $exerciseData['min_target_reps'] ?? 8,
                        'max_target_reps' => $exerciseData['max_target_reps'] ?? 12,
                        'target_weight' => $exerciseData['target_weight'] ?? 0,
                        'rest_seconds' => $exerciseData['rest_seconds'] ?? 90,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (! empty($exercisesToInsert)) {
                    WorkoutSessionExercise::insert($exercisesToInsert);
                }

                Log::info('Draft workout session created', [
                    'user_id' => $user->id,
                    'session_id' => $session->id,
                    'exercises_count' => count($exercisesToInsert),
                ]);

                return $session->fresh([
                    'workoutSessionExercises.exercise.category',
                    'workoutSessionExercises.exercise.muscleGroups',
                    'workoutSessionExercises.exercise.movementPattern',
                    'workoutSessionExercises.exercise.targetRegion',
                    'workoutSessionExercises.exercise.equipmentType',
                    'workoutSessionExercises.exercise.angle',
                    'setLogs',
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Workout generation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Confirm a draft session - set status to active and set performed_at
     */
    public function confirmSession(WorkoutSession $session): WorkoutSession
    {
        if ($session->status !== WorkoutSessionStatus::Draft) {
            throw new \Exception('Only draft sessions can be confirmed');
        }

        $session->update([
            'status' => WorkoutSessionStatus::Active,
            'performed_at' => Carbon::now(),
        ]);

        Log::info('Workout session confirmed', [
            'user_id' => $session->user_id,
            'session_id' => $session->id,
        ]);

        return $session->fresh([
            'workoutSessionExercises.exercise.category',
            'workoutSessionExercises.exercise.muscleGroups',
            'setLogs',
        ]);
    }

    /**
     * Regenerate a workout session - cancel the current draft and create a new one
     */
    public function regenerateSession(WorkoutSession $session, array $preferences = []): WorkoutSession
    {
        if ($session->status !== WorkoutSessionStatus::Draft) {
            throw new \Exception('Only draft sessions can be regenerated');
        }

        return DB::transaction(function () use ($session, $preferences) {
            // Cancel the old session
            $session->update([
                'status' => WorkoutSessionStatus::Cancelled,
            ]);

            Log::info('Workout session cancelled for regeneration', [
                'user_id' => $session->user_id,
                'session_id' => $session->id,
            ]);

            // Generate new session
            $newSession = $this->generate($session->user, $preferences);

            // Link the new session to the old one
            $newSession->update([
                'replaced_session_id' => $session->id,
            ]);

            Log::info('New workout session generated', [
                'user_id' => $session->user_id,
                'new_session_id' => $newSession->id,
                'replaced_session_id' => $session->id,
            ]);

            return $newSession;
        });
    }

    /**
     * Validate user has required profile data
     */
    public function validateUserProfile(User $user): void
    {
        $profile = $user->profile;

        if (! $profile) {
            throw new \Exception('User profile is required for workout generation');
        }

        if (! $profile->fitness_goal) {
            throw new \Exception('Fitness goal is required for workout generation');
        }

        if (! $profile->training_experience) {
            throw new \Exception('Training experience is required for workout generation');
        }
    }

    /**
     * Calculate estimated duration based on exercises
     */
    private function calculateEstimatedDuration(array $exercises): int
    {
        $setDuration = config('workout_generator.set_duration_seconds', 45);
        $totalSeconds = 0;

        foreach ($exercises as $exercise) {
            $sets = $exercise['target_sets'] ?? 3;
            $restSeconds = $exercise['rest_seconds'] ?? 90;

            // Time = sets × set_duration + (sets - 1) × rest
            $totalSeconds += ($sets * $setDuration) + (($sets - 1) * $restSeconds);
        }

        return (int) ceil($totalSeconds / 60);
    }
}
