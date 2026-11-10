<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator\Slots;

/**
 * One position in a Blueprint: a movement role carrying a Tier, a set
 * count, and a rep range (GLOSSARY: Slot). Hydrated from
 * config/workout_blueprints.php.
 */
final class Slot
{
    /**
     * @param  list<SlotRole>  $roles
     * @param  list<string>  $regions
     */
    public function __construct(
        public readonly string $key,
        public readonly int $tier,
        public readonly int $sets,
        public readonly int $repMin,
        public readonly int $repMax,
        public readonly array $regions,
        public readonly array $roles,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            key: $config['key'],
            tier: $config['tier'],
            sets: $config['sets'],
            repMin: $config['reps'][0],
            repMax: $config['reps'][1],
            regions: $config['regions'],
            roles: array_map(
                fn (array $role) => new SlotRole($role['pattern'], $role['angles'] ?? null),
                $config['roles']
            ),
        );
    }

    /**
     * The slot's compound/isolation class, read off its first pattern via
     * the existing classification in config/workout_generator.php.
     */
    public function isCompound(): bool
    {
        return self::isCompoundPattern($this->roles[0]->pattern);
    }

    /**
     * Whether a movement pattern is classified compound — the one place
     * the compound_patterns classification is consulted.
     */
    public static function isCompoundPattern(?string $pattern): bool
    {
        return $pattern !== null && in_array(
            $pattern,
            config('workout_generator.compound_patterns', []),
            true
        );
    }
}
