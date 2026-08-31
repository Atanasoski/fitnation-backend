<?php

namespace App\Http\Resources\Api;

use App\Services\WorkoutSession\SessionDetail;
use App\Services\WorkoutSession\SessionExerciseDetail;
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
        $detail = SessionDetail::for($this->resource, $request->user());

        $exercisesData = $detail->exercises()
            ->map(fn (SessionExerciseDetail $exercise) => [
                'session_exercise' => WorkoutSessionExerciseResource::forDetail($exercise),
                'logged_sets' => SetLogResource::collection($exercise->loggedSets),
                'previous_sets' => SetLogResource::collection($exercise->previousSets),
                'is_completed' => $exercise->isCompleted,
            ])
            ->all();

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
            'progress' => $detail->progress(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
