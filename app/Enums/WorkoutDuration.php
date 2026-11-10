<?php

namespace App\Enums;

/**
 * The fixed menu of session lengths a member can request (issue #109).
 *
 * Validation accepts only these values; stored profile durations predating
 * the menu (e.g. 75) are still read as-is, so generation never needs them
 * migrated.
 */
enum WorkoutDuration: int
{
    case Express = 30;
    case Short = 45;
    case Standard = 60;
    case Long = 90;
    case Extended = 120;

    /**
     * @return array<int, int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
