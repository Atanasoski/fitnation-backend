<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec 025 keeps the partner_admin role dormant. Tests elsewhere use it as a
 * staff fixture that must stay out of the app users and off the super-admin
 * panel; this locks both directly, so neither rests on a page 025 deletes.
 */
class PartnerAdminFixtureCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private User $partnerAdmin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $gym = Partner::factory()->create();
        $this->member = User::factory()->create(['partner_id' => $gym->id]);
        $this->partnerAdmin = User::factory()->create(['partner_id' => $gym->id]);
        $this->partnerAdmin->roles()->attach(Role::firstOrCreate(['slug' => 'partner_admin'], ['name' => 'Partner Admin'])->id);
    }

    public function test_a_partner_admin_is_not_an_app_user(): void
    {
        $this->assertTrue($this->partnerAdmin->isStaff());
        $this->assertFalse($this->member->isStaff());
        $this->assertSame([$this->member->id], User::appUsers()->whereKey([$this->member->id, $this->partnerAdmin->id])->pluck('id')->all());
    }

    public function test_a_partner_admin_gets_403_on_the_super_admin_panel(): void
    {
        $this->actingAs($this->partnerAdmin)->get('/admin')->assertForbidden();
        $this->actingAs($this->partnerAdmin)->get('/admin/users')->assertForbidden();
        $this->actingAs($this->partnerAdmin)->get("/admin/users/{$this->member->id}")->assertForbidden();
    }
}
