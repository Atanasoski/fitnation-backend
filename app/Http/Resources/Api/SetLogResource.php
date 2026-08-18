<?php

namespace App\Http\Resources\Api;

use App\Http\Resources\Concerns\FormatsWeights;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SetLogResource extends JsonResource
{
    use FormatsWeights;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workout_session_id' => $this->workout_session_id,
            'exercise_id' => $this->exercise_id,
            'set_number' => $this->set_number,
            'weight' => $this->formatTrainingWeight($this->weight, auth()->user()?->unitSystem()),
            'reps' => $this->reps,
            'rest_seconds' => $this->rest_seconds,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
