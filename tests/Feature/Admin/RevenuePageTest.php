<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\SubscriptionStore;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Insights Revenue tab (spec 024): the module's answers appear on the
 * page. The numbers themselves are RevenueTest's job.
 */
class RevenuePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-07 12:00:00');
    }

    public function test_the_revenue_tab_shows_the_tiles_conversion_and_sandbox_count(): void
    {
        Subscription::factory()->create(['price' => 9.99]);
        Subscription::factory()->yearly()->create(['price' => 60.00, 'store' => SubscriptionStore::PlayStore, 'product_id' => 'com.fitnation.app.premium.yearly:yearly']);
        Subscription::factory()->billingIssue()->create(['price' => null]);
        Subscription::factory()->trial()->create();
        Subscription::factory()->sandbox()->count(3)->create();

        $this->actingAs($this->admin())
            ->get('/admin/insights/revenue')
            ->assertOk()
            ->assertSee('Sandbox excluded (3)')
            ->assertSeeInOrder(['Expected Monthly Revenue', '$14.99', 'RevenueCat’s USD conversion'], false)
            ->assertSeeInOrder(['Paying', '3', '2 monthly · 1 yearly'])
            ->assertSeeInOrder(['In trial', '1'])
            ->assertSeeInOrder(['Billing issue', '1'])
            ->assertSee(route('admin.users.index', ['access' => 'billing_issue']), false)
            ->assertSee('1 paying subscription has no price')
            ->assertSeeInOrder(['Trial → paid', 'Monthly', '100%', '2 of 2 finished trials'])
            ->assertSee('Not in this version');
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id);

        return $user;
    }
}
