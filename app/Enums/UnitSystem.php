<?php

namespace App\Enums;

enum UnitSystem: string
{
    case Metric = 'metric';
    case Imperial = 'imperial';

    /**
     * The label a weight carries when shown in this system.
     */
    public function weightUnit(): string
    {
        return match ($this) {
            self::Metric => 'kg',
            self::Imperial => 'lbs',
        };
    }
}
