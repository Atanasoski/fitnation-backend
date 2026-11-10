<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator\Slots;

/**
 * The declared shape of one workout day type: an ordered list of Slots
 * (GLOSSARY: Blueprint). Six exist — see config/workout_blueprints.php.
 */
final class Blueprint
{
    /**
     * @param  list<Slot>  $slots
     */
    public function __construct(
        public readonly string $code,
        private readonly array $slots,
    ) {}

    /**
     * @return list<Slot>
     */
    public function slots(): array
    {
        return $this->slots;
    }
}
