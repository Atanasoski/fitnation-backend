<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Enums\UnitSystem;
use App\Http\Resources\Concerns\FormatsMeasurements;

/**
 * Weights on a user's admin pages are shown in the user's own Unit System
 * with the unit appended, converted at the HTTP boundary (ADR-0001), so the
 * admin reads the same numbers the user sees in the app.
 */
trait DisplaysWeights
{
    use FormatsMeasurements;

    private function weight(string|float|null $kg, string $table, string $column, UnitSystem $units): ?string
    {
        $value = $this->formatMeasured($kg, $table, $column, $units);

        return $value === null ? null : $value.' '.$units->weightUnit();
    }
}
