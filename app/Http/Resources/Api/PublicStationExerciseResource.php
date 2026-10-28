<?php

namespace App\Http\Resources\Api;

use App\Models\Exercise;
use App\Models\Partner;
use App\Services\Exercise\PartnerExerciseView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * One exercise as the public QR detail page sees it, in a station's context:
 * the station partner's overridden media and description, plus the structured
 * guidance (`instructions`, `common_mistakes`). Both guidance columns may be
 * null — the client renders `description` while `instructions` has not been
 * backfilled.
 */
class PublicStationExerciseResource extends JsonResource
{
    public function __construct(Exercise $resource, private readonly Partner $partner)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = PartnerExerciseView::of($this->resource, $this->partner);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $view->description,
            'instructions' => $this->instructions,
            'common_mistakes' => $this->common_mistakes,
            'image' => $view->imageUrl,
            'video' => $view->videoUrl,
            'muscle_group_image' => $this->muscle_group_image ? Storage::url($this->muscle_group_image) : null,
            'difficulty' => $this->difficulty,
            'equipment' => $this->equipmentType?->name,
            'primary_muscles' => $this->primaryMuscleGroups->pluck('name')->values(),
            'secondary_muscles' => $this->secondaryMuscleGroups->pluck('name')->values(),
        ];
    }
}
