<?php

namespace App\Http\Resources\Api;

use App\Services\WorkoutSession\PersonalRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PersonalRecord
 */
class PersonalRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * A weight record's numbers are kilograms and go out unconverted, which
     * ADR-0001 says they should not: an imperial client reads them as pounds.
     * That predates the module and is left alone here — this resource exists so
     * the fix has one place to happen, not because it has happened. See issue
     * 010 and tests/Feature/PersonalRecordDetectionTest.php.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'exercise_id' => $this->exerciseId,
            'exercise_name' => $this->exerciseName,
            'pr_type' => $this->type->value,
            'previous_best' => $this->previousBest,
            'new_best' => $this->newBest,
        ];
    }
}
