<?php

namespace App\Services;

use App\Enums\MeasurementKind;
use App\Enums\UnitSystem;

class UnitConversionService
{
    private const KG_TO_LBS = 2.2046226218;

    private const CM_TO_INCHES = 0.3937007874;

    /**
     * Format a stored canonical value for display in the given unit system.
     * Metric passes through; imperial converts and snaps to the kind's step.
     *
     * This and toStorage() are inverses, and every read/write pair in the app
     * must go through them so the two can never disagree about a field. The
     * kind comes from MeasuredFields, which is the single place a column's
     * measurement kind is declared, and the kind is the single place a
     * rounding step is declared — there is no per-kind entry point here.
     */
    public function toDisplay(int|string|float|null $stored, MeasurementKind $kind, ?UnitSystem $unitSystem): float|int|null
    {
        if ($stored === null) {
            return null;
        }

        return $kind === MeasurementKind::Height
            ? $this->toInches($stored, $unitSystem)
            : $this->toLbsAtStep($stored, $unitSystem, $kind->imperialStep());
    }

    /**
     * Convert an incoming display value back to its canonical storage unit.
     * The inverse of toDisplay(), modulo that kind's display rounding.
     */
    public function toStorage(float $input, MeasurementKind $kind, UnitSystem $unitSystem): float|int
    {
        return $kind === MeasurementKind::Height
            ? $this->toCm($input, $unitSystem)
            : $this->toKg($input, $unitSystem);
    }

    /**
     * Convert an incoming weight value to kg for storage, given the unit
     * system it was submitted in.
     */
    public function toKg(float $weight, UnitSystem $unitSystem): float
    {
        return $unitSystem === UnitSystem::Imperial ? $weight / self::KG_TO_LBS : $weight;
    }

    /**
     * Convert an incoming height value (inches) to cm for storage.
     */
    public function toCm(float $height, UnitSystem $unitSystem): int
    {
        return $unitSystem === UnitSystem::Imperial
            ? (int) round($height / self::CM_TO_INCHES)
            : (int) round($height);
    }

    /**
     * Display formatting for a stored cm height: metric passes through,
     * imperial converts to total inches rounded to the nearest whole inch.
     */
    private function toInches(int|string|float $heightCm, ?UnitSystem $unitSystem): int
    {
        $cm = (float) $heightCm;

        return $unitSystem === UnitSystem::Imperial
            ? (int) round($cm * self::CM_TO_INCHES)
            : (int) $cm;
    }

    /**
     * Display formatting for a stored kg weight: metric passes through,
     * imperial converts to lbs and snaps to the given step.
     */
    private function toLbsAtStep(int|string|float $weightKg, ?UnitSystem $unitSystem, float $stepLbs): float|int
    {
        $kg = (float) $weightKg;

        if ($unitSystem !== UnitSystem::Imperial) {
            return $this->stripTrailingZeros($kg);
        }

        return $this->stripTrailingZeros($this->roundToStep($kg * self::KG_TO_LBS, $stepLbs));
    }

    private function roundToStep(float $value, float $step): float
    {
        return round($value / $step) * $step;
    }

    private function stripTrailingZeros(float $value): float|int
    {
        return $value == (int) $value ? (int) $value : $value;
    }
}
