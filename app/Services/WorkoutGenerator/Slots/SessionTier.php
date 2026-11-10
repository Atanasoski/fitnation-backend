<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator\Slots;

/**
 * The session-length tier a day is assembled at (GLOSSARY: Tier). Express
 * keeps tier-1 slots only, Standard tiers 1–2, Extended all tiers plus one
 * extra set on the first two tier-1 slots. Thresholds stay duration-based
 * (<40 / 40–60 / >60) so legacy stored durations resolve without migration.
 */
enum SessionTier: string
{
    case Express = 'express';
    case Standard = 'standard';
    case Extended = 'extended';

    public static function fromDuration(int $minutes): self
    {
        return match (true) {
            $minutes < 40 => self::Express,
            $minutes <= 60 => self::Standard,
            default => self::Extended,
        };
    }

    public function includesTier(int $tier): bool
    {
        return $tier <= match ($this) {
            self::Express => 1,
            self::Standard => 2,
            self::Extended => 3,
        };
    }
}
