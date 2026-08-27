<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $exercisesData = [];

        if ($this->relationLoaded('workoutSessionExercises') && $this->relationLoaded('setLogs')) {
            $exerciseIds = $this->workoutSessionExercises->pluck('exercise_id')->toArray();
            $previousSetLogs = $this->getPreviousSetLogsForExercises($exerciseIds);
            // Exercises appearing on more than one row: legacy sets carrying no
            // row id cannot be attributed to either, so they are not shown under
            // both.
            $duplicatedExerciseIds = $this->workoutSessionExercises
                ->groupBy('exercise_id')
                ->filter(fn ($rows) => $rows->count() > 1)
                ->keys();

            $progression = WorkoutSessionExerciseResource::batchProgression(
                $this->workoutSessionExercises,
                $request->user()
            );

            foreach ($this->workoutSessionExercises as $sessionExercise) {

                $loggedSets = $sessionExercise->ownedSetsFrom(
                    $this->setLogs,
                    ! $duplicatedExerciseIds->contains($sessionExercise->exercise_id)
                );

                $previousSets = $previousSetLogs->get($sessionExercise->exercise_id, collect());

                $exercisesData[] = [
                    'session_exercise' => WorkoutSessionExerciseResource::forRow(
                        $sessionExercise,
                        $progression[$sessionExercise->exercise_id] ?? null
                    ),
                    'logged_sets' => SetLogResource::collection($loggedSets),
                    'previous_sets' => SetLogResource::collection($previousSets),
                    'is_completed' => $loggedSets->count() >= ($sessionExercise->target_sets ?? 3),
                ];
            }
        }

        $completedExercises = collect($exercisesData)->filter(fn ($ex) => $ex['is_completed'])->count();
        $totalExercises = count($exercisesData);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'workout_template_id' => $this->workout_template_id,
            'performed_at' => $this->performed_at,
            'completed_at' => $this->completed_at,
            'status' => $this->status,           // ← missing from GET
            'rationale' => $this->notes,
            'is_auto_generated' => $this->is_auto_generated,
            'replaced_session_id' => $this->replaced_session_id,
            'notes' => $this->notes,
            'exercises' => $exercisesData,
            'progress' => [
                'total_exercises' => $totalExercises,
                'completed_exercises' => $completedExercises,
                'progress_percent' => $totalExercises > 0
                    ? round(($completedExercises / $totalExercises) * 100, 2)
                    : 0,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get previous set logs for exercises using the model method
     */
    protected function getPreviousSetLogsForExercises(array $exerciseIds): \Illuminate\Support\Collection
    {
        return $this->resource->getPreviousSetLogsForExercises($exerciseIds);
    }
}
