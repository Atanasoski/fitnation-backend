<?php

namespace App\Http\Resources\Concerns;

use App\Enums\UnitSystem;
use App\Services\MeasuredFields;
use App\Services\UnitConversionService;
use LogicException;

trait FormatsMeasurements
{
    /**
     * Format a stored measurement for display in the given unit system.
     *
     * The caller names the table and column but never the measurement kind —
     * that comes from MeasuredFields, the same source the write path uses, so
     * the two can never disagree about a field. An unregistered column is a
     * programming error and throws rather than silently passing through
     * unconverted, which is how the 135-lbs-stored-as-135-kg bug stayed quiet.
     *
     * @throws LogicException when a column is not registered in MeasuredFields
     */
    private function formatMeasured(string|float|null $value, string $table, string $column, ?UnitSystem $unitSystem = null): float|int|null
    {
        $kind = MeasuredFields::kindFor($table, $column);

        if ($kind === null) {
            throw new LogicException(
                "{$table}.{$column} is not registered in ".MeasuredFields::class.
                '. Register it there so the read and write paths stay in agreement.'
            );
        }

        return app(UnitConversionService::class)->toDisplay($value, $kind, $unitSystem);
    }
}
