<?php

namespace App\Http\Resources\Api;

use App\Services\Exercise\PartnerExerciseView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Station as the public QR page sees it: the partner's identity and ordered
 * exercise summaries (enough for the chooser), with the partner's media
 * overrides already applied. No performance data, no user data — the caller
 * is anonymous.
 */
class PublicStationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $views = PartnerExerciseView::forExercises($this->exercises, $this->partner);

        return [
            'name' => $this->name,
            'short_code' => $this->short_code,
            'partner' => [
                'name' => $this->partner->name,
                'slug' => $this->partner->slug,
            ],
            'exercises' => $this->exercises->map(function ($exercise) use ($views) {
                $view = $views[$exercise->getKey()];

                return [
                    'id' => $exercise->id,
                    'name' => $exercise->name,
                    'image' => $view->imageUrl,
                    'difficulty' => $exercise->difficulty,
                    'equipment' => $exercise->equipmentType?->name,
                    'primary_muscles' => $exercise->primaryMuscleGroups->pluck('name')->values(),
                ];
            })->values(),
        ];
    }
}
