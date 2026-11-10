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

    private const FULL_BODY_TRIO = ['LOWER', 'UPPER_PULL', 'UPPER_PUSH'];

    private const ALL_REGIONS = ['ARMS', 'CORE', 'LOWER', 'UPPER_PULL', 'UPPER_PUSH'];

    private const DEFAULT_FOCUS = 'UPPER_PUSH';

    public function __construct(
        private readonly BlueprintRepository $blueprints = new BlueprintRepository,
    ) {}

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
     * Focus-first reorder — one blueprint, no variants (ADR-0010).
     * Compound slots stay ahead of isolation slots (a short session must
     * still lead with the heavy lifts, #107 user story 5); within each
     * class, the slots serving the focus region move to the front and
     * everything else keeps its blueprint order.
     */
    private function fullBodyFocusedOn(string $focus): Blueprint
    {
        $blueprint = $this->blueprints->blueprint('FULL_BODY');

        $slots = collect($blueprint->slots())
            ->sortBy(fn (Slot $slot) => [
                $slot->isCompound() ? 0 : 1,
                in_array($focus, $slot->regions, true) ? 0 : 1,
            ])
            ->values()
            ->all();

        return new Blueprint($blueprint->code, $slots);
    }
}
