<?php

namespace App\Http\Requests\Concerns;

use App\Enums\UnitSystem;
use App\Services\MeasuredFields;
use App\Services\UnitConversionService;
use LogicException;

trait ConvertsIncomingUnits
{
    /**
     * Rewrite the named inputs from the request's unit system into the
     * canonical storage units, so the canonical validation bounds in rules()
     * apply to the converted values.
     *
     * Call this from prepareForValidation(). Metric requests are a no-op.
     *
     * The caller names the table and columns but never the measurement kind —
     * that comes from MeasuredFields, so a request physically cannot disagree
     * with the read path about what a field holds. An unregistered column is a
     * programming error and throws rather than silently passing through.
     *
     * @param  string  $table  the table these inputs are stored in
     * @param  array<int, string>  $columns  input names, which must match column names
     * @param  UnitSystem|null  $unitSystem  overrides the authenticated user's stored preference
     *
     * @throws LogicException when a column is not registered in MeasuredFields
     */
    protected function convertMeasuredInputs(string $table, array $columns, ?UnitSystem $unitSystem = null): void
    {
        $unitSystem ??= $this->user()?->unitSystem() ?? UnitSystem::Metric;

        $conversionService = app(UnitConversionService::class);
        $merge = [];

        foreach ($columns as $column) {
            $kind = MeasuredFields::kindFor($table, $column);

            if ($kind === null) {
                throw new LogicException(
                    "{$table}.{$column} is not registered in ".MeasuredFields::class.
                    '. Register it there so the read and write paths stay in agreement.'
                );
            }

            if ($unitSystem !== UnitSystem::Imperial || ! $this->filled($column)) {
                continue;
            }

            $merge[$column] = $conversionService->toStorage((float) $this->input($column), $kind, $unitSystem);
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
