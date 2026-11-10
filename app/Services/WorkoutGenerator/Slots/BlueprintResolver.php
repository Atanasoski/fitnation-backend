<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator\Slots;

/**
 * Maps a request's region set to its Blueprint — the mapping table signed
 * off in issue #107 / ADR-0010. Order-insensitive except for Full Body,
 * where the first requested region becomes the focus.
 */
final class BlueprintResolver
{
    private const DIRECT_MAPPINGS = [
        'UPPER_PUSH' => 'PUSH',
        'ARMS|UPPER_PUSH' => 'PUSH',
        'UPPER_PULL' => 'PULL',
        'ARMS|UPPER_PULL' => 'PULL',
        'UPPER_PULL|UPPER_PUSH' => 'UPPER',
        'LOWER' => 'LOWER',
        'CORE|LOWER' => 'LOWER',
        'ARMS' => 'ARMS',
    ];

    public function __construct(
        private readonly BlueprintRepository $blueprints = new BlueprintRepository,
    ) {}

    private const FULL_BODY_TRIO = ['LOWER', 'UPPER_PULL', 'UPPER_PUSH'];

    private const ALL_REGIONS = ['ARMS', 'CORE', 'LOWER', 'UPPER_PULL', 'UPPER_PUSH'];

    private const DEFAULT_FOCUS = 'UPPER_PUSH';

    /**
     * @param  list<string>  $regions  requested target-region codes
     *
     * @throws FragmentCompositionRequired when the combination matches no blueprint
     */
    public function resolve(array $regions): Blueprint
    {
        $set = array_values(array_unique($regions));
        sort($set);

        $code = self::DIRECT_MAPPINGS[implode('|', $set)] ?? null;

        if ($code !== null) {
            return $this->blueprints->blueprint($code);
        }

        if ($this->isFullBody($set)) {
            return $this->fullBodyFocusedOn($regions[0] ?? self::DEFAULT_FOCUS);
        }

        throw FragmentCompositionRequired::for($regions);
    }

    /**
     * Empty list, all regions, or the push/pull/lower trio — every Full
     * Body spelling resolves to the one blueprint, reordered only.
     *
     * @param  list<string>  $set  sorted, deduplicated region codes
     */
    private function isFullBody(array $set): bool
    {
        return $set === []
            || $set === self::FULL_BODY_TRIO
            || array_diff(self::ALL_REGIONS, $set) === [];
    }

    /**
     * Focus-first reorder: the slots serving the focus region move to the
     * front, everything keeps its blueprint order otherwise — one
     * blueprint, no variants (ADR-0010).
     */
    private function fullBodyFocusedOn(string $focus): Blueprint
    {
        $blueprint = $this->blueprints->blueprint('FULL_BODY');

        $focused = array_filter($blueprint->slots(), fn (Slot $slot) => in_array($focus, $slot->regions, true));
        $rest = array_filter($blueprint->slots(), fn (Slot $slot) => ! in_array($focus, $slot->regions, true));

        return new Blueprint($blueprint->code, [...array_values($focused), ...array_values($rest)]);
    }
}
