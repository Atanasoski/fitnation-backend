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
 *
 * The label is the day type members read (#131): the workout's name and
 * its description both come from it, so they always name the same day.
 */
final class Blueprint
{
    /**
     * @param  string  $label  the day type: "Push", "Upper Body", or a composed day's region labels ("Push & Legs")
     * @param  list<Slot>  $slots
     * @param  string|null  $focusLabel  a Full Body day's focus region ("Push"), named in the workout name
     */
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        private readonly array $slots,
        public readonly bool $fragmentComposed = false,
        public readonly ?string $focusLabel = null,
    ) {}

    /**
     * @return list<Slot>
     */
    public function slots(): array
    {
        return $this->slots;
    }

    /**
     * The name a program's workout carries: "Push Day", "Push & Legs Day",
     * or a focused day's "Full Body (Push)".
     */
    public function workoutName(): string
    {
        return $this->focusLabel === null
            ? "{$this->label} Day"
            : "{$this->label} ({$this->focusLabel})";
    }
}
