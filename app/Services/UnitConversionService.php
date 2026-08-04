<?php

namespace App\Services;

use App\Enums\UnitSystem;

class UnitConversionService
{
    private const KG_TO_LBS = 2.2046226218;

    private const CM_TO_INCHES = 0.3937007874;

    private const TRAINING_WEIGHT_STEP_LBS = 5.0;

    private const BODY_WEIGHT_STEP_LBS = 0.5;

    /**
     * Format a stored kg training weight for display: metric passthrough
     * (trailing zeros stripped), imperial rounds to the nearest 5 lbs.
     */
    public function formatTrainingWeight(string|float|null $weightKg, ?UnitSystem $unitSystem): float|int|null
    {
        if ($weightKg === null) {
            return null;
        }

        $kg = (float) $weightKg;

        if ($unitSystem !== UnitSystem::Imperial) {
            return $this->stripTrailingZeros($kg);
        }

        $lbs = $this->roundToStep($kg * self::KG_TO_LBS, self::TRAINING_WEIGHT_STEP_LBS);

        return $this->stripTrailingZeros($lbs);
    }

    /**
     * Format a stored kg body weight for display: metric passthrough,
     * imperial rounds to the nearest 0.5 lb.
     */
    public function formatBodyWeight(string|float|null $weightKg, ?UnitSystem $unitSystem): float|int|null
    {
        if ($weightKg === null) {
            return null;
        }

        $kg = (float) $weightKg;

        if ($unitSystem !== UnitSystem::Imperial) {
            return $this->stripTrailingZeros($kg);
        }

        $lbs = $this->roundToStep($kg * self::KG_TO_LBS, self::BODY_WEIGHT_STEP_LBS);

        return $this->stripTrailingZeros($lbs);
    }

    /**
     * Format a stored cm height for display: metric passthrough, imperial
     * converts to total inches rounded to the nearest whole inch.
     */
    public function formatHeight(int|string|null $heightCm, ?UnitSystem $unitSystem): int|null
    {
        if ($heightCm === null) {
            return null;
        }

        $cm = (float) $heightCm;

        if ($unitSystem !== UnitSystem::Imperial) {
            return (int) $cm;
        }

        return (int) round($cm * self::CM_TO_INCHES);
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

    private function roundToStep(float $value, float $step): float
    {
        return round($value / $step) * $step;
    }

    private function stripTrailingZeros(float $value): float|int
    {
        return $value == (int) $value ? (int) $value : $value;
    }
}
