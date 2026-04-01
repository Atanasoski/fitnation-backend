<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'partner_id' => $this->partner_id,
            'name' => $this->name,
            'description' => $this->description,
            'cover_image' => $this->cover_image ? Storage::url($this->cover_image) : null,
            'is_active' => $this->is_active,
            'is_auto_generated' => $this->is_auto_generated,
            'type' => $this->type?->value,
            'duration_weeks' => $this->duration_weeks,
            'workout_templates' => $this->whenLoaded('workoutTemplates', function () {
                return $this->workoutTemplates->map(function ($template) {
                    return new WorkoutTemplateResource($template);
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
