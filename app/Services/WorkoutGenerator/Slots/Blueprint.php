<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator\Slots;

/**
 * The declared shape of one workout day type: an ordered list of Slots
 * (GLOSSARY: Blueprint). Six declared day types exist — see
 * config/workout_blueprints.php.
 *
 * A fragment-composed day (#112) is also a Blueprint: its slots come from
 * per-region Fragments, already tier-filtered and trimmed to the tier's
 * set budget. The flag matters to the generator because the budget is a
 * composed day's volume mechanism — it never gets the Extended +1 bonus.
 */
final class Blueprint
{
    /**
     * @param  list<Slot>  $slots
     */
    public function __construct(
        public readonly string $code,
        private readonly array $slots,
        public readonly bool $fragmentComposed = false,
    ) {}

    /**
     * @return list<Slot>
     */
    public function slots(): array
    {
        return $this->slots;
    }
}
