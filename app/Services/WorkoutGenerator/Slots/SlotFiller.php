<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator\Slots;

use App\Models\Exercise;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Resolves one Slot to one exercise. An empty role walks the Relaxation
 * Waterfall — drop the angle; drop the pattern keeping region and
 * compound/isolation class; fall back to bodyweight equivalents; skip —
 * and every non-zero relaxation is logged with the slot key and level.
 */
final class SlotFiller
{
    /**
     * @param  list<int>  $excludeIds  already-picked exercise ids
     */
    public function fill(Slot $slot, SlotPool $pool, array $excludeIds = []): SlotFillResult
    {
        $levels = [
            RelaxationLevel::None->value => fn () => $pool->roleCandidates($slot),
            RelaxationLevel::AngleDropped->value => fn () => $pool->roleCandidates($slot, ignoreAngles: true),
            RelaxationLevel::PatternDropped->value => fn () => $pool->regionClassCandidates($slot),
            RelaxationLevel::BodyweightEquivalent->value => fn () => $pool->bodyweightCandidates($slot),
        ];

        foreach ($levels as $level => $candidates) {
            $pick = $this->pick($candidates(), $excludeIds);

            if ($pick === null) {
                continue;
            }

            $relaxation = RelaxationLevel::from($level);
            $this->logRelaxation($slot, $relaxation);

            return SlotFillResult::filled($pick, $relaxation);
        }

        $this->logRelaxation($slot, RelaxationLevel::Skipped);

        return SlotFillResult::skipped();
    }

    /**
     * Pick = shuffle, excluding already-picked ids. The stable sort by
     * selection_priority descending after the shuffle is ADR-0010's
     * "pre-shuffle sort": wired but inert while the column is uncurated,
     * because equal priorities keep their shuffled order.
     *
     * @param  Collection<int, Exercise>  $candidates
     * @param  list<int>  $excludeIds
     */
    private function pick(Collection $candidates, array $excludeIds): ?Exercise
    {
        return $candidates
            ->reject(fn (Exercise $exercise) => in_array($exercise->id, $excludeIds, true))
            ->shuffle()
            ->sortByDesc(fn (Exercise $exercise) => (int) ($exercise->selection_priority ?? 0))
            ->first();
    }

    private function logRelaxation(Slot $slot, RelaxationLevel $level): void
    {
        if ($level === RelaxationLevel::None) {
            return;
        }

        Log::info('workout.slot_relaxed', [
            'slot_key' => $slot->key,
            'level' => $level->value,
        ]);
    }
}
