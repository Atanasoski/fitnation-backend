<?php

namespace App\Http\Resources\Api;

use App\Enums\UnitSystem;
use App\Services\UnitConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FitnessMetricsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * FitnessMetricsService works in kilograms and knows nothing of unit
     * systems; its weekly volumes go out in the user's Unit System here
     * (ADR-0001). The strength score, balance and every percentage are
     * unitless and pass through.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                ...$this->resource,
                'weekly_progress' => $this->weeklyProgress($this->resource['weekly_progress'], auth()->user()?->unitSystem()),
            ],
            'message' => 'Fitness metrics retrieved successfully',
        ];
    }

    /**
     * A week's volume is a sum of weight × reps, so each one is a display
     * total. The difference is taken between the converted totals, so the
     * three numbers on screen always add up.
     *
     * @param  array<string, mixed>  $progress
     * @return array<string, mixed>
     */
    private function weeklyProgress(array $progress, ?UnitSystem $unitSystem): array
    {
        $total = fn (int|float $kg): int => app(UnitConversionService::class)->toDisplayTotal($kg, $unitSystem);

        if (array_key_exists('current_week_volume', $progress)) {
            $progress['current_week_volume'] = $total($progress['current_week_volume']);
            $progress['previous_week_volume'] = $total($progress['previous_week_volume']);
            $progress['volume_difference'] = $progress['current_week_volume'] - $progress['previous_week_volume'];
        }

        if (array_key_exists('daily_breakdown', $progress)) {
            $progress['daily_breakdown'] = array_map(
                fn (array $day) => [...$day, 'volume' => $total($day['volume'])],
                $progress['daily_breakdown'],
            );
        }

        return $progress;
    }
}
