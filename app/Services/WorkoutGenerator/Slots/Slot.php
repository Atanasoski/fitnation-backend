<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator\Slots;

use App\Enums\MovementClass;

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
     * @param  string  $label  the role in members' words ("side delts"), named when the day could not fill it
     */
    public function __construct(
        public readonly string $key,
        public readonly int $tier,
        public readonly int $sets,
        public readonly int $repMin,
        public readonly int $repMax,
        public readonly array $regions,
        public readonly array $roles,
        public readonly string $label,
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
            label: $config['label'],
        );
    }

    /**
     * The slot's compound/isolation class: the class of its first role's
     * pattern.
     */
    public function movementClass(): MovementClass
    {
        return MovementClass::fromPattern($this->roles[0]->pattern);
    }

    public function isCompound(): bool
    {
        return $this->movementClass()->isCompound();
    }
}
