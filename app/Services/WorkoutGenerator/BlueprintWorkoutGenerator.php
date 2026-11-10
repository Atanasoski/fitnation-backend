<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator;

use App\Enums\FitnessGoal;
use App\Enums\TrainingExperience;
use App\Models\EquipmentType;
use App\Models\User;
use App\Services\WorkoutGenerator\Slots\Blueprint;
use App\Services\WorkoutGenerator\Slots\BlueprintResolver;
use App\Services\WorkoutGenerator\Slots\FilledSlot;
use App\Services\WorkoutGenerator\Slots\SessionTier;
use App\Services\WorkoutGenerator\Slots\Slot;
use App\Services\WorkoutGenerator\Slots\SlotConstraints;
use App\Services\WorkoutGenerator\Slots\SlotFiller;
use App\Services\WorkoutGenerator\Slots\SlotPool;
use Illuminate\Support\Facades\Log;

/**
 * Generates a plan's days from their Blueprints (ADR-0010): per day, the
 * resolver picks the day type, the session tier picks the slots, the
 * filler resolves each slot through the Relaxation Waterfall, skipped
 * slots hand their sets to the earliest filled ones, and every row carries
 * a slot-consistent prescription plus the slot key that produced it. A
 * single session is a one-day plan.
 */
class BlueprintWorkoutGenerator
{
    /** Extended sessions add one set to this many leading tier-1 slots. */
    private const EXTENDED_BONUS_SLOTS = 2;

    /** A redistributed slot may grow by at most this many sets. */
    private const REDISTRIBUTION_HEADROOM = 1;

    public function __construct(
        private BlueprintResolver $resolver,
        private SlotFiller $filler,
        private ProgressionCalculatorService $progressionCalculator,
        private PrescriptionCalculator $prescriptionCalculator,
    ) {}

    /**
     * Generates a plan's days (ADR-0010): the pool loads once, since a
     * plan's days share their constraints; each day resolves and fills on
     * its own, so duplicate day types still differ; the member's history
     * for every picked exercise resolves in one batched lookup; then each
     * day's filled Slots are prescribed.
     *
     * @param  array<string, mixed>  $preferences  duration_minutes, equipment_types, movement_patterns, angles, training_styles — shared by every day
     * @param  list<list<string>>  $days  each day's requested regions, in plan order
     * @return list<GeneratedDay> one per requested day, in the same order
     */
    public function generatePlan(User $user, array $preferences, array $days): array
    {
        $durationMinutes = (int) ($preferences['duration_minutes']
            ?? $user->profile?->workout_duration_minutes
            ?? 60);
        $tier = SessionTier::fromDuration($durationMinutes);
        $pool = SlotPool::load($this->constraintsFor($user, $preferences));

        $fills = array_map(function (mixed $regions) use ($tier, $pool) {
            $blueprint = $this->resolver->resolve($this->normalizeStringList($regions) ?? [], $tier);
            [$filled, $skipped] = $this->fillSlots($this->tierAdjustedSets($blueprint, $tier), $pool);

            if ($filled === []) {
                throw new \Exception('No exercises available matching the specified criteria');
            }

            Log::info('workout.day_generated', [
                'blueprint' => $blueprint->code,
                'tier' => $tier->value,
                'filled_slots' => array_map(fn (FilledSlot $filledSlot) => $filledSlot->slot->key, $filled),
                'skipped_slots' => array_map(fn (Slot $slot) => $slot->key, $skipped),
            ]);

            return ['blueprint' => $blueprint, 'filled' => $filled, 'skipped' => $skipped];
        }, array_values($days));

        $lastPerformances = $this->progressionCalculator->getLastPerformanceForExercises(
            collect($fills)
                ->flatMap(fn (array $fill) => $fill['filled'])
                ->map(fn (FilledSlot $filledSlot) => $filledSlot->exercise),
            $user,
        );

        return array_map(fn (array $fill) => new GeneratedDay(
            blueprint: $fill['blueprint'],
            tier: $tier,
            durationMinutes: $durationMinutes,
            filledSlots: $fill['filled'],
            skippedSlots: $fill['skipped'],
            rows: $this->prescribeRows($fill['filled'], $user, $lastPerformances),
            description: $this->describeDay($fill['blueprint'], $durationMinutes, $fill['filled'], $fill['skipped']),
        ), $fills);
    }

    /**
     * One day: a single generated session is a one-day plan. Reads the
     * day's regions from `target_regions` and returns its rows as
     * `exercises` and its description as `rationale`.
     *
     * @param  array<string, mixed>  $preferences
     * @return array{exercises: list<array<string, mixed>>, rationale: string}
     */
    public function generate(User $user, array $preferences = []): array
    {
        $day = $this->generatePlan($user, $preferences, [$preferences['target_regions'] ?? []])[0];

        return ['exercises' => $day->rows, 'rationale' => $day->description];
    }

