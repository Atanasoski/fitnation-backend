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
        // UPPER's arms slot is tier 3, so arm work arrives at Extended only
        // — accepted (#127): a complete upper day beats a composed day
        // with biceps but no triceps.
        'ARMS|UPPER_PULL|UPPER_PUSH' => 'UPPER',
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
     * Every requestable region declares a Fragment (ADR-0010); one that
     * doesn't is refused by BlueprintRepository::fragment(), never dropped.
     *
     * @param  list<string>  $regions
     */
    private function composeFromFragments(array $regions, SessionTier $tier): Blueprint
    {
        $orderedRegions = array_values(array_unique($regions));

        $slotsByRegion = [];
        foreach ($orderedRegions as $region) {
            $slotsByRegion[$region] = array_values(array_filter(
                $this->blueprints->fragment($region),
                fn (Slot $slot) => $tier->includesTier($slot->tier),
            ));
        }

        $slotsByRegion = $this->trimToSetBudget($slotsByRegion, $this->blueprints->fragmentSetBudget($tier));

        return new Blueprint(
            implode('+', $orderedRegions),
            array_merge(...array_values($slotsByRegion)),
            fragmentComposed: true,
        );
    }

    /**
     * Fair trim (#127): while the day is over the tier's set budget, the
     * region holding the most sets loses its last Slot (ties: the
     * later-requested region), and a region loses its first Slot only once
     * every region is down to one — so no requested region is trimmed away
     * while another keeps several. The budget is a composed day's volume
     * mechanism (it replaces the Extended +1-set bonus, which fragment days
     * never get).
     *
     * @param  array<string, list<Slot>>  $slotsByRegion  in request order
     * @return array<string, list<Slot>>
     */
    private function trimToSetBudget(array $slotsByRegion, int $budget): array
    {
        while ($this->totalSets(array_merge(...array_values($slotsByRegion))) > $budget) {
            array_pop($slotsByRegion[$this->regionToTrim($slotsByRegion)]);
        }

        return $slotsByRegion;
    }

    /**
     * The region that loses its last Slot next: among the regions still
     * holding more than one Slot — or, once every region is down to one,
     * among those holding any — the one with the most sets, the
     * later-requested on a tie.
     *
     * @param  array<string, list<Slot>>  $slotsByRegion  in request order, at least one Slot left
     */
    private function regionToTrim(array $slotsByRegion): string
    {
        $candidates = array_filter($slotsByRegion, fn (array $slots) => count($slots) > 1)
            ?: array_filter($slotsByRegion, fn (array $slots) => $slots !== []);

        $chosen = array_key_first($candidates);
        foreach ($candidates as $region => $slots) {
            if ($this->totalSets($slots) >= $this->totalSets($candidates[$chosen])) {
                $chosen = $region;
            }
        }

        return $chosen;
    }

    /**
     * @param  list<Slot>  $slots
     */
    private function totalSets(array $slots): int
    {
        return array_sum(array_map(fn (Slot $slot) => $slot->sets, $slots));
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
            || $set === self::ALL_REGIONS;
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
