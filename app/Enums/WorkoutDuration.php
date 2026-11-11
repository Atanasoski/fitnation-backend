<?php

namespace App\Enums;

/**
 * The fixed menu of session lengths a member can request (issue #109).
 *
 * Every endpoint that takes a duration accepts only these values: API and
 * web profile update, session generate and regenerate, and the admin
 * preview. Stored durations predating the menu (75) were migrated to the
 * nearest menu value of the same Tier, so a client can always echo the
 * stored value back.
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
