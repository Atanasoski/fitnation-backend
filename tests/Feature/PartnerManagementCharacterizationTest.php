<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks how the /partners pages behave before ticket 12 moves the super
 * admin's Partners list and page under /admin. Create, edit and their
 * redirects must not change; partner admins keep their side as it is.
 */
class PartnerManagementCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_sees_every_partner_on_the_partners_index(): void
    {
        Partner::factory()->create(['name' => 'Iron Temple']);
        Partner::factory()->create(['name' => 'Lift Club']);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/partners')
            ->assertRedirect(route('admin.partners.index'));
    }

    public function test_an_admin_can_open_a_partner(): void
    {
        $partner = Partner::factory()->create(['name' => 'Iron Temple', 'slug' => 'iron-temple']);
        $partner->identity()->create(['primary_color' => '#112233', 'secondary_color' => '#445566']);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/partners/iron-temple')
            ->assertRedirect(route('admin.partners.show', $partner));
    }

    public function test_an_admin_can_open_the_create_and_edit_forms(): void
    {
        $partner = Partner::factory()->create(['name' => 'Iron Temple', 'slug' => 'iron-temple']);
        $partner->identity()->create(['primary_color' => '#112233', 'secondary_color' => '#445566']);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get('/partners/create')->assertOk()->assertSee('Create New Partner');
        $this->actingAs($admin)->get('/partners/iron-temple/edit')->assertOk()->assertSee('Iron Temple');
    }

    public function test_an_admin_creates_a_partner_with_its_branding(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->post('/partners', [
                'name' => 'Iron Temple',
                'slug' => 'iron-temple',
                'is_active' => '1',
                'primary_color' => '#112233',
                'secondary_color' => '#445566',
            ])
            ->assertRedirect(route('partners.index'))
            ->assertSessionHas('success', 'Partner created successfully.');

        $partner = Partner::where('slug', 'iron-temple')->firstOrFail();
        $this->assertSame('Iron Temple', $partner->name);
        $this->assertTrue($partner->is_active);
        $this->assertSame('#112233', $partner->identity->primary_color);
        $this->assertSame('#445566', $partner->identity->secondary_color);
    }

    public function test_an_admin_updates_a_partner_and_its_branding(): void
    {
        $partner = Partner::factory()->create(['name' => 'Iron Temple', 'slug' => 'iron-temple']);
        $partner->identity()->create(['primary_color' => '#112233', 'secondary_color' => '#445566']);

        $this->actingAs($this->userWithRole('admin'))
            ->put('/partners/iron-temple', [
                'name' => 'Iron Temple Gym',
                'slug' => 'iron-temple',
                'is_active' => '1',
                'primary_color' => '#aabbcc',
                'secondary_color' => '#445566',
            ])
            ->assertRedirect(route('partners.index'))
            ->assertSessionHas('success', 'Partner updated successfully.');

        $partner->refresh();
        $this->assertSame('Iron Temple Gym', $partner->name);
        $this->assertSame('#aabbcc', $partner->identity->primary_color);
    }

    public function test_a_partner_admin_sees_their_own_partner_but_not_another(): void
    {
        $own = Partner::factory()->create(['name' => 'Iron Temple', 'slug' => 'iron-temple']);
        $own->identity()->create(['primary_color' => '#112233', 'secondary_color' => '#445566']);
        Partner::factory()->create(['slug' => 'lift-club']);
        $partnerAdmin = $this->userWithRole('partner_admin', ['partner_id' => $own->id]);

        $this->actingAs($partnerAdmin)->get('/partners')->assertOk();
        $this->actingAs($partnerAdmin)->get('/partners/iron-temple')->assertOk()->assertSee('Iron Temple');
        $this->actingAs($partnerAdmin)->get('/partners/lift-club')->assertForbidden();
    }

    public function test_a_plain_user_cannot_see_partners(): void
    {
        $this->actingAs($this->userWithRole('user'))->get('/partners')->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function userWithRole(string $slug, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id);

        return $user;
    }
}
