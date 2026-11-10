<?php

declare(strict_types=1);

namespace App\Services\WorkoutGenerator;

use App\Enums\FitnessGoal;
use App\Enums\TrainingExperience;
use App\Models\User;
use App\Services\WorkoutGenerator\Slots\Blueprint;
use App\Services\WorkoutGenerator\Slots\BlueprintResolver;
use App\Services\WorkoutGenerator\Slots\SessionTier;
use App\Services\WorkoutGenerator\Slots\Slot;
use App\Services\WorkoutGenerator\Slots\SlotConstraints;
use App\Services\WorkoutGenerator\Slots\SlotFiller;
use App\Services\WorkoutGenerator\Slots\SlotPool;

/**
 * Generates a day from its Blueprint (ADR-0010): the resolver picks the
 * day type, the session tier picks the slots, the filler resolves each
 * slot through the Relaxation Waterfall, skipped slots hand their sets to
 * the earliest filled ones, and every row carries a slot-consistent
 * prescription plus the slot key that produced it.
 *
 * Same contract as DeterministicWorkoutGenerator::generate(); production
 * callers swap over in ticket #113.
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
    ) {}

    /**
     * @param  array<string, mixed>  $preferences
     * @return array{exercises: list<array<string, mixed>>, rationale: string}
     */
    public function generate(User $user, array $preferences = []): array
    {
        $durationMinutes = (int) ($preferences['duration_minutes']
            ?? $user->profile?->workout_duration_minutes
            ?? 60);
        $tier = SessionTier::fromDuration($durationMinutes);

        $blueprint = $this->resolver->resolve(
            $this->normalizeStringList($preferences['target_regions'] ?? null) ?? [],
            $tier,
        );

        $slotSets = $this->tierAdjustedSets($blueprint, $tier);
        $filled = $this->fillSlots($blueprint, $tier, $slotSets, $this->constraintsFor($user, $preferences));

        if ($filled === []) {
            throw new \Exception('No exercises available matching the specified criteria');
        }

        return [
            'exercises' => $this->prescribeRows($filled, $user),
            'rationale' => $this->buildRationale($blueprint, $tier, $durationMinutes, count($filled)),
        ];
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

        return new SlotConstraints(
            equipmentTypes: $this->normalizeStringList($preferences['equipment_types'] ?? null),
            beginner: $experience === TrainingExperience::Beginner,
            partner: $user->partner,
            movementPatterns: $this->normalizeStringList($preferences['movement_patterns'] ?? null),
            angles: $this->normalizeStringList($preferences['angles'] ?? null),
        );
    }

    /**
     * Fill every slot from one day pool, then redistribute skipped slots'
     * sets: +1 at a time to the earliest filled slots, each capped at its
     * own slot's sets + 1; sets that fit nowhere are dropped.
     *
     * @param  list<array{slot: Slot, sets: int}>  $slotSets
     * @return list<array{slot: Slot, sets: int, exercise: \App\Models\Exercise}>
     */
    private function fillSlots(Blueprint $blueprint, SessionTier $tier, array $slotSets, SlotConstraints $constraints): array
    {
        $pool = SlotPool::load($constraints);

        $filled = [];
        $pickedIds = [];
        $skippedSlotSets = 0;

        foreach ($slotSets as ['slot' => $slot, 'sets' => $sets]) {
            $result = $this->filler->fill($slot, $pool, $pickedIds);

            if ($result->isSkipped()) {
                $skippedSlotSets += $sets;

                continue;
            }

            $filled[] = ['slot' => $slot, 'sets' => $sets, 'exercise' => $result->exercise, 'cap' => $sets + self::REDISTRIBUTION_HEADROOM];
            $pickedIds[] = $result->exercise->id;
        }

        return $this->redistribute($filled, $skippedSlotSets);
    }

    /**
     * @param  list<array{slot: Slot, sets: int, exercise: \App\Models\Exercise, cap: int}>  $filled
     * @return list<array{slot: Slot, sets: int, exercise: \App\Models\Exercise}>
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
            fn (array $row) => ['slot' => $row['slot'], 'sets' => $row['sets'], 'exercise' => $row['exercise']],
            $filled
        );
    }

    /**
     * Prescription precedence (#107): the member's logged history outranks
     * everything; without history the slot's rep range applies, goal-shifted
     * on compound slots only. Slot sets always win in generated flows; the
     * ADR-0009 matrix contributes only its rest fallback, exactly as before.
     *
     * @param  list<array{slot: Slot, sets: int, exercise: \App\Models\Exercise}>  $filled
     * @return list<array<string, mixed>>
     */
    private function prescribeRows(array $filled, User $user): array
    {
        $goal = $user->profile?->fitness_goal ?? FitnessGoal::GeneralFitness;
        $experience = $user->profile?->training_experience;
        $matrixDefaults = config('workout_generator.fitness_goal_defaults', [])[$goal->value] ?? ['rest_seconds' => 90];

        $rows = [];
        $order = 1;

        foreach ($filled as ['slot' => $slot, 'sets' => $sets, 'exercise' => $exercise]) {
            $lastPerformance = $this->progressionCalculator->getLastPerformance($exercise, $user);
            $targets = $this->progressionCalculator->calculateTargetsFrom($exercise, $user, $experience, $lastPerformance);

            [$minReps, $maxReps] = $this->slotRepRange($slot, $goal);

            if ($lastPerformance !== null && $targets['min_target_reps'] !== null) {
                $minReps = $targets['min_target_reps'];
                $maxReps = $targets['max_target_reps'];
            }

            $rows[] = [
                'exercise_id' => $exercise->id,
                'order' => $order++,
                'target_sets' => $sets,
                'min_target_reps' => $minReps,
                'max_target_reps' => $maxReps,
                'target_weight' => $targets['target_weight'] ?? 0,
                'rest_seconds' => $lastPerformance !== null
                    ? $targets['rest_seconds']
                    : ($matrixDefaults['rest_seconds'] ?? 90),
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

    private function buildRationale(Blueprint $blueprint, SessionTier $tier, int $durationMinutes, int $filledSlots): string
    {
        $dayName = str_replace(['_', '+'], [' ', ' + '], strtolower($blueprint->code));

        if ($blueprint->fragmentComposed) {
            return "Composed a {$dayName} day from its region fragments at the {$tier->value} tier "
                ."({$durationMinutes} minutes): {$filledSlots} slots filled in request order, "
                ."trimmed to the tier's set budget.";
        }

        return "Generated a {$dayName} day from its blueprint at the {$tier->value} tier "
            ."({$durationMinutes} minutes): {$filledSlots} slots filled in blueprint order, "
            .'heavy lifts leading, with sets and rep ranges read off each slot.';
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
