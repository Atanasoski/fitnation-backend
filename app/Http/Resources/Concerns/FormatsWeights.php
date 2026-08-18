<?php

namespace App\Http\Resources\Concerns;

use App\Enums\UnitSystem;
use App\Services\UnitConversionService;

trait FormatsWeights
{
    /**
     * Format a training weight (kg, as stored) for display in the given
     * unit system. Delegates all conversion/rounding to UnitConversionService.
     */
    private function formatTrainingWeight(string|float|null $weight, ?UnitSystem $unitSystem = null): float|int|null
    {
        return app(UnitConversionService::class)->formatTrainingWeight($weight, $unitSystem);
    }
}
