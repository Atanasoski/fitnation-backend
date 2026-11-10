<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator\Slots;

use App\Enums\ExerciseDifficulty;
use App\Models\Exercise;
use Illuminate\Support\Collection;

/**
 * The day's exercise pool: one catalog query under the hard constraints,
 * partitioned per Slot in memory. Bodyweight exercises ride along even
 * when the equipment selection excludes them, so the waterfall's
 * bodyweight-equivalents level never needs a second query; they only
 * surface through bodyweightCandidates() unless BODYWEIGHT was selected.
 */
final class SlotPool
{
    public const BODYWEIGHT = 'BODYWEIGHT';

    /**
     * @param  Collection<int, Exercise>  $exercises
     */
    private function __construct(
        private readonly Collection $exercises,
        private readonly ?array $equipmentTypes,
    ) {}

    public static function load(SlotConstraints $constraints): self
    {
        $query = Exercise::query()
            ->with(['movementPattern', 'targetRegion', 'equipmentType', 'angle'])
            ->available();

        if ($constraints->partner !== null) {
            $query->forPartner($constraints->partner);
        }

        if ($constraints->equipmentTypes !== null) {
            $allowed = array_values(array_unique([...$constraints->equipmentTypes, self::BODYWEIGHT]));
            $query->whereHas('equipmentType', fn ($q) => $q->whereIn('code', $allowed));
        }

        if ($constraints->beginner) {
            $query->where(function ($q) {
                $q->whereNull('difficulty')
                    ->orWhere('difficulty', '!=', ExerciseDifficulty::Advanced->value);
            });

            $excluded = config('workout_generator.beginner_excluded_equipment', []);
            if ($excluded !== []) {
                $query->whereHas('equipmentType', fn ($q) => $q->whereNotIn('code', $excluded));
            }
        }

        if ($constraints->movementPatterns !== null) {
            $query->whereHas('movementPattern', fn ($q) => $q->whereIn('code', $constraints->movementPatterns));
        }

        if ($constraints->angles !== null) {
            $query->where(function ($q) use ($constraints) {
                $q->whereHas('angle', fn ($sub) => $sub->whereIn('code', $constraints->angles))
                    ->orWhereNull('angle_id');
            });
        }

        return new self($query->get()->toBase(), $constraints->equipmentTypes);
    }

    /**
     * Waterfall level 0 / 1: exercises matching one of the slot's roles,
     * optionally ignoring the roles' angle requirements.
     *
     * @return Collection<int, Exercise>
     */
    public function roleCandidates(Slot $slot, bool $ignoreAngles = false): Collection
    {
        return $this->withinEquipmentSelection()->filter(
            fn (Exercise $exercise) => collect($slot->roles)->contains(
                fn (SlotRole $role) => $ignoreAngles
                    ? $role->matchesIgnoringAngle($exercise)
                    : $role->matches($exercise)
            )
        )->values();
    }

    /**
     * Waterfall level 2: the pattern is dropped; exercises keep the slot's
     * region(s) and its compound/isolation class.
     *
     * @return Collection<int, Exercise>
     */
    public function regionClassCandidates(Slot $slot): Collection
    {
        return $this->withinEquipmentSelection()
            ->filter(fn (Exercise $exercise) => $this->servesRegionAndClass($exercise, $slot))
            ->values();
    }

    /**
     * Waterfall level 3: bodyweight equivalents — bodyweight exercises
     * serving the slot's role (any angle) or its region(s) and class, even
     * when the equipment selection excluded bodyweight. Outside the slot's
     * own role, a compound never stands in for an isolation slot (or vice
     * versa); a slot with no bodyweight equivalent at all skips instead.
     *
     * @return Collection<int, Exercise>
     */
    public function bodyweightCandidates(Slot $slot): Collection
    {
        return $this->exercises
            ->filter(
                fn (Exercise $exercise) => $exercise->equipmentType?->code === self::BODYWEIGHT
                    && ($this->matchesAnyRoleIgnoringAngle($exercise, $slot)
                        || $this->servesRegionAndClass($exercise, $slot))
            )
            ->values();
    }

    private function matchesAnyRoleIgnoringAngle(Exercise $exercise, Slot $slot): bool
    {
        return collect($slot->roles)->contains(
            fn (SlotRole $role) => $role->matchesIgnoringAngle($exercise)
        );
    }

    private function servesRegionAndClass(Exercise $exercise, Slot $slot): bool
    {
        if (! in_array($exercise->targetRegion?->code, $slot->regions, true)) {
            return false;
        }

        return Slot::isCompoundPattern($exercise->movementPattern?->code) === $slot->isCompound();
    }

    /**
     * @return Collection<int, Exercise>
     */
    private function withinEquipmentSelection(): Collection
    {
        if ($this->equipmentTypes === null) {
            return $this->exercises;
        }

        return $this->exercises->filter(
            fn (Exercise $exercise) => in_array($exercise->equipmentType?->code, $this->equipmentTypes, true)
        );
    }
}
