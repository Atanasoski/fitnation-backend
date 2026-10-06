<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A partner admin's Users pages link a member's plans to the plan outline
 * (023/08), not to the plan pages it replaced.
 */
class PartnerUserPagesPlanLinksTest extends TestCase
{
    use RefreshDatabase;

    private User $partnerAdmin;

    private User $member;

    private Plan $program;

    protected function setUp(): void
    {
        parent::setUp();

        $partner = Partner::factory()->create();
        $this->partnerAdmin = User::factory()->create(['partner_id' => $partner->id]);
        $this->partnerAdmin->roles()->attach(
            Role::firstOrCreate(['slug' => 'partner_admin'], ['name' => 'Partner Admin'])->id
        );
        $this->member = User::factory()->create(['partner_id' => $partner->id]);
        $this->program = Plan::factory()->program()->create([
            'user_id' => $this->member->id,
            'name' => 'Strength Block',
            'is_active' => true,
        ]);
    }

    public function test_the_users_list_links_the_active_program_to_its_outline_node(): void
    {
        $this->actingAs($this->partnerAdmin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee(route('plans.index', ['user' => $this->member->id, 'plan' => $this->program->id]), false)
            ->assertSee(route('plans.index', $this->member), false)
            ->assertDontSee(route('plans.show', $this->program), false);
    }

    public function test_the_user_page_links_each_plan_to_its_outline_node(): void
    {
        $this->actingAs($this->partnerAdmin)
            ->get(route('users.show', $this->member))
            ->assertOk()
            ->assertSee(route('plans.index', ['user' => $this->member->id, 'plan' => $this->program->id]), false)
            ->assertDontSee(route('plans.show', $this->program), false);
    }
}
