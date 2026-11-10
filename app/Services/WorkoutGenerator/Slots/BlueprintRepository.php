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

        return new Blueprint($code, $this->hydrateSlots($config['slots']));
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
     * Whether a target region declares a Fragment. CONDITIONING never does
     * (#107); it contributes nothing to a composed day.
     */
    public function hasFragment(string $region): bool
    {
        return config("workout_blueprints.fragments.{$region}") !== null;
    }

    /**
     * A target region's short, ordered Slot list (GLOSSARY: Fragment).
     *
     * @return list<Slot>
     */
    public function fragment(string $region): array
    {
        $config = config("workout_blueprints.fragments.{$region}");

        if ($config === null) {
            throw new InvalidArgumentException("No fragment for region [{$region}]");
        }

        return $this->hydrateSlots($config);
    }

    /**
     * @return array<string, list<Slot>> keyed by region code
     */
    public function fragments(): array
    {
        return array_map(
            [$this, 'hydrateSlots'],
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
     * @param  list<array<string, mixed>>  $slotConfigs
     * @return list<Slot>
     */
    private function hydrateSlots(array $slotConfigs): array
    {
        return array_map(Slot::fromConfig(...), $slotConfigs);
    }
}
