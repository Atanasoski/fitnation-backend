<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProgramResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'cover_image' => $this->cover_image ? Storage::url($this->cover_image) : null,
            'duration_weeks' => $this->duration_weeks,
            'is_active' => $this->is_active,
            'is_auto_generated' => $this->is_auto_generated,
            'is_library_plan' => $this->isPartnerLibraryPlan(),
            'cover_image' => $this->cover_image ? Storage::url($this->cover_image) : null,
            'progress_percentage' => $this->when(
                $this->user_id,
                fn () => $this->getProgressPercentage(auth()->user())
            ),
            'next_workout' => $this->when(
                $this->user_id,
                fn () => new WorkoutTemplateResource($this->nextWorkout(auth()->user()))
            ),
            'current_active_week' => $this->when(
                $this->user_id,
                fn () => $this->getCurrentActiveWeek(auth()->user())
            ),
            'workout_templates' => WorkoutTemplateResource::collection($this->whenLoaded('workoutTemplates')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
