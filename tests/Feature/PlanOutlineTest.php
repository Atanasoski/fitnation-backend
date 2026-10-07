<?php

namespace Tests\Feature;

use App\Enums\PlanType;
use App\Enums\WorkoutSessionStatus;
use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutTemplate;
use App\Models\WorkoutTemplateExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A user's plan outline (023/06): every Program and Routine in one tree, the
 * selected plan's editor beside it, used by super admins and partner admins.
 */
class PlanOutlineTest extends TestCase
{
    use RefreshDatabase;

    private Partner $gym;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Partner::factory()->create();
        $this->member = User::factory()->create(['partner_id' => $this->gym->id]);
    }

    public function test_the_tree_lists_programs_and_routines(): void
    {
        Plan::factory()->program()->create(['user_id' => $this->member->id, 'name' => 'Strength Block']);
        Plan::factory()->create(['user_id' => $this->member->id, 'type' => PlanType::Routine, 'name' => 'Morning Mobility']);

        $this->actingAs($this->actor('own_admin'))
            ->get(route('plans.index', $this->member))
            ->assertOk()
            ->assertSee('Strength Block')
            ->assertSee('Morning Mobility');
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function pageMatrix(): array
    {
        return [
            'super admin × an app user' => ['super_admin', 'member', 200],
            'super admin × a staff account' => ['super_admin', 'staff', 403],
            'partner admin × own member' => ['own_admin', 'member', 200],
            "partner admin × another partner's member" => ['rival_admin', 'member', 403],
            'plain user × a member of their partner' => ['plain', 'member', 403],
        ];
    }

    #[DataProvider('pageMatrix')]
    public function test_who_may_open_the_outline(string $who, string $whose, int $status): void
    {
        $owner = $whose === 'staff'
            ? $this->withRole('partner_admin', ['partner_id' => $this->gym->id])
            : $this->member;

        $this->actingAs($this->actor($who))->get(route('plans.index', $owner))->assertStatus($status);
    }

    public function test_a_super_admin_sees_it_in_the_admin_shell(): void
    {
        $this->actingAs($this->actor('super_admin'))
            ->get(route('plans.index', $this->member))
            ->assertOk()
            ->assertSee('href="/admin/users"', false)
            ->assertSee(route('admin.users.show', $this->member), false)
            ->assertDontSee('href="/partner/programs"', false)
            ->assertSee('href="/admin/users" class="menu-item group menu-item-active', false);
    }

    public function test_the_selected_plan_comes_from_the_url(): void
    {
        Plan::factory()->program()->create(['user_id' => $this->member->id, 'name' => 'First']);
        $second = Plan::factory()->create(['user_id' => $this->member->id, 'type' => PlanType::Routine, 'name' => 'Second', 'description' => 'Only in the editor']);

        $this->actingAs($this->actor('own_admin'))
            ->get(route('plans.index', ['user' => $this->member, 'plan' => $second->id]))
            ->assertOk()
            ->assertSee('Only in the editor')
            ->assertSee(route('plans.update', $second), false);
    }

    public function test_a_plan_of_someone_else_in_the_url_is_not_selected(): void
    {
        $foreign = Plan::factory()->program()->create(['user_id' => User::factory()->create()->id, 'description' => 'Not yours']);

        $this->actingAs($this->actor('super_admin'))
            ->get(route('plans.index', ['user' => $this->member, 'plan' => $foreign->id]))
            ->assertOk()
            ->assertDontSee('Not yours')
            ->assertDontSee(route('plans.update', $foreign), false);
    }

    public function test_the_create_form_opens_from_the_url(): void
    {
        $this->actingAs($this->actor('own_admin'))
            ->get(route('plans.index', ['user' => $this->member, 'create' => 'routine']))
            ->assertOk()
            ->assertSee('Create routine')
            ->assertSee(route('plans.store', $this->member), false);
    }

    public function test_creating_a_program_and_a_routine_both_start_inactive_and_open_on_the_new_node(): void
    {
        $admin = $this->actor('super_admin');

        $this->actingAs($admin)->post(route('plans.store', $this->member), [
            'name' => 'Strength Block', 'type' => 'program', 'duration_weeks' => 6, 'description' => 'Heavy',
            'is_active' => true,
        ])->assertRedirect(route('plans.index', ['user' => $this->member, 'plan' => $this->planNamed('Strength Block')->id]));

        $this->actingAs($admin)->post(route('plans.store', $this->member), [
            'name' => 'Morning Mobility', 'type' => 'routine', 'duration_weeks' => 6,
        ])->assertRedirect(route('plans.index', ['user' => $this->member, 'plan' => $this->planNamed('Morning Mobility')->id]));

        $program = $this->planNamed('Strength Block');
        $this->assertSame(PlanType::Program, $program->type);
        $this->assertSame(6, $program->duration_weeks);
        $this->assertSame('Heavy', $program->description);
        $this->assertFalse($program->is_active);

        $routine = $this->planNamed('Morning Mobility');
        $this->assertSame(PlanType::Routine, $routine->type);
        $this->assertNull($routine->duration_weeks);
        $this->assertFalse($routine->is_active);
    }

    public function test_a_program_needs_weeks(): void
    {
        $this->actingAs($this->actor('own_admin'))
            ->post(route('plans.store', $this->member), ['name' => 'No weeks', 'type' => 'program'])
            ->assertSessionHasErrors('duration_weeks');

        $this->assertFalse($this->member->plans()->exists());
    }

    public function test_activating_a_routine_while_a_program_is_active_keeps_both_active(): void
    {
        $program = Plan::factory()->program()->create(['user_id' => $this->member->id, 'is_active' => true]);
        $routine = Plan::factory()->create(['user_id' => $this->member->id, 'type' => PlanType::Routine, 'is_active' => false]);

        $this->actingAs($this->actor('own_admin'))
            ->post(route('plans.activate', $routine))
            ->assertRedirect(route('plans.index', ['user' => $this->member, 'plan' => $routine->id]));

        $this->assertTrue($program->fresh()->is_active);
        $this->assertTrue($routine->fresh()->is_active);
    }

    public function test_activating_a_second_program_replaces_the_first_and_the_editor_says_so_beforehand(): void
    {
        $first = Plan::factory()->program()->create(['user_id' => $this->member->id, 'is_active' => true, 'name' => 'Old Block']);
        $second = Plan::factory()->program()->create(['user_id' => $this->member->id, 'is_active' => false, 'name' => 'New Block']);
        $admin = $this->actor('super_admin');

        $this->actingAs($admin)
            ->get(route('plans.index', ['user' => $this->member, 'plan' => $second->id]))
            ->assertSeeInOrder(['Activating replaces', 'Old Block', 'as the active program']);

        $this->actingAs($admin)->post(route('plans.activate', $second))->assertRedirect();

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
    }

    public function test_saving_a_plan_never_activates_it_and_opens_on_it(): void
    {
        $plan = Plan::factory()->program()->create(['user_id' => $this->member->id, 'is_active' => false]);

        $this->actingAs($this->actor('own_admin'))
            ->put(route('plans.update', $plan), [
                'name' => 'Renamed', 'type' => 'program', 'duration_weeks' => 10, 'description' => 'New words',
            ])
            ->assertRedirect(route('plans.index', ['user' => $this->member, 'plan' => $plan->id]));

        $plan->refresh();
        $this->assertSame('Renamed', $plan->name);
        $this->assertSame(10, $plan->duration_weeks);
        $this->assertSame('New words', $plan->description);
        $this->assertFalse($plan->is_active);
    }

    public function test_turning_an_active_routine_into_a_program_re_enters_activation(): void
    {
        $program = Plan::factory()->program()->create(['user_id' => $this->member->id, 'is_active' => true]);
        $routine = Plan::factory()->create(['user_id' => $this->member->id, 'type' => PlanType::Routine, 'is_active' => true]);

        $this->actingAs($this->actor('own_admin'))->put(route('plans.update', $routine), [
            'name' => $routine->name, 'type' => 'program', 'duration_weeks' => 4,
        ])->assertRedirect();

        $this->assertTrue($routine->fresh()->is_active);
        $this->assertSame(PlanType::Program, $routine->fresh()->type);
        $this->assertFalse($program->fresh()->is_active);
    }

    public function test_a_routine_drops_its_weeks(): void
    {
        $plan = Plan::factory()->program()->create(['user_id' => $this->member->id, 'duration_weeks' => 8]);

        $this->actingAs($this->actor('own_admin'))->put(route('plans.update', $plan), [
            'name' => $plan->name, 'type' => 'routine', 'duration_weeks' => 8,
        ]);

        $this->assertNull($plan->fresh()->duration_weeks);
    }

    public function test_deleting_a_plan_keeps_logged_sessions_and_set_logs(): void
    {
        $plan = Plan::factory()->program()->create(['user_id' => $this->member->id]);
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $plan->id]);
        $exercise = Exercise::factory()->create();
        WorkoutTemplateExercise::create([
            'workout_template_id' => $workout->id, 'exercise_id' => $exercise->id, 'order' => 0,
            'target_sets' => 3, 'min_target_reps' => 8, 'max_target_reps' => 12, 'target_weight' => 0, 'rest_seconds' => 90,
        ]);
        $session = WorkoutSession::factory()->create([
            'user_id' => $this->member->id,
            'workout_template_id' => $workout->id,
            'status' => WorkoutSessionStatus::Completed,
            'performed_at' => now()->subDay(),
            'completed_at' => now()->subDay()->addHour(),
        ]);
        $set = SetLog::create([
            'workout_session_id' => $session->id, 'workout_session_exercise_id' => null, 'exercise_id' => $exercise->id,
            'set_number' => 1, 'weight' => 60, 'reps' => 8, 'rest_seconds' => 90,
        ]);

        $this->actingAs($this->actor('own_admin'))
            ->get(route('plans.index', ['user' => $this->member, 'plan' => $plan->id]))
            ->assertSee('logged sessions are kept');

        $this->actingAs($this->actor('own_admin'))
            ->delete(route('plans.destroy', $plan))
            ->assertRedirect(route('plans.index', $this->member));

        $this->assertModelMissing($plan);
        $this->assertModelMissing($workout);
        $this->assertNull($session->fresh()->workout_template_id);
        $this->assertModelExists($set);
    }

    public function test_writes_are_refused_to_another_partners_admin(): void
    {
        $plan = Plan::factory()->program()->create(['user_id' => $this->member->id, 'is_active' => false, 'name' => 'Ours']);
        $rival = $this->actor('rival_admin');

        $this->actingAs($rival)->post(route('plans.activate', $plan))->assertForbidden();
        $this->actingAs($rival)->put(route('plans.update', $plan), ['name' => 'Theirs', 'type' => 'program', 'duration_weeks' => 4])->assertForbidden();
        $this->actingAs($rival)->delete(route('plans.destroy', $plan))->assertForbidden();
        $this->actingAs($rival)->post(route('plans.store', $this->member), ['name' => 'X', 'type' => 'routine'])->assertForbidden();

        $this->assertSame('Ours', $plan->fresh()->name);
        $this->assertFalse($plan->fresh()->is_active);
        $this->assertSame(1, $this->member->plans()->count());
    }

    private function planNamed(string $name): Plan
    {
        return Plan::where('user_id', $this->member->id)->where('name', $name)->sole();
    }

    private function actor(string $who): User
    {
        return match ($who) {
            'plain' => User::factory()->create(['partner_id' => $this->gym->id]),
            'super_admin' => $this->withRole('admin'),
            'own_admin' => $this->withRole('partner_admin', ['partner_id' => $this->gym->id]),
            'rival_admin' => $this->withRole('partner_admin', ['partner_id' => Partner::factory()->create()->id]),
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
}
