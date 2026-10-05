<?php

namespace Tests\Feature;

use App\Enums\PlanType;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locked the partner-admin user-plan pages — index, store, update and
 * destroy — before the plan outline (023/06) replaced them. The expectations
 * were then changed deliberately, one per behaviour 023/06 asks to change:
 * Routines are listed, store keeps the type asked for and starts inactive,
 * and every write lands back on the outline.
 */
class UserPlanPagesCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $partner = Partner::factory()->create();
        $this->admin = User::factory()->create(['partner_id' => $partner->id]);
        $this->admin->roles()->attach(
            Role::firstOrCreate(['slug' => 'partner_admin'], ['name' => 'Partner Admin'])->id
        );
        $this->member = User::factory()->create(['partner_id' => $partner->id]);
    }

    public function test_the_index_lists_the_members_programs_and_routines(): void
    {
        Plan::factory()->program()->create(['user_id' => $this->member->id, 'name' => 'Strength Block']);
        Plan::factory()->create(['user_id' => $this->member->id, 'type' => PlanType::Routine, 'name' => 'Morning Mobility']);

        $this->actingAs($this->admin)
            ->get(route('plans.index', $this->member))
            ->assertOk()
            ->assertSee('Strength Block')
            ->assertSee('Morning Mobility');
    }

    public function test_store_creates_the_type_asked_for_inactive_and_opens_it_in_the_outline(): void
    {
        $response = $this->actingAs($this->admin)->post(route('plans.store', $this->member), [
            'name' => 'Asked For A Routine',
            'type' => PlanType::Routine->value,
            'duration_weeks' => 3,
            'is_active' => true,
        ]);

        $plan = Plan::where('name', 'Asked For A Routine')->sole();
        $response->assertRedirect(route('plans.index', ['user' => $this->member, 'plan' => $plan]))->assertSessionHas('success', 'Routine created.');
        $this->assertSame(PlanType::Routine, $plan->type);
        $this->assertSame($this->member->id, $plan->user_id);
        $this->assertNull($plan->duration_weeks);
        $this->assertFalse($plan->is_active);
    }

    public function test_update_saves_and_returns_to_the_plan_in_the_outline(): void
    {
        $plan = Plan::factory()->program()->create(['user_id' => $this->member->id, 'is_active' => false]);

        $this->actingAs($this->admin)
            ->put(route('plans.update', $plan), [
                'name' => 'Renamed',
                'description' => 'New words',
                'type' => PlanType::Program->value,
                'duration_weeks' => 8,
            ])
            ->assertRedirect(route('plans.index', ['user' => $this->member, 'plan' => $plan]))
            ->assertSessionHas('success', 'Plan saved.');

        $plan->refresh();
        $this->assertSame('Renamed', $plan->name);
        $this->assertSame('New words', $plan->description);
        $this->assertSame(8, $plan->duration_weeks);
        $this->assertFalse($plan->is_active);
    }

    public function test_destroy_deletes_and_returns_to_the_outline(): void
    {
        $plan = Plan::factory()->program()->create(['user_id' => $this->member->id]);

        $this->actingAs($this->admin)
            ->delete(route('plans.destroy', $plan))
            ->assertRedirect(route('plans.index', $this->member))
            ->assertSessionHas('success', "{$plan->name} deleted. Logged sessions are kept.");

        $this->assertModelMissing($plan);
    }
}
