<?php

namespace App\Http\Resources\Api;

use App\Http\Resources\Concerns\FormatsWeights;
use App\Services\WorkoutGenerator\ProgressionCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutSessionExerciseResource extends JsonResource
{
    use FormatsWeights;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $progressionCalculator = new ProgressionCalculatorService;
        $lastPerformance = null;
        $targets = [
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 0,
            'rest_seconds' => $this->exercise->default_rest_sec ?? 90,
        ];
        $progressionStatus = 'no_history';

        if ($user) {
            $lastPerformance = $progressionCalculator->getLastPerformance($this->exercise, $user);
            $targets = $progressionCalculator->calculateTargets($this->exercise, $user, $user->profile?->training_experience);
        }

        $targetSets = $this->target_sets;
        $minTargetReps = $this->min_target_reps;
        $maxTargetReps = $this->max_target_reps;
        $restSeconds = $this->rest_seconds;

        // target_weight is always taken from the progression calculator so it
        // reflects the user's latest completed session, not a stale stored value.
        $targetWeight = $targets['target_weight'];

        if (! $targetSets) {
            $targetSets = $targets['target_sets'];
        }
        if (! $minTargetReps) {
            $minTargetReps = $targets['min_target_reps'];
        }
        if (! $maxTargetReps) {
            $maxTargetReps = $targets['max_target_reps'];
        }
        if (! $restSeconds) {
            $restSeconds = $targets['rest_seconds'];
        }
        if ($user) {
            $progressionStatus = $progressionCalculator->getProgressionStatus($lastPerformance, $minTargetReps, $maxTargetReps);
        }

        return [
            'id' => $this->id,
            'workout_session_id' => $this->workout_session_id,
            'exercise_id' => $this->exercise_id,
            'exercise' => $this->whenLoaded('exercise', function () {
                return new ExerciseResource($this->exercise->load('partners', 'muscleGroups', 'primaryMuscleGroups', 'secondaryMuscleGroups'));
            }),
            'order' => $this->order,
            'target_sets' => $targetSets,
            'min_target_reps' => $minTargetReps,
            'max_target_reps' => $maxTargetReps,
            'progression_status' => $progressionStatus,
            'target_weight' => $this->formatWeight($targetWeight),
            'rest_seconds' => $restSeconds,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
