<?php

namespace App\Http\Resources\Api;

use App\Enums\WorkoutSessionStatus;
use App\Http\Resources\Concerns\FormatsWeights;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class WorkoutTemplateResource extends JsonResource
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
            'plan_id' => $this->plan_id,
            'name' => $this->name,
            'description' => $this->description,
            'day_of_week' => $this->day_of_week,
            'week_number' => $this->week_number,
            'order_index' => $this->order_index,
            'last_completed_session_id' => $this->when(
                auth()->check(),
                fn () => $this->workoutSessions()
                    ->where('user_id', auth()->id())
                    ->where('status', WorkoutSessionStatus::Completed)
                    ->latest('completed_at')
                    ->value('id')
            ),
            'plan' => $this->whenLoaded('plan', function () {
                return new PlanResource($this->plan);
            }),
            'exercises' => $this->whenLoaded('exercises', function () {
                $partner = auth()->user()?->partner;

                return $this->exercises->map(function ($exercise) use ($partner) {
                    $image = $exercise->getImage($partner);
                    $video = $exercise->getVideo($partner);

                    return [
                        'id' => $exercise->id,
                        'name' => $exercise->name,
                        'description' => $exercise->getDescription($partner),
                        'image' => $image ? Storage::url($image) : null,
                        'video' => $video ? Storage::url($video) : null,
                        'muscle_group_image' => $exercise->muscle_group_image ? Storage::url($exercise->muscle_group_image) : null,
                        'default_rest_sec' => $exercise->default_rest_sec,
                        'category' => $exercise->category ? new CategoryResource($exercise->category) : null,
                        'muscle_groups' => $exercise->relationLoaded('muscleGroups')
                            ? MuscleGroupResource::collection($exercise->muscleGroups)
                            : [],
                        'pivot' => [
                            'id' => $exercise->pivot->id,
                            'order' => $exercise->pivot->order,
                            'target_sets' => $exercise->pivot->target_sets,
                            'min_target_reps' => $exercise->pivot->min_target_reps,
                            'max_target_reps' => $exercise->pivot->max_target_reps,
                            'target_weight' => $this->formatWeight($exercise->pivot->target_weight),
                            'rest_seconds' => $exercise->pivot->rest_seconds,
                        ],
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
