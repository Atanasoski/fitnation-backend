<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks how the /partners create and edit pages behave. The list and page
 * moved under /admin (024/12); since spec 025 the old /partners list and page
 * are gone and every write returns to admin.partners.index.
 */
class PartnerManagementCharacterizationTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertRedirect(route('admin.partners.index'))
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
            ->assertRedirect(route('admin.partners.index'))
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
            ->assertRedirect(route('admin.partners.index'));

        $identity = $partner->refresh()->identity;
        $this->assertSame('#aabbcc', $identity->primary_color);
        $this->assertSame('#654321', $identity->secondary_color_dark);
        $this->assertSame($hidden, $identity->only(array_keys($hidden)));
        // is_active was not sent, so it is left as it was.
        $this->assertTrue($partner->is_active);
    }

    public function test_an_admin_deletes_a_partner_without_users_and_returns_to_the_partners_index(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple']);
        $partner->identity()->create(['primary_color' => '#112233', 'secondary_color' => '#445566']);

        $this->actingAs($this->userWithRole('admin'))
            ->delete('/partners/iron-temple')
            ->assertRedirect(route('admin.partners.index'))
            ->assertSessionHas('success', 'Partner deleted successfully.');

        $this->assertModelMissing($partner);
    }

    public function test_an_admin_cannot_delete_a_partner_with_users(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple']);
        User::factory()->create(['partner_id' => $partner->id]);

        $this->actingAs($this->userWithRole('admin'))
            ->delete('/partners/iron-temple')
            ->assertRedirect(route('admin.partners.index'))
            ->assertSessionHas('error', 'Cannot delete partner with existing users. Please remove all users first.');

        $this->assertModelExists($partner);
    }

    public function test_a_plain_user_cannot_open_the_partner_forms(): void
    {
        $partner = Partner::factory()->create();

        $this->actingAs($this->userWithRole('user'))->get('/partners/create')->assertForbidden();
        $this->actingAs($this->userWithRole('user'))->get("/partners/{$partner->slug}/edit")->assertForbidden();
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
