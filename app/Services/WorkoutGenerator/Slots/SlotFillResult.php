<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator\Slots;

use App\Models\Exercise;

/**
 * The outcome of resolving one Slot: the picked exercise and the
 * relaxation level it took, or a skip when even the waterfall ran dry.
 */
final class SlotFillResult
{
    private function __construct(
        public readonly ?Exercise $exercise,
        public readonly RelaxationLevel $level,
    ) {}

    public static function filled(Exercise $exercise, RelaxationLevel $level): self
    {
        return new self($exercise, $level);
    }

    public static function skipped(): self
    {
        return new self(null, RelaxationLevel::Skipped);
    }

    public function isSkipped(): bool
    {
        return $this->level === RelaxationLevel::Skipped;
    }
}
