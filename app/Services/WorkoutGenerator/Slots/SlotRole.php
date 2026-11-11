<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator\Slots;

use App\Models\Exercise;

/**
 * One alternative a Slot accepts: a movement pattern, optionally narrowed
 * to a set of angles. A null-angle exercise matches any angle requirement,
 * mirroring the catalog selector convention.
 */
final class SlotRole
{
    /**
     * @param  list<string>|null  $angles
     */
    public function __construct(
        public readonly string $pattern,
        public readonly ?array $angles = null,
    ) {}

    public function matches(Exercise $exercise): bool
    {
        return $this->matchesIgnoringAngle($exercise) && $this->matchesAngle($exercise);
    }

    public function matchesIgnoringAngle(Exercise $exercise): bool
    {
        return $exercise->movementPattern?->code === $this->pattern;
    }

    private function matchesAngle(Exercise $exercise): bool
    {
        if ($this->angles === null) {
            return true;
        }

        $angle = $exercise->angle?->code;

        return $angle === null || in_array($angle, $this->angles, true);
    }
}
