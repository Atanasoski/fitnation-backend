<?php

namespace Tests\Feature;

use App\Enums\UnitSystem;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The plan outline's workout and exercise-row editors (023/07): every write
 * goes through a resourceful route guarded by PlanPolicy and reopens the
 * outline on the node it touched.
 */
class PlanOutlineWorkoutsTest extends TestCase
{
    use RefreshDatabase;

    private Partner $gym;

    private User $member;

    private User $admin;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Partner::factory()->create();
        $this->member = User::factory()->create(['partner_id' => $this->gym->id]);
        $this->admin = User::factory()->create(['partner_id' => $this->gym->id]);
        $this->admin->roles()->attach(Role::firstOrCreate(['slug' => 'partner_admin'], ['name' => 'Partner Admin'])->id);
        $this->plan = Plan::factory()->program()->create(['user_id' => $this->member->id, 'partner_id' => null]);
    }

    public function test_adding_a_workout_opens_the_outline_on_it(): void
    {
        $this->actingAs($this->admin)
            ->post(route('workouts.store', $this->plan), ['plan_id' => $this->plan->id, 'name' => 'Push', 'day_of_week' => 2])
            ->assertRedirect($this->outline(['workout' => $workout = $this->plan->workoutTemplates()->sole()]));

        $this->assertSame('Push', $workout->name);
        $this->assertSame(2, $workout->day_of_week);
    }

    public function test_renaming_and_re_daying_a_workout_reopens_it(): void
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id, 'name' => 'Push', 'day_of_week' => 0]);

        $this->actingAs($this->admin)
            ->put(route('workouts.update', $workout), ['name' => 'Push A', 'day_of_week' => ''])
            ->assertRedirect($this->outline(['workout' => $workout]));

        $this->assertSame('Push A', $workout->fresh()->name);
        $this->assertNull($workout->fresh()->day_of_week);
    }

    public function test_removing_a_workout_reopens_its_plan(): void
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id]);

        $this->actingAs($this->admin)
            ->delete(route('workouts.destroy', $workout))
            ->assertRedirect($this->outline([]));

        $this->assertModelMissing($workout);
    }

    public function test_a_day_must_be_a_day_of_the_week(): void
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id, 'day_of_week' => 1]);

        $this->actingAs($this->admin)
            ->put(route('workouts.update', $workout), ['name' => 'Push', 'day_of_week' => 7])
            ->assertSessionHasErrors('day_of_week');

        $this->assertSame(1, $workout->fresh()->day_of_week);
    }

    public function test_adding_an_exercise_appends_a_row_and_opens_it(): void
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id]);
        $first = $this->row($workout);
        $squat = $this->exercise('Back Squat');

        $this->actingAs($this->admin)
            ->post(route('workout-exercises.store', $workout), ['exercise_id' => $squat->id])
            ->assertRedirect($this->outline(['workout' => $workout, 'row' => $row = $workout->workoutTemplateExercises()->where('exercise_id', $squat->id)->sole()]));

        $this->assertSame([$first->id, $row->id], $this->order($workout));
    }

    public function test_the_picker_offers_only_the_owners_partner_exercises_that_are_not_archived(): void
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id]);
        $this->exercise('Back Squat');
        $this->exercise('Retired Lunge')->forceFill(['archived_at' => now()])->save();
        Exercise::factory()->create(['name' => 'Rival Press'])->partners()->attach(Partner::factory()->create());

        $this->actingAs($this->admin)
            ->get($this->outline(['workout' => $workout, 'add' => 'exercise']))
            ->assertOk()
            ->assertSee('Back Squat')
            ->assertDontSee('Retired Lunge')
            ->assertDontSee('Rival Press');
    }

    public function test_an_archived_or_another_partners_exercise_cannot_be_added_or_swapped_in(): void
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id]);
        $row = $this->row($workout);
        $archived = $this->exercise('Retired Lunge');
        $archived->forceFill(['archived_at' => now()])->save();
        $rival = Exercise::factory()->create();
        $rival->partners()->attach(Partner::factory()->create());

        foreach ([$archived, $rival] as $exercise) {
            $this->actingAs($this->admin)
                ->post(route('workout-exercises.store', $workout), ['exercise_id' => $exercise->id])
                ->assertSessionHasErrors('exercise_id');
            $this->actingAs($this->admin)
                ->put(route('workout-exercises.swap', [$workout, $row]), ['exercise_id' => $exercise->id])
                ->assertSessionHasErrors('exercise_id');
        }

        $this->assertSame([$row->id], $this->order($workout));
        $this->assertNotContains($row->fresh()->exercise_id, [$archived->id, $rival->id]);
    }

    public function test_editing_a_row_reopens_it(): void
    {
        $row = $this->row(WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id]));

        $this->actingAs($this->admin)
            ->put(route('workout-exercises.update', [$row->workout_template_id, $row]), [
                'target_sets' => 5, 'min_target_reps' => 3, 'max_target_reps' => 5, 'target_weight' => 102.5, 'rest_seconds' => 180,
            ])
            ->assertRedirect($this->outline(['workout' => $row->workoutTemplate, 'row' => $row]));

        $row->refresh();
        $this->assertSame([5, 3, 5, 180], [$row->target_sets, $row->min_target_reps, $row->max_target_reps, $row->rest_seconds]);
        $this->assertEquals(102.5, $row->target_weight);
    }

    public function test_a_blank_target_weight_clears_it(): void
    {
        $row = $this->row(WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id]), ['target_weight' => 60]);

        $this->actingAs($this->admin)->put(route('workout-exercises.update', [$row->workout_template_id, $row]), [
            'target_sets' => 3, 'min_target_reps' => 8, 'max_target_reps' => 12, 'target_weight' => '', 'rest_seconds' => 120,
        ]);

        $this->assertEquals(0, $row->fresh()->target_weight);
    }

    public function test_an_imperial_members_target_weight_is_entered_in_pounds_and_stored_in_kilograms(): void
    {
        $this->member->profile->update(['unit_system' => UnitSystem::Imperial]);
        $row = $this->row($workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id]));

        $this->actingAs($this->admin)->put(route('workout-exercises.update', [$workout, $row]), [
            'target_sets' => 3, 'min_target_reps' => 8, 'max_target_reps' => 12, 'target_weight' => 225, 'rest_seconds' => 120,
        ]);

        $this->assertEqualsWithDelta(102.06, (float) $row->fresh()->target_weight, 0.01);

        $this->actingAs($this->admin)
            ->get($this->outline(['workout' => $workout, 'row' => $row]))
            ->assertOk()
            ->assertSee('Target weight (lbs)')
            ->assertSee('value="225"', false);
    }

    public function test_an_imperial_members_new_row_converts_its_weight_too(): void
    {
        $this->member->profile->update(['unit_system' => UnitSystem::Imperial]);
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id]);

        $this->actingAs($this->admin)->post(route('workout-exercises.store', $workout), [
            'exercise_id' => $this->exercise('Back Squat')->id, 'target_weight' => 100,
        ]);

        $this->assertEqualsWithDelta(45.36, (float) $workout->workoutTemplateExercises()->sole()->target_weight, 0.01);
    }

    public function test_swapping_a_rows_exercise_keeps_its_targets(): void
    {
        $row = $this->row($workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id]), ['target_sets' => 5, 'rest_seconds' => 200]);
        $front = $this->exercise('Front Squat');

        $this->actingAs($this->admin)
            ->put(route('workout-exercises.swap', [$workout, $row]), ['exercise_id' => $front->id])
            ->assertRedirect($this->outline(['workout' => $workout, 'row' => $row]));

        $row->refresh();
        $this->assertSame($front->id, $row->exercise_id);
        $this->assertSame([5, 200], [$row->target_sets, $row->rest_seconds]);
    }

    public function test_removing_a_row_keeps_the_order_contiguous_and_reopens_the_workout(): void
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id]);
        [$a, $b, $c] = [$this->row($workout), $this->row($workout), $this->row($workout)];

        $this->actingAs($this->admin)
            ->delete(route('workout-exercises.destroy', [$workout, $b]))
            ->assertRedirect($this->outline(['workout' => $workout]));

        $this->assertSame([$a->id, $c->id], $this->order($workout));
        $this->assertSame([0, 1], $workout->workoutTemplateExercises()->pluck('order')->all());
    }

    public function test_moving_a_row_up_or_down_keeps_the_order_contiguous(): void
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id]);
        // Gapped and duplicated orders, as older data has them.
        $a = $this->row($workout, ['order' => 0]);
        $b = $this->row($workout, ['order' => 4]);
        $c = $this->row($workout, ['order' => 4]);

        $this->actingAs($this->admin)
            ->post(route('workout-exercises.move', [$workout, $c]), ['direction' => 'up'])
            ->assertRedirect($this->outline(['workout' => $workout]));

        $this->assertSame([$a->id, $c->id, $b->id], $this->order($workout));
        $this->assertSame([0, 1, 2], $workout->workoutTemplateExercises()->pluck('order')->all());

        $this->actingAs($this->admin)->post(route('workout-exercises.move', [$workout, $a]), ['direction' => 'down']);
        $this->assertSame([$c->id, $a->id, $b->id], $this->order($workout));

        // The last row cannot go further down.
        $this->actingAs($this->admin)->post(route('workout-exercises.move', [$workout, $b]), ['direction' => 'down']);
        $this->assertSame([$c->id, $a->id, $b->id], $this->order($workout));
        $this->assertSame([0, 1, 2], $workout->workoutTemplateExercises()->pluck('order')->all());
    }

    public function test_another_partners_admin_cannot_swap_or_move_a_row(): void
    {
        $row = $this->row($workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id]));
        $rival = User::factory()->create(['partner_id' => Partner::factory()->create()->id]);
        $rival->roles()->attach(Role::firstOrCreate(['slug' => 'partner_admin'], ['name' => 'Partner Admin'])->id);

        foreach ([$rival, $this->member] as $intruder) {
            $this->actingAs($intruder)
                ->put(route('workout-exercises.swap', [$workout, $row]), ['exercise_id' => $this->exercise('Front Squat')->id])
                ->assertForbidden();
            $this->actingAs($intruder)
                ->post(route('workout-exercises.move', [$workout, $row]), ['direction' => 'up'])
                ->assertForbidden();
        }
    }

    public function test_a_row_of_another_workout_is_not_found_through_this_one(): void
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id]);
        $other = $this->row(WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id]));

        $this->actingAs($this->admin)
            ->post(route('workout-exercises.move', [$workout, $other]), ['direction' => 'up'])
            ->assertNotFound();
    }

    public function test_the_outline_opens_on_a_workout_and_a_row(): void
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan->id, 'name' => 'Leg Day', 'day_of_week' => 3]);
        $row = $this->row($workout);

        $this->actingAs($this->admin)
            ->get($this->outline(['workout' => $workout]))
            ->assertOk()
            ->assertSee(route('workouts.update', $workout), false)
            ->assertSee(route('workout-exercises.move', [$workout, $row]), false);

        $this->actingAs($this->admin)
            ->get($this->outline(['workout' => $workout, 'row' => $row]))
            ->assertOk()
            ->assertSee(route('workout-exercises.update', [$workout, $row]), false)
            ->assertSee(route('workout-exercises.swap', [$workout, $row]), false);
    }

    private function exercise(string $name): Exercise
    {
        $exercise = Exercise::factory()->create(['name' => $name]);
        $exercise->partners()->attach($this->gym);

        return $exercise;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function row(WorkoutTemplate $workout, array $attributes = []): WorkoutTemplateExercise
    {
        return WorkoutTemplateExercise::create($attributes + [
            'workout_template_id' => $workout->id,
            'exercise_id' => $this->exercise('Exercise '.uniqid())->id,
            'order' => $workout->workoutTemplateExercises()->count(),
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 0,
            'rest_seconds' => 120,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function order(WorkoutTemplate $workout): array
    {
        return $workout->workoutTemplateExercises()->orderBy('order')->orderBy('id')->pluck('id')->all();
    }

    /**
     * @param  array<string, Plan|WorkoutTemplate|WorkoutTemplateExercise|string>  $nodes
     */
    private function outline(array $nodes): string
    {
        $query = ['user' => $this->member->id, 'plan' => $this->plan->id];
        foreach ($nodes as $key => $node) {
            $query[$key] = is_object($node) ? $node->id : $node;
        }

        return route('plans.index', $query);
    }
}
