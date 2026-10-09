<?php

namespace Tests\Feature\Auth;

use App\Helpers\MenuHelper;
use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Since spec 025 the web panel is for super admins only: a partner admin is
 * turned away at login with the same mobile-app message an app user gets.
 */
class WebLoginAdminOnlyTest extends TestCase
{
    use RefreshDatabase;

    private const MOBILE_APP_MESSAGE = 'This portal is for gym administrators only. Please use the Fit Nation mobile app to access your account.';

    public function test_a_partner_admin_is_refused_with_the_mobile_app_message(): void
    {
        $partnerAdmin = User::factory()->create(['partner_id' => Partner::factory()->create()->id]);
        $partnerAdmin->roles()->attach(Role::firstOrCreate(['slug' => 'partner_admin'], ['name' => 'Partner Admin'])->id);

        $this->post('/login', ['email' => $partnerAdmin->email, 'password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => self::MOBILE_APP_MESSAGE]);

        $this->assertGuest();
    }

    public function test_a_super_admin_still_lands_on_the_overview(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($admin);

        $this->get(route('dashboard'))->assertRedirect(route('admin.overview'));
    }

    public function test_a_signed_in_partner_admin_gets_403_on_the_dashboard_and_no_menu(): void
    {
        $partnerAdmin = User::factory()->create(['partner_id' => Partner::factory()->create()->id]);
        $partnerAdmin->roles()->attach(Role::firstOrCreate(['slug' => 'partner_admin'], ['name' => 'Partner Admin'])->id);

        $this->actingAs($partnerAdmin)->get(route('dashboard'))->assertForbidden();
        $this->assertSame([], MenuHelper::getMainNavItems());
    }
}
