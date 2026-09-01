<?php

namespace App\Http\Resources\Api;

use App\Models\User;
use App\Services\Plan\ProgramProgress;
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
        // $request->user() rather than auth(): a program has to be serializable
        // from a queue job or a test, where there is no authenticated session.
        $user = $request->user();
        $progress = ProgramProgress::for($this->resource, $user);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'cover_image' => $this->cover_image ? Storage::url($this->cover_image) : null,
            'duration_weeks' => $this->duration_weeks,
            'is_active' => $this->is_active,
            'is_auto_generated' => $this->is_auto_generated,
            'is_library_plan' => $this->isPartnerLibraryPlan(),
            'progress_percentage' => $this->when(
                $this->user_id,
                fn () => $progress->percentComplete()
            ),
            'next_workout' => $this->when(
                $this->user_id,
                fn () => ($next = $progress->nextWorkout())
                    ? WorkoutTemplateResource::forTemplate($next, $user, $progress)
                    : null
            ),
            'current_active_week' => $this->when(
                $this->user_id,
                fn () => $progress->currentWeek()
            ),
            'workout_templates' => $this->whenLoaded(
                'workoutTemplates',
                fn () => WorkoutTemplateResource::collectionForTemplates(
                    $this->workoutTemplates,
                    $user,
                    $progress
                )
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
