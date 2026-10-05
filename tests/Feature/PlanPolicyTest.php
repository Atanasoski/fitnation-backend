<?php

namespace Tests\Feature;

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
 * Who may manage a plan (023 Seam 4): a super admin any app user's plans, a
 * partner admin their own members' plans, and a library plan its own
 * partner's admin (or a super admin).
 */
class PlanPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Partner $gym;

    private Partner $rival;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Partner::factory()->create();
        $this->rival = Partner::factory()->create();
        $this->member = User::factory()->create(['partner_id' => $this->gym->id]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function intruders(): array
    {
        return [
            'a plain user of the same partner' => ['plain'],
            "another partner's admin" => ['rival_admin'],
        ];
    }

    #[DataProvider('intruders')]
    public function test_an_intruder_cannot_update_or_delete_someone_elses_workout(string $who): void
    {
        $this->markTestSkipped('The hole 023/05 closes: workout writes have no authorisation yet.');

        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->memberPlan()->id, 'name' => 'Theirs']);
        $intruder = $this->actor($who);

        $this->actingAs($intruder)->put(route('workouts.update', $workout), ['name' => 'Mine'])->assertForbidden();
        $this->actingAs($intruder)->delete(route('workouts.destroy', $workout))->assertForbidden();
        $this->actingAs($intruder)->post(route('workouts.store', $workout->plan_id), [
            'plan_id' => $workout->plan_id, 'name' => 'Extra',
        ])->assertForbidden();

        $this->assertSame('Theirs', $workout->fresh()->name);
        $this->assertSame(1, WorkoutTemplate::where('plan_id', $workout->plan_id)->count());
    }

    #[DataProvider('intruders')]
    public function test_an_intruder_cannot_change_someone_elses_exercise_rows(string $who): void
    {
        $this->markTestSkipped('The hole 023/05 closes: workout-exercise writes have no authorisation yet.');

        $row = $this->row($this->memberPlan());
        $intruder = $this->actor($who);

        $this->actingAs($intruder)->put(route('workout-exercises.update', [$row->workout_template_id, $row]), [
            'target_sets' => 9,
        ])->assertForbidden();
        $this->actingAs($intruder)->delete(route('workout-exercises.destroy', [$row->workout_template_id, $row]))
            ->assertForbidden();
        $this->actingAs($intruder)->post(route('workout-exercises.store', $row->workout_template_id), [
            'exercise_id' => $row->exercise_id,
        ])->assertForbidden();

        $this->assertSame(3, $row->fresh()->target_sets);
        $this->assertSame(1, WorkoutTemplateExercise::where('workout_template_id', $row->workout_template_id)->count());
    }

    private function memberPlan(): Plan
    {
        return Plan::factory()->program()->create(['user_id' => $this->member->id, 'partner_id' => null]);
    }

    private function actor(string $who): User
    {
        return match ($who) {
            'plain' => User::factory()->create(['partner_id' => $this->gym->id]),
            'rival_admin' => $this->withRole('partner_admin', ['partner_id' => $this->rival->id]),
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function withRole(string $slug, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['slug' => $slug], ['name' => $slug])->id);

        return $user;
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
