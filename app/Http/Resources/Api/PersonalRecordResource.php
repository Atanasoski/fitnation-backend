<?php

namespace App\Http\Resources\Api;

use App\Enums\PersonalRecordType;
use App\Http\Resources\Concerns\FormatsMeasurements;
use App\Services\WorkoutSession\PersonalRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PersonalRecord
 */
class PersonalRecordResource extends JsonResource
{
    use FormatsMeasurements;

    /**
     * Transform the resource into an array.
     *
     * A weight record's numbers are kilograms and go out in the user's Unit
     * System (ADR-0001). They are the heaviest logged sets, so they format as
     * `workout_session_set_logs.weight` does: an imperial user reads 140 lbs
     * where they logged 140 lbs, not the 63.5 kg it is stored as. A reps
     * record is a count and goes out as it is.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'exercise_id' => $this->exerciseId,
            'exercise_name' => $this->exerciseName,
            'pr_type' => $this->type->value,
            'previous_best' => $this->best($this->previousBest),
            'new_best' => $this->best($this->newBest),
        ];
    }

    private function best(int|float $value): int|float
    {
        if ($this->type !== PersonalRecordType::Weight) {
            return $value;
        }

        return $this->formatMeasured($value, 'workout_session_set_logs', 'weight', auth()->user()?->unitSystem());
    }
}
