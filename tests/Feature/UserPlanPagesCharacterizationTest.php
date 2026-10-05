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
 * Locks the partner-admin user-plan pages as they answer today — index,
 * store, update and destroy — before the plan outline (023/06) replaces them.
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

    public function test_the_index_lists_the_members_programs_only(): void
    {
        Plan::factory()->program()->create(['user_id' => $this->member->id, 'name' => 'Strength Block']);
        Plan::factory()->create(['user_id' => $this->member->id, 'type' => PlanType::Routine, 'name' => 'Morning Mobility']);

        $this->actingAs($this->admin)
            ->get(route('plans.index', $this->member))
            ->assertOk()
            ->assertSee('Strength Block')
            ->assertDontSee('Morning Mobility');
    }

    public function test_store_creates_a_program_whatever_type_is_asked_and_opens_its_page(): void
    {
        $response = $this->actingAs($this->admin)->post(route('plans.store', $this->member), [
            'name' => 'Asked For A Routine',
            'type' => PlanType::Routine->value,
            'duration_weeks' => 3,
            'is_active' => true,
        ]);

        $plan = Plan::where('name', 'Asked For A Routine')->sole();
        $response->assertRedirect(route('plans.show', $plan))->assertSessionHas('success', 'Plan created successfully!');
        $this->assertSame(PlanType::Program, $plan->type);
        $this->assertSame($this->member->id, $plan->user_id);
        $this->assertSame(3, $plan->duration_weeks);
        $this->assertTrue($plan->is_active);
    }

    public function test_update_saves_and_returns_to_the_index(): void
    {
        $plan = Plan::factory()->program()->create(['user_id' => $this->member->id, 'is_active' => false]);

        $this->actingAs($this->admin)
            ->put(route('plans.update', $plan), [
                'name' => 'Renamed',
                'description' => 'New words',
                'type' => PlanType::Program->value,
                'duration_weeks' => 8,
            ])
            ->assertRedirect(route('plans.index', $this->member))
            ->assertSessionHas('success', 'Plan updated successfully!');

        $plan->refresh();
        $this->assertSame('Renamed', $plan->name);
        $this->assertSame('New words', $plan->description);
        $this->assertSame(8, $plan->duration_weeks);
        $this->assertFalse($plan->is_active);
    }

    public function test_destroy_deletes_and_returns_to_the_index(): void
    {
        $plan = Plan::factory()->program()->create(['user_id' => $this->member->id]);

        $this->actingAs($this->admin)
            ->delete(route('plans.destroy', $plan))
            ->assertRedirect(route('plans.index', $this->member))
            ->assertSessionHas('success', 'Plan deleted successfully!');

        $this->assertModelMissing($plan);
    }
}
