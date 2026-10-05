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
     * The Seam 4 matrix: actor × whose plan → may they manage it?
     *
     * @return array<string, array{string, string, bool}>
     */
    public static function matrix(): array
    {
        return [
            'super admin × an app user' => ['super_admin', 'member', true],
            'super admin × a staff account' => ['super_admin', 'staff', false],
            'super admin × a library plan' => ['super_admin', 'library', true],
            'partner admin × own member' => ['own_admin', 'member', true],
            'partner admin × a fellow staff account' => ['own_admin', 'staff', false],
            'partner admin × own library plan' => ['own_admin', 'library', true],
            "partner admin × another partner's member" => ['rival_admin', 'member', false],
            "partner admin × another partner's library plan" => ['rival_admin', 'library', false],
            'plain user × a member of their partner' => ['plain', 'member', false],
            'plain user × their partner library plan' => ['plain', 'library', false],
            'the owner on the web' => ['owner', 'member', false],
        ];
    }

    #[DataProvider('matrix')]
    public function test_who_may_manage_a_plan(string $who, string $whose, bool $allowed): void
    {
        $plan = match ($whose) {
            'member' => $this->memberPlan(),
            'staff' => Plan::factory()->program()->create([
                'user_id' => $this->withRole('partner_admin', ['partner_id' => $this->gym->id])->id,
                'partner_id' => null,
            ]),
            'library' => Plan::factory()->partnerLibrary($this->gym)->create(),
        };

        $this->assertSame($allowed, $this->actor($who)->can('manage', $plan));

        if ($plan->user !== null) {
            $this->assertSame($allowed, $this->actor($who)->can('manageFor', [Plan::class, $plan->user]));
        }
    }

    public function test_the_owner_may_still_change_their_own_plan_but_nobody_elses(): void
    {
        $plan = $this->memberPlan();

        $this->assertTrue($this->member->can('update', $plan));
        $this->assertFalse($this->actor('plain')->can('update', $plan));
        $this->assertTrue($this->actor('own_admin')->can('update', $plan));
    }

    public function test_a_super_admin_manages_a_members_plan_over_http(): void
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->memberPlan()->id]);
        $admin = $this->actor('super_admin');

        $this->actingAs($admin)->put(route('workouts.update', $workout), ['name' => 'By the super admin'])
            ->assertRedirect(route('plans.show', $workout->plan_id));
        $this->actingAs($admin)->post(route('workouts.store', $workout->plan_id), [
            'plan_id' => $workout->plan_id, 'name' => 'Added',
        ])->assertRedirect(route('plans.show', $workout->plan_id));
        $this->actingAs($admin)->post(route('plans.store', $this->member), [
            'name' => 'Set up by staff', 'type' => 'routine',
        ])->assertRedirect();
        $this->actingAs($admin)->get(route('plans.index', $this->member))->assertOk();
        $this->actingAs($admin)->get(route('plans.show', $workout->plan_id))->assertOk();
        $this->actingAs($admin)->get(route('workouts.show', $workout))->assertOk();

        $this->assertSame('By the super admin', $workout->fresh()->name);
        $this->assertSame(2, WorkoutTemplate::where('plan_id', $workout->plan_id)->count());
        $this->assertTrue($this->member->plans()->where('name', 'Set up by staff')->exists());
    }

    public function test_staff_plans_are_not_managed_through_the_user_plan_pages(): void
    {
        $staff = $this->withRole('partner_admin', ['partner_id' => $this->gym->id]);

        $this->actingAs($this->actor('super_admin'))->get(route('plans.index', $staff))->assertForbidden();
        $this->actingAs($this->actor('super_admin'))->post(route('plans.store', $staff), [
            'name' => 'Nope', 'type' => 'routine',
        ])->assertForbidden();
    }

    public function test_another_partners_admin_cannot_touch_a_library_programme(): void
    {
        $plan = Plan::factory()->partnerLibrary($this->gym)->create(['name' => 'Ours']);
        $rival = $this->actor('rival_admin');

        $this->actingAs($rival)->put(route('partner.programs.update', $plan), ['name' => 'Theirs', 'type' => 'program'])
            ->assertForbidden();
        $this->actingAs($rival)->delete(route('partner.programs.destroy', $plan))->assertForbidden();

        $this->assertSame('Ours', $plan->fresh()->name);
    }

    public function test_moving_a_workout_into_a_plan_the_actor_may_not_change_is_refused(): void
    {
        $workout = WorkoutTemplate::factory()->create(['plan_id' => $this->memberPlan()->id]);
        $rivalMember = User::factory()->create(['partner_id' => $this->rival->id]);
        $foreign = Plan::factory()->program()->create(['user_id' => $rivalMember->id, 'partner_id' => null]);
        $sameMembersOther = $this->memberPlan();

        $this->actingAs($this->actor('own_admin'))
            ->put(route('workouts.update', $workout), ['plan_id' => $foreign->id, 'name' => 'Moved'])
            ->assertSessionHasErrors('plan_id');
        $this->assertSame($workout->plan_id, $workout->fresh()->plan_id);

        $this->actingAs($this->actor('own_admin'))
            ->put(route('workouts.update', $workout), ['plan_id' => $sameMembersOther->id, 'name' => 'Moved'])
            ->assertSessionHasNoErrors();
        $this->assertSame($sameMembersOther->id, $workout->fresh()->plan_id);
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
            'owner' => $this->member,
            'super_admin' => $this->withRole('admin'),
            'own_admin' => $this->withRole('partner_admin', ['partner_id' => $this->gym->id]),
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
