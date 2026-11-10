<?php

namespace Tests\Feature;

use App\Services\WorkoutGenerator\Slots\BlueprintRepository;
use App\Services\WorkoutGenerator\Slots\RelaxationLevel;
use App\Services\WorkoutGenerator\Slots\Slot;
use App\Services\WorkoutGenerator\Slots\SlotConstraints;
use App\Services\WorkoutGenerator\Slots\SlotFiller;
use App\Services\WorkoutGenerator\Slots\SlotPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesCatalogExercises;
use Tests\TestCase;

class SlotFillerTest extends TestCase
{
    use CreatesCatalogExercises;
    use RefreshDatabase;

    private function slot(string $blueprint, string $slotKey): \App\Services\WorkoutGenerator\Slots\Slot
    {
        $slots = (new BlueprintRepository)->blueprint($blueprint)->slots();

        foreach ($slots as $slot) {
            if ($slot->key === $slotKey) {
                return $slot;
            }
        }

        $this->fail("No slot {$slotKey} in blueprint {$blueprint}");
    }

    public function test_fills_a_slot_with_a_role_matching_exercise_at_level_zero(): void
    {
        $bench = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');
        $this->catalogExercise('ROW', 'UPPER_PULL', 'BARBELL', 'HORIZONTAL'); // decoy

        $pool = SlotPool::load(new SlotConstraints);
        $result = (new SlotFiller)->fill($this->slot('PUSH', 'main_press'), $pool);

        $this->assertFalse($result->isSkipped());
        $this->assertSame($bench->id, $result->exercise->id);
        $this->assertSame(RelaxationLevel::None, $result->level);
    }

    public function test_equipment_selection_is_a_hard_constraint(): void
    {
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');
        $dumbbellPress = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'DUMBBELL', 'FLAT');

        $pool = SlotPool::load(new SlotConstraints(equipmentTypes: ['DUMBBELL']));
        $result = (new SlotFiller)->fill($this->slot('PUSH', 'main_press'), $pool);

