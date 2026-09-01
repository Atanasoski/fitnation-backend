<?php

namespace App\Http\Resources\Api;

use App\Services\Exercise\PartnerExerciseView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ExerciseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = PartnerExerciseView::of($this->resource, auth()->user()?->partner);

        return [
            'id' => $this->id,
            'category' => $this->whenLoaded('category', function () {
                return new CategoryResource($this->category);
            }),
            'muscle_groups' => $this->whenLoaded('muscleGroups', function () {
                return MuscleGroupResource::collection($this->muscleGroups);
            }),
            'primary_muscle_groups' => $this->whenLoaded('primaryMuscleGroups', function () {
                return MuscleGroupResource::collection($this->primaryMuscleGroups);
            }),
            'secondary_muscle_groups' => $this->whenLoaded('secondaryMuscleGroups', function () {
                return MuscleGroupResource::collection($this->secondaryMuscleGroups);
            }),
            'angle' => $this->whenLoaded('angle', function () {
                return new AngleResource($this->angle);
            }),
            'movement_pattern' => $this->whenLoaded('movementPattern', function () {
                return new MovementPatternResource($this->movementPattern);
            }),
            'target_region' => $this->whenLoaded('targetRegion', function () {
                return new TargetRegionResource($this->targetRegion);
            }),
            'equipment_type' => $this->whenLoaded('equipmentType', function () {
                return new EquipmentTypeResource($this->equipmentType);
            }),
            'name' => $this->name,
            'description' => $view->description,
            'muscle_group_image' => $this->muscle_group_image ? Storage::url($this->muscle_group_image) : null,
            'image' => $view->imageUrl,
            'video' => $view->videoUrl,
            'default_rest_sec' => $this->default_rest_sec,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
