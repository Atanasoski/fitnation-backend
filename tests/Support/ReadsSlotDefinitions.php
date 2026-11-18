<?php

namespace Tests\Support;

use App\Enums\MovementClass;
use App\Services\WorkoutGenerator\Slots\Blueprint;
use App\Services\WorkoutGenerator\Slots\BlueprintRepository;

/**
 * Looks a generated row's Slot up from its slot_key. A key names the same
 * role in every Blueprint and Fragment it appears in, so this is how a test
 * asks "was that row a compound?" without hard-coding a slot list — which
 * matters once a split contains a day type whose tier-1 slots are not all
 * compounds (a Legs day's ham_iso is tier 1).
 */
trait ReadsSlotDefinitions
{
    /**
     * The class of the Slot a generated row fills, from its slot_key.
     */
    protected function slotClass(string $slotKey): MovementClass
    {
        $repository = new BlueprintRepository;
        $slots = array_merge(
            ...array_map(fn (Blueprint $blueprint) => $blueprint->slots(), $repository->blueprints()),
            ...array_values($repository->fragments()),
        );

        $slot = collect($slots)->firstWhere('key', $slotKey);
        $this->assertNotNull($slot, "No Slot has the key {$slotKey}");

        return $slot->movementClass();
    }
}
