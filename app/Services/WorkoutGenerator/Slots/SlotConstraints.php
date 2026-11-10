<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator\Slots;

use App\Models\Partner;

/**
 * The hard constraints a day's pool is queried under. The Relaxation
 * Waterfall never loosens these — it only loosens the Slot's own role
 * (angle, then pattern), and finally falls back to bodyweight equivalents,
 * which every member can perform regardless of equipment selection.
 */
final class SlotConstraints
{
    /**
     * @param  list<string>|null  $equipmentTypes  available equipment codes; null = everything
     * @param  bool  $beginner  excludes advanced-difficulty exercises and beginner-excluded equipment
     * @param  Partner|null  $partner  restricts to the partner's catalog
     * @param  list<string>|null  $movementPatterns  user-supplied pattern filter
     * @param  list<string>|null  $angles  user-supplied angle filter (null-angle exercises still match)
     */
    public function __construct(
        public readonly ?array $equipmentTypes = null,
        public readonly bool $beginner = false,
        public readonly ?Partner $partner = null,
        public readonly ?array $movementPatterns = null,
        public readonly ?array $angles = null,
    ) {}
}
