<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator;

use App\Services\WorkoutGenerator\Slots\Blueprint;
use App\Services\WorkoutGenerator\Slots\FilledSlot;
use App\Services\WorkoutGenerator\Slots\SessionTier;
use App\Services\WorkoutGenerator\Slots\Slot;

/**
 * One day of a generated plan: the Blueprint its region-set resolved to,
 * the Slots it filled and skipped, the prescribed rows (one per filled
 * Slot, in session order) and the day's description.
 */
final class GeneratedDay
{
    /**
     * @param  list<FilledSlot>  $filledSlots  in session order, one per row
     * @param  list<Slot>  $skippedSlots  Slots no exercise could fill, even after the Relaxation Waterfall
     * @param  list<array<string, mixed>>  $rows  exercise_id, order, target_sets, min/max_target_reps, target_weight, rest_seconds, slot_key
     */
    public function __construct(
        public readonly Blueprint $blueprint,
        public readonly SessionTier $tier,
        public readonly int $durationMinutes,
        public readonly array $filledSlots,
        public readonly array $skippedSlots,
        public readonly array $rows,
        public readonly string $description,
    ) {}

    /**
     * The Slot one of this day's rows fills, found by the row's slot_key.
     * Slot keys are unique within a day: within a Blueprint, and across the
     * Fragments a composed day draws on.
     *
     * @param  array<string, mixed>  $row
     */
    public function slotFor(array $row): Slot
    {
        foreach ($this->filledSlots as $filledSlot) {
            if ($filledSlot->slot->key === $row['slot_key']) {
                return $filledSlot->slot;
            }
        }

        throw new \LogicException("No Slot of this day has the key [{$row['slot_key']}]");
    }

    /**
     * The same day with different rows, e.g. its Deload Week variant.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function withRows(array $rows): self
    {
        return new self(
            $this->blueprint,
            $this->tier,
            $this->durationMinutes,
            $this->filledSlots,
            $this->skippedSlots,
            $rows,
            $this->description,
        );
    }
}
