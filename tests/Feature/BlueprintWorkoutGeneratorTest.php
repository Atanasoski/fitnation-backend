<?php

namespace Tests\Feature;

use App\Enums\FitnessGoal;
use App\Enums\TrainingExperience;
use App\Models\User;
use App\Services\WorkoutGenerator\BlueprintWorkoutGenerator;
use App\Services\WorkoutGenerator\Slots\BlueprintRepository;
use App\Services\WorkoutGenerator\Slots\Slot;
use App\Services\WorkoutGenerator\Slots\SlotRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCatalogExercises;
use Tests\TestCase;

/**
 * The generator seam (issue #107's primary seam): user + preferences in,
 * prescribed rows out. Rows must carry the expected slot keys in blueprint
 * order, slot-consistent sets and rep bounds, and exercises whose pattern
 * and angle satisfy their slot's role — composition is read off the
 * Blueprint, never asserted through internals.
 */
class BlueprintWorkoutGeneratorTest extends TestCase
{
    use CreatesCatalogExercises;
    use RefreshDatabase;

    private BlueprintWorkoutGenerator $generator;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(BlueprintWorkoutGenerator::class);

        $this->user = User::factory()->create();
        $this->user->profile->update([
            'fitness_goal' => FitnessGoal::GeneralFitness,
            'training_experience' => TrainingExperience::Intermediate,
        ]);
    }

    /**
     * One level-0 candidate per role across all six blueprints, with enough
     * duplicates that same-role slots (second_row, arms_finisher…) can
     * dedupe without relaxing.
     */
    private function seedFullGymCatalog(): void
    {
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'INCLINE');
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'VERTICAL');
        $this->catalogExercise('SHOULDER_RAISE', 'UPPER_PUSH', 'DUMBBELL');
        $this->catalogExercise('FLY', 'UPPER_PUSH', 'CABLE', 'HORIZONTAL');
        $this->catalogExercise('ELBOW_EXTENSION', 'ARMS', 'CABLE');
        $this->catalogExercise('ELBOW_EXTENSION', 'ARMS', 'BARBELL');
        $this->catalogExercise('ELBOW_FLEXION', 'ARMS', 'DUMBBELL');
        $this->catalogExercise('ELBOW_FLEXION', 'ARMS', 'CABLE');
        $this->catalogExercise('PULL_VERTICAL', 'UPPER_PULL', 'CABLE');
        $this->catalogExercise('ROW', 'UPPER_PULL', 'BARBELL', 'HORIZONTAL');
        $this->catalogExercise('ROW', 'UPPER_PULL', 'CABLE', 'HORIZONTAL');
        $this->catalogExercise('REAR_DELT_FLY', 'UPPER_PULL', 'DUMBBELL');
        $this->catalogExercise('PULLOVER_STRAIGHT_ARM', 'UPPER_PULL', 'CABLE');
        $this->catalogExercise('SQUAT', 'LOWER', 'BARBELL');
        $this->catalogExercise('HINGE', 'LOWER', 'BARBELL');
        $this->catalogExercise('KNEE_EXTENSION', 'LOWER', 'MACHINE');
        $this->catalogExercise('KNEE_FLEXION', 'LOWER', 'MACHINE');
        $this->catalogExercise('CALF_RAISE', 'LOWER', 'MACHINE');
        $this->catalogExercise('TRUNK_FLEXION', 'CORE', 'BODYWEIGHT');
        $this->catalogExercise('ROTATION', 'CORE', 'CABLE');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function assertRowSatisfiesSlot(array $row, Slot $slot): void
    {
        $this->assertSame($slot->key, $row['slot_key']);

        $exercise = \App\Models\Exercise::with(['movementPattern', 'angle'])->findOrFail($row['exercise_id']);
        $satisfies = collect($slot->roles)->contains(fn (SlotRole $role) => $role->matches($exercise));

        $this->assertTrue($satisfies, "Exercise {$exercise->id} does not satisfy slot {$slot->key}'s role");
    }

    /**
     * Every blueprint × tier: the expected slot keys in session order and
     * each row's set count (Extended adds +1 to the first two tier-1
     * slots). Full Body arrives via the push/pull/lower trio with LOWER
     * first, so its session order is the LOWER-focused reorder.
     *
     * @return array<string, array{0: list<string>, 1: int, 2: string, 3: list<string>, 4: list<int>}>
     */
    public static function blueprintTierTable(): array
    {
        return [
            'PUSH express' => [['UPPER_PUSH'], 30, 'PUSH', ['main_press', 'overhead_press', 'triceps'], [3, 3, 3]],
            'PUSH standard' => [['UPPER_PUSH'], 60, 'PUSH', ['main_press', 'incline_press', 'overhead_press', 'side_delts', 'triceps'], [3, 3, 3, 3, 3]],
            'PUSH extended' => [['UPPER_PUSH'], 90, 'PUSH', ['main_press', 'incline_press', 'overhead_press', 'side_delts', 'triceps', 'chest_iso'], [4, 3, 4, 3, 3, 3]],
            'PULL express' => [['UPPER_PULL'], 30, 'PULL', ['vertical_pull', 'heavy_row', 'biceps'], [3, 3, 3]],
            'PULL standard' => [['UPPER_PULL'], 60, 'PULL', ['vertical_pull', 'heavy_row', 'second_row', 'rear_delts', 'biceps'], [3, 3, 3, 3, 3]],
            'PULL extended' => [['UPPER_PULL'], 90, 'PULL', ['vertical_pull', 'heavy_row', 'second_row', 'rear_delts', 'biceps', 'lats_iso'], [4, 4, 3, 3, 3, 3]],
            'UPPER express' => [['UPPER_PUSH', 'UPPER_PULL'], 30, 'UPPER', ['chest_press', 'heavy_pull', 'press_2'], [3, 3, 3]],
            'UPPER standard' => [['UPPER_PUSH', 'UPPER_PULL'], 60, 'UPPER', ['chest_press', 'heavy_pull', 'press_2', 'second_pull', 'side_delts'], [3, 3, 3, 3, 3]],
            'UPPER extended' => [['UPPER_PUSH', 'UPPER_PULL'], 90, 'UPPER', ['chest_press', 'heavy_pull', 'press_2', 'second_pull', 'side_delts', 'arms'], [4, 4, 3, 3, 3, 3]],
            // Push+pull+arms (the split seeder's Upper Body day) is an UPPER
            // day in any order; its arms slot is tier 3, so arm work arrives
            // at Extended only (#127).
            'UPPER via push+pull+arms standard' => [['UPPER_PUSH', 'UPPER_PULL', 'ARMS'], 60, 'UPPER', ['chest_press', 'heavy_pull', 'press_2', 'second_pull', 'side_delts'], [3, 3, 3, 3, 3]],
            'UPPER via arms+pull+push extended' => [['ARMS', 'UPPER_PULL', 'UPPER_PUSH'], 90, 'UPPER', ['chest_press', 'heavy_pull', 'press_2', 'second_pull', 'side_delts', 'arms'], [4, 4, 3, 3, 3, 3]],
            'LOWER express' => [['LOWER'], 30, 'LOWER', ['quad_compound', 'hinge', 'ham_iso'], [3, 3, 3]],
            'LOWER standard' => [['LOWER'], 60, 'LOWER', ['quad_compound', 'hinge', 'quad_iso', 'ham_iso', 'calves'], [3, 3, 3, 3, 3]],
            'LOWER extended' => [['LOWER'], 90, 'LOWER', ['quad_compound', 'hinge', 'quad_iso', 'ham_iso', 'calves', 'abs'], [4, 4, 3, 3, 3, 3]],
            'FULL_BODY express' => [['LOWER', 'UPPER_PUSH', 'UPPER_PULL'], 30, 'FULL_BODY', ['lower_compound', 'push', 'pull'], [3, 3, 3]],
            'FULL_BODY standard' => [['LOWER', 'UPPER_PUSH', 'UPPER_PULL'], 60, 'FULL_BODY', ['lower_compound', 'push', 'pull', 'lower_iso', 'delts_arms'], [3, 3, 3, 3, 3]],
            'FULL_BODY extended' => [['LOWER', 'UPPER_PUSH', 'UPPER_PULL'], 90, 'FULL_BODY', ['lower_compound', 'push', 'pull', 'lower_iso', 'delts_arms', 'core'], [4, 4, 3, 3, 3, 3]],
            'ARMS express' => [['ARMS'], 30, 'ARMS', ['overhead_press', 'biceps_heavy', 'triceps_heavy'], [3, 3, 3]],
            'ARMS standard' => [['ARMS'], 60, 'ARMS', ['overhead_press', 'biceps_heavy', 'triceps_heavy', 'side_delts', 'arms_finisher'], [3, 3, 3, 3, 3]],
            'ARMS extended' => [['ARMS'], 90, 'ARMS', ['overhead_press', 'biceps_heavy', 'triceps_heavy', 'side_delts', 'arms_finisher', 'rear_delts'], [4, 4, 3, 3, 3, 3]],
        ];
    }

    /**
     * @param  list<string>  $regions
     * @param  list<string>  $expectedSlotKeys
     * @param  list<int>  $expectedSets
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('blueprintTierTable')]
    public function test_each_blueprint_and_tier_produces_its_slots_in_order_with_slot_consistent_rows(
        array $regions,
        int $durationMinutes,
        string $blueprintCode,
        array $expectedSlotKeys,
        array $expectedSets,
    ): void {
        $this->seedFullGymCatalog();

        $workout = $this->generator->generate($this->user, [
            'target_regions' => $regions,
            'duration_minutes' => $durationMinutes,
        ]);

        $rows = $workout['exercises'];
        $this->assertSame($expectedSlotKeys, array_column($rows, 'slot_key'));
        $this->assertSame($expectedSets, array_column($rows, 'target_sets'));
        $this->assertSame(range(1, count($expectedSlotKeys)), array_column($rows, 'order'));

        $slots = collect((new BlueprintRepository)->blueprint($blueprintCode)->slots())->keyBy('key');
        $pickedIds = array_column($rows, 'exercise_id');
        $this->assertSame(count($pickedIds), count(array_unique($pickedIds)), 'An exercise was picked twice');

        foreach ($rows as $row) {
            $slot = $slots->get($row['slot_key']);
            $this->assertRowSatisfiesSlot($row, $slot);
            $this->assertSame($slot->repMin, $row['min_target_reps']);
            $this->assertSame($slot->repMax, $row['max_target_reps']);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function rowsBySlotKey(array $rows): array
    {
        return array_combine(array_column($rows, 'slot_key'), $rows);
    }

    public function test_strength_goal_shifts_compound_slots_to_four_to_six_and_leaves_isolations_alone(): void
    {
        $this->user->profile->update(['fitness_goal' => FitnessGoal::Strength]);
        $this->seedFullGymCatalog();

        $rows = $this->rowsBySlotKey($this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 45,
        ])['exercises']);

        foreach (['main_press', 'incline_press', 'overhead_press'] as $compoundSlot) {
            $this->assertSame(4, $rows[$compoundSlot]['min_target_reps'], $compoundSlot);
            $this->assertSame(6, $rows[$compoundSlot]['max_target_reps'], $compoundSlot);
        }

        foreach (['side_delts', 'triceps'] as $isolationSlot) {
            $this->assertSame(10, $rows[$isolationSlot]['min_target_reps'], $isolationSlot);
            $this->assertSame(15, $rows[$isolationSlot]['max_target_reps'], $isolationSlot);
        }
    }

    public function test_fat_loss_goal_shifts_compound_slots_to_eight_to_twelve_and_leaves_isolations_alone(): void
    {
        $this->user->profile->update(['fitness_goal' => FitnessGoal::FatLoss]);
        $this->seedFullGymCatalog();

        $rows = $this->rowsBySlotKey($this->generator->generate($this->user, [
            'target_regions' => ['LOWER'],
            'duration_minutes' => 45,
        ])['exercises']);

        foreach (['quad_compound', 'hinge'] as $compoundSlot) {
            $this->assertSame(8, $rows[$compoundSlot]['min_target_reps'], $compoundSlot);
            $this->assertSame(12, $rows[$compoundSlot]['max_target_reps'], $compoundSlot);
        }

        $this->assertSame(10, $rows['ham_iso']['min_target_reps']);
        $this->assertSame(15, $rows['ham_iso']['max_target_reps']);
        $this->assertSame(12, $rows['calves']['min_target_reps']);
        $this->assertSame(15, $rows['calves']['max_target_reps']);
    }

    public function test_muscle_gain_and_general_fitness_keep_the_slot_base_ranges_on_compounds(): void
    {
        $this->user->profile->update(['fitness_goal' => FitnessGoal::MuscleGain]);
        $this->seedFullGymCatalog();

        $rows = $this->rowsBySlotKey($this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 45,
        ])['exercises']);

        $this->assertSame(6, $rows['main_press']['min_target_reps']);
        $this->assertSame(10, $rows['main_press']['max_target_reps']);
    }

    public function test_logged_history_outranks_the_goal_shifted_slot_range(): void
    {
        $this->user->profile->update(['fitness_goal' => FitnessGoal::Strength]);
        $this->seedFullGymCatalog();

        // The catalog's only FLAT press — main_press lands on it deterministically.
        $bench = \App\Models\Exercise::whereHas('angle', fn ($q) => $q->where('code', 'FLAT'))->sole();

        $session = \App\Models\WorkoutSession::factory()->create([
            'user_id' => $this->user->id,
            'status' => \App\Enums\WorkoutSessionStatus::Completed,
            'completed_at' => now(),
        ]);
        foreach ([1, 2, 3] as $setNumber) {
            \App\Models\SetLog::create([
                'workout_session_id' => $session->id,
                'exercise_id' => $bench->id,
                'set_number' => $setNumber,
                'weight' => 50,
                'reps' => 12,
            ]);
        }

        $rows = $this->rowsBySlotKey($this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 45,
        ])['exercises']);

        // History wins: the calculator's full prescription — rep range from
        // the ADR-0009 matrix (strength compound 3–6), weight progressed by
        // double progression (reps maxed at 50kg ⇒ 52.5kg) — not the
        // no-history goal-shifted slot range (4–6).
        $this->assertSame($bench->id, $rows['main_press']['exercise_id']);
        $this->assertSame(3, $rows['main_press']['min_target_reps']);
        $this->assertSame(6, $rows['main_press']['max_target_reps']);
        $this->assertEqualsWithDelta(52.5, (float) $rows['main_press']['target_weight'], 0.001);

        // Slot sets still win for generated rows, even with history.
        $this->assertSame(3, $rows['main_press']['target_sets']);

        // A compound without history keeps the strength shift.
        $this->assertSame(4, $rows['overhead_press']['min_target_reps']);
        $this->assertSame(6, $rows['overhead_press']['max_target_reps']);
    }

    public function test_a_skipped_slots_sets_move_one_at_a_time_to_the_earliest_filled_slots(): void
    {
        // No SHOULDER_RAISE, no UPPER_PUSH isolation, no bodyweight stand-in:
        // side_delts skips at waterfall level 4 and its 3 sets cascade.
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'INCLINE');
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'VERTICAL');
        $this->catalogExercise('ELBOW_EXTENSION', 'ARMS', 'CABLE');

        $rows = $this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 45,
        ])['exercises'];

        $this->assertSame(
            ['main_press', 'incline_press', 'overhead_press', 'triceps'],
            array_column($rows, 'slot_key'),
        );
        $this->assertSame([4, 4, 4, 3], array_column($rows, 'target_sets'));
    }

    public function test_redistributed_sets_cap_at_slot_sets_plus_one_and_the_rest_are_dropped(): void
    {
        // Bodyweight-only arms day: pike push-up serves overhead_press, the
        // bench dip serves biceps_heavy via level-2 relaxation, and the
        // remaining three slots skip — 9 orphaned sets chase two rows
        // capped at 4 each, so 7 are dropped by design.
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BODYWEIGHT', 'VERTICAL');
        $this->catalogExercise('ELBOW_EXTENSION', 'ARMS', 'BODYWEIGHT');

        $rows = $this->generator->generate($this->user, [
            'target_regions' => ['ARMS'],
            'duration_minutes' => 45,
            'equipment_types' => ['BODYWEIGHT'],
        ])['exercises'];

        $this->assertCount(2, $rows);
        $this->assertSame(['overhead_press', 'biceps_heavy'], array_column($rows, 'slot_key'));
        $this->assertSame([4, 4], array_column($rows, 'target_sets'));
    }

    public function test_duplicate_day_types_in_a_split_fill_independently(): void
    {
        // A split with two push days calls the generator once per day:
        // each fill shuffles its own picks, so the week gets variety while
        // both days stay proper push days (#107 stories 17 and 19 —
        // regenerating a draft rides the same mechanism).
        $this->seedFullGymCatalog();
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'DUMBBELL', 'FLAT');

        $mainPressPicks = collect(range(1, 20))
            ->map(fn () => $this->rowsBySlotKey($this->generator->generate($this->user, [
                'target_regions' => ['UPPER_PUSH'],
                'duration_minutes' => 30,
            ])['exercises'])['main_press']['exercise_id'])
            ->unique();

        $this->assertGreaterThan(1, $mainPressPicks->count());
    }

    public function test_redistribution_caps_at_the_sessions_tier_adjusted_sets_plus_one(): void
    {
        // Extended push day, same thin catalog: side_delts and chest_iso
        // skip (6 orphaned sets). The two Extended-bonused tier-1 slots sit
        // at 4 sets, so their cap is 5 — the cap reads the session's
        // tier-adjusted sets, not the blueprint base. Four sets land, two
        // fit nowhere and are dropped.
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'INCLINE');
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'VERTICAL');
        $this->catalogExercise('ELBOW_EXTENSION', 'ARMS', 'CABLE');

        $rows = $this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 90,
        ])['exercises'];

        $this->assertSame(
            ['main_press', 'incline_press', 'overhead_press', 'triceps'],
            array_column($rows, 'slot_key'),
        );
        $this->assertSame([5, 4, 5, 4], array_column($rows, 'target_sets'));
    }

    public function test_no_requested_regions_means_a_push_focused_full_body_day(): void
    {
        $this->seedFullGymCatalog();

        $rows = $this->generator->generate($this->user, [
            'duration_minutes' => 60,
        ])['exercises'];

        $this->assertSame(
            ['push', 'lower_compound', 'pull', 'delts_arms', 'lower_iso'],
            array_column($rows, 'slot_key'),
        );
    }

    /**
     * Fragment-composed days (#112): a combination matching no Blueprint
     * concatenates its regions' Fragments in request order, tier-filters,
     * and trims fairly to the tier's set budget (10/16/22): the region
     * holding the most sets loses its last slot (ties: the later-requested
     * region), and no region loses its first slot while another still has
     * two (#127).
     *
     * @return array<string, array{0: list<string>, 1: int, 2: list<string>, 3: list<int>}>
     */
    public static function fragmentDayTable(): array
    {
        return [
            'push+lower express' => [['UPPER_PUSH', 'LOWER'], 30, ['main_press', 'quad_compound', 'hinge'], [3, 3, 3]],
            'push+lower standard' => [['UPPER_PUSH', 'LOWER'], 60, ['main_press', 'secondary_press', 'quad_compound', 'hinge', 'ham_iso'], [3, 3, 3, 3, 3]],
            'lower+push standard follows request order' => [['LOWER', 'UPPER_PUSH'], 60, ['quad_compound', 'hinge', 'ham_iso', 'main_press', 'secondary_press'], [3, 3, 3, 3, 3]],
            'arms+core express' => [['ARMS', 'CORE'], 30, ['biceps', 'triceps', 'trunk_flexion'], [3, 3, 3]],
            'arms+core standard' => [['ARMS', 'CORE'], 60, ['biceps', 'triceps', 'side_delts', 'trunk_flexion', 'rotation'], [3, 3, 3, 3, 3]],
            'core alone standard' => [['CORE'], 60, ['trunk_flexion', 'rotation'], [3, 3]],
            'core alone express' => [['CORE'], 30, ['trunk_flexion'], [3]],
            // Express, 12 sets > 10 with a 6–6 tie: the later-requested
            // region loses its last slot.
            'two regions trim at express' => [['ARMS', 'LOWER'], 30, ['biceps', 'triceps', 'quad_compound'], [3, 3, 3]],
            // Express, 18 sets > 10: LOWER then ARMS lose their second
            // slot, then the later-requested UPPER_PUSH its only one — no
            // region empties while another keeps two.
            'four regions trim at express' => [['ARMS', 'CORE', 'LOWER', 'UPPER_PUSH'], 30, ['biceps', 'trunk_flexion', 'quad_compound'], [3, 3, 3]],
            // Standard, 21 sets > 16: ARMS holds the most and loses
            // side_delts; then the 6–6–6 tie trims the later UPPER_PUSH.
            'three regions trim at standard' => [['ARMS', 'CORE', 'UPPER_PUSH'], 60, ['biceps', 'triceps', 'trunk_flexion', 'rotation', 'main_press'], [3, 3, 3, 3, 3]],
            // Extended: 9 slots, 27 sets > 22 — and no Extended +1 bonus:
            // the set budget is a fragment day's whole volume mechanism.
            'three regions trim at extended' => [['UPPER_PUSH', 'LOWER', 'ARMS'], 90, ['main_press', 'secondary_press', 'chest_iso', 'quad_compound', 'hinge', 'biceps', 'triceps'], [3, 3, 3, 3, 3, 3, 3]],
        ];
    }

    /**
     * @param  list<string>  $regions
     * @param  list<string>  $expectedSlotKeys
     * @param  list<int>  $expectedSets
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('fragmentDayTable')]
    public function test_an_unmapped_region_combination_composes_a_fragment_day(
        array $regions,
        int $durationMinutes,
        array $expectedSlotKeys,
        array $expectedSets,
    ): void {
        $this->seedFullGymCatalog();

        $rows = $this->generator->generate($this->user, [
            'target_regions' => $regions,
            'duration_minutes' => $durationMinutes,
        ])['exercises'];

        $this->assertSame($expectedSlotKeys, array_column($rows, 'slot_key'));
        $this->assertSame($expectedSets, array_column($rows, 'target_sets'));
        $this->assertSame(range(1, count($expectedSlotKeys)), array_column($rows, 'order'));

        $slots = collect((new BlueprintRepository)->fragments())
            ->flatMap(fn (array $fragmentSlots) => $fragmentSlots)
            ->keyBy('key');
        $pickedIds = array_column($rows, 'exercise_id');
        $this->assertSame(count($pickedIds), count(array_unique($pickedIds)), 'An exercise was picked twice');

        foreach ($rows as $row) {
            $slot = $slots->get($row['slot_key']);
            $this->assertRowSatisfiesSlot($row, $slot);
            $this->assertSame($slot->repMin, $row['min_target_reps']);
            $this->assertSame($slot->repMax, $row['max_target_reps']);
            $this->assertArrayHasKey('target_weight', $row);
            $this->assertIsInt($row['rest_seconds']);
        }
    }

    public function test_a_fragment_days_rationale_names_the_regions_and_the_budget_not_a_blueprint(): void
    {
        $this->seedFullGymCatalog();

        $rationale = $this->generator->generate($this->user, [
            'target_regions' => ['ARMS', 'CORE'],
            'duration_minutes' => 45,
        ])['rationale'];

        $this->assertStringContainsString('arms + core', $rationale);
        $this->assertStringContainsString('fragments', $rationale);
        $this->assertStringNotContainsString('blueprint', $rationale);
    }

    public function test_the_literal_full_body_region_generates_the_push_focused_full_body_day(): void
    {
        // ['FULL_BODY'] is user-selectable via the API but missing from
        // #107's mapping table — it resolves like an empty region list.
        $this->seedFullGymCatalog();

        $rows = $this->generator->generate($this->user, [
            'target_regions' => ['FULL_BODY'],
            'duration_minutes' => 60,
        ])['exercises'];

        $this->assertSame(
            ['push', 'lower_compound', 'pull', 'delts_arms', 'lower_iso'],
            array_column($rows, 'slot_key'),
        );
    }

    public function test_fragment_compound_slots_goal_shift_and_isolations_keep_their_range(): void
    {
        $this->user->profile->update(['fitness_goal' => FitnessGoal::Strength]);
        $this->seedFullGymCatalog();

        $rows = $this->rowsBySlotKey($this->generator->generate($this->user, [
            'target_regions' => ['LOWER', 'ARMS'],
            'duration_minutes' => 60,
        ])['exercises']);

        // Compound fragment slots shift to 4–6 under the strength goal…
        foreach (['quad_compound', 'hinge'] as $compoundSlot) {
            $this->assertSame(4, $rows[$compoundSlot]['min_target_reps'], $compoundSlot);
            $this->assertSame(6, $rows[$compoundSlot]['max_target_reps'], $compoundSlot);
        }

        // …while isolations never leave their slot range.
        $this->assertSame(10, $rows['ham_iso']['min_target_reps']);
        $this->assertSame(15, $rows['ham_iso']['max_target_reps']);
        $this->assertSame(8, $rows['biceps']['min_target_reps']);
        $this->assertSame(12, $rows['biceps']['max_target_reps']);
    }

    public function test_generation_fails_loudly_when_every_slot_skips(): void
    {
        $this->expectExceptionMessage('No exercises available matching the specified criteria');

        $this->generator->generate($this->user, [
            'target_regions' => ['ARMS'],
            'duration_minutes' => 45,
        ]);
    }

    /**
     * Retags an exercise as FUNCTIONAL only, removing the factory's default
     * BODYBUILDING tag.
     */
    private function tagFunctionalOnly(\App\Models\Exercise $exercise): void
    {
        $exercise->trainingStyles()->sync([
            \App\Models\TrainingStyle::firstOrCreate(
                ['code' => 'FUNCTIONAL'],
                ['name' => 'Functional', 'display_order' => 20]
            )->id,
        ]);
    }

    /**
     * Training-style semantics carried over from the legacy generator: with
     * neither an equipment selection nor a style filter, the day defaults to
     * the BODYBUILDING catalog, so functional-only movements (kettlebell
     * flows, TRX work) never surprise a plain request.
     */
    public function test_a_plain_request_defaults_to_the_bodybuilding_catalog(): void
    {
        $this->seedFullGymCatalog();
        $thruster = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');
        $this->tagFunctionalOnly($thruster);

        $pickedIds = collect(range(1, 10))
            ->flatMap(fn () => array_column($this->generator->generate($this->user, [
                'target_regions' => ['UPPER_PUSH'],
                'duration_minutes' => 60,
            ])['exercises'], 'exercise_id'))
            ->unique();

        $this->assertNotContains($thruster->id, $pickedIds->all());
    }

    public function test_an_equipment_selection_applies_no_training_style_default(): void
    {
        // TRX is functional equipment: a member who selected it gets its
        // functional-only exercises without naming a style.
        $trxPress = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'TRX', 'FLAT');
        $this->tagFunctionalOnly($trxPress);

        $rows = $this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 45,
            'equipment_types' => ['TRX'],
        ])['exercises'];

        $this->assertContains($trxPress->id, array_column($rows, 'exercise_id'));
    }

    public function test_a_client_bodybuilding_default_yields_to_functional_equipment(): void
    {
        // Mobile sends BODYBUILDING by default; a member who then picks TRX
        // meant the equipment, so the style filter is dropped rather than
        // starving the day.
        $trxPress = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'TRX', 'FLAT');
        $this->tagFunctionalOnly($trxPress);

        $rows = $this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 45,
            'equipment_types' => ['TRX'],
            'training_styles' => ['BODYBUILDING'],
        ])['exercises'];

        $this->assertContains($trxPress->id, array_column($rows, 'exercise_id'));
    }

    public function test_an_explicit_training_style_combines_with_the_equipment_selection(): void
    {
        $trxFunctional = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'TRX', 'FLAT');
        $this->tagFunctionalOnly($trxFunctional);
        $trxBodybuilding = $this->catalogExercise('FLY', 'UPPER_PUSH', 'TRX', 'HORIZONTAL');

        $rows = $this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 45,
            'equipment_types' => ['TRX'],
            'training_styles' => ['FUNCTIONAL'],
        ])['exercises'];

        $pickedIds = array_column($rows, 'exercise_id');
        $this->assertContains($trxFunctional->id, $pickedIds);
        $this->assertNotContains($trxBodybuilding->id, $pickedIds);
    }

    public function test_a_bodybuilding_filter_holds_for_a_non_functional_equipment_selection(): void
    {
        $dumbbellPress = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'DUMBBELL', 'FLAT');
        $dumbbellThruster = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'DUMBBELL', 'VERTICAL');
        $this->tagFunctionalOnly($dumbbellThruster);

        $rows = $this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 45,
            'equipment_types' => ['DUMBBELL'],
            'training_styles' => ['BODYBUILDING'],
        ])['exercises'];

        $pickedIds = array_column($rows, 'exercise_id');
        $this->assertContains($dumbbellPress->id, $pickedIds);
        $this->assertNotContains($dumbbellThruster->id, $pickedIds);
    }

    public function test_functional_equipment_anywhere_in_the_selection_drops_the_bodybuilding_default(): void
    {
        $dumbbellPress = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'DUMBBELL', 'FLAT');
        $trxRow = $this->catalogExercise('ROW', 'UPPER_PULL', 'TRX', 'HORIZONTAL');
        $this->tagFunctionalOnly($trxRow);

        $rows = $this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PUSH', 'UPPER_PULL'],
            'duration_minutes' => 45,
            'equipment_types' => ['DUMBBELL', 'TRX'],
            'training_styles' => ['BODYBUILDING'],
        ])['exercises'];

        $pickedIds = array_column($rows, 'exercise_id');
        $this->assertContains($dumbbellPress->id, $pickedIds);
        $this->assertContains($trxRow->id, $pickedIds);
    }

    public function test_an_empty_training_styles_array_is_treated_as_unset_alongside_an_equipment_selection(): void
    {
        $trxPress = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'TRX', 'FLAT');
        $this->tagFunctionalOnly($trxPress);

        $rows = $this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 45,
            'equipment_types' => ['TRX'],
            'training_styles' => [],
        ])['exercises'];

        $this->assertContains($trxPress->id, array_column($rows, 'exercise_id'));
    }

    public function test_generation_fails_when_equipment_and_training_style_do_not_overlap(): void
    {
        $barbellThruster = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');
        $this->tagFunctionalOnly($barbellThruster);

        $this->expectExceptionMessage('No exercises available matching the specified criteria');

        $this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 45,
            'equipment_types' => ['BARBELL'],
            'training_styles' => ['BODYBUILDING'],
        ]);
    }

    /**
     * The beginner difficulty exclusion is the config value it appears to
     * be (#123): changing which difficulties are excluded changes which
     * exercise a beginner's main press can be.
     */
    public function test_the_beginner_difficulty_exclusion_is_read_from_config(): void
    {
        $this->user->profile->update(['training_experience' => TrainingExperience::Beginner]);
        $intermediatePress = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT', ['difficulty' => 'intermediate']);
        $advancedPress = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT', ['difficulty' => 'advanced']);

        $pickedIds = fn () => array_column($this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 30,
        ])['exercises'], 'exercise_id');

        $byDefault = $pickedIds();
        $this->assertContains($intermediatePress->id, $byDefault);
        $this->assertNotContains($advancedPress->id, $byDefault);

        config(['workout_generator.beginner_excluded_difficulties' => ['intermediate']]);

        $reconfigured = $pickedIds();
        $this->assertContains($advancedPress->id, $reconfigured);
        $this->assertNotContains($intermediatePress->id, $reconfigured);
    }

    public function test_a_standard_push_day_is_the_push_blueprints_tier_one_and_two_slots_in_order(): void
    {
        $this->seedFullGymCatalog();

        $workout = $this->generator->generate($this->user, [
            'target_regions' => ['UPPER_PUSH'],
            'duration_minutes' => 45,
        ]);

        $this->assertIsString($workout['rationale']);
        $this->assertNotSame('', $workout['rationale']);

        $rows = $workout['exercises'];
        $this->assertSame(
            ['main_press', 'incline_press', 'overhead_press', 'side_delts', 'triceps'],
            array_column($rows, 'slot_key'),
        );
        $this->assertSame([1, 2, 3, 4, 5], array_column($rows, 'order'));

        $slots = collect((new BlueprintRepository)->blueprint('PUSH')->slots())->keyBy('key');

        foreach ($rows as $row) {
            $slot = $slots->get($row['slot_key']);
            $this->assertRowSatisfiesSlot($row, $slot);
            $this->assertSame($slot->sets, $row['target_sets']);
            $this->assertSame($slot->repMin, $row['min_target_reps']);
            $this->assertSame($slot->repMax, $row['max_target_reps']);
            $this->assertArrayHasKey('target_weight', $row);
            $this->assertIsInt($row['rest_seconds']);
        }
    }
}
