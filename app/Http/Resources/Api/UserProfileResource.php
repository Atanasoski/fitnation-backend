<?php

namespace App\Http\Resources\Api;

use App\Http\Resources\Concerns\FormatsMeasurements;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    use FormatsMeasurements;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $unitSystem = $this->unit_system;

        return [
            'fitness_goal' => $this->fitness_goal?->value,
            'age' => $this->age,
            'gender' => $this->gender?->value,
            'height' => $this->formatMeasured($this->height, 'user_profiles', 'height', $unitSystem),
            'weight' => $this->formatMeasured($this->weight, 'user_profiles', 'weight', $unitSystem),
            'unit_system' => $unitSystem?->value,
            'training_experience' => $this->training_experience?->value,
            'training_days_per_week' => $this->training_days_per_week,
            'workout_duration_minutes' => $this->workout_duration_minutes,
        ];
    }
}
