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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Locks what the people who are meant to write plans can do today, before
 * the plan policy (023/05) puts every write behind one rule: a partner admin
 * on their own member's plans and their own library plans, and an app user on
 * their own plan through the API.
 */
class PlanWritesCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private Partner $partner;

    private User $partnerAdmin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = Partner::factory()->create();
        $this->partnerAdmin = User::factory()->create(['partner_id' => $this->partner->id]);
        $this->partnerAdmin->roles()->attach(
            Role::firstOrCreate(['slug' => 'partner_admin'], ['name' => 'Partner Admin'])->id
        );
        $this->member = User::factory()->entitled()->create(['partner_id' => $this->partner->id]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function plans(): array
    {
        return ['a member plan' => ['member'], 'a library plan' => ['library']];
    }

    #[DataProvider('plans')]
    public function test_own_partner_admin_updates_a_workout(string $kind): void
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan($kind)->id, 'name' => 'Old']);

        $this->actingAs($this->partnerAdmin)
            ->put(route('workouts.update', $workout), ['name' => 'Renamed'])
            ->assertRedirect(route('plans.show', $workout->plan_id));

        $this->assertSame('Renamed', $workout->fresh()->name);
    }

    #[DataProvider('plans')]
    public function test_own_partner_admin_deletes_a_workout(string $kind): void
    {
        $plan = $this->plan($kind);
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $plan->id]);

        $this->actingAs($this->partnerAdmin)
            ->delete(route('workouts.destroy', $workout))
            ->assertRedirect(route($kind === 'library' ? 'partner.programs.show' : 'plans.show', $plan));

        $this->assertModelMissing($workout);
    }

    #[DataProvider('plans')]
    public function test_own_partner_admin_adds_an_exercise_row(string $kind): void
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->plan($kind)->id]);
        $exercise = Exercise::factory()->create();

        $this->actingAs($this->partnerAdmin)
            ->post(route('workout-exercises.store', $workout), ['exercise_id' => $exercise->id, 'target_sets' => 5])
            ->assertRedirect(route('workouts.show', $workout));

        $row = $workout->workoutTemplateExercises()->sole();
        $this->assertSame($exercise->id, $row->exercise_id);
        $this->assertSame(5, $row->target_sets);
    }

    #[DataProvider('plans')]
    public function test_own_partner_admin_updates_an_exercise_row(string $kind): void
    {
        $row = $this->row($this->plan($kind));

        $this->actingAs($this->partnerAdmin)
            ->put(route('workout-exercises.update', [$row->workout_template_id, $row]), [
                'target_sets' => 6, 'min_target_reps' => 3, 'max_target_reps' => 5,
                'target_weight' => 80, 'rest_seconds' => 180,
            ])
            ->assertRedirect(route('workouts.show', $row->workout_template_id));

        $this->assertSame(6, $row->fresh()->target_sets);
        $this->assertSame(180, $row->fresh()->rest_seconds);
    }

    /**
     * Today a partial update resets every omitted field to its default.
     */
    public function test_a_partial_row_update_resets_the_omitted_fields(): void
    {
        $row = $this->row($this->plan('member'));
        $row->update(['target_sets' => 5, 'min_target_reps' => 4, 'max_target_reps' => 6, 'target_weight' => 100, 'rest_seconds' => 200]);

        $this->actingAs($this->partnerAdmin)
            ->put(route('workout-exercises.update', [$row->workout_template_id, $row]), ['target_sets' => 4])
            ->assertRedirect(route('workouts.show', $row->workout_template_id));

        $row->refresh();
        $this->assertSame(4, $row->target_sets);
        $this->assertSame(8, $row->min_target_reps);
        $this->assertSame(12, $row->max_target_reps);
        $this->assertEquals(0, $row->target_weight);
        $this->assertSame(120, $row->rest_seconds);
    }

    /**
     * Today the web row form stores a target weight as sent, whatever the
     * plan owner's Unit System.
     */
    public function test_a_row_target_weight_is_stored_as_sent_for_an_imperial_member(): void
    {
        $this->member->profile->update(['unit_system' => UnitSystem::Imperial]);
        $row = $this->row($this->plan('member'));

        $this->actingAs($this->partnerAdmin)
            ->put(route('workout-exercises.update', [$row->workout_template_id, $row]), [
                'target_sets' => 3, 'min_target_reps' => 8, 'max_target_reps' => 12,
                'target_weight' => 100, 'rest_seconds' => 120,
            ]);

        $this->assertEquals(100, $row->fresh()->target_weight);
    }

    #[DataProvider('plans')]
    public function test_own_partner_admin_removes_an_exercise_row(string $kind): void
    {
        $row = $this->row($this->plan($kind));

        $this->actingAs($this->partnerAdmin)
            ->delete(route('workout-exercises.destroy', [$row->workout_template_id, $row]))
            ->assertRedirect(route('workouts.show', $row->workout_template_id));

        $this->assertModelMissing($row);
    }

    public function test_an_app_user_creates_and_moves_a_workout_on_their_own_plans_through_the_api(): void
    {
        $plan = Plan::factory()->create(['user_id' => $this->member->id]);
        $other = Plan::factory()->create(['user_id' => $this->member->id]);

        $id = $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/workout-templates', ['plan_id' => $plan->id, 'name' => 'Push'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->member, 'sanctum')
            ->putJson("/api/workout-templates/{$id}", ['plan_id' => $other->id, 'name' => 'Push'])
            ->assertOk();

        $this->assertSame($other->id, WorkoutTemplate::findOrFail($id)->plan_id);
    }

    public function test_an_app_user_cannot_move_a_workout_into_someone_elses_plan_through_the_api(): void
    {
        $plan = Plan::factory()->create(['user_id' => $this->member->id]);
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $plan->id]);
        $foreign = Plan::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->member, 'sanctum')
            ->putJson("/api/workout-templates/{$workout->id}", ['plan_id' => $foreign->id, 'name' => 'Push'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan_id');

        $this->assertSame($plan->id, $workout->fresh()->plan_id);
    }

    private function plan(string $kind): Plan
    {
        return $kind === 'library'
            ? Plan::factory()->partnerLibrary($this->partner)->create()
            : Plan::factory()->program()->create(['user_id' => $this->member->id, 'partner_id' => null]);
    }

    private function row(Plan $plan): WorkoutTemplateExercise
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $plan->id]);

        return WorkoutTemplateExercise::create([
            'workout_template_id' => $workout->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'order' => 0,
            'target_sets' => 3,
            'min_target_reps' => 8,
            'max_target_reps' => 12,
            'target_weight' => 0,
            'rest_seconds' => 120,
        ]);
    }
}