        $this->assertSame($dumbbellPress->id, $result->exercise->id);
        $this->assertSame(RelaxationLevel::None, $result->level);
    }

    public function test_beginner_constraint_excludes_advanced_difficulty_and_excluded_equipment(): void
    {
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT', ['difficulty' => 'advanced']);
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'SMITH', 'FLAT');
        $safe = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT', ['difficulty' => 'beginner']);

        $pool = SlotPool::load(new SlotConstraints(beginner: true));
        $result = (new SlotFiller)->fill($this->slot('PUSH', 'main_press'), $pool);

        $this->assertSame($safe->id, $result->exercise->id);
        $this->assertSame(RelaxationLevel::None, $result->level);
    }

    public function test_partner_catalog_is_a_hard_constraint(): void
    {
        $partner = \App\Models\Partner::factory()->create();
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');
        $partnerPress = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'DUMBBELL', 'FLAT');
        $partner->exercises()->attach($partnerPress->id);

        $pool = SlotPool::load(new SlotConstraints(partner: $partner));
        $result = (new SlotFiller)->fill($this->slot('PUSH', 'main_press'), $pool);

        $this->assertSame($partnerPress->id, $result->exercise->id);
    }

    public function test_user_supplied_pattern_and_angle_filters_are_hard_constraints(): void
    {
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');
        $pushup = $this->catalogExercise('PUSHUP', 'UPPER_PUSH', 'BODYWEIGHT', 'HORIZONTAL');

        $pool = SlotPool::load(new SlotConstraints(movementPatterns: ['PUSHUP']));
        $result = (new SlotFiller)->fill($this->slot('PUSH', 'main_press'), $pool);

        $this->assertSame($pushup->id, $result->exercise->id);

        $pool = SlotPool::load(new SlotConstraints(angles: ['FLAT']));
        $result = (new SlotFiller)->fill($this->slot('PUSH', 'main_press'), $pool);

        $this->assertSame('FLAT', $result->exercise->angle->code);
    }

    public function test_waterfall_level_one_drops_the_angle(): void
    {
        $flatPress = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');

        $pool = SlotPool::load(new SlotConstraints);
        $result = (new SlotFiller)->fill($this->slot('PUSH', 'incline_press'), $pool);

        $this->assertSame($flatPress->id, $result->exercise->id);
        $this->assertSame(RelaxationLevel::AngleDropped, $result->level);
    }

    public function test_waterfall_prefers_dropping_the_angle_over_dropping_the_pattern(): void
    {
        $flatPress = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');
        $this->catalogExercise('FLY', 'UPPER_PUSH', 'CABLE', 'HORIZONTAL'); // level-2 bait

        $pool = SlotPool::load(new SlotConstraints);
        $result = (new SlotFiller)->fill($this->slot('PUSH', 'incline_press'), $pool);

        $this->assertSame($flatPress->id, $result->exercise->id);
        $this->assertSame(RelaxationLevel::AngleDropped, $result->level);
    }

    public function test_waterfall_level_two_drops_the_pattern_keeping_region_and_class(): void
    {
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');       // same region, compound — out
        $this->catalogExercise('ELBOW_FLEXION', 'ARMS', 'DUMBBELL');            // isolation, wrong region — out
        $fly = $this->catalogExercise('FLY', 'UPPER_PUSH', 'CABLE', 'HORIZONTAL'); // isolation, same region — in

        $pool = SlotPool::load(new SlotConstraints);
        $result = (new SlotFiller)->fill($this->slot('PUSH', 'side_delts'), $pool);

        $this->assertSame($fly->id, $result->exercise->id);
        $this->assertSame(RelaxationLevel::PatternDropped, $result->level);
    }

    public function test_waterfall_level_three_falls_back_to_bodyweight_equivalents(): void
    {
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'MACHINE', 'FLAT');
        $benchDip = $this->catalogExercise('ELBOW_EXTENSION', 'ARMS', 'BODYWEIGHT');

        $pool = SlotPool::load(new SlotConstraints(equipmentTypes: ['MACHINE']));
        $result = (new SlotFiller)->fill($this->slot('PUSH', 'triceps'), $pool);

        $this->assertSame($benchDip->id, $result->exercise->id);
        $this->assertSame(RelaxationLevel::BodyweightEquivalent, $result->level);
    }

    public function test_bodyweight_equivalents_keep_the_slots_compound_isolation_class(): void
    {
        // A push-up (compound, UPPER_PUSH) must not fill an isolation
        // side-delts slot just because it is bodyweight and same-region.
        $this->catalogExercise('PUSHUP', 'UPPER_PUSH', 'BODYWEIGHT', 'HORIZONTAL');

        $pool = SlotPool::load(new SlotConstraints(equipmentTypes: ['BODYWEIGHT']));
        $result = (new SlotFiller)->fill($this->slot('PUSH', 'side_delts'), $pool);

        $this->assertTrue($result->isSkipped());
    }

    public function test_bodyweight_equivalents_accept_a_role_matching_exercise_of_either_class(): void
    {
        // quad_iso's roles mix classes (KNEE_EXTENSION isolation,
        // LUNGE_SPLIT_SQUAT compound). A bodyweight lunge literally serves
        // the role, so level 3 must not reject it for being a compound.
        $lunge = $this->catalogExercise('LUNGE_SPLIT_SQUAT', 'LOWER', 'BODYWEIGHT');

        $pool = SlotPool::load(new SlotConstraints(equipmentTypes: ['MACHINE']));
        $result = (new SlotFiller)->fill($this->slot('LOWER', 'quad_iso'), $pool);

        $this->assertSame($lunge->id, $result->exercise->id);
        $this->assertSame(RelaxationLevel::BodyweightEquivalent, $result->level);
    }

    public function test_slot_is_skipped_when_even_the_waterfall_runs_dry(): void
    {
        $this->catalogExercise('ROW', 'UPPER_PULL', 'BARBELL', 'HORIZONTAL');

        $pool = SlotPool::load(new SlotConstraints);
        $result = (new SlotFiller)->fill($this->slot('PUSH', 'side_delts'), $pool);

        $this->assertTrue($result->isSkipped());
        $this->assertNull($result->exercise);
        $this->assertSame(RelaxationLevel::Skipped, $result->level);
    }

    public function test_non_zero_relaxations_are_logged_with_slot_key_and_level(): void
    {
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');
        $pool = SlotPool::load(new SlotConstraints);

        \Illuminate\Support\Facades\Log::spy();

        (new SlotFiller)->fill($this->slot('PUSH', 'incline_press'), $pool);

        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')->once()->with(
            'workout.slot_relaxed',
            ['slot_key' => 'incline_press', 'level' => RelaxationLevel::AngleDropped->value]
        );
    }

    public function test_level_zero_fills_are_not_logged(): void
    {
        $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');
        $pool = SlotPool::load(new SlotConstraints);

        \Illuminate\Support\Facades\Log::spy();

        (new SlotFiller)->fill($this->slot('PUSH', 'main_press'), $pool);

        \Illuminate\Support\Facades\Log::shouldNotHaveReceived('info');
    }

    public function test_skips_are_logged(): void
    {
        $pool = SlotPool::load(new SlotConstraints);

        \Illuminate\Support\Facades\Log::spy();

        (new SlotFiller)->fill($this->slot('PUSH', 'side_delts'), $pool);

        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')->once()->with(
            'workout.slot_relaxed',
            ['slot_key' => 'side_delts', 'level' => RelaxationLevel::Skipped->value]
        );
    }

    public function test_already_picked_exercises_are_excluded(): void
    {
        $first = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'BARBELL', 'FLAT');
        $second = $this->catalogExercise('PRESS', 'UPPER_PUSH', 'DUMBBELL', 'FLAT');

        $pool = SlotPool::load(new SlotConstraints);
        $result = (new SlotFiller)->fill($this->slot('PUSH', 'main_press'), $pool, [$first->id]);

        $this->assertSame($second->id, $result->exercise->id);
    }
}
