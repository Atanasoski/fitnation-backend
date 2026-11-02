<?php

namespace App\Http\Resources\Api;

use App\Enums\UnitSystem;
use App\Http\Resources\Concerns\FormatsMeasurements;
use App\Services\UnitConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseHistoryResource extends JsonResource
{
    use FormatsMeasurements;

    /**
     * Transform the resource into an array.
     *
     * The controller builds the history in kilograms; it goes out in the
     * user's Unit System (ADR-0001). A session's weight is its heaviest set,
     * so it formats as `workout_session_set_logs.weight` does. A session's
     * volume is a sum of weight × reps, so it is a display total, rounded to
     * the whole unit and never stepped.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $unitSystem = auth()->user()?->unitSystem();
        $stats = $this->resource['stats'];

        return [
            'exercise_id' => $this->resource['exercise_id'],
            'exercise_name' => $this->resource['exercise_name'],
            'stats' => [
                ...$stats,
                'current_weight' => $this->weight($stats['current_weight'], $unitSystem),
                'best_weight' => $this->weight($stats['best_weight'], $unitSystem),
            ],
            'performance_data' => array_map(fn (array $point) => [
                ...$point,
                'weight' => $this->weight($point['weight'], $unitSystem),
                'volume' => app(UnitConversionService::class)->toDisplayTotal($point['volume'], $unitSystem),
            ], $this->resource['performance_data']),
        ];
    }

    private function weight(int|float $kg, ?UnitSystem $unitSystem): int|float
    {
        return $this->formatMeasured($kg, 'workout_session_set_logs', 'weight', $unitSystem);
    }
}
