<?php

namespace App\Http\Requests\Concerns;

use App\Enums\UnitSystem;
use App\Services\UnitConversionService;

trait ConvertsIncomingUnits
{
    /**
     * Rewrite the named inputs from the request's unit system into the
     * canonical kg/cm storage units, so the kg/cm validation bounds in
     * rules() apply to the converted values.
     *
     * Call this from prepareForValidation(). Metric requests are a no-op.
     *
     * @param  array<int, string>  $weightFields  inputs holding a weight (lbs when imperial)
     * @param  array<int, string>  $heightFields  inputs holding a height (inches when imperial)
     * @param  UnitSystem|null  $unitSystem  overrides the authenticated user's stored preference
     */
    protected function convertIncomingUnits(array $weightFields, array $heightFields = [], ?UnitSystem $unitSystem = null): void
    {
        $unitSystem ??= $this->user()?->unitSystem() ?? UnitSystem::Metric;

        if ($unitSystem !== UnitSystem::Imperial) {
            return;
        }

        $conversionService = app(UnitConversionService::class);
        $merge = [];

        foreach ($weightFields as $field) {
            if ($this->filled($field)) {
                $merge[$field] = $conversionService->toKg((float) $this->input($field), UnitSystem::Imperial);
            }
        }

        foreach ($heightFields as $field) {
            if ($this->filled($field)) {
                $merge[$field] = $conversionService->toCm((float) $this->input($field), UnitSystem::Imperial);
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
