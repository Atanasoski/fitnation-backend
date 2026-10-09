<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks the web login before spec 025 narrows it to super admins: a super
 * admin lands on the Overview through /dashboard, an app user is turned away
 * with the mobile-app message and stays a guest.
 */
class WebLoginCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private const MOBILE_APP_MESSAGE = 'This portal is for gym administrators only. Please use the Fit Nation mobile app to access your account.';

    public function test_a_super_admin_signs_in_and_lands_on_the_overview_through_the_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->last_login_at);

        $this->get(route('dashboard'))->assertRedirect(route('admin.overview'));
        $this->get('/')->assertRedirect(route('dashboard'));
        $this->get(route('admin.overview'))->assertOk();
    }

    public function test_an_app_user_is_refused_with_the_mobile_app_message(): void
    {
        $member = User::factory()->create();

        $this->post('/login', ['email' => $member->email, 'password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => self::MOBILE_APP_MESSAGE]);

        $this->assertGuest();
    }

    public function test_an_app_user_who_is_signed_in_gets_403_on_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertForbidden();
    }
}
