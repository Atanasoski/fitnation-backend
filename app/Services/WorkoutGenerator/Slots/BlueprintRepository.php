<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator\Slots;

use InvalidArgumentException;

/**
 * Hydrates Blueprints and Fragments from config/workout_blueprints.php.
 */
final class BlueprintRepository
{
    public function blueprint(string $code): Blueprint
    {
        $config = config("workout_blueprints.blueprints.{$code}");

        if ($config === null) {
            throw new InvalidArgumentException("Unknown blueprint [{$code}]");
        }

        return new Blueprint($code, $config['label'], $this->hydrateSlots($config['slots']));
    }

    /**
     * @return list<Blueprint>
     */
    public function blueprints(): array
    {
        return array_map(
            fn (string $code) => $this->blueprint($code),
            array_keys(config('workout_blueprints.blueprints', []))
        );
    }

    /**
     * A target region's short, ordered Slot list (GLOSSARY: Fragment).
     *
     * @return list<Slot>
     */
    public function fragment(string $region): array
    {
        return $this->hydrateSlots($this->fragmentConfig($region)['slots']);
    }

    /**
     * The name members read for a region (#131): a composed day joins
     * them ("Push & Legs"), a Full Body day names its focus with one.
     */
    public function fragmentLabel(string $region): string
    {
        return $this->fragmentConfig($region)['label'];
    }

    /**
     * @return array<string, list<Slot>> keyed by region code
     */
    public function fragments(): array
    {
        return array_map(
            fn (array $fragment) => $this->hydrateSlots($fragment['slots']),
            config('workout_blueprints.fragments', [])
        );
    }

    /**
     * Total-set ceiling for a fragment-composed day at this tier (#112).
     */
    public function fragmentSetBudget(SessionTier $tier): int
    {
        $budget = config("workout_blueprints.fragment_set_budgets.{$tier->value}");

        if (! is_int($budget)) {
            throw new InvalidArgumentException("No fragment set budget for tier [{$tier->value}]");
        }

        return $budget;
    }

    /**
     * @return array{label: string, slots: list<array<string, mixed>>}
     */
    private function fragmentConfig(string $region): array
    {
        $config = config("workout_blueprints.fragments.{$region}");

        if ($config === null) {
            throw new InvalidArgumentException("No fragment for region [{$region}]");
        }

        return $config;
    }

    /**
     * @param  list<array<string, mixed>>  $slotConfigs
     * @return list<Slot>
     */
    private function hydrateSlots(array $slotConfigs): array
    {
        return array_map(Slot::fromConfig(...), $slotConfigs);
    }
}