    /**
     * The tier's slots with their session set counts: Extended includes
     * every tier and adds one set to the first two tier-1 slots — on
     * blueprint days only. A fragment-composed day's volume is governed
     * entirely by its tier set budget (#112), already applied by the
     * resolver, so it never receives the Extended bonus.
     *
     * @return list<array{slot: Slot, sets: int}>
     */
    private function tierAdjustedSets(Blueprint $blueprint, SessionTier $tier): array
    {
        $slots = array_values(array_filter(
            $blueprint->slots(),
            fn (Slot $slot) => $tier->includesTier($slot->tier)
        ));

        $bonusesLeft = $tier === SessionTier::Extended && ! $blueprint->fragmentComposed
            ? self::EXTENDED_BONUS_SLOTS
            : 0;

        return array_map(function (Slot $slot) use (&$bonusesLeft) {
            $bonus = 0;
            if ($bonusesLeft > 0 && $slot->tier === 1) {
                $bonus = 1;
                $bonusesLeft--;
            }

            return ['slot' => $slot, 'sets' => $slot->sets + $bonus];
        }, $slots);
    }

    /**
     * @param  array<string, mixed>  $preferences
     */
    private function constraintsFor(User $user, array $preferences): SlotConstraints
    {
        $experience = $user->profile?->training_experience ?? TrainingExperience::Beginner;
        $equipmentTypes = $this->normalizeStringList($preferences['equipment_types'] ?? null);

        return new SlotConstraints(
            equipmentTypes: $equipmentTypes,
            beginner: $experience === TrainingExperience::Beginner,
            partner: $user->partner,
            movementPatterns: $this->normalizeStringList($preferences['movement_patterns'] ?? null),
            angles: $this->normalizeStringList($preferences['angles'] ?? null),
            trainingStyles: $this->resolveTrainingStyleFilter($preferences, $equipmentTypes),
        );
    }

    /**
     * The training-style filter a day's pool is queried under: with no
     * equipment selection the day defaults to the BODYBUILDING catalog so
     * functional-only movements never surprise a plain request; with one,
     * only an explicit style filters — except a BODYBUILDING-only default
     * alongside functional equipment, which is dropped (the member meant
     * the equipment).
     *
     * @param  array<string, mixed>  $preferences
     * @param  list<string>|null  $equipmentTypes
     * @return list<string>|null
     */
    private function resolveTrainingStyleFilter(array $preferences, ?array $equipmentTypes): ?array
    {
        $trainingStyles = $this->normalizeStringList($preferences['training_styles'] ?? null);

        if (! empty($equipmentTypes)) {
            if ($trainingStyles === ['BODYBUILDING'] && $this->hasAnyFunctionalEquipment($equipmentTypes)) {
                return null;
            }

            return $trainingStyles;
        }

        return $trainingStyles ?? ['BODYBUILDING'];
    }

    /**
     * @param  list<string>  $equipmentTypes
     */
    private function hasAnyFunctionalEquipment(array $equipmentTypes): bool
    {
        return collect($equipmentTypes)->contains(
            fn (string $code) => in_array($code, EquipmentType::FUNCTIONAL_CODES, true)
        );
    }

    /**
     * Fill one day's slots from the plan's pool, then redistribute skipped
     * slots' sets: +1 at a time to the earliest filled slots, each capped
     * at its own slot's sets + 1; sets that fit nowhere are dropped.
     *
     * @param  list<array{slot: Slot, sets: int}>  $slotSets
     * @return array{0: list<FilledSlot>, 1: list<Slot>} the filled slots and the skipped ones
     */
    private function fillSlots(array $slotSets, SlotPool $pool): array
    {
        $filled = [];
        $skipped = [];
        $pickedIds = [];
        $skippedSlotSets = 0;

        foreach ($slotSets as ['slot' => $slot, 'sets' => $sets]) {
            $result = $this->filler->fill($slot, $pool, $pickedIds);

            if ($result->isSkipped()) {
                $skipped[] = $slot;
                $skippedSlotSets += $sets;

                continue;
            }

            $filled[] = ['slot' => $slot, 'sets' => $sets, 'exercise' => $result->exercise, 'cap' => $sets + self::REDISTRIBUTION_HEADROOM];
            $pickedIds[] = $result->exercise->id;
        }

        return [$this->redistribute($filled, $skippedSlotSets), $skipped];
    }

    /**
     * @param  list<array{slot: Slot, sets: int, exercise: \App\Models\Exercise, cap: int}>  $filled
     * @return list<FilledSlot>
     */
    private function redistribute(array $filled, int $skippedSlotSets): array
    {
        while ($skippedSlotSets > 0) {
            $placed = false;

            foreach ($filled as &$row) {
                if ($skippedSlotSets === 0) {
                    break;
                }

                if ($row['sets'] < $row['cap']) {
                    $row['sets']++;
                    $skippedSlotSets--;
                    $placed = true;
                }
            }
            unset($row);

            if (! $placed) {
                break; // remaining sets fit nowhere — dropped by design
            }
        }

        return array_map(
            fn (array $row) => new FilledSlot($row['slot'], $row['exercise'], $row['sets']),
            $filled
        );
    }

