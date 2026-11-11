<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether a movement is a Compound Movement or an Isolation Movement
 * (GLOSSARY). Values match the compound/isolation keys of the
 * Prescription matrix in config/workout_generator.php.
 */
enum MovementClass: string
{
    case Compound = 'compound';
    case Isolation = 'isolation';

    /**
     * The one compound/isolation classifier: a movement pattern listed in
     * workout_generator.compound_patterns is compound; any other pattern,
     * or none, is isolation.
     */
    public static function fromPattern(?string $movementPatternCode): self
    {
        return $movementPatternCode !== null && in_array(
            $movementPatternCode,
            config('workout_generator.compound_patterns', []),
            true
        ) ? self::Compound : self::Isolation;
    }

    public function isCompound(): bool
    {
        return $this === self::Compound;
    }
}
