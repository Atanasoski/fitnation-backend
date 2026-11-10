<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator\Slots;

/**
 * Maps a request's region set to its Blueprint — the mapping table signed
 * off in issue #107 / ADR-0010 — and composes a fragment day for any
 * combination the table misses (#112). Mapping is order-insensitive except
 * for Full Body, where the first requested region becomes the focus;
 * fragment composition follows the request order.
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
     */
    public function resolve(array $regions, SessionTier $tier): Blueprint
    {
        $set = array_values(array_unique($regions));
        sort($set);

        $code = self::DIRECT_MAPPINGS[implode('|', $set)] ?? null;

        if ($code !== null) {
            return $this->blueprints->blueprint($code);
        }

        if ($set === ['FULL_BODY']) {
            // User-selectable via the API but absent from #107's table: the
            // literal FULL_BODY region means the whole-body day — Push
            // focus, exactly like an empty region list.
            return $this->fullBodyFocusedOn(self::DEFAULT_FOCUS);
        }

        if ($this->isFullBody($set)) {
            return $this->fullBodyFocusedOn($regions[0] ?? self::DEFAULT_FOCUS);
        }

        return $this->composeFromFragments($regions, $tier);
    }

    /**
     * Fragment composition (#112): a combination matching no Blueprint
     * still yields a structured day — each requested region's Fragment
     * concatenates in request order (duplicates keep their first position).
     *
     * @param  list<string>  $regions
     */
    private function composeFromFragments(array $regions, SessionTier $tier): Blueprint
    {
        $orderedRegions = array_values(array_unique($regions));

        $slots = [];
        foreach ($orderedRegions as $region) {
            if (! $this->blueprints->hasFragment($region)) {
                // CONDITIONING — or the literal FULL_BODY inside a larger
                // combination — declares no Fragment and contributes nothing.
                continue;
            }

            foreach ($this->blueprints->fragment($region) as $slot) {
                if ($tier->includesTier($slot->tier)) {
                    $slots[] = $slot;
                }
            }
        }

        $slots = $this->trimToSetBudget($slots, $this->blueprints->fragmentSetBudget($tier));

        return new Blueprint(implode('+', $orderedRegions), $slots, fragmentComposed: true);
    }

    /**
     * Whole trailing slots trim until the total fits the tier's set budget
     * — the budget is a composed day's volume mechanism (it replaces the
     * Extended +1-set bonus, which fragment days never get).
     *
     * @param  list<Slot>  $slots
     * @return list<Slot>
     */
    private function trimToSetBudget(array $slots, int $budget): array
    {
        $totalSets = array_sum(array_map(fn (Slot $slot) => $slot->sets, $slots));

        while ($totalSets > $budget && $slots !== []) {
            $totalSets -= array_pop($slots)->sets;
        }

        return $slots;
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
