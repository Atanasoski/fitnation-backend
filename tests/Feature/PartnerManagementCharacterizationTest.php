<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkoutSession;
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

    public function test_an_update_leaves_identity_columns_it_was_not_sent_unchanged(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple', 'is_active' => true]);
        $hidden = [
            'background_color' => '#fafafa',
            'card_background_color' => '#f1f1f1',
            'text_primary_color' => '#101010',
            'text_on_primary_color' => '#fefefe',
            'font_family' => 'Poppins',
            'background_pattern' => 'storage/partners/dots.png',
            'background_color_dark' => '#0a0a0a',
            'text_primary_color_dark' => '#eeeeee',
            'text_secondary_color_dark' => '#bbbbbb',
        ];
        $partner->identity()->create(['primary_color' => '#112233', 'secondary_color' => '#445566'] + $hidden);

        $this->actingAs($this->userWithRole('admin'))
            ->put('/partners/iron-temple', [
                'name' => 'Iron Temple',
                'slug' => 'iron-temple',
                'primary_color' => '#aabbcc',
                'secondary_color' => '#ddeeff',
                'primary_color_dark' => '#123456',
                'secondary_color_dark' => '#654321',
            ])
            ->assertRedirect(route('partners.index'));

        $identity = $partner->refresh()->identity;
        $this->assertSame('#aabbcc', $identity->primary_color);
        $this->assertSame('#654321', $identity->secondary_color_dark);
        $this->assertSame($hidden, $identity->only(array_keys($hidden)));
        // is_active was not sent, so it is left as it was.
        $this->assertTrue($partner->is_active);
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

    public function test_a_partner_admin_sees_member_counts_without_staff_on_their_partner_page(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple']);
        $partner->identity()->create(['primary_color' => '#112233', 'secondary_color' => '#445566']);
        $partnerAdmin = $this->userWithRole('partner_admin', ['partner_id' => $partner->id]);
        $active = User::factory()->create(['partner_id' => $partner->id]);
        User::factory()->create(['partner_id' => $partner->id]);
        WorkoutSession::factory()->create(['user_id' => $active->id, 'performed_at' => now()]);
        WorkoutSession::factory()->create(['user_id' => $partnerAdmin->id, 'performed_at' => now()]);

        $this->actingAs($partnerAdmin)->get('/partners/iron-temple')
            ->assertOk()
            ->assertViewHas('usersCount', 3)
            ->assertViewHas('totalMembers', 2)
            ->assertViewHas('activeMembersThisWeek', 1);
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
