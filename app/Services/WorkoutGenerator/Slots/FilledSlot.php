<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator\Slots;

use App\Models\Exercise;

/**
 * A Slot of one generated day, filled by one Exercise, with the session's
 * set count for it (tier-adjusted, plus any sets redistributed from
 * skipped Slots). The input to the Prescription step.
 */
final class FilledSlot
{
    public function __construct(
        public readonly Slot $slot,
        public readonly Exercise $exercise,
        public readonly int $sets,
    ) {}
}
