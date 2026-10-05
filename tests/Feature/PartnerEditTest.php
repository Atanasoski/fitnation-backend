<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The partner edit and create pages (spec 023, ticket 01): details, logo and
 * four colours. Every other identity column is left as it is.
 */
class PartnerEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_house_partner_cannot_be_deactivated_from_the_edit_form(): void
    {
        $house = Partner::factory()->create(['name' => 'Fit Nation', 'slug' => 'fit-nation', 'is_active' => true]);
        config(['partners.house_partner_id' => $house->id]);

        $this->actingAs($this->userWithRole('admin'))
            ->put('/partners/fit-nation', $this->form(['name' => 'Fit Nation', 'slug' => 'fit-nation', 'is_active' => '0']))
            ->assertSessionHasErrors(['is_active' => 'The House Partner cannot be deactivated.']);

        $this->assertTrue($house->refresh()->is_active);
    }

    public function test_saving_the_form_writes_the_four_colours_and_leaves_hidden_columns_byte_for_byte(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple']);
        $hidden = [
            'background_color' => '#fafafa',
            'font_family' => 'Poppins',
            'background_pattern' => 'storage/partners/dots.png',
            'text_primary_color_dark' => '#eeeeee',
            'text_secondary_color_dark' => '#bbbbbb',
            'border_color_dark' => '#333333',
        ];
        $partner->identity()->create(['primary_color' => '#112233', 'secondary_color' => '#445566'] + $hidden);

        $this->actingAs($this->userWithRole('admin'))
            ->put('/partners/iron-temple', $this->form())
            ->assertSessionHasNoErrors();

        $identity = $partner->refresh()->identity;
        $this->assertSame(
            ['primary_color' => '#aabbcc', 'secondary_color' => '#ddeeff', 'primary_color_dark' => '#123456', 'secondary_color_dark' => '#654321'],
            $identity->only(['primary_color', 'secondary_color', 'primary_color_dark', 'secondary_color_dark']),
        );
        $this->assertSame($hidden, $identity->only(array_keys($hidden)));
    }

    public function test_unchecking_active_deactivates_the_partner(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple', 'is_active' => true]);

        // An unchecked box sends only the hidden 0.
        $this->actingAs($this->userWithRole('admin'))
            ->put('/partners/iron-temple', $this->form(['is_active' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertFalse($partner->refresh()->is_active);
    }

    public function test_a_new_logo_replaces_the_old_one(): void
    {
        Storage::fake('public');
        $partner = Partner::factory()->create(['slug' => 'iron-temple']);
        $partner->identity()->create(['primary_color' => '#112233', 'secondary_color' => '#445566', 'logo' => 'storage/partners/old.png']);

        $this->actingAs($this->userWithRole('admin'))
            ->put('/partners/iron-temple', $this->form(['logo' => UploadedFile::fake()->image('new.png', 256, 256)]))
            ->assertSessionHasNoErrors();

        $logo = $partner->refresh()->identity->logo;
        $this->assertNotSame('storage/partners/old.png', $logo);
        Storage::disk('public')->assertExists(str_replace('storage/', '', $logo));
    }

    public function test_creating_a_partner_saves_the_four_colours(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->post('/partners', $this->form())
            ->assertSessionHasNoErrors();

        $identity = Partner::where('slug', 'iron-temple')->firstOrFail()->identity;
        $this->assertSame('#123456', $identity->primary_color_dark);
        $this->assertSame('#654321', $identity->secondary_color_dark);
        $this->assertNull($identity->font_family);
    }

    public function test_a_partner_admin_edits_only_their_own_partner(): void
    {
        $own = Partner::factory()->create(['slug' => 'iron-temple']);
        Partner::factory()->create(['slug' => 'lift-club']);
        $partnerAdmin = $this->userWithRole('partner_admin', ['partner_id' => $own->id]);

        $this->actingAs($partnerAdmin)->get('/partners/iron-temple/edit')->assertOk()->assertSee('name="primary_color_dark"', escape: false);
        $this->actingAs($partnerAdmin)->put('/partners/iron-temple', $this->form(['name' => 'Iron Temple Gym']))->assertSessionHasNoErrors();
        $this->assertSame('Iron Temple Gym', $own->refresh()->name);

        $this->actingAs($partnerAdmin)->get('/partners/lift-club/edit')->assertForbidden();
        $this->actingAs($partnerAdmin)->put('/partners/lift-club', $this->form(['slug' => 'lift-club']))->assertForbidden();
        $this->actingAs($partnerAdmin)->get('/partners/create')->assertForbidden();
    }

    public function test_a_plain_user_is_forbidden(): void
    {
        Partner::factory()->create(['slug' => 'iron-temple']);
        $user = $this->userWithRole('user');

        $this->actingAs($user)->get('/partners/iron-temple/edit')->assertForbidden();
        $this->actingAs($user)->put('/partners/iron-temple', $this->form())->assertForbidden();
        $this->actingAs($user)->get('/partners/create')->assertForbidden();
        $this->actingAs($user)->post('/partners', $this->form())->assertForbidden();
    }

    public function test_the_edit_form_asks_for_details_logo_and_four_colours_only(): void
    {
        $partner = Partner::factory()->create(['slug' => 'iron-temple']);
        $partner->identity()->create(['primary_color' => '#112233', 'secondary_color' => '#445566', 'font_family' => 'Poppins']);

        $response = $this->actingAs($this->userWithRole('admin'))->get('/partners/iron-temple/edit')->assertOk();

        foreach (['name', 'slug', 'domain', 'is_active', 'logo', 'primary_color', 'secondary_color', 'primary_color_dark', 'secondary_color_dark'] as $field) {
            $response->assertSee("name=\"{$field}\"", escape: false);
        }
        foreach (['font_family', 'background_pattern', 'background_color', 'text_primary_color', 'border_color_dark'] as $hidden) {
            $response->assertDontSee("name=\"{$hidden}\"", escape: false);
        }
        // Stored colours prefill light; the dark ones fall back to the config defaults.
        $response->assertSee('#112233')->assertSee('#445566')
            ->assertSee(config('branding.dark.primary'))->assertSee(config('branding.dark.secondary'));
    }

    public function test_the_create_form_has_the_same_fields_prefilled_with_the_defaults(): void
    {
        $response = $this->actingAs($this->userWithRole('admin'))->get('/partners/create')->assertOk();

        foreach (['name', 'slug', 'domain', 'is_active', 'logo', 'primary_color', 'secondary_color', 'primary_color_dark', 'secondary_color_dark'] as $field) {
            $response->assertSee("name=\"{$field}\"", escape: false);
        }
        $response->assertDontSee('name="font_family"', escape: false)
            ->assertSee(config('branding.light.primary'))->assertSee(config('branding.dark.secondary'));
    }

    /**
     * What the edit form posts.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Iron Temple',
            'slug' => 'iron-temple',
            'domain' => '',
            'is_active' => '1',
            'primary_color' => '#aabbcc',
            'secondary_color' => '#ddeeff',
            'primary_color_dark' => '#123456',
            'secondary_color_dark' => '#654321',
        ];
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