    /**
     * Prescribes one day's filled slots from the plan's batch of last
     * performances (ADR-0010). Every row stores its Slot's sets and rep
     * range, the range goal-shifted on compound Slots only, with or without
     * history. History moves only the Working Weight (progression, or the
     * starting estimate without history) and the rest: the member's logged
     * rest when they have one, otherwise the Prescription rest for the
     * Slot's class — the exercise's Default Rest scaled by the goal and
     * clamped by that class's floor.
     *
     * @param  list<FilledSlot>  $filled
     * @param  array<int, array<string, mixed>>  $lastPerformances  keyed by exercise id; absent = no history
     * @return list<array<string, mixed>>
     */
    private function prescribeRows(array $filled, User $user, array $lastPerformances): array
    {
        $goal = $user->profile?->fitness_goal ?? FitnessGoal::GeneralFitness;
        $experience = $user->profile?->training_experience;

        $rows = [];
        $order = 1;

        foreach ($filled as $filledSlot) {
            $slot = $filledSlot->slot;
            $exercise = $filledSlot->exercise;
            $lastPerformance = $lastPerformances[$exercise->id] ?? null;
            $targets = $this->progressionCalculator->calculateTargetsFrom($exercise, $user, $experience, $lastPerformance);

            [$minReps, $maxReps] = $this->slotRepRange($slot, $goal);

            $rows[] = [
                'exercise_id' => $exercise->id,
                'order' => $order++,
                'target_sets' => $filledSlot->sets,
                'min_target_reps' => $minReps,
                'max_target_reps' => $maxReps,
                'target_weight' => $targets['target_weight'] ?? 0,
                'rest_seconds' => $lastPerformance['rest_seconds']
                    ?? $this->prescriptionCalculator->defaults(
                        $slot->movementClass(),
                        $goal,
                        $exercise->default_rest_sec,
                        $exercise->movementPattern?->code,
                    )['rest_seconds'],
                'slot_key' => $slot->key,
            ];
        }

        return $rows;
    }

    /**
     * The slot's rep range, goal-shifted on compound slots only: strength
     * presses at 4–6, fat loss at 8–12, every other goal keeps the slot
     * base — and an isolation slot never leaves its range (#107: no
     * lateral-raise triples).
     *
     * @return array{0: int, 1: int}
     */
    private function slotRepRange(Slot $slot, FitnessGoal $goal): array
    {
        if (! $slot->isCompound()) {
            return [$slot->repMin, $slot->repMax];
        }

        return match ($goal) {
            FitnessGoal::Strength => [4, 6],
            FitnessGoal::FatLoss => [8, 12],
            default => [$slot->repMin, $slot->repMax],
        };
    }

    /**
     * The description members read as session notes and as a program
     * workout's description (#131): "Push day, 60 min: 5 exercises, heavy
     * compound lifts first, then isolation work.", plus the roles the day
     * could not fill. The order of work is named only when the filled Slots
     * include both classes; a day of one kind ends "…: 2 exercises.". It
     * names the same day type as the workout name; the tier and relaxation
     * detail stay in the logs.
     *
     * @param  list<FilledSlot>  $filled
     * @param  list<Slot>  $skipped
     */
    private function describeDay(Blueprint $blueprint, int $durationMinutes, array $filled, array $skipped): string
    {
        $exercises = count($filled) === 1 ? '1 exercise' : count($filled).' exercises';
        $fillsBothClasses = collect($filled)->contains(fn (FilledSlot $filledSlot) => $filledSlot->slot->isCompound())
            && collect($filled)->contains(fn (FilledSlot $filledSlot) => ! $filledSlot->slot->isCompound());

        $description = "{$blueprint->label} day, {$durationMinutes} min: {$exercises}"
            .($fillsBothClasses ? ', heavy compound lifts first, then isolation work.' : '.');

        if ($skipped === []) {
            return $description;
        }

        $roles = $this->joinWithOr(array_values(array_unique(array_map(fn (Slot $slot) => $slot->label, $skipped))));

        return "{$description} No suitable exercise was available for {$roles}, so their sets went to earlier exercises.";
    }

    /**
     * "a", "a or b", "a, b or c".
     *
     * @param  non-empty-list<string>  $items
     */
    private function joinWithOr(array $items): string
    {
        $last = array_pop($items);

        return $items === [] ? $last : implode(', ', $items)." or {$last}";
    }

    /**
     * @return list<string>|null
     */
    private function normalizeStringList(mixed $values): ?array
    {
        if (! is_array($values)) {
            return null;
        }

        $normalized = array_values(array_filter(
            $values,
            fn ($value) => is_string($value) && $value !== ''
        ));

        return $normalized === [] ? null : $normalized;
    }
}
