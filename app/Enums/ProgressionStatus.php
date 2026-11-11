<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a member stands on an exercise, as the app reads it: the verdict on
 * their latest completed working session (ADR-0011), or no history at all.
 * The values are the API's `progression_status`; mobile clients switch on
 * them, so they never change.
 */
enum ProgressionStatus: string
{
    /** No completed working session of the exercise yet. */
    case NoHistory = 'no_history';

    /** Some set fell under the minimum of its Logged Range. */
    case BelowMin = 'below_min';

    /** Neither topped nor below the minimum: the Working Weight holds. */
    case Working = 'working';

    /** Topped: every set reached the top of its Logged Range, so the Working Weight rises one step. */
    case Ready = 'ready';

    /**
     * The status when there is history but no verdict to give (total-reps
     * progression has no rep range to judge; a Deload Week holds the
     * Working Weight): working, or no history when there is none.
     */
    public static function unjudged(bool $hasHistory): self
    {
        return $hasHistory ? self::Working : self::NoHistory;
    }
}
